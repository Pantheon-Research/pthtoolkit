<?php

namespace PTHToolkit;

class SQLProcessor
{
    protected $ToolkitServiceObj = null;
    //protected $rawOutput = null;
    protected $xml = null;

    /*
    * @TODO Error logging
    */
    public function __construct($ToolkitServiceObj)
    {
        $this->ToolkitServiceObj = $ToolkitServiceObj;
    }

    /**
     * Wrap the passed sql
     */
    public function wrapSQL($statement, $options, $sqlOptions = null)
    {
        // Placeholder merge for options
        $options = array_merge([
            'fetch' => false,
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
     * @param bool $original
     */
    public function runQuery($original = false)
    {
        if ($this->xml) {
            // send XML to XMLSERVICE
            if ($original == false){
                $this->ToolkitServiceObj->appendCallXML($this->xml, null);
            } else {
                $this->rawOutput = $this->ToolkitServiceObj->sendXml($this->xml, null);
            }
        } else {
            $this->rawOutput = "Error";
        }
    }

    /**
     * Get raw xml string output from processed query
     * @return string
     */
    public function getRawOutput($rawOutput)
    {
        $this->rawOutput = $rawOutput;
        return $this->rawOutput;
    }


    /**
     * @param $rawOutput
     * @return \SimpleXMLElement
     */
    public function getXMLObject($rawOutput)
    {
        $this->rawOutput = utf8_encode($rawOutput);
        return simplexml_load_string($this->rawOutput);
    }

    /**
     * Parse response XML to JSON
     * @return array
     */
    public function getJson($rawOutput)
    {
        $result = $this->getXMLObject($rawOutput);
        $json = $this->jsonParser($result->sql);
        //todo prepare for multiple sql fetches
        // @GSC removed the JSON encode for Laravel compatibility

        return $json;
    }

    /**
     * Parse object to Json
     * @return array
     */
    private function jsonParser($obj)
    {
        $cursor = $obj->fetch;
        $result = [];
        $columnHeaders = [];
        $i = 0;

        //do we have a cursor?
        if(!isset($cursor) || !isset($cursor->row)){
            return $result;
        }

        foreach ($cursor->row as $row) {
            $rowReturn = [];

            foreach ($row->data as $key => $record) {
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
