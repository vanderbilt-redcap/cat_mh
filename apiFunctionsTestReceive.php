<?php

$json = json_decode(file_get_contents("php://input"), true);

// test createInterview
$mockAuthValues = [
	'interviewID' => 123,
	'identifier' => 'asdjfw98ej',
	'signature' => '98j4gjiog'
];
if ($json['organizationID'] == 114) exit(json_encode($mockAuthValues));

// test authInterview
if ($json['j_username']=='abc' and $json['j_password']=='def' and $json['interviewID']==123) {
	header('Location: https://www.cat-mh.com/interview/secure/index.html');
	http_response_code(302);
	setcookie("JSESSIONID", "ymOPw72ci6jeBibjJq3ca3np.ip-172-31-24-54; path=/interview; secure; HttpOnly", time()+3600, null, null, true, true);
	setcookie("AWSELB", "B3BD39AF16F0CF0B7377E928EC0A11EA291D20E9280654915BB0D2B0C64597CFFC8D11A03DDAA378D175FAFCC8D6F00508243C6EBB8818EFE4B8D60FA4409C8093B13010D6;PATH=/", time()+3600, null, null, true, true);
}

// // test startInterview
// if (
	// $_COOKIE["JSESSIONID"] == "ymOPw72ci6jeBibjJq3ca3np.ip-172-31-24-54" and
	// $_COOKIE["AWSELB"] == "B3BD39AF16F0CF0B7377E928EC0A11EA291D20E9280654915BB0D2B0C64597CFFC8D11A03DDAA378D175FAFCC8D6F00508243C6EBB8818EFE4B8D60FA4409C8093B13010D6"
// ) {
	// echo('{
		// "id":12345,
		// "startTime":null,
		// "endTime":null,
		// "iter":0,
		// "languageID":1,
		// "interviewTests":[1,5],
		// "conditionalTests":null,
		// "subjectID":null,
		// "displayResults":0
	// }');
// }

// // test getInterviewStatus
// $headers = getallheaders();
// if (
	// $headers['applicationid'] == "VU_Portal" and
	// $json['organizationID'] == 114 and
	// $json['identifier'] == 'abc' and
	// $json['signature'] == 'def' and
	// $json['interviewID'] == 123
// ) {
	// echo('{
		// "interviewValid":true,
		// "credentialsValid":true,
		// "startTime":null,
		// "endTime":null,
		// "inProgress":false
	// }');
// } else {
	// echo(json_encode($headers['applicationid'] == "VU_Portal") . "<br />");
	// echo(json_encode($json['organizationID'] == 114) . "<br />");
	// echo(json_encode($json['identifier'] == 'abc') . "<br />");
	// echo(json_encode($json['signature'] == 'def') . "<br />");
	// echo(json_encode($json['interviewID'] == 123) . "<br />");
// }

// // test getQuestion
// if (
	// $_COOKIE["JSESSIONID"] == "ymOPw72ci6jeBibjJq3ca3np.ip-172-31-24-54" and
	// $_COOKIE["AWSELB"] == "B3BD39AF16F0CF0B7377E928EC0A11EA291D20E9280654915BB0D2B0C64597CFFC8D11A03DDAA378D175FAFCC8D6F00508243C6EBB8818EFE4B8D60FA4409C8093B13010D6"
// ) {
	// echo('{
		// "questionID":14,
		// "questionNumber":2,
		// "questionDescription":"In the past 2 weeks, how much of the time did you feel depressed?",
		// "questionAnswers":[
		// {
		// "answerOrdinal":1,
		// "answerDescription":"None of the time",
		// "answerWeight":1.0
		// },
		// {
		// "answerOrdinal":2,
		// "answerDescription":"A little of the time",
		// "answerWeight":2.0
		// },
		// {
		// "answerOrdinal":3,
		// "answerDescription":"Some of the time",
		// "answerWeight":3.0
		// },
		// {
		// "answerOrdinal":4,
		// "answerDescription":"Most of the time",
		// "answerWeight":4.0
		// },
		// {
		// "answerOrdinal":5,
		// "answerDescription":"All of the time",
		// "answerWeight":5.0
		// }
		// ],
		// "questionAudioID":14,
		// "questionSymptom":null
	// }');
// }


// // test submitAnswer
// if (
	// $_COOKIE["JSESSIONID"] == "ymOPw72ci6jeBibjJq3ca3np.ip-172-31-24-54" and
	// $_COOKIE["AWSELB"] == "B3BD39AF16F0CF0B7377E928EC0A11EA291D20E9280654915BB0D2B0C64597CFFC8D11A03DDAA378D175FAFCC8D6F00508243C6EBB8818EFE4B8D60FA4409C8093B13010D6" and
	// $json['questionID'] > 0 and
	// $json['response'] > 0 and
	// $json['duration'] > 0
// ) {
	// http_response_code(200);
// } else {
	
// }

// // get interview results
// if (
	// $_COOKIE["JSESSIONID"] == "ymOPw72ci6jeBibjJq3ca3np.ip-172-31-24-54" and
	// $_COOKIE["AWSELB"] == "B3BD39AF16F0CF0B7377E928EC0A11EA291D20E9280654915BB0D2B0C64597CFFC8D11A03DDAA378D175FAFCC8D6F00508243C6EBB8818EFE4B8D60FA4409C8093B13010D6"
// ) {
	// echo('{
		// "interviewId":12346,
		// "subjectId":"0002",
		// "startTime":1484538912297,
		// "endTime":1484539170177,
		// "tests":[
		// {
		// "type":"MDD","label":"Major Depressive Disorder","diagnosis":"positive","confidence":99.3,
		// "severity":null,"category":null,"precision":null,"prob":null,"percentile":null,
		// "items":[{"questionId":925,"response":3,"duration":5.002},
		// {"questionId":927,"response":4,"duration":53.666},
		// {"questionId":922,"response":5,"duration":8.997},
		// {"questionId":924,"response":5,"duration":6.828}
		// ]
		// },
		// {
		// "type":"DEP","label":"Depression","diagnosis":null,"confidence":null,"severity":87.5,
		// "category":"severe","precision":4.9,"prob":0.999,"percentile":92.5,
		// "items":[{"questionId":5,"response":5,"duration":0.0},
		// {"questionId":9,"response":3,"duration":0.0},
		// {"questionId":16,"response":5,"duration":0.0},
		// {"questionId":117,"response":4,"duration":0.0},
		// {"questionId":41,"response":4,"duration":56.013},
		// {"questionId":240,"response":5,"duration":18.217},
		// {"questionId":386,"response":4,"duration":7.611},
		// {"questionId":84,"response":5,"duration":35.978},
		// {"questionId":384,"response":4,"duration":8.323},
		// {"questionId":288,"response":4,"duration":9.088},
		// {"questionId":313,"response":4,"duration":10.97},
		// {"questionId":279,"response":4,"duration":7.057},
		// {"questionId":262,"response":4,"duration":6.165},
		// {"questionId":146,"response":5,"duration":9.789},
		// {"questionId":341,"response":4,"duration":7.552}
	// ]
	// }
	// ]
	// }');
// } else {
	
// }

// // end interview
// if (
	// $_COOKIE["JSESSIONID"] == "ymOPw72ci6jeBibjJq3ca3np.ip-172-31-24-54" and
	// $_COOKIE["AWSELB"] == "B3BD39AF16F0CF0B7377E928EC0A11EA291D20E9280654915BB0D2B0C64597CFFC8D11A03DDAA378D175FAFCC8D6F00508243C6EBB8818EFE4B8D60FA4409C8093B13010D6"
// ) {
	// http_response_code(302);
	// setcookie("JSESSIONID", "ymOPw72ci6jeBibjJq3ca3np.ip-172-31-24-54; path=/interview; secure; HttpOnly", time()+3600, null, null, true, true);
	// setcookie("AWSELB", "B3BD39AF16F0CF0B7377E928EC0A11EA291D20E9280654915BB0D2B0C64597CFFC8D11A03DDAA378D175FAFCC8D6F00508243C6EBB8818EFE4B8D60FA4409C8093B13010D6;PATH=/", time()+3600, null, null, true, true);
// } else {
	// echo(json_encode($_COOKIE["JSESSIONID"] == "ymOPw72ci6jeBibjJq3ca3np.ip-172-31-24-54"));
	// echo(json_encode($_COOKIE["AWSELB"] == "B3BD39AF16F0CF0B7377E928EC0A11EA291D20E9280654915BB0D2B0C64597CFFC8D11A03DDAA378D175FAFCC8D6F00508243C6EBB8818EFE4B8D60FA4409C8093B13010D6"));
// }

// // end interview
// if (
	// $_COOKIE["JSESSIONID"] == "ymOPw72ci6jeBibjJq3ca3np.ip-172-31-24-54" and
	// $_COOKIE["AWSELB"] == "B3BD39AF16F0CF0B7377E928EC0A11EA291D20E9280654915BB0D2B0C64597CFFC8D11A03DDAA378D175FAFCC8D6F00508243C6EBB8818EFE4B8D60FA4409C8093B13010D6"
// ) {
	// http_response_code(302);
// } else {
	// echo(json_encode($_COOKIE["JSESSIONID"] == "ymOPw72ci6jeBibjJq3ca3np.ip-172-31-24-54"));
	// echo(json_encode($_COOKIE["AWSELB"] == "B3BD39AF16F0CF0B7377E928EC0A11EA291D20E9280654915BB0D2B0C64597CFFC8D11A03DDAA378D175FAFCC8D6F00508243C6EBB8818EFE4B8D60FA4409C8093B13010D6"));
// }