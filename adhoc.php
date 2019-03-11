<?php
$module->removeLogs('true');
$out = $module->createInterviews(['instrument' => 'survey_2', 'recordID' => 1]);
print_r($out);