<?php
$module->llog('post data: ' . print_r($_POST, true));
$json = new \stdClass();
$pid = $module->getProjectId();
$sid = $_POST['sid'];
$reviewed = $_POST['reviewed'];
$seq = $_POST['seq'];
$kcat = $_POST['kcat'];
$sched_dt = $_POST['date'];
$test_name = $_POST['test'];
$time_now = time();

// get matching interview
if (empty($kcat)) {
	$interview = $module->getSequence($seq, $sched_dt, $sid);
} else {
	$interview = $module->getSequence($seq, $sched_dt, $sid, $kcat);
}

if (empty($interview))
	$json->error = "The CAT-MH module wasn't able to find the interview for this record using the supplied datetime ($sched_dt) and sequence name ($seq).";
if (empty($interview->results))
	$json->error = "The CAT-MH module found a matching interview, but no results were attached. Please contact your program administrator and the module author with this error.";
if (empty($interview->results->tests))
	$json->error = "The CAT-MH module found a matching interview, but no per-test results were attached. Please contact your program administrator and the module author with this error.";
if (!empty($json->error))
	exit(json_encode($json));

// find matching test object
foreach ($interview->results->tests as &$test) {
	if ($test->label == $test_name)
		$target_test = $test;
}

if (empty($target_test)) {
	$json->error = "The CAT-MH module found a matching interview but the test in question ($test_name) is missing from the results set. Please contact your program administrator and the module author with this error.";
	exit(json_encode($json));
}

if ($reviewed === 'true') {
	if ($target_test->reviewed) {
		$success = true;
	} else {
		$target_test->reviewed = true;
		$success = $module->updateInterview($interview);
	}
} else {
	if (!$target_test->reviewed) {
		$success = true;
	} else {
		$target_test->reviewed = false;
		$success = $module->updateInterview($interview);
	}
}

if ($success) {
	$json->success = true;
} else {
	$json->error = "The CAT-MH module wasn't able to change the acknowledgement status of this sequence. Please contact DataCore@vumc.org with this message.";
}
exit(json_encode($json));