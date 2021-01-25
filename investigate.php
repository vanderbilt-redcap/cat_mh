<?php
require_once APP_PATH_DOCROOT . 'ProjectGeneral/header.php';
$sequences = $module->getScheduledSequences();
$reminder_settings = (object) $module->getReminderSettings();
echo "<pre>";
echo "Sanity checking sequences from getScheduledSequences.\n";

foreach($sequences as $seq) {
	$name = $seq[1];
	$offset = $seq[2];
	$tod = $seq[3];
	echo "TEST: $name\tOFFSET: $offset\tTIME_OF_DAY: $tod\n";
}

echo "\n\n";
echo "Reminder settings: " . print_r($reminder_settings, true) . "\n\n";

$record_ids = ['1', '58', '59', '60'];
$enrollment_field_name = $module->getProjectSetting('enrollment_field');
$catmh_email_field_name = $module->getProjectSetting('participant_email_field');
$param_fields = [
	$module->getRecordIdField(),
	"$enrollment_field_name",
	'subjectid',
	$catmh_email_field_name
];
// add filter_fields to getData request
if (!empty($filter_fields = $module->getProjectSetting('filter-fields')))
	$param_fields = array_merge($param_fields, $filter_fields);
$params = [
	'project_id' => $module->getProjectId(),
	'return_format' => 'json',
	'fields' => $param_fields,
	'records' => $record_ids
];
$data = json_decode(\REDCap::getData($params));

$in_30_days = strtotime("+30 days", time());
$in_30_days_date = date('Y-m-d H:i', $in_30_days);
echo "30 days from now: $in_30_days_date\n";
echo "in_30_days (timestamp): $in_30_days\n";
foreach ($data as $record) {
	$invites = $module->getInvitationsDue($record, $in_30_days);
	echo "Invitations due for record $record: \n" . print_r($invites, true) . "\n";
};

echo "Printing all project settings: " . print_r($module->getProjectSettings(), true) . "\n\n";

echo "</pre>";
require_once APP_PATH_DOCROOT . 'ProjectGeneral/footer.php';
?>