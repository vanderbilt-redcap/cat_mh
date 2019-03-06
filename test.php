<?php
echo "<pre>";
// $pid = $module->getProjectId();
$input = [];
// $input['questionID'] = 327;
// $input['response'] = 3;
// $input['duration'] = 2040;
// $input['projectSettings'] = $module->getInterviewConfig($pid, 'my_first_instrument');
// $input['subjectID'] = $input['projectSettings']['subjectID'];
// $out = $module->submitAnswer($input);
$out = $module->endInterview($input);
print_r($out);
echo "</pre>";