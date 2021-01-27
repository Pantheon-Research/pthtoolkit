<?php

namespace PTHToolkit;

// Don't forget to include the autoload.php in your project!
//require_once 'vendor/autoload.php';
require_once 'SQLProcessor.php';

use Exception;
use PTHToolkit\PTHToolkitServiceXML;
use ToolkitApi\ProgramParameter;
use ToolkitApi\Toolkit;
use PTHToolkit\SQLProcessor;
use PDO;

class PTHToolkit extends Toolkit
{
    protected $fullXML = "<script>";
    protected $disconnect = null;
    protected $query = null;
    private \PTHToolkit\PTHToolkitServiceXML $XMLWrapperPTH;

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
    public function executeSQL($statement, $options = [], $sqlOptions = NULL, $original = false)
    {

        $this->query = new SQLProcessor($this);

        $this->query->wrapSQL($statement, $options, $sqlOptions);
        $this->query->runQuery($original);

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


    /*
    * @MSI Overloading CLCommand to enable a single XML call
    */
    /**
     * @param array $command
     * @param string $exec
     * @param bool $original
     * @return array|bool|void
     * @throws \Exception
     */
    public function CLCommand($command, $exec = '', $original = false)
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

        if ($original == false){
            //append xml
            $this->appendCallXML($inputXml, false);
        }else{
            $this->appendCallXML($inputXml, false);
            $this->fullXML .= "</script>";
            // send single xml (original toolkit)
            $outputXml = $this->sendXml($this->fullXML, false);

            // get status: error or success, with a real CPF error message, and set the error code/msg.
            $successFlag = $this->XMLWrapper->getCmdResultFromXml($outputXml, $parentTag);

            if ($successFlag) {
                $this->cpfErr = '0';
                $this->error = '';
            } else {
                $this->cpfErr = $this->XMLWrapper->getErrorCode();
                $this->error = $this->cpfErr; // ->error is ambiguous. Include for backward compat.
                $this->errorText = $this->XMLWrapper->getErrorMsg();
            }

            if ($successFlag && $expectDataOutput) {
                // if we expect to receive data, extract it from the XML and return it.
                $outputParamArray = $this->XMLWrapper->getRowsFromXml($outputXml, $parentTag);

                unset($this->XMLWrapper);
                return $outputParamArray;
            } else {
                // don't expect data. Return true/false (success);
                unset($this->XMLWrapper);
                return $successFlag;
            }
        }
    }

    /*
    * @MSI Overloading pgmCall to enable a single XML call
    */
    /**
     * pgmCall
     *
     * @param string $pgmName Name of program to call, without library
     * @param string $lib Library of program. Leave blank to use library list or current library
     * @param null $inputParam An array of ProgramParameter objects OR XML representing params, to be sent as-is.
     * @param null $returnParam ReturnValue Array of one parameter that's the return value parameter
     * @param null $options Array of other options. The most popular is 'func' indicating the name of a subprocedure or function.
     * @param bool $original
     * @return void
     * @throws \Exception
     */
    public function pgmCall($pgmName, $lib, $inputParam = NULL, $returnParam = NULL, $options = NULL, $original = false)
    {
        $this->cpfErr = '';
        $this->error = '';
        $this->joblog = '';
        $function = NULL;

        ProgramParameter::initializeFallbackVarName();

        // If only one 'return' param, turn it into an array for later processing.
        if ($returnParam instanceof ProgramParameter) {
            $returnParam = array($returnParam);
        }

        $this->XMLWrapperPTH = new PTHToolkitServiceXML(array('encoding' => $this->getOption('encoding')), $this);

        $outputParamArray = false;

        if (isset($options['func'])) {
            $function = $options['func'];
        }

        $inputXml = $this->XMLWrapperPTH->buildXmlIn($inputParam, $returnParam, $pgmName, $lib, $function);

        if ($original == false){
            //append xml
            $this->appendCallXML($inputXml, false);
        }else{
            $this->appendCallXML($inputXml, false);
            $this->fullXML .= "</script>";
            // send single xml (original toolkit)
            $outputXml = $this->sendXml($this->fullXML, false);

            if ($outputXml != '') {

                $outputParamArray = $this->XMLWrapper->getParamsFromXml($outputXml);

                // didn't get expected return, search logs to find out why
                if (!is_array($outputParamArray)) {
                    // No real data. Look for errors. Retrieve details from joblog.
                    $this->joblog = $this->XMLWrapper->getLastJoblog();

                    // standard list of programs that provide CPF codes in joblog
                    $programsToLookFor = array($pgmName, '< lveContext', '#mnrnrl', 'QRNXIE', '< allProgram');

                    if (isset($this->_cpfMapping[$pgmName])) {
                        // list of other programs not called directly that might generate CPF codes in joblog.
                        $programsToLookFor = array_merge($programsToLookFor, $this->_cpfMapping[$pgmName]);
                    }

                    // put values in $this->cpfErr and $this->error
                    $this->extractErrorFromJoblog($programsToLookFor);
                }
            }
            return $outputParamArray;
        }
        unset ($this->XMLWrapper);
    }

    /**
     * @param $inputXml
     * @param false $disconnect
     */
    public function appendCallXML($inputXml, $disconnect=false){
        $this->fullXML .= $inputXml;
        $this->disconnect = $disconnect;
    }

    /**
     * @param false $results
     * @return array
     */
    public function sendFullXML($results = false, $log = false){
        $this->fullXML .= "</script>";

        $t1 = microtime(true);
        $outputXml = $this->sendXml($this->fullXML, null);
        $t2 = microtime(true);
        $this->fullXML = "<script>";
        if ($log != false){
            $td = $t2-$t1;
            file_put_contents("log/".$log."_".date("j.n.Y").'.log', $td. "\n", FILE_APPEND);
        }

        // get status: error or success, with a real CPF error message, and set the error code/msg.
        $successFlag = true;//$this->XMLWrapperPTH->getResultFromXML($outputXml);

        if ($successFlag) {
            $this->cpfErr = '0';
            $this->error = '';
        } else {
            $this->cpfErr = $this->XMLWrapperPTH->getErrorCode();
            $this->error = $this->cpfErr; // ->error is ambiguous. Include for backward compat.
            $this->errorText = $this->XMLWrapperPTH->getErrorMsg();
            throw new Exception($this->cpfErr .': '. $this->errorText);
        }

        if ($successFlag && $results) {
            switch (strtolower($results)) {
                case 'json':
                    return $this->query->getJson($outputXml);
                    break;
                case 'xmlobject':
                    return $this->query->getXMLObject($outputXml);
                    break;
                case 'rawoutput':
                default:
                    return $this->query->getRawOutput($outputXml);
                    break;
            }
        } else {
            // don't expect data. Return true/false (success);
            return $successFlag;
        }
    }

}
