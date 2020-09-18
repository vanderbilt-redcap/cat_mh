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
	"fields" => ["cat_mh_data", $record_id_field]
];
$data = \REDCap::getData($params);
// $module->llog("got data: " . print_r($data, true));

// get scheduled sequence information
$sequences = $module->getScheduledSequences();
foreach ($sequences as $i => $seq) {
	$sequences[$i] = [
		"name" => $seq[2],
		"scheduled_datetime" => $seq[1]
	];
}

// $module->llog("got sequences: " . print_r($sequences, true));

$json = new \stdClass();
$table_data = [];
foreach($data as $rid => $record) {
	$row = [];
	
	// append record home link as first value
	$row[] = "<a href='" . $record_link . $rid . "'>$rid</a>";
	
	// prepare interviews array
	$catmh = json_decode($record[$eid]['cat_mh_data']);
	$interviews = $catmh->interviews;
	// $module->llog("got interviews: " . print_r($interviews, true));
	
	// append icon/links for each sequence
	foreach ($sequences as $i => $seq) {
		$interview_index = null;
		$interview_ts = null;
		foreach ($interviews as $j => $interview) {
			if ($interview->sequence == $seq['name'] and $interview->scheduled_datetime == $seq['scheduled_datetime'] and $interview->status == 4) {
				// ensure we refer to most recently completed interview
				if (empty($interview_ts)) {
					$interview_index = $j;
					$interview_ts = $interview->timestamp;
				} elseif ($interview_ts < $interview->timestamp) {
					$interview_index = $j;
					$interview_ts = $interview->timestamp;
				}
			}
		}
		if (empty($interview_index)) {
			// none completed, append gray circle img
			$row[] = "<img src='" . APP_PATH_IMAGES . "circle_gray.png' class='fstatus' style='width:16px;margin-right:6px;' alt=''>";
		} else {
			// append green circle (which itself, is a link to filtered results report)
			$interview = $interviews[$interview_index];
			$img = "<img src='" . APP_PATH_IMAGES . "circle_green_tick.png' class='fstatus' style='width:16px;margin-right:6px;' alt=''>";
			$link = $module->getUrl('resultsReport.php') . "&record=$rid&seq=" . urlencode($seq['name']) . "&sched_dt=" . urlencode($seq['scheduled_datetime']);
			$row[] = "<a href='$link'>$img</a>";
		}
	}
	
	$table_data[] = $row;
}

$json->data = $table_data;
exit(json_encode($json));
