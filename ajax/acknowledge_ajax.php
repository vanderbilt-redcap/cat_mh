<?php
$module->llog('post data: ' . print_r($_POST, true));
$json = new \stdClass();
$pid = $module->getProjectId();
$rid = $_POST['rid'];
$acknowledged = $_POST['acknowledged'];
$seq = $_POST['seq'];
$kcat = $_POST['kcat'];
$sched_dt = $_POST['date'];
$time_now = time();
if (isset($_GET['dash_time'])) {
	$time_now = strtotime($_GET['dash_time']);
}

// get subjectID for this record
$sid = $module->getSubjectID($rid);
// validate sid
if (empty($sid)) {
	$json->error = "Couldn't find a matching subjectID for this record: $rid";
	exit(json_encode($json));
}

// count any existing acks for this specific sequence
$existing_ack_count = $module->countLogs("message = ? AND sequence = ? AND scheduled_datetime = ? AND subjectID = ? AND kcat = ?", ["acknowledged_delinquent", $seq, $sched_dt, $sid, $kcat]);
$module->llog("existing_ack_count: $existing_ack_count");

$color_to_return = 'blue';
if ($acknowledged === 'true') {
	if ($existing_ack_count) {
		$success = true;
	} else {
		$success = $module->log("acknowledged_delinquent", [
			"sequence" => $seq,
			"scheduled_datetime" => $sched_dt,
			"subjectID" => $sid,
			'kcat' => $kcat
		]);
	}
} else {
	if (!$existing_ack_count) {
		$module->llog("no existing ack");
		$success = true;
	} else {
		$module->llog("removing existing ack");
		$success = $module->removeLogs("message = ? AND sequence = ? AND scheduled_datetime = ? AND subjectID = ? AND kcat = ?", ["acknowledged_delinquent", $seq, $sched_dt, $sid, $kcat]);
	}
	
	// change color to return based on interview status
	$days_to_complete = $module->getProjectSetting('expected_complete')[$module->getSequenceIndex($seq)];
	$date_to_complete = date("Y-m-d H:i", strtotime("+$days_to_complete days", strtotime($sched_dt)));
	$completed_within_window = "";
	if ($time_now >= strtotime($date_to_complete))
		$completed_within_window = "N";
	$interview = $module->getSequence($seq, $sched_dt, $sid);
	$module->llog('interview on ack off: '  . print_r($interview, true));
	if (empty($interview) or ($interview->status == false) or ($interview->status == 1)) {
		if ($completed_within_window == 'N') {		// not started or completed, AND overdue/delinquent: red circle icon
			$color_to_return = 'red';
		} else {									// not started or completed, append gray circle img
			$color_to_return = 'gray';
		}
	} elseif ($interview->status != 4) {			// started but not completed, append yellow circle img
		$color_to_return = 'yellow';
	} elseif ($interview->status == 4) {			// append green circle (which itself, is a link to filtered results report)
		$color_to_return = 'green';
	}
}

if ($success) {
	$json->success = true;
	$json->color = $color_to_return;
} else {
	$json->error = "The CAT-MH module wasn't able to change the acknowledgement status of this sequence. Please contact DataCore@vumc.org with this message.";
}
exit(json_encode($json));