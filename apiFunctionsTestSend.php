<?php
// // test createInterview
// echo "<pre>";
// $pid = $module->getProjectId();
// $input = [];
// $input['projectSettings'] = $module->getInterviewConfig($pid, 'second_instrument');
// $input['subjectID'] = $input['projectSettings']['subjectID'];
// $out = $module->createInterview($input);
// print_r($out);
// echo "</pre>";

// // test authInterview
// echo "<pre>";
// $_SESSION['identifier'] = 'abc';
// $_SESSION['signature'] = 'def';
// $_SESSION['interviewID'] = 123;
// $input = [];
// $out = $module->authInterview($input);
// print_r($out);
// echo "</pre>";

// // test startInterview
// echo "<pre>";
// $_SESSION['JSESSIONID'] = "ymOPw72ci6jeBibjJq3ca3np.ip-172-31-24-54; path=/interview; secure; HttpOnly";
// $_SESSION['AWSELB'] = "B3BD39AF16F0CF0B7377E928EC0A11EA291D20E9280654915BB0D2B0C64597CFFC8D11A03DDAA378D175FAFCC8D6F00508243C6EBB8818EFE4B8D60FA4409C8093B13010D6;PATH=/";
// $out = $module->startInterview([]);
// print_r($out);
// echo "</pre>";

// // test getInterviewStatus
// echo "<pre>";
// $pid = $module->getProjectId();
// $input = [];
// $_SESSION['identifier'] = 'abc';
// $_SESSION['signature'] = 'def';
// $_SESSION['interviewID'] = 123;
// $input['projectSettings'] = $module->getInterviewConfig($pid, 'second_instrument');
// $out = $module->getInterviewStatus($input);
// print_r($out);
// echo "</pre>";

// // test getQuestion
// echo "<pre>";
// $_SESSION['JSESSIONID'] = "ymOPw72ci6jeBibjJq3ca3np.ip-172-31-24-54; path=/interview; secure; HttpOnly";
// $_SESSION['AWSELB'] = "B3BD39AF16F0CF0B7377E928EC0A11EA291D20E9280654915BB0D2B0C64597CFFC8D11A03DDAA378D175FAFCC8D6F00508243C6EBB8818EFE4B8D60FA4409C8093B13010D6;PATH=/";
// $out = $module->getQuestion([]);
// print_r($out);
// echo "</pre>";

// // test submitAnswer
// echo "<pre>";
// $_SESSION['JSESSIONID'] = "ymOPw72ci6jeBibjJq3ca3np.ip-172-31-24-54; path=/interview; secure; HttpOnly";
// $_SESSION['AWSELB'] = "B3BD39AF16F0CF0B7377E928EC0A11EA291D20E9280654915BB0D2B0C64597CFFC8D11A03DDAA378D175FAFCC8D6F00508243C6EBB8818EFE4B8D60FA4409C8093B13010D6;PATH=/";
// $input = [
	// 'questionID' => 1,
	// 'response' => 2,
	// 'duration' => 4455
// ];
// $out = $module->submitAnswer($input);
// print_r($out);
// echo "</pre>";

// // get interview results
// echo "<pre>";
// $_SESSION['JSESSIONID'] = "ymOPw72ci6jeBibjJq3ca3np.ip-172-31-24-54; path=/interview; secure; HttpOnly";
// $_SESSION['AWSELB'] = "B3BD39AF16F0CF0B7377E928EC0A11EA291D20E9280654915BB0D2B0C64597CFFC8D11A03DDAA378D175FAFCC8D6F00508243C6EBB8818EFE4B8D60FA4409C8093B13010D6;PATH=/";
// $out = $module->getResults([]);
// print_r($out);
// echo "</pre>";

// // end interview
// echo "<pre>";
// $_SESSION['JSESSIONID'] = "ymOPw72ci6jeBibjJq3ca3np.ip-172-31-24-54; path=/interview; secure; HttpOnly";
// $_SESSION['AWSELB'] = "B3BD39AF16F0CF0B7377E928EC0A11EA291D20E9280654915BB0D2B0C64597CFFC8D11A03DDAA378D175FAFCC8D6F00508243C6EBB8818EFE4B8D60FA4409C8093B13010D6;PATH=/";
// $out = $module->endInterview([]);
// print_r($out);
// echo "</pre>";

// // test breakLock
// echo "<pre>";
// $_SESSION['JSESSIONID'] = "ymOPw72ci6jeBibjJq3ca3np.ip-172-31-24-54; path=/interview; secure; HttpOnly";
// $_SESSION['AWSELB'] = "B3BD39AF16F0CF0B7377E928EC0A11EA291D20E9280654915BB0D2B0C64597CFFC8D11A03DDAA378D175FAFCC8D6F00508243C6EBB8818EFE4B8D60FA4409C8093B13010D6;PATH=/";
// $out = $module->breakLock([]);
// print_r($out);
// echo "</pre>";