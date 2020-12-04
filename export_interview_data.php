<?php
require_once APP_PATH_DOCROOT . 'ProjectGeneral/header.php';
?>
<p>Raw JSON:</p>
<textarea style='min-width: 600px; min-height: 300px; width:85%;'>
<?php
$interviews = [];
$result = $module->queryLogs("SELECT interview WHERE message = ?", ['catmh_interview']);
while ($row = db_fetch_assoc($result)) {
	echo $row['interview'];
	$interviews[] = $row['interview'];
}
?>
</textarea>
<br>
<p>Human Readable Text:</p>
<textarea style='min-width: 600px; height: 100%; width:85%;'>
<?php
foreach($interviews as $interview) {
	print_r(json_decode($interview));
}
?>
</textarea>
<?php
// require_once APP_PATH_DOCROOT . 'ProjectGeneral/footer.php';