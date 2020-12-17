<?php

require_once APP_PATH_DOCROOT . 'ProjectGeneral/header.php';

echo "<pre>";

$pid = $module->getProjectId();

$enroll_dates = [
	"",
    "2020-10-8",
    "",
    "2020-10-28",
    "2020-10-28",
    "2020-10-28",
    "2020-10-28",
	"",
	"2020-10-28",
	"2020-10-28",
	"2020-10-28",
	"2020-10-28",
	"2020-10-28",
	"2020-10-28",
	"",
	"2020-10-28",
	"2020-10-28",
	"2020-10-28",
	"2020-10-28",
	"2020-10-28",
	"2020-10-28",
	"2020-10-28",
	"",
	"2020-10-28",
	"2020-10-28",
	"2020-10-28",
	"2020-10-28",
	"2020-10-28",
	"2020-10-28",
	"2020-10-28",
	"2020-10-28",
	"2020-10-28",
	"2020-10-28",
	"2020-10-28",
	"2020-10-29",
	"2020-10-29",
	"2020-10-29",
	"2020-10-29",
	"2020-10-29",
	"2020-10-29",
	"2020-10-29",
	"2020-10-29",
	"2020-10-29",
	"",
	"2020-10-29",
	"2020-10-29",
	"2020-10-29",
	"",
	"2020-10-30",
	"",
	"2020-11-01",
	"2020-11-02",
	"2020-11-03",
	"2020-11-03",
	"2020-11-03",
	"2020-11-04",
	"2020-11-06",
	"2020-11-06",
	"2020-11-09",
	"2020-11-11",
	"2020-11-16",
	"2020-12-01"
];
$module->removeLogs("message = ?", 'invitationSent');

for ($record = 1; $record < 61; $record++) {
	$invitation = new \stdClass();
	$invitation->record = $record;
	
	$enroll_date = $enroll_dates[$record];
	
	$invitation->sequence = 'Test1';
	$invitation->offset = '0';
	$invitation->time_of_day = '16:15';
	$invitation->sched_dt = $enroll_date . " 16:15";
	$invitation->kcat = false;
	$module->log('invitationSent', (array) $invitation);
}

for ($record = 1; $record < 61; $record++) {
	$invitation = new \stdClass();
	$invitation->record = $record;
	
	$enroll_date = $enroll_dates[$record];
	
	$invitation->sequence = 'Test1';
	$invitation->offset = '30';
	$invitation->time_of_day = '16:15';
	$invitation->sched_dt = $enroll_date . " 16:15";
	$invitation->kcat = false;
	$module->log('invitationSent', (array) $invitation);
}

echo "invitationSent logged " . $module->countLogs("message = ?", "invitationSent") . " times";

echo "</pre>";
require_once APP_PATH_DOCROOT . 'ProjectGeneral/footer.php';
?>