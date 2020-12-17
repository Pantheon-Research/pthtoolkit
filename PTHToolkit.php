<?php

namespace PTHToolkit;

// Don't forget to include the autoload.php in your project!
//require_once 'vendor/autoload.php';
require_once 'SQLProcessor.php';

use ToolkitApi\Toolkit;
use PTHToolkit\SQLProcessor;
use PDO;
use PTHToolkit\PTHToolkitServiceXML;

class PTHToolkit extends Toolkit
{
    protected $fullXML = null;
    protected $disconnect = null;

    public function __construct($databaseNameOrResource = false, $userOrI5NamingFlag = false, $password = false, $transportType = false, $isPersistent = false)
    {
    }

    /*
     * Add a connection function
     */
    public function makeConnection($databaseNameOrResource, $userOrI5NamingFlag = '0', $password = '', $transportType = '', $isPersistent = false)
    {
        // Check for PDO connection
        if (strtolower($transportType) === 'pdo') {
            $dbconn = $this->pdoConn($databaseNameOrResource, $userOrI5NamingFlag[0], $password, $userOrI5NamingFlag[1]);
        }

        parent::__construct($dbconn, $userOrI5NamingFlag[1], $password, $transportType, $isPersistent);
    }

    /*
     * Add SQL support (straight execute)
     */
    public function executeSQL($statement, $options = [], $sqlOptions = NULL, $results = false)
    {

        $query = new SQLProcessor($this);

        $query->wrapSQL($statement, $options, $sqlOptions);
        $query->runQuery();

//        if ($results) {
//            switch (strtolower($results)) {
//                case 'json':
//                    return $query->getJson();
//                    break;
//                case 'xmlobject':
//                    return $query->getXMLObject();
//                    break;
//                case 'rawoutput':
//                default:
//                    return $query->getRawOutput();
//                    break;
//            }
//        } else {
//            return;
//        }
    }

    /*
     * Add PDO Connection
     */
    private function pdoConn($databaseName, $user, $pass, $namingMode = 1, $persistence = false)
    {
        if (PHP_OS == "OS400") {
            $constr = "odbc:DSN=$databaseName;NAM=$namingMode";
        } elseif (PHP_OS == "Linux") {
            $constr = "odbc:DSN=PTHSDNS;CMT=2";
        } else {
            $constr = "odbc:Driver={iSeries Access odbc Driver};System=pths02;NAM=$namingMode";
        }

        try {
            $dbconn = new PDO($constr, $user, $pass, array(
                PDO::ATTR_PERSISTENT => $persistence,
                PDO::ATTR_ERRMODE => PDO::ERRMODE_WARNING,
            ));
        } catch (PDOException $e) {
            die ('PDO connection failed: ' . $e->getMessage());
        }

        return $dbconn;
    }

    /*
     * @GSC Overloading the makeDbCall to fix disconnect errors
     */
    protected function makeDbCall($internalKey, $plugSize, $controlKeyString, $inputXml, $disconnect = false)
    {
        $toolkitLib = $this->getOption('XMLServiceLib');
        $schemaSep = $this->getOption('schemaSep');
        $transportType = $this->getOption('transportType');

        $plugPrefix = $this->getOption('plugPrefix');
        // construct plug name from prefix + size
        $plug = $plugPrefix . $plugSize; // e.g. iPLUG512K

        if ($plugPrefix == 'iPLUG') {
            // db2 driver stored procedures take 4 params
            $sql = "call {$toolkitLib}{$schemaSep}{$plug}(?,?,?,?)";
        } else {    /*odbc, iPLUGR */
            // only three params for odbc stored procedures
            $sql = "call {$toolkitLib}{$schemaSep}{$plug}(?,?,?)";
        }

        $bindArray = array(
            'internalKey' => $internalKey,
            'controlKey' => $controlKeyString,
            'inputXml' => $inputXml,
            'outputXml' => '',
            'disconnect' => $disconnect
        );

        // if debug mode, log control key, stored procedure statement, and input XML.
        if ($this->isDebug()) {
            $this->debugLog("\nExec start: " . date("Y-m-d H:i:s") . "\nVersion of toolkit front end: " . self::getFrontEndVersion() . "\nIPC: '" . $this->getInternalKey() . "'. Control key: $controlKeyString\nStmt: $sql with transport: $transportType\nInput XML: $inputXml\n");
            $this->execStartTime = microtime(true);
        }

        // can return false if prepare or exec failed.
        $outputXml = $this->db->execXMLStoredProcedure($this->conn, $sql, $bindArray);

        if (!$outputXml && !$disconnect) {
            // if false returned, was a database error (stored proc prepare or execute error)
            // @todo add ODBC SQL State codes

            // If can't find stored proc for ODBC: Database code (if any): S1000. Message: [unixODBC][IBM][System i Access ODBC Driver][DB2 for i5/OS]SQL0440 - Routine IPLUG512K in XMLSERVICE not found with specified parameters.
            //Warning: odbc_prepare(): SQL error: [unixODBC][IBM][System i Access ODBC Driver][DB2 for i5/OS]SQL0440 - Routine IPLUG512K in XMLSERVICE not found with specified parameters., SQL state S1000 in SQLPrepare in /usr/local/zend/ToolkitAPI/Odbcsupp.php on line 89
            $this->cpfErr = $this->db->getErrorCode();
            $this->setErrorMsg($this->db->getErrorMsg());

            $errorReason = $this->getErrorReason($plugSize);

            logThis($errorReason);
            die($errorReason);
        }

        if ($disconnect) {
            $this->db->disconnect($this->conn);

            if ($this->isDebug()) {
                $this->debugLog("Db disconnect requested and done.\n");
            } //(debug)
        }

        return $outputXml;
    }


    public function appendCallXML($inputXml, $disconnect=false){
        $this->fullXML .= $inputXml;
        $this->disconnect = $disconnect;
    }

    public function sendFullXML($results = false){
        $this->rawOutput = $this->sendXml($this->fullXML, null);

        var_dump($this->fullXML);

        $query = new SQLProcessor($this);
        if ($results) {
            switch (strtolower($results)) {
                case 'json':
                    return $query->getJson();
                    break;
                case 'xmlobject':
                    return $query->getXMLObject();
                    break;
                case 'rawoutput':
                default:
                    return $query->getRawOutput();
                    break;
            }
        } else {
            return;
        }
    }



    /**
     * CLCommand
     *
     * @param array $command string will be turned into an array
     * @param string $exec could be 'pase', 'pasecmd', 'system,' 'rexx', or 'cmd'
     * @return array|bool
     */
    public function CLCommandPTH($command, $exec = '')
    {
        $this->XMLWrapperPTH = new PTHToolkitServiceXML(array('encoding' => $this->getOption('encoding')), $this);

        $this->cpfErr = '0';
        $this->error = '';
        $this->errorText = '';

        $inputXml = $this->XMLWrapperPTH->buildCommandXmlIn($command, $exec);

        // rexx and pase are the ways we might get data back.
        $expectDataOutput = in_array($exec, array('rexx', 'pase', 'pasecmd'));

        // if a PASE command is to be run, the tag will be 'sh'. Otherwise, 'cmd'.
        if ($exec == 'pase' || $exec == 'pasecmd') {
            $parentTag = 'sh';
        } else {
            $parentTag = 'cmd';
        }

        $this->VerifyPLUGName();

        // send the XML, running the command
        //$outputXml = $this->sendXml($inputXml, false);
        $this->appendCallXML($inputXml, false);

//        // get status: error or success, with a real CPF error message, and set the error code/msg.
//        $successFlag = $this->XMLWrapperPTH->getCmdResultFromXml($outputXml, $parentTag);
//
//        if ($successFlag) {
//            $this->cpfErr = '0';
//            $this->error = '';
//        } else {
//            $this->cpfErr = $this->XMLWrapperPTH->getErrorCode();
//            $this->error = $this->cpfErr; // ->error is ambiguous. Include for backward compat.
//            $this->errorText = $this->XMLWrapperPTH->getErrorMsg();
//        }
//
//        if ($successFlag && $expectDataOutput) {
//            // if we expect to receive data, extract it from the XML and return it.
//            $outputParamArray = $this->XMLWrapperPTH->getRowsFromXml($outputXml, $parentTag);
//
//            unset($this->XMLWrapperPTH);
//            return $outputParamArray;
//        } else {
//            // don't expect data. Return true/false (success);
//            unset($this->XMLWrapperPTH);
//            return $successFlag;
//        }
    }


}
