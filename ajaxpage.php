<?php

namespace CB1\ExternalModule;

require_once(__DIR__ . '/ExternalModule.php');

$record_id = $_REQUEST['recordId'];
$instrument = $_REQUEST['instrument'];

$EM = new ExternalModule();

$patient_data = $EM->getPatientInfo($record_id, $instrument);
echo json_encode($patient_data);
