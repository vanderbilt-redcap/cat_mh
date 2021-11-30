<?php
require_once APP_PATH_DOCROOT . 'ProjectGeneral/header.php';

$rid_field = $module->getRecordIdField();
$enroll_field = $module->getProjectSetting("enrollment_field");

$records = [
	new stdClass(),
	new stdClass(),
	new stdClass()
];
$records[0]->$rid_field = '1';
$records[0]->$enroll_field = "01-11-2021";
$records[1]->$rid_field = '2';
$records[1]->$enroll_field = "02-11-2021";
$records[2]->$rid_field = '3';
$records[2]->$enroll_field = "03-11-2021";

$current_time = time() + 30 * 24 * 60 * 60;
$invites_due = [
	$module->getInvitationsDue($records[0], $current_time),
	$module->getInvitationsDue($records[1], $current_time),
	$module->getInvitationsDue($records[2], $current_time)
];

// make urls and links to pipe into email body
$urls = [];
$links = [];
$base_url = $module->getUrl("interview.php") . "&NOAUTH&sid=$sid";
foreach ($invites_due as $record_invites) {
	foreach ($record_invites as $name_and_time => $invitation) {
		$seq_name = $invitation->sequence;
		echo "<pre>
			" . print_r($invitation, true) . "
		</pre>";
		$seq_date = date("Y-m-d H:i", $invitation->sched_dt);
		$month_day_only = date("m/d", strtotime($seq_date));
		
		// handle K-CAT interviews differently, generate two links, not just one
		if ($invitation->kcat) {
			$prim_seq_url = $base_url . "&sequence=" . urlencode($seq_name) . "&sched_dt=" . urlencode($seq_date) . "&kcat=primary";
			$prim_seq_link = "<a href=\"$prim_seq_url\">CAT-MH Interview - $seq_name ($month_day_only) - Child</a>";
			$sec_seq_url = $base_url . "&sequence=" . urlencode($seq_name) . "&sched_dt=" . urlencode($seq_date) . "&kcat=secondary";
			$sec_seq_link = "<a href=\"$sec_seq_url\">CAT-MH Interview - $seq_name ($month_day_only) - Parent</a>";
			$urls[] = $prim_seq_url;
			$urls[] = $sec_seq_url;
			$links[] = $prim_seq_link;
			$links[] = $sec_seq_link;
		} else {
			$seq_url = $base_url . "&sequence=" . urlencode($seq_name) . "&sched_dt=" . urlencode($seq_date);
			$seq_link = "<a href=\"$seq_url\">CAT-MH Interview - $seq_name ($month_day_only)</a>";
			$urls[] = $seq_url;
			$links[] = $seq_link;
		}
	}
}

echo "<pre>";
echo print_r($invites_due, true);
echo print_r($urls, true);
echo print_r($links, true);
echo "</pre>";

require_once APP_PATH_DOCROOT . 'ProjectGeneral/footer.php';