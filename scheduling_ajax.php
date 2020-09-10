<?php

// sanitize inputs
$_POST  = filter_input_array(INPUT_POST, FILTER_SANITIZE_STRING);
$module->llog("\$_POST:\n" . print_r($_POST, true));

// initialize functions/properties
$sequenceNames = $module->getProjectSetting('sequence');

function isValidSequenceName($name) {
	global $sequenceNames;
	foreach ($sequenceNames as $i => $seq_name) {
		if ($name == $seq_name)
			return true;
	}
	return false;
}

// process request
$json = new \stdClass();
$user_sequence = $_POST['sequence'];

if ($_POST['schedulingMethod'] == 'calendar') {
	$module->llog("\$_POST['schedulingMethod'] == 'calendar'");
	
	if (!isValidSequenceName($user_sequence))
		$json->error = "'$user_sequence' is not the name of a valid sequence.";
	
	$timestamp = strtotime($_POST['datetime']);
	if ($timestamp === false)
		$json->error = $_POST['datetime'] . " is not a valid datetime.";
	
	$module->llog("user_sequence: $user_sequence");
	$module->llog("timestamp: $timestamp");
	
	$user_datetime = date('Y-m-d H:i:s', $timestamp);
	$module->llog("user_datetime: $user_datetime");
	
	
} elseif ($_POST['schedulingMethod'] == 'interval') {
	$module->llog("\$_POST['schedulingMethod'] == 'interval'");
} else {
	$json->error = 'No scheduling method specified (must be calendar or interval).';
}

// send response
$module->llog("json: " . print_r($json, true));
exit(json_encode($json));
