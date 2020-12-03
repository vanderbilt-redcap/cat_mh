<?php
$json = new \stdClass();
$pid = $module->getProjectId();
$rid = $_POST['rid'];
$sid = $module->getSubjectID($rid);
if (empty($sid)) {
	$json->error = "Couldn't find a matching subjectID for this record: $rid";
	exit(json_encode($json));
}

$seq = $_POST['seq'];
$sched_dt = $_POST['date'];

$interview = $module->getSequence($seq, $sched_dt, $sid);
if (empty($sid)) {
	$json->error = "Couldn't find a matching interview for (record, sequence, datetime): ($rid, $seq, $sched_dt)";
	exit(json_encode($json));
}

if (!$success = $module->countLogs("message = ? AND sequence = ? AND scheduled_datetime = ? AND subjectID = ?", ["acknowledged_delinquent", $seq, $sched_dt, $sid])) {
	$success = $module->log("acknowledged_delinquent", [
		"sequence" => $seq,
		"scheduled_datetime" => $sched_dt,
		"subjectID" => $sid
	]);
}

if ($success) {
	$json->success = true;
} else {
	$json->error = "Couldn't find a matching interview for (record, sequence, datetime): ($rid, $seq, $sched_dt)";
}
exit(json_encode($json));