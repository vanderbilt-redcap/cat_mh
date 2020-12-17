<?php
// sanitize inputs
$_POST  = filter_input_array(INPUT_POST, FILTER_SANITIZE_STRING);

// initialize functions/properties
// first, determine array of valid sequence names (k-cat and non-kcat)
$kcat_seq_names = $module->getProjectSetting('kcat_sequence');
if (!empty($kcat_seq_names)) {
	$sequenceNames = array_merge(
		$module->getProjectSetting('sequence'),
		$kcat_seq_names
	);
} else {
	$sequenceNames = $module->getProjectSetting('sequence');
}

function isValidSequenceName($name) {
	global $sequenceNames;
	foreach ($sequenceNames as $i => $seq_name) {
		if ($name == $seq_name and !empty($name))
			return true;
	}
	return false;
}

// process request
$json = new \stdClass();
$user_sequence = $_POST['sequence'];

if (empty($_POST['time_of_day'])) {
	$time_of_day = "00:00";
} else {
	$time_of_day = preg_replace("/[^\d:]/", "", $_POST['time_of_day']);
}

if ($_POST['schedulingMethod'] == 'single') {
	if (!isValidSequenceName($user_sequence)) {
		$json->error = "'$user_sequence' is not the name of a valid sequence.";
		exit(json_encode($json));
	}
	
	$offset = intval($_POST['offset']);
	if ($offset != $_POST['offset'] or $offset < 0) {
		$json->error = $_POST['offset'] . " is not a valid offset -- must be an integer greater than or equal to 0.";
		exit(json_encode($json));
	}
	
	list($ok, $msg) = $module->scheduleSequence($user_sequence, $offset, $time_of_day);
	
	if (!$ok) {
		$json->error = $msg;
	} else {
		$json->sequences = $module->getScheduledSequences();
	}
} elseif ($_POST['schedulingMethod'] == 'interval') {
	if (!isValidSequenceName($user_sequence)) {
		$json->error = "'$user_sequence' is not the name of a valid sequence.";
		exit(json_encode($json));
	}
	$frequency = (int) $_POST['frequency'];
	$duration = (int) $_POST['duration'];
	$delay = (int) $_POST['delay'];
	
	// require frequency and duration
	if (empty($frequency) or empty($duration)) {
		$json->error = "The CAT-MH module can't schedule sequences by interval without valid frequency and duration parameters.";
		exit(json_encode($json));
	}
	if ($duration > 9998) {
		$json->error = "The maximum allowed duration value is 9998.";
		exit(json_encode($json));
	}
	
	for ($day_offset = $delay; $day_offset < $duration + $delay; $day_offset += $frequency) {
		
		list($ok, $id_or_msg) = $module->scheduleSequence($user_sequence, $day_offset, $time_of_day);
		if (!$ok) {
			$json->error = $id_or_msg;
			exit(json_encode($json));
		}
	}
	
	$json->sequences = $module->getScheduledSequences();
} elseif ($_POST['schedulingMethod'] == 'delete') {
	
	$sequences = $_POST['sequencesToDelete'];
	
	foreach($sequences as $seq_index => $sequence) {
		if (!isValidSequenceName($sequence['name'])) {
			$json->error = "'$user_sequence' is not the name of a valid sequence.";
			exit(json_encode($json));
		}
		$name = $sequence['name'];
		$offset = intval($sequence['offset']);
		$time_of_day = preg_replace("/[^\d:]/", "", $sequence['time_of_day']);
		$return_value = $module->unscheduleSequence($name, $offset, $time_of_day);
	}
	
	$json->sequences = $module->getScheduledSequences();
} elseif ($_POST['schedulingMethod'] == 'setReminderSettings') {
	
	$settings = [];
	if ($_POST['enabled'] == 'on') {
		$settings['enabled'] = true;
		$settings['frequency'] = (int) $_POST['frequency'];
		$settings['duration'] = (int) $_POST['duration'];
		$settings['delay'] = (int) $_POST['delay'];
		
		if (empty($settings['frequency']))
			$settings['frequency'] = 1;
		if (empty($settings['duration']))
			$settings['duration'] = 1;
		if (empty($settings['delay']))
			$settings['delay'] = 1;
	} else {
		$settings['enabled'] = false;
		$settings['frequency'] = null;
		$settings['duration'] = null;
		$settings['delay'] = null;
	}
	
	$module->setReminderSettings($settings);
	$json->reminderSettings = $module->getReminderSettings();
	
} else {
	$json->error = 'No scheduling method specified (must be single or interval).';
}

// send response
exit(json_encode($json));
