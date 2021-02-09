<?php

require_once APP_PATH_DOCROOT . 'ProjectGeneral/header.php';

$module->llog("get: " . print_r($_GET,true));
$extra_params = [];
foreach($_GET as $key => $value) {
	if (strlen($key) <= 3 and substr($key, 0, 1) == 'p' and $key != 'pid') {
		$extra_params[] = db_escape(urldecode($value));
	}
}
$extra_params = empty($extra_params) ? '' : ', ' . implode(', ', $extra_params);

echo "<pre>";

$pid = $module->getProjectId();

$result = $module->queryLogs("SELECT message, timestamp, sequence, subjectID, scheduled_datetime, record, offset, time_of_day" . $extra_params);
while ($row = db_fetch_assoc($result)) {
	print_r($row);
	echo "\n";
}

echo "</pre>";
require_once APP_PATH_DOCROOT . 'ProjectGeneral/footer.php';
?>