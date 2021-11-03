<?php
$months = intval($_POST['months']);
if ($months < 0)
	$months = 0;

// store interview_ids for interviews that we're going to delete
// $interview_ids = [];
$deleted = 0;
$failures = 0;
$result = $module->queryLogs("SELECT interviewID, scheduled_datetime WHERE message = ?", ['catmh_interview']);
while ($row = db_fetch_assoc($result)) {
	if (strtotime($row['scheduled_datetime'] . " + $months months") < time()) {
		$success = $module->removeLogs("message = ? AND interviewID = ?", [
			"catmh_interview",
			$row['interviewID']
		]);
		if ($success) {
			$deleted++;
		} else {
			$failures++;
		}
	}
}

\REDCap::logEvent("CAT-MH External Module", "Deleted interview data for $deleted interviews ($failures failures). Months parameter: $months.", NULL, NULL, NULL, $module->getProjectId());
$response = new \stdClass();
$response->success = true;
$response->failures = $failures;
$response->deleted = $deleted;
exit(json_encode($response));