<?php

require_once APP_PATH_DOCROOT . 'ProjectGeneral/header.php';

echo "<pre>";

$module->llog("REMOVING ALL SCHEDULED SEQUENCES");
$pid = $module->getProjectId();

$module->removeLogs("message='scheduleSequence'");

echo "</pre>";
require_once APP_PATH_DOCROOT . 'ProjectGeneral/footer.php';
?>