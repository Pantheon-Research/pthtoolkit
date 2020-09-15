<?php

ini_set('display_startup_errors', 1);
ini_set('display_errors', 1);
error_reporting(-1);

// Get test components
require_once 'components/index.php';

// Setup test
$loopNr = 1;
$persistence = true;
$ToolkitServiceObj = getConnection($persistence);

for ($i = 0; $i < $loopNr ; $i++) {
    // Create table
    $ToolkitServiceObj->executeSQL("CREATE OR REPLACE TABLE QTEMP.peetable (firstname VARCHAR(255), surname VARCHAR(255), nationality VARCHAR(255))");

    // Populate rows
    $options = ['journaling' => false];
    
    $ToolkitServiceObj->executeSQL("INSERT INTO QTEMP.peetable (firstname, surname, nationality) VALUES ('Gav', 'de Ste Croix', 'British')", $options);
    $ToolkitServiceObj->executeSQL("INSERT INTO QTEMP.peetable (firstname, surname, nationality) VALUES ('Jeroen', 'Verzijl', 'Dutch')", $options);
    $ToolkitServiceObj->executeSQL("INSERT INTO QTEMP.peetable (firstname, surname, nationality) VALUES ('Martijn', 'Van Breden', 'Dutch')", $options);
    $ToolkitServiceObj->executeSQL("INSERT INTO QTEMP.peetable (firstname, surname, nationality) VALUES ('Roel', 'Krikke', 'Dutch')", $options);
    $ToolkitServiceObj->executeSQL("INSERT INTO QTEMP.peetable (firstname, surname, nationality) VALUES ('Joep', 'Beckeringh', 'Dutch')", $options);
    $ToolkitServiceObj->executeSQL("INSERT INTO QTEMP.peetable (firstname, surname, nationality) VALUES ('Menno', 'Siekerman', 'Dutch')", $options);

    // Get the table as Json
    $options = ['fetch' => true];
    $result = $ToolkitServiceObj->executeSQL("SELECT * FROM QTEMP.peetable", $options, null, 'json');

    // Decode the Json
    $decodedResult = json_decode($result);

    // Display the results
    if ($decodedResult) {
        echo '<PRE>' . print_r($decodedResult, true) . '</PRE>';
    }

    // If !persistence test then disconnect
    if (!$persistence) {
        $ToolkitServiceObj->disconnect();
    } 
}

// If persistence test then disconnect
if ($persistence) {
    $ToolkitServiceObj->disconnectPersistent();
}
