<?php

require dirname(__FILE__) . '/../../src/pth_toolkit.php';

function getConnection($persistence = false, $flag = null) {
    $db = '*LOCAL';
    $user = 'gsc';
    $pass = 'One4six';
    $extension = 'pdo';
    $namingMode = 1;
    $InternalKey = "/tmp/$user$flag";

    try {
        $ToolkitServiceObj = PTH_ToolkitService::getInstance($db, [$user, $namingMode], $pass, $extension, $persistence);
    } catch (Exception $e) {
        echo $e->getMessage(), "\n";
        exit();
    }

    $ToolkitServiceObj->setToolkitServiceParams(array(
        'InternalKey' => $InternalKey,
        'debug' => true,
        'debugLogFile' => "/pfmphp/notkotlin/log/tkit_debug.log"
    ));

    return $ToolkitServiceObj;
}