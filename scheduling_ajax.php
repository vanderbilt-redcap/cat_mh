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
	// $module->llog("\$_POST['schedulingMethod'] == 'calendar'");
	
	if (!isValidSequenceName($user_sequence))
		$json->error = "'$user_sequence' is not the name of a valid sequence.";
	
	$timestamp = strtotime($_POST['datetime']);
	if ($timestamp === false)
		$json->error = $_POST['datetime'] . " is not a valid datetime.";
	
	if (!empty($json->error))
		exit(json_encode($json));
	
	$user_datetime = date('Y-m-d H:i:s', $timestamp);
	
	list($ok, $msg) = $module->scheduleSequence($user_sequence, $user_datetime);
	
	if (!$ok) {
		$json->error = $msg;
	} else {
		$json->sequences = $module->getScheduledSequences();
	}
} elseif ($_POST['schedulingMethod'] == 'interval') {
	$module->llog("\$_POST['schedulingMethod'] == 'interval'");
	
} elseif ($_POST['schedulingMethod'] == 'delete') {
	$module->llog("\$_POST['schedulingMethod'] == 'delete'");
	
	$sequences = $_POST['sequencesToDelete'];
	foreach($sequences as $seq_index => $sequence) {
		$return_value = $module->unscheduleSequence($sequence['name'], $sequence['datetime']);
		$module->llog("unschedule result: " . print_r($return_value, true));
	}
	
	$json->sequences = $module->getScheduledSequences();
} elseif ($_POST['schedulingMethod'] == 'setReminderSettings') {
	$module->llog("\$_POST['schedulingMethod'] == 'setReminderSettings'");
	
	$settings = [];
	if ($_POST['enabled'] == 'on') {
		$settings['enabled'] = true;
	} else {
		$settings['enabled'] = false;
	}
	
	$settings['frequency'] = (int) $_POST['frequency'];
	$settings['duration'] = (int) $_POST['duration'];
	$settings['delay'] = (int) $_POST['delay'];
	
	$module->setReminderSettings($settings);
	$json->reminderSettings = $module->getReminderSettings();
	$module->llog("\$json->reminderSettings\n" . print_r($json->reminderSettings, true));
	
} else {
	$json->error = 'No scheduling method specified (must be calendar or interval).';
}

// send response
// $module->llog("json: " . print_r($json, true));
exit(json_encode($json));
