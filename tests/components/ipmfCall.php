<?php

function ipmfCall($ToolkitServiceObj) {
    $param = [];
    $param[] = $ToolkitServiceObj->AddParameterChar('out', 10, 'JOBID', 'JOBID', '');
    $param[] = $ToolkitServiceObj->AddParameterChar('out', 10, 'USER', 'USER', '');
    $param[] = $ToolkitServiceObj->AddParameterChar('out', 10, 'CURUSER', 'CURUSER', '');

    return $ToolkitServiceObj->PgmCall("JOBINFO", "PROPGM73D", $param, null, null);
}