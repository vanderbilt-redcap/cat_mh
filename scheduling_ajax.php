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
	
	if (!empty($json->error))
		exit(json_encode($json));
	
	$user_datetime = date('Y-m-d H:i', $timestamp);
	
	list($ok, $msg) = $module->scheduleSequence($user_sequence, $user_datetime);
	
	if (!$ok) {
		$json->error = $msg;
	} else {
		$json->sequences = $module->getScheduledSequences();
	}
} elseif ($_POST['schedulingMethod'] == 'interval') {
	$module->llog("\$_POST['schedulingMethod'] == 'interval'");
		
	$frequency = (int) $_POST['frequency'];
	$duration = (int) $_POST['duration'];
	$delay = (int) $_POST['delay'];
	if (empty($_POST['time_of_day'])) {
		$time_of_day = "00:00";
	} else {
		$time_of_day = $_POST['time_of_day'];
	}
	
	// require frequency and duration
	if (empty($frequency) or empty($duration)) {
		$json->error = "The CAT-MH module can't schedule sequences by interval without valid frequency and duration parameters.";
		exit(json_encode($json));
	}
	if ($duration > 999) {
		$json->error = "The maximum allowed duration value is 999.";
		exit(json_encode($json));
	}
	
	$start_date = date("Y-m-d");
	if (!empty($delay)) {
		$start_date = date("Y-m-d", strtotime($start_date . " +" . $delay . " days"));
	}
	$module->llog("start_date: $start_date");
	
	for ($day_offset = 0; $day_offset < $duration; $day_offset += $frequency) {
		$next_datetime = date("Y-m-d", strtotime($start_date . " +" . $day_offset . " days"));
		list($ok, $id_or_msg) = $module->scheduleSequence($_POST['sequence'], $next_datetime . " $time_of_day");
		if (!$ok) {
			$json->error = $id_or_msg;
			exit(json_encode($json));
		}
	}
	
	$json->sequences = $module->getScheduledSequences();
	$module->llog("seqs: " . print_r($json->sequences, true));
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
