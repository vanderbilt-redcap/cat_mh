<?php
echo("<pre>");
// get pid
$pid = $module->getProjectId();

// // test delete record
// $tpk = \Records::getTablePK($module->getProjectId());
// $ret = \Records::deleteRecord(1, $tpk, null, null, null, null, null, "CAT-MH module removed record for consent==0", true);

// // test insert data
// $data = [];
// $data[1] = [];
// $data[1][60] = [];
// $data[1][60]["cat_mh_data"] = 'abc';
// // $data[1][60]["name"] = json_encode(['abc', 'def']);
// $result = \REDCap::saveData($pid, 'array', $data);

// // get instrument names
// $result = \REDCap::getInstrumentNames();

// // fetch event ID
// $project = new \Project($pid);
// $result = $project->firstEventId;

// fetch data
// $result = \REDCap::getData($pid, 'array', 1);
$result = $module->getPatientData("t9KyCq5afWbul7EOxrsHuc5221UcRbJa");

print_r($result);
echo("</pre>");
