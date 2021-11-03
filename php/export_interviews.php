<?php
$months = intval($_GET['months']);
if ($months < 0)
	$months = 0;

$interviews = [];
$result = $module->queryLogs("SELECT interview WHERE message = ?", ['catmh_interview']);
while ($row = db_fetch_assoc($result)) {
	$interview = json_decode($row['interview']);
	unset($interview->jsessionid);
	unset($interview->awselb);
	if (strtotime($interview->scheduled_datetime . " + $months months") < time()) {
		$interviews[] = $interview;
	}
}

header('Content-disposition: attachment; filename=catmh_interview_export.json');
header('Content-type: application/download');
exit(json_encode($interviews));
