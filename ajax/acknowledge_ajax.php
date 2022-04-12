<?php
$json = new \stdClass();
$pid = $module->getProjectId();
$rid = $_POST['rid'];
$acknowledged = $_POST['acknowledged'];
$seq = $_POST['seq'];
$kcat = $_POST['kcat'];
$sched_dt = $_POST['date'];
$time_now = time();
if (isset($_GET['dash_time'])) {
	$time_now = strtotime(htmlentities($_GET['dash_time'], ENT_QUOTES, 'UTF-8'));
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
// $module->llog("existing_ack_count: $existing_ack_count");

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
	
	$completed_icon = "<img src='{$module->interviewStatusIconURLs['blue']}' class='fstatus' data-color='blue' style='width:16px;margin-right:6px;' alt=''>";
} else {
	if (!$existing_ack_count) {
		// $module->llog("no existing ack");
		$success = true;
	} else {
		// $module->llog("removing existing ack");
		$success = $module->removeLogs("message = ? AND sequence = ? AND scheduled_datetime = ? AND subjectID = ? AND kcat = ?", ["acknowledged_delinquent", $seq, $sched_dt, $sid, $kcat]);
	}
	
	// change color to return based on interview status
	$days_to_complete = $module->getProjectSetting('expected_complete')[$module->getSequenceIndex($seq)];
	$date_to_complete = date("Y-m-d H:i", strtotime("+$days_to_complete days", strtotime($sched_dt)));
	$completed_within_window = "";
	if ($time_now >= strtotime($date_to_complete))
		$completed_within_window = "N";
	
	if (empty($kcat)) {
		$interview = $module->getSequence($seq, $sched_dt, $sid);
	} else {
		$interview = $module->getSequence($seq, $sched_dt, $sid, $kcat);
	}
	
	// Completed column	# priority: green (completed) > blue (acknowledged) > yellow (started) > gray/red (incomplete/delinquent)
	$completed_icon = null;
	if ($interview->status == 4) {			// append green circle (which itself, is a link to filtered results report)
		$img = "<img src='{$module->interviewStatusIconURLs['green']}' class='fstatus' data-color='green' style='width:16px;margin-right:6px;' alt=''>";
		$link = $module->getUrl('resultsReport.php') . "&record=$rid&seq=" . urlencode($seq) . "&sched_dt=" . urlencode($sched_dt);
		$completed_icon = "<a href='$link'>$img</a>";
	} elseif ($interview->status > 1) {			// started but not completed, append yellow circle img
		$completed_icon = "<img src='{$module->interviewStatusIconURLs['yellow']}' class='fstatus' data-color='yellow' style='width:16px;margin-right:6px;' alt=''>";
	} elseif ($interview_acknowledged_delinquent) {		// acknowledged delinquent, blue circle icon
		$completed_icon = "<img src='{$module->interviewStatusIconURLs['blue']}' class='fstatus' data-color='blue' style='width:16px;margin-right:6px;' alt=''>";
	} else {
		if ($completed_within_window == 'N') {		// not started or completed, AND overdue/delinquent: red circle icon
			$completed_icon = "<img src='{$module->interviewStatusIconURLs['red']}' class='fstatus' data-color='red' style='width:16px;margin-right:6px;' alt=''>";
		} else {									// not started or completed, append gray circle img
			$completed_icon = "<img src='{$module->interviewStatusIconURLs['gray']}' class='fstatus' data-color='gray' style='width:16px;margin-right:6px;' alt=''>";
		}
	}
}

if ($success) {
	$json->success = true;
	$json->icon = $completed_icon;
} else {
	$json->error = "The CAT-MH module wasn't able to change the acknowledgement status of this sequence. Please contact DataCore@vumc.org with this message.";
}
exit(json_encode($json));