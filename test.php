<?php

require_once APP_PATH_DOCROOT . 'ProjectGeneral/header.php';

echo "<pre>";

$module->sendScheduledSequenceEmails();
$module->sendReminderEmails();

// $module->clearQueuedReminderEmails();
// $module->queueAllReminderEmails();

// reset cat_mh_data for all records
	// $params = [
		// "project_id" => $module->getProjectId(),
		// "return_format" => "array",
		// "fields" => ["cat_mh_data", "record_id"]
	// ];
	// $data = \REDCap::getData($params);
	// foreach ($data as $rid => $record) {
		// $eid = array_keys($data[$rid])[0];
		// $data[$rid][$eid]['cat_mh_data'] = '{"interviews":[]}';
	// }
	// $module->llog("data: " . print_r($data, true));
	// $result = \REDCap::saveData($module->getProjectId(), 'array', $data);
	// print_r($result);
	
// // lgo cat_mh data
	// $params = [
		// "project_id" => $module->getProjectId(),
		// "return_format" => "array",
		// "fields" => ["cat_mh_data", "record_id"]
	// ];
	// $data = \REDCap::getData($params);
	// foreach ($data as $rid => $record) {
		// $eid = array_keys($data[$rid])[0];
		// $catmh = json_decode($data[$rid][$eid]['cat_mh_data'], true);
		// // $module->llog("catmh: " . print_r($catmh, true));
		// print_r($catmh);
	// }
	
// echo $module->sequenceCompleted("6", "seq2", "2020-09-15 00:00");

// print reminder email queue logs
	$arr = [];
	$result = $module->queryLogs("SELECT message, name, scheduled_datetime WHERE message='scheduleReminder'");
	while ($row = db_fetch_assoc($result))
		$arr[] = $row;
	print_r($arr);

echo "</pre>";

require_once APP_PATH_DOCROOT . 'ProjectGeneral/footer.php';
?>