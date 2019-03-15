<?php
// $out = $module->removeLogs("true");
// $out = $module->createInterviews(['instrument' => 'survey_2', 'recordID' => 1]);
// $subjectID = $_GET['sid'];
// $out = $module->getQueryLogsSql("select interview, instrument, recordID, status where subjectID='$subjectID' order by timestamp desc");
// print_r($out);


// $params = [
	// 'subjectID' => "8iPISYvnnZYFM4FIzdqFglOgybMZKlgm",
	// 'recordID' =>  5,
	// 'interviewID' => 191569,
	// 'status' => 0,
	// 'timestamp' => time(),
	// 'instrument' => 'survey_1',
	// 'identifier' => 'kadg',
	// 'signature' => 'vu7r5p',
	// 'type' => 'mdd',
	// 'label' => 'Major Depressive Disorder'
// ];
// $module->log("createInterviews", $params);


$args = [
	'subjectID' => $_GET['sid']
];
$iviews = $module->getInterviews($args);
$iview1 = $iviews[0];
$out = $module->startInterview($iview1);
echo "<pre>";
print_r($out);
echo "</pre>";