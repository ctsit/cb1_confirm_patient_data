<?php

namespace CBC\ExternalModule;
require_once(__DIR__ . '/ExternalModule.php');

$record_id = $_REQUEST['recordId'];
$instrument = $_REQUEST['instrument'];

$EM = new ExternalModule();

$caregiver_data = $EM->getCaregiverInfo($record_id, $instrument);
echo json_encode($caregiver_data);

?>
