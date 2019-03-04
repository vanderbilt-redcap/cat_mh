<?php
// test createInterview
echo "<pre>";
$pid = $module->getProjectId();
$input = [];
$input['projectSettings'] = $module->getInterviewConfig($pid, 'second_instrument');
$input['subjectID'] = $input['projectSettings']['subjectID'];
$out = $module->createInterview($input);
print_r($out);
echo "</pre>";

// // test authInterview
// echo "<pre>";
// $_SESSION['identifier'] = 'abc';
// $_SESSION['signature'] = '123';
// $_SESSION['interviewID'] = 'def';
// $input = [];
// $out = $module->authInterview($input);
// print_r($out);
// echo "</pre>";