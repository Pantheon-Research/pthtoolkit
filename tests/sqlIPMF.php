<?php

ini_set('display_startup_errors', 1);
ini_set('display_errors', 1);
error_reporting(-1);

// Get test components
require_once 'components/index.php';

// Setup test
$loopNr = 3;
$persistence = true;
$ToolkitServiceObj = getConnection($persistence);

for ($i = 0; $i < $loopNr ; $i++) {
    // Call ipmf program (RPG creates table in QTEMP)
    ipmfCall($ToolkitServiceObj);

    $options = [
        'fetch' => true,
    ];

    $result = $ToolkitServiceObj->executeSQL("SELECT * FROM QTEMP.IPMF", $options, null, 'xmlobject');

    // Decode the Json
    $parsedResults = ipmfParser($result);

    // Display the results
    if ($parsedResults) {
        echo '<PRE>' . print_r($parsedResults, true) . '</PRE>';
    }

    // Drop the table for next loop
    $ToolkitServiceObj->executeSQL("DROP table QTEMP.IPMF");

    // If !persistence test then disconnect
    if (!$persistence) {
        $ToolkitServiceObj->disconnect();
    }
}

// If persistence test then disconnect
if ($persistence) {
    $ToolkitServiceObj->disconnectPersistent();
}