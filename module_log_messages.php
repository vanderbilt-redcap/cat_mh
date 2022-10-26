<?php

require_once APP_PATH_DOCROOT . 'ProjectGeneral/header.php';

/** Get list of current parameters available in the log table*/
$project_id = (int)$_GET['pid'];
$result = $module->query("SELECT 
							DISTINCT name
							FROM redcap_external_modules_log_parameters
							INNER JOIN redcap_external_modules_log USING(log_id)
							WHERE project_id = ?",$project_id);

$allowed_params = [];							
while ($row = db_fetch_assoc($result)) {
	$allowed_params[] = $row['name'];
}


// $module->llog("get: " . print_r($_GET,true));
$extra_params = [];
foreach($_GET as $key => $value) {
	if (strlen($key) <= 3 and substr($key, 0, 1) == 'p' and $key != 'pid' and in_array($key,$allowed_params)) {
		$extra_params[] = db_escape(urldecode($value));
	}
}
$extra_params = empty($extra_params) ? '' : ', ' . implode(', ', $extra_params);


echo "<pre>";

$pid = $module->getProjectId();

$result = $module->queryLogs("SELECT message, timestamp, sequence, subjectid, scheduled_datetime, record, offset, time_of_day" . $extra_params);
while ($row = db_fetch_assoc($result)) {
	print_r($row);
	echo "\n";
}

echo "</pre>";
require_once APP_PATH_DOCROOT . 'ProjectGeneral/footer.php';
?>