<?php
$out = $module->removeLogs("true");
$out = $module->createInterviews(['instrument' => 'survey_1', 'recordID' => 1]);

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
if ($iviews !== false) {
$iview1 = $iviews[0];
$out = $module->getInterviewStatus($iview1);
} else {
	$out = 'no interviews found';
}

echo "<pre>";
// print_r($iviews);
print_r($out);
echo "</pre>";