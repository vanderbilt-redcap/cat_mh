<?php
require_once APP_PATH_DOCROOT . 'ProjectGeneral/header.php';
?>
<p>Raw JSON:</p>
<textarea style='min-width: 600px; min-height: 300px; width:85%;'>
<?php
$interviews = [];
$result = $module->queryLogs("SELECT interview WHERE message = ?", ['catmh_interview']);
while ($row = db_fetch_assoc($result)) {
	$interview = json_decode($row['interview']);
	unset($interview->jsessionid);
	unset($interview->awselb);
	$interviews[] = $interview;
	echo htmlspecialchars(json_encode($interview), ENT_QUOTES);
}
?>
</textarea>
<br>
<p>Human Readable Text:</p>
<textarea style='min-width: 600px; height: 100%; width:85%;'>
<?php
foreach($interviews as $interview) {
	$interviewOutput = [];
	foreach($interview as $index => $value) {
		$interviewOutput[$index] = $value;
	}
	echo htmlspecialchars(json_encode($interviewOutput, JSON_PRETTY_PRINT), ENT_QUOTES);
}
?>
</textarea>
<?php
// require_once APP_PATH_DOCROOT . 'ProjectGeneral/footer.php';
