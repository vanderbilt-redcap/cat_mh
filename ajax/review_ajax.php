<?php
// $module->llog('post data: ' . print_r($_POST, true));
$json = new \stdClass();
$pid = $module->getProjectId();
$sid = $_POST['sid'];
$reviewed = $_POST['reviewed'];
$seq = $_POST['seq'];
$sched_dt = $_POST['date'];
$test_name = $_POST['test'];
$time_now = time();

if ($module->getKCATSequenceIndex($seq) === false) {
	$test_reviewed = $module->countLogs("message = ? AND subjectid = ? AND sequence = ? AND scheduled_datetime = ? AND test_name = ?", [
		'reviewed_test',
		$sid,
		$seq,
		$sched_dt,
		$test_name
	]);
	
	if ($reviewed === 'true') {
		if ($test_reviewed) {
			$success = true;
		} else {
			$success = $module->log('reviewed_test', [
				"subjectid" => $sid,
				"sequence" => $seq,
				"scheduled_datetime" => $sched_dt,
				"test_name" => $test_name
			]);
		}
	} else {
		if (!$test_reviewed) {
			$success = true;
		} else {
			$success = $module->removeLogs("message = ? AND subjectid = ? AND sequence = ? AND scheduled_datetime = ? AND test_name = ?", [
				'reviewed_test',
				$sid,
				$seq,
				$sched_dt,
				$test_name
			]);
		}
	}
} else {
	$kcat = $_POST['kcat'];
	$test_reviewed = $module->countLogs("message = ? AND subjectid = ? AND sequence = ? AND scheduled_datetime = ? AND test_name = ? AND kcat = ?", [
		'reviewed_test',
		$sid,
		$seq,
		$sched_dt,
		$test_name,
		$kcat
	]);
	
	if ($reviewed === 'true') {
		if ($test_reviewed) {
			$success = true;
		} else {
			$success = $module->log('reviewed_test', [
				"subjectid" => $sid,
				"sequence" => $seq,
				"scheduled_datetime" => $sched_dt,
				"test_name" => $test_name,
				"kcat" => $kcat
			]);
		}
	} else {
		if (!$test_reviewed) {
			$success = true;
		} else {
			$success = $module->removeLogs("message = ? AND subjectid = ? AND sequence = ? AND scheduled_datetime = ? AND test_name = ? AND kcat = ?", [
				'reviewed_test',
				$sid,
				$seq,
				$sched_dt,
				$test_name,
				$kcat
			]);
		}
	}
}

if ($success) {
	$json->success = true;
} else {
	$json->error = "The CAT-MH module wasn't able to change the acknowledgement status of this sequence.";
}