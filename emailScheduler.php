<?php
// determine number of days that have elapsed
$daysElapsed = $module->getProjectSetting('days-elapsed');
if (!isset($daysElapsed)) {
	$daysElapsed = 0;
	$module->setProjectSetting('days-elapsed', 0);
}
$daysElapsed = intval($daysElapsed);

// determine which sequences to send emails for
$urls = [];
$settings = $module->getProjectSettings();
// echo("<pre>");
// print_r($settings);
// echo("</pre>");
foreach ($settings['sequence']['value'] as $i => $sequence) {
	$period_every = $settings['periodicity-every']['value'][$i];
	$period_end = $settings['periodicity-end']['value'][$i];
	if (isset($period_every) and isset($period_end)) {
		$period_every = intval($period_every);
		$period_end = intval($period_end);
		if (($daysElapsed % $period_every) == 0 and $daysElapsed <= $period_end and $daysElapsed != 0) {
			$urls[] = $module->getUrl("interview.php") . "&NOAUTH&sequence=$sequence";
		}
	}
}

$emailSender = $settings['email-sender']['value'][0];
$emailSubject = $settings['email-subject']['value'][0];
$emailBody = $settings['email-body']['value'][0];

if (empty($urls) or !isset($emailSender) or !isset($emailSubject) or !isset($emailBody)) {
	// increment daysElapsed
	$module->setProjectSetting('days-elapsed', $daysElapsed + 1);
	exit();
}

// prepare email body by replacing [interview-links] and [interview-urls]
$emailBody = str_replace("[interview-urls]", implode($urls, "\r\n"), $emailBody);
foreach($urls as $i => $url) {
	$urls[$i] = "<a href=\"$url\">CAT-MH Interview Link</a>";
}
$emailBody = str_replace("[interview-links]", implode($urls, "\r\n"), $emailBody);

// we have links to send so for each participant with a listed email, invite to take interview(s)
$data = \REDCap::getData($module->getProjectId(), 'array');
foreach($data as $rid => $record) {
	$eid = array_keys($record)[0];
	$addressTo = $record[$eid]['participant_email'];
	if (isset($addressTo)) {
		foreach($urls as $url) {
			$success = \REDCap::email($addressTo, $emailSender, $emailSubject, $emailBody);
			if ($success === false) {
				\REDCap::logEvent("Failed Sending Interview Email", "$addressTo, $emailSender, $emailSubject, $emailBody", NULL, $rid, $eid, $module->getProjectId());
			} else {
				\REDCap::logEvent("Sent Interview Email", "$addressTo, $emailSender, $emailSubject, $emailBody", NULL, $rid, $eid, $module->getProjectId());
			}
		}
	}
}

// increment daysElapsed
$module->setProjectSetting('days-elapsed', $daysElapsed + 1);
echo($daysElapsed + 1);