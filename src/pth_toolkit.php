<?php

$toolkitDir = 'ToolkitApi/';

require $toolkitDir . 'ToolkitService.php';

use ToolkitApi\Toolkit;
use ToolkitApi\XMLWrapper;
use ToolkitApi\ProgramParameter;

class PTH_ToolkitService extends ToolkitService {
    static function getInstance($databaseNameOrResource = '*LOCAL', $userOrI5NamingFlag = '', $password = '', $transportType = '', $isPersistent = false) {
        return new PTH_Toolkit($databaseNameOrResource, $userOrI5NamingFlag, $password, $transportType, $isPersistent);
    }
}

class PTH_Toolkit extends Toolkit {

    public function __construct($databaseNameOrResource, $userOrI5NamingFlag = '0', $password = '', $transportType = '', $isPersistent = false) {

        // Check for PDO connection
        if (strtolower($transportType) === 'pdo') {
            $dbconn = $this->pdoConn($databaseNameOrResource, $userOrI5NamingFlag[0], $password, $userOrI5NamingFlag[1]);
        }

        parent::__construct($dbconn, $userOrI5NamingFlag[1], $password, $transportType, $isPersistent);
    }

    /*
     * Add SQL support (straight execute)
     */
    public function executeSQL($statement, $options = [], $sqlOptions = NULL, $results = false) {

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
    private function pdoConn($databaseName, $user, $pass, $namingMode = 1, $persistence = false) {
        try {
            $dbconn = new PDO("odbc:DSN=$databaseName;NAM=$namingMode", $user, $pass, array(
                PDO::ATTR_PERSISTENT => $persistence,
                PDO::ATTR_ERRMODE => PDO::ERRMODE_WARNING,
            ));
        } catch (PDOException $e) {
            die ('PDO connection failed: ' . $e->getMessage());
        }

        return $dbconn;
    }
}

class SQLProcessor {
    protected $ToolkitServiceObj = null;
    protected $rawOutput = null;
    protected $xml = null;

    /*
     * @TODO Error logging
     */
    public function __construct($ToolkitServiceObj) {
        $this->ToolkitServiceObj = $ToolkitServiceObj;
    }

    /**
     * Wrap the passed sql
     */
    public function wrapSQL($statement, $options, $sqlOptions = null) {
        // Placeholder merge for options
        $options = array_merge([
            'fetch'      => false,
            'journaling' => true
        ], $options);

        $xml = "<sql>";

        if ($sqlOptions) {
            $xml .= "<sqlOptions ";

            foreach ($sqlOptions as $option) {
                echo $option . " ";
            }

            $xml .= "/>";
        }

        $xml .= "<query>$statement";

        // Disable Commitment control for Insert
        if ($options['journaling'] === false) {
            $xml .= " with NONE";
        }

        $xml .= "</query>";

        // If we require an output then add a fetch block
        if ($options['fetch'] === true) {
            $xml .= "<fetch block = 'all' desc = 'on' />";
        }

        $xml .= "</sql>";

        // Store XML
        $this->xml = $xml;
    }

    /**
     * Run query from wrapped sql
     */
    public function runQuery() {
        if ($this->xml) {
            // send XML to XMLSERVICE
            $this->rawOutput = $this->ToolkitServiceObj->sendXml($this->xml,  null);
        } else {
            $this->rawOutput = "Error";
        }
    }

    /**
     * Get raw xml string output from processed query
     * @return string
     */
    public function getRawOutput() {
        return $this->rawOutput;
    }

    /**
     * Parse response XML to object
     * @return obj
     */
    public function getXMLObject() {
        return simplexml_load_string($this->rawOutput);
    }

    /**
     * Parse response XML to JSON
     * @return string
     */
    public function getJson() {
        $result = $this->getXMLObject();
        $json = $this->jsonParser($result);

        return json_encode($json);
    }

    /**
     * Parse object to Json
     * @return string
     */
    private function jsonParser($obj) {
        $cursor = $obj->fetch;
        $result = [];
        $columnHeaders = [];
        $i = 0;

        foreach($cursor->row as $row) {
            $rowReturn = [];

            foreach($row->data as $key=>$record) {
                // Only store the column header once
                if ($i < 1) {
                    // Add column header to header array
                    $columnHeaders[] = (string)$record['desc'];
                }

                // Add column result to row return
                $rowReturn[] = (string)$record;
            }

            $i++;

            // Add row to the result
            $result[] = $rowReturn;
        }

        // Push column headers to top of array
        array_unshift($result, $columnHeaders);

        return $result;
    }
}