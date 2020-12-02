<?php

$json = new \stdClass();
$pid = $module->getProjectId();
$rid = $_GET['rid'];
$sid = $module->getSubjectID($rid);
if (empty($sid)) {
	$json->error("Couldn't find a matching subjectID for this record: $rid");
	exit(json_encode($json));
}

$seq = $_GET['seq'];
$sched_dt = $_GET['date'];

$interview = $module->getSequence($seq, $sched_dt, $sid);
if (empty($sid)) {
	$json->error("Couldn't find a matching interview for (record, sequence, datetime): ($rid, $seq, $sched_dt)");
	exit(json_encode($json));
}

$interview->acknowledged_delinquent = true;
$success = $module->updateInterview($interview);
if ($success) {
	$json->success = true;
} else {
	$json->error("Couldn't find a matching interview for (record, sequence, datetime): ($rid, $seq, $sched_dt)");
}
exit(json_encode($json));