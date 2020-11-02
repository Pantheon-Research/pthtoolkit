<?php

namespace PTHToolkit;

// Don't forget to include the autoload.php in your project!
//require_once 'vendor/autoload.php';
require_once 'SQLProcessor.php';

use ToolkitApi\Toolkit;
use PTHToolkit\SQLProcesor;
use PDO;

class PTHToolkit extends Toolkit
{

    public function __construct($databaseNameOrResource = false, $userOrI5NamingFlag = false, $password = false, $transportType = false, $isPersistent = false)
    {
        // @GSC Empty
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
}
