<?php

$toolkitDir = 'ToolkitApi/';

require $toolkitDir . 'ToolkitService.php';

use ToolkitApi\Toolkit;
use ToolkitApi\XMLWrapper;
use ToolkitApi\ProgramParameter;

class PTH_ToolkitService extends ToolkitService
{
    static function getInstance($databaseNameOrResource = '*LOCAL', $userOrI5NamingFlag = '', $password = '', $transportType = '', $isPersistent = false)
    {
        return new PTH_Toolkit($databaseNameOrResource, $userOrI5NamingFlag, $password, $transportType, $isPersistent);
    }
}

class PTH_Toolkit extends Toolkit {


    public function executeSQL($type, $statement, $parser = null, $options = NULL) {
        $query = new SQLProcessor($this, $type);

        $query->wrapSQL($statement, $options);
        $query->runQuery();

        if ($parser) {
            $parsedResult = '';

            switch($parser) {
                case "json":
                    $parsedResult = $query->getJson();
                    break;
                default:
                    $parsedResult = 'No parser';
            }

            return $parsedResult;
        } else {
            return;
        }
    }
}


class SQLProcessor {
    protected $ToolkitServiceObj ;
    protected String $rawOutput;
    protected XMLWrapper $xml;

    private String $type;

    /*
     * @TODO Error logging
     */
    public function __construct($ToolkitServiceObj, $type) {
        $this->ToolkitServiceObj = $ToolkitServiceObj;
        $this->type = $type;
    }

    /**
     * Wrap the passed sql
     */
    public function wrapSQL($statement, $options = null) {
        $xml = "<sql>";

        if ($options) {
            $xml .= "<options ";

            foreach ($options as $option) {
                echo $option . " ";
            }

            $xml .= "/>";
        }

        $xml .= "<query>$statement";

        // Disable Commitment control for Insert
        if ($this.type === SqlType::insert) {
            $xml .= " with NONE";
        }

        $xml .= "</query>";

        // If we require an output then add a fetch block
        if ($this.type === SqlType::select) {
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

/**
 * turn IPMF  into associative array
 * @param $cursor
 * @return array
 */
function IPMFAsObject($cursor){
    $retArr = [];

    foreach ($cursor->row as $record) {
        $name = (string) $record->data[0];
        $value =  (string) $record->data[1];
        $retArr[$name] = $value;
    }

    return $retArr;
}

function debugVar($var) {
    echo '<p></p>';
    echo '<p>************************</p>';

    var_dump($var);

    echo '<p>************************</p>';
    echo '<p></p>';
}


abstract class  SqlType {
    const update ="update";
    const insert = "insert";
    const select = "select";
}