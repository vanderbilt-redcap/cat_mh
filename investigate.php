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
	'fields' => $param_fields
	// 'records' => $record_ids
];
$data = json_decode(\REDCap::getData($params));

$in_360_days = strtotime("+360 days", time());
$in_360_days_date = date('Y-m-d H:i', $in_360_days);
echo "360 days from now: $in_360_days_date\n";
echo "in_360_days (timestamp): $in_360_days\n";

echo "</pre>";

echo "<br>";
echo "<table id='invites'>
	<thead>
		<tr>
			<th>Record ID</th>
			<th>Enrollment Date</th>
			<th>Invite Interview Name</th>
			<th>Invite DateTime</th>
			<th>Invite Offset</th>
			<th>Invite Time of Day</th>
		</tr>
	</thead>
	<tbody>";
	
foreach ($data as $record) {
	$invites = $module->getInvitationsDue($record, $in_360_days);
	$rid_field_name = $module->getRecordIdField();
	$rid = $record->$rid_field_name;
	$enroll_date = $record->$enrollment_field_name;
	
	foreach ($invites as $invite) {
		if (empty($enroll_date))
			continue;
		echo "<tr>
			<td>$rid</td>
			<td>$enroll_date</td>
			<td>{$invite->sequence}</td>
			<td>" . date("Y-m-d H:i", $invite->sched_dt) . "</td>
			<td>{$invite->offset}</td>
			<td>{$invite->time_of_day}</td>
		</tr>";
	}
};
echo "</tbody>
	</table><br><br>";

echo "Printing all project settings: <pre>" . print_r($module->getProjectSettings(), true) . "</pre>\n\n";

require_once APP_PATH_DOCROOT . 'ProjectGeneral/footer.php';
?>
<script type='text/javascript' src='//cdn.datatables.net/1.10.23/js/jquery.dataTables.min.js'></script>
<script type='text/javascript'>
	$(document).ready(function() {
		$('head').append('<link rel="stylesheet" type="text/css" href="//cdn.datatables.net/1.10.23/css/jquery.dataTables.min.css">');
		var invites_dt = $('#invites').DataTable();
	});
</script>