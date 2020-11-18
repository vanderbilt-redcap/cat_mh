<?php

$pid = $module->getProjectId();
$project = new \Project($pid);
$eid = $project->firstEventId;
$record_id_field = \REDCap::getRecordIdField();

// determine record home page link address
if (strpos(APP_PATH_WEBROOT_FULL, "/redcap/") !== false) {	// dev
	$link_base = str_replace("/redcap/", APP_PATH_WEBROOT, APP_PATH_WEBROOT_FULL);
} else {	// test/prod
	$link_base = substr(APP_PATH_WEBROOT_FULL, 0, -1) . APP_PATH_WEBROOT;
}

$record_link = $link_base . "DataEntry/record_home.php?pid=$pid&arm=1&id=";

$params = [
	"project_id" => $pid,
	"return_format" => 'array',
	"fields" => [
		"subjectid",
		"cat_mh_data",
		$record_id_field
	]
];
$data = \REDCap::getData($params);

// get scheduled sequence information
$sequences = $module->getScheduledSequences();
foreach ($sequences as $i => $seq) {
	$sequences[$i] = [
		"name" => $seq[2],
		"scheduled_datetime" => $seq[1]
	];
}

$json = new \stdClass();
$table_data = [];
foreach($data as $rid => $record) {
	
	// prepare interviews array
	$catmh = json_decode($record[$eid]['cat_mh_data']);
	$interviews = $catmh->interviews;
	$sid = $record[$eid]['subjectid'];
	$reminderSettings = $module->getReminderSettings();
	$reminder_delay = $reminderSettings['delay'];
	if (empty($reminder_delay))
		$reminder_delay = 0;
	
	$missed_surveys = 0;
	foreach ($sequences as $seq) {
		if ($module->getSequenceStatus($rid, $seq['name'], $seq['scheduled_datetime']) != 4) {
			$missed_surveys++;
		}
	}
	
	// append icon/links for each sequence
	foreach ($sequences as $i => $seq) {
		$interview = $module->getInterview($sid, $seq['name'], $seq['scheduled_datetime']);
		$module->llog("RID $rid seq $i interview type: " . gettype($interview));
		
		// preparation/calculation
		$date_to_complete = date("Y-m-d H:i", strtotime("+$reminder_delay days", strtotime($seq['scheduled_datetime'])));
		//
		
		$row = [];
		
		// Record ID column
		$row[] = "<a href='" . $record_link . $rid . "'>$rid</a>";
		
		// Completed column
		$completed_icon = null;
		if (empty($interview) or ($interview->status == false)) {	// unstarted
			// not started or completed, append gray circle img
			$completed_icon = "<img src='" . APP_PATH_IMAGES . "circle_gray.png' class='fstatus' style='width:16px;margin-right:6px;' alt=''>";
		} elseif ($interview->status != 4) {
			// started but not completed, append yellow circle img
			$completed_icon = "<img src='" . APP_PATH_IMAGES . "circle_yellow.png' class='fstatus' style='width:16px;margin-right:6px;' alt=''>";
		} elseif ($interview->status == 4) {
			// append green circle (which itself, is a link to filtered results report)
			$img = "<img src='" . APP_PATH_IMAGES . "circle_green_tick.png' class='fstatus' style='width:16px;margin-right:6px;' alt=''>";
			$link = $module->getUrl('resultsReport.php') . "&record=$rid&seq=" . urlencode($seq['name']) . "&sched_dt=" . urlencode($seq['scheduled_datetime']);
			$completed_icon = "<a href='$link'>$img</a>";
		}
		$row[] = $completed_icon;
		
		// Within Window column
		$completed_within_window = "";
		
		if (time() > strtotime($date_to_complete))
			$completed_within_window = "N";
		
		if (!empty($interview) and ($interview->status == 4)) {
			if ($interview->timestamp < strtotime($date_to_complete)) {
				$completed_within_window = "Y";
			} else {
				$completed_within_window = "N";
			}
		}
		$row[] = $completed_within_window;
		
		// Date Scheduled column
		$row[] = date("Y-m-d H:i", strtotime($seq['scheduled_datetime']));
		
		// Date to Complete column
		$row[] = $date_to_complete;
		
		// Date Taken column
		if (!empty($interview) and !empty($interview->timestamp)) {
			$date_taken = date("Y-m-d H:i", $interview->timestamp);
		} else {
			$date_taken = "";
		}
		$row[] = $date_taken;
		
		// Elapsed Time column
		$elapsed_time = "";
		if ($completed_within_window = "N") {
			$dt1 = date_create(date("Y-m-d", time()));
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
		
		// Reviewed column
		$seq_name = $seq['name'];
		$seq_date = $seq['scheduled_datetime'];
		$row[] = "<button class='review' data-rid='$rid' data-seq='$seq_name' data-date='$seq_date'>Mark Reviewed</button>";
		$table_data[] = $row;
	}
}
// $module->llog("table data: " . print_r($table_data, true));

$json->data = $table_data;
exit(json_encode($json));
