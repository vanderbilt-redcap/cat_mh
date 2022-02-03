<?php

require_once APP_PATH_DOCROOT . 'ProjectGeneral/header.php';

echo "<pre>";

echo "REMOVING ALL SCHEDULED SEQUENCES AND INTERVIEWS";

// $module->removeLogs("message='scheduleSequence'");
// $module->removeLogs("message=? OR message=?", ['scheduleSequence', 'catmh_interview']);

echo "\nDONE";
echo "</pre>";
require_once APP_PATH_DOCROOT . 'ProjectGeneral/footer.php';
?>