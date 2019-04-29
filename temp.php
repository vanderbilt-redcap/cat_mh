<?php
echo("<pre>");
// get pid
$pid = $module->getProjectId();

// // remove all log data
// $module->removeLogs("subjectID<>''");

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
// $result = \REDCap::getData($pid, 'array', 2);
// $result = $module->getPatientData("t9KyCq5afWbul7EOxrsHuc5221UcRbJa");

// $val = $module->getProjectSettings()['sequence']['value'][0];
// var_dump($pid);

// // test authInterview
// $args = [
	// "subjectID" => "M9reQwkrr5qbKJTWNThLO2G9yWrFOpb3",
	// "interviewID" => 197504,
	// "identifier" => "nm87",
	// "signature" => "2cf466",
// ];
// $result = $module->authInterview($args);

// // test getAuthValues
// $result = $module->getAuthValues($args);

// $data = \REDCap::getData($pid, 'array', 2);
// $rid = array_keys($data)[0];
// $eid = array_keys($data[$rid])[0];
// $data[$rid][$eid]['cat_mh_data'] = json_decode($data[$rid][$eid]['cat_mh_data'], true);
// print_r($data);

// // test module->get/setProjectSetting
// $module->setProjectSetting('daysElapsed', 0);
$results = $module->getProjectSetting('daysElapsed');

var_dump($results);
echo("</pre>");
