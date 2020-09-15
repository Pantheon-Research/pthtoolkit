<?php

/**
 * turn IPMF  into associative array
 * @param $cursor
 * @return array
 */
function ipmfParser($result){
    $cursor = $result->fetch;
    $retArr = [];

    foreach ($cursor->row as $record) {
        $name = (string)$record->data[0];
        $value =  (string)$record->data[1];
        $retArr[$name] = $value;
    }

    return $retArr;
}