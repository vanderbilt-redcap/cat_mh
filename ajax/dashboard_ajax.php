<?php

$pid = $module->getProjectId();
$project = new \Project($pid);
$eid = $project->firstEventId;
$record_id_field = \REDCap::getRecordIdField();
$time_now = time();

// determine record home page link address
if (strpos(APP_PATH_WEBROOT_FULL, "/redcap/") !== false) {	// dev
	$link_base = str_replace("/redcap/", APP_PATH_WEBROOT, APP_PATH_WEBROOT_FULL);
} else {	// test/prod
	$link_base = substr(APP_PATH_WEBROOT_FULL, 0, -1) . APP_PATH_WEBROOT;
}

$record_link = $link_base . "DataEntry/record_home.php?pid=$pid&arm=1&id=";
if (empty($enrollment_field_name = $module->getProjectSetting('enrollment_field'))) {
	exit("{}");
}

$params = [
	"project_id" => $pid,
	"return_format" => 'array',
	"fields" => [
		$record_id_field,
		$enrollment_field_name
	]
];
$data = \REDCap::getData($params);

$json = new \stdClass();
$table_data = [];
foreach($data as $rid => $record) {
	// determine sheduled date for this participant
	if (!$enrollment_timestamp = strtotime($record[$eid][$enrollment_field_name]))
		continue;
	
	$sid = $module->getSubjectID($rid);
	$enroll_date = date("Y-m-d", $enrollment_timestamp);
	$missed_surveys = 0;
	$sequences = $module->getScheduledSequences();
	
	// get scheduled sequence information
	foreach ($sequences as $i => $seq) {
		$seq_name = $seq[1];
		$kcat_seq_index = $module->getKCATSequenceIndex($seq_name);
		$seq_offset = $seq[2];
		$seq_time_of_day = $seq[3];
		$enroll_and_time = "$enroll_date " . $seq_time_of_day;
		$sched_dt = strtotime("+$seq_offset days", strtotime($enroll_and_time));
		
		if ($kcat_seq_index === false) {
			$days_to_complete = $module->getProjectSetting('expected_complete')[$module->getSequenceIndex($seq_name)];
		} else {
			$days_to_complete = $module->getProjectSetting('kcat_expected_complete')[$kcat_seq_index];
		}
		
		if (empty($days_to_complete))
			$days_to_complete = 0;
		
		$sequences[$i] = [
			"name" => $seq_name,
			"scheduled_datetime" => date("Y-m-d H:i", $sched_dt),
			"days_to_complete" => $days_to_complete
		];
		$sequences[$i]['date_to_complete'] = date("Y-m-d H:i", strtotime("+$days_to_complete days", strtotime($sequences[$i]['scheduled_datetime'])));
		
		if ($kcat_seq_index !== false) {
			$sequences[$i]['kcat'] = 'primary';
			$sequences[$i]['status'] = $module->getSequenceStatus($rid, $sequences[$i]['name'], $sequences[$i]['scheduled_datetime'], 'primary');
			
			// add secondary interview too, if sequence is kcat
			$sec_seq = $sequences[$i];
			$sec_seq['kcat'] = 'secondary';
			$sec_seq['status'] = $module->getSequenceStatus($rid, $sequences[$i]['name'], $sequences[$i]['scheduled_datetime'], 'secondary');
			if ($sec_seq['status'] != 4 and strtotime($sequences[$i]['date_to_complete']) <= $time_now)
				$missed_surveys++;
			
			$sequences[] = $sec_seq;
		} else {
			$sequences[$i]['status'] = $module->getSequenceStatus($rid, $sequences[$i]['name'], $sequences[$i]['scheduled_datetime']);
		}
		
		// // check to see if acknowledged as delinquent
		// $interview_acknowledged_delinquent = $module->countLogs("message = ? AND sequence = ? AND scheduled_datetime = ? AND subjectID = ?", [
			// "acknowledged_delinquent",
			// $seq_name,
			// $sequences[$i]['scheduled_datetime'],
			// $sid
		// ]);
		
		if (
			$sequences[$i]['status'] != 4
			&&
			strtotime($sequences[$i]['date_to_complete']) <= $time_now
			// &&
			// !$interview_acknowledged_delinquent
		) {
			// $module->llog("missed survey: sid=$sid, seq: " . print_r($seq, true));
			$missed_surveys++;
		}
	}
	
	// $module->llog('sequences: ' . print_r($sequences, true));
	
	// append icon/links for each sequence
	foreach ($sequences as $i => $seq) {
		// preparation/calculation
		$seq_name = $seq['name'];
		$seq_date = $seq['scheduled_datetime'];
		
		// kcat variables
		$kcat_seq_index = $module->getKCATSequenceIndex($seq_name);
		
		// skip if not scheduled to take yet (invite not sent)
		if ($time_now < strtotime($seq_date))
			continue;
		
		// get actual interview if it exists
		if (!empty($seq['kcat'])) {
			$interview = $module->getSequence($seq_name, $seq_date, $sid, $seq['kcat']);
		} else {
			$interview = $module->getSequence($seq_name, $seq_date, $sid);
		}
		
		$date_to_complete = date("Y-m-d H:i", strtotime("+{$seq['days_to_complete']} days", strtotime($seq_date)));
		$completed_within_window = "";
		if ($time_now >= strtotime($date_to_complete))
			$completed_within_window = "N";
		
		
		if (empty($seq['kcat'])) {
			$interview_acknowledged_delinquent = $module->countLogs("message = ? AND sequence = ? AND scheduled_datetime = ? AND subjectID = ?", [
				"acknowledged_delinquent",
				$seq_name,
				$seq_date,
				$sid
			]);
		} else {
			$interview_acknowledged_delinquent = $module->countLogs("message = ? AND sequence = ? AND scheduled_datetime = ? AND subjectID = ? AND kcat = ?", [
				"acknowledged_delinquent",
				$seq_name,
				$seq_date,
				$sid,
				$seq['kcat']
			]);
		}
		
		$row = [];
		
		// Record ID column
		$row[] = "<a href='" . $record_link . $rid . "'>$rid</a>";
		
		// Sequence column
		if ($kcat_seq_index === false) {
			$row[] = $seq_name;
		} else {
			// $module->llog('$seq[kcat] = ' . $seq['kcat']);
			$row[] = $seq_name . ' ' . ($seq['kcat'] == 'primary' ? '(Child)' : '(Parent)');
		}
		
		// Completed column	# priority: green (completed) > blue (acknowledged) > yellow (started) > gray/red (incomplete/delinquent)
		$completed_icon = null;
		if ($interview->status == 4) {			// append green circle (which itself, is a link to filtered results report)
			$img = "<img src='{$module->interviewStatusIconURLs['green']}' class='fstatus' data-color='green' style='width:16px;margin-right:6px;' alt=''>";
			$link = $module->getUrl('resultsReport.php') . "&record=$rid&seq=" . urlencode($seq_name) . "&sched_dt=" . urlencode($seq_date);
			$completed_icon = "<a href='$link'>$img</a>";
		} elseif ($interview_acknowledged_delinquent) {		// acknowledged delinquent, blue circle icon
			$completed_icon = "<img src='{$module->interviewStatusIconURLs['blue']}' class='fstatus' data-color='blue' style='width:16px;margin-right:6px;' alt=''>";
		} elseif ($interview->status > 1) {			// started but not completed, append yellow circle img
			$completed_icon = "<img src='{$module->interviewStatusIconURLs['yellow']}' class='fstatus' data-color='yellow' style='width:16px;margin-right:6px;' alt=''>";
		} else {
			if ($completed_within_window == 'N') {		// not started or completed, AND overdue/delinquent: red circle icon
				$completed_icon = "<img src='{$module->interviewStatusIconURLs['red']}' class='fstatus' data-color='red' style='width:16px;margin-right:6px;' alt=''>";
			} else {									// not started or completed, append gray circle img
				$completed_icon = "<img src='{$module->interviewStatusIconURLs['gray']}' class='fstatus' data-color='gray' style='width:16px;margin-right:6px;' alt=''>";
			}
		}
		$row[] = $completed_icon;
		
		// Within Window column
		
		if (!empty($interview) and ($interview->status == 4)) {
			if ($interview->timestamp <= strtotime($date_to_complete)) {
				$completed_within_window = "Y";
			} else {
				$completed_within_window = "N";
			}
		}
		$row[] = $completed_within_window;
		
		// Date Scheduled column
		$row[] = date("Y-m-d H:i", strtotime($seq_date));
		
		// Date to Complete column
		$row[] = $date_to_complete;
		
		// Date Taken column
		if (!empty($interview) and !empty($interview->timestamp)) {
			$date_taken = date("Y-m-d H:i", $interview->timestamp);
		} else {
			$date_taken = "";
		}
		if (!empty($seq['kcat']) and $interview->status == 1)
			$date_taken = '';
		$row[] = $date_taken;
		
		// Elapsed Time column
		$elapsed_time = "";
		if ($completed_within_window == "N") {
			$dt1 = date_create(date("Y-m-d", $time_now));
			$dt2 = date_create($date_to_complete);
			$interval = date_diff($dt1, $dt2);
			$elapsed_time = $interval->format("%d days");
		} elseif ($interview->status == 4) {
			$dt1 = date_create(date("Y-m-d", $interview->timestamp));
			$dt2 = date_create($seq['scheduled_datetime']);
			$interval = date_diff($dt1, $dt2);
			$elapsed_time = $interval->format("%d days");
		}
		$row[] = $elapsed_time;
		
		// Missed Surveys column
		$row[] = $missed_surveys;
		
		// Acknowledged/Mark Reviewed column
		if ($interview_acknowledged_delinquent) {
			$row[] = "<input type='checkbox' class='ack_cbox' data-rid='$rid' data-seq='$seq_name' data-date='$seq_date' data-checked='true' data-kcat='{$seq['kcat']}'>";
		} elseif ($completed_within_window == 'N' && $interview->status != 4) {
			$row[] = "<input type='checkbox' class='ack_cbox' data-rid='$rid' data-seq='$seq_name' data-date='$seq_date' data-checked='false' data-kcat='{$seq['kcat']}'>";
		} else {
			$row[] = '';
		}
		
		$table_data[] = $row;
	}
}

$json->data = $table_data;

exit(json_encode($json));
