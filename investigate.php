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

$records_of_interest = ['1', '58', '59', '60'];
$records_of_interest = ['1'];
$in_30_days = strtotime("+30 days", time());
$in_30_days_date = date('Y-m-d H:i', $in_30_days);
echo "30 days from now: $in_30_days_date\n";
echo "in_30_days (timestamp): $in_30_days\n";
foreach ($records_of_interest as $record) {
	$invites = $module->getInvitationsDue($record, $in_30_days);
	echo "Invitations due for record $record: \n" . print_r($invites, true) . "\n";
};

echo "Printing all project settings: " . print_r($module->getProjectSettings(), true) . "\n\n";

echo "</pre>";
require_once APP_PATH_DOCROOT . 'ProjectGeneral/footer.php';
?>