<?php
$out = $module->removeLogs("true");
// $out = $module->createInterviews(['instrument' => 'survey_1', 'recordID' => 1]);

// $out = $module->removeLogs("interviewID=192140");

// $params = [
	// 'identifier' => 'kadg',
	// 'instrument' => 'survey_1',
	// 'interviewID' => 191569,
	// 'label' => 'Anxiety Disorder (Perinatal)',
	// 'recordID' =>  1,
	// 'signature' => 'vu7r5p',
	// 'status' => 0,
	// 'subjectID' => "8IgG569YkMzDl4uPHyXoEmfW0FUcjEkP",
	// 'tstamp' => time(),
	// 'type' => 'mdd'
// ];
// $out = $module->log("createInterviews", $params);

// $args = [
	// 'subjectID' => $_GET['sid']
// ];
// $iview = $module->getInterview($args);
// if ($iview !== false) {
	// $out = $module->authInterview($iview);
// } else {
	// $out = 'no interviews found';
// }

// $in = [];
// $out = $module->getResultsTableData($in);

// $result = $module->queryLogs("select tstamp");
// $out = db_fetch_assoc($result);

echo "<pre>";
// print_r($iviews);
print_r($out);
echo "</pre>";