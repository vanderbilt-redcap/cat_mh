<?php
$out = $module->removeLogs("true");
$out = $module->createInterviews(['instrument' => 'second_instrument', 'recordID' => 1]);
// $subjectID = $_GET['sid'];
// $out = $module->getQueryLogsSql("select interview, instrument, recordID, status where subjectID='$subjectID' order by timestamp desc");
print_r($out);