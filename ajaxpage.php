<?php

namespace CBC\ExternalModule;
require_once(__DIR__ . '/ExternalModule.php');

$record_id = $_REQUEST['recordId'];
$instrument = $_REQUEST['instrument'];

$EM = new ExternalModule();

echo json_encode($EM->getPersonInfo($record_id, $instrument));

?>
