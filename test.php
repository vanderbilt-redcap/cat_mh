<?php
// $result = $module->queryLogs("select interviews, timestamp, subjectID where abc='def'");
$subjectID = $_GET['sid'];
$result = $module->queryLogs("select interviews where subjectID='$subjectID' order by timestamp desc");
print_r($result);
echo("<br />");
if (db_num_rows($result) > 0) {
	$record = db_fetch_assoc($result);
	print_r($record);
	echo("<br />");
	// echo("test interviews: " . $record['interviews']);
	$interviews = json_decode($record['interviews'], true);
	echo("identifier: " . $interviews[0]['identifier']);
} else {
	echo($subjectID);
}