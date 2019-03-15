<?php
$action = $_GET['action'];
switch($action) {
	case "createInterviews":
		$data = [
			"interviews" => [
				0 => [
					"interviewID" => 123,
					"identifier" => 'abc',
					"signature" => 'def',
				]
				// 1 => [
					// "interviewID" => 456,
					// "identifier" => 'ghi',
					// "signature" => 'jkl',
				// ],
			]
		];
		header("Content-Type: application/json");
		echo(json_encode($data));
		break;
	case "authInterview":
		header("Location: " . "https://www.cat-mh.com/interview/secure/index.html");
		http_response_code(302);
		setcookie("JSESSIONID", "ymOPw72ci6jeBibjJq3ca3np.ip-172-31-24-54; path=/interview; secure; HttpOnly", time()+3600, "/");
		setcookie("AWSELB", "B3BD39AF16F0CF0B7377E928EC0A11EA291D20E9280654915BB0D2B0C64597CFFC8D11A03DDAA378D175FAFCC8D6F00508243C6EBB8818EFE4B8D60FA4409C8093B13010D6;PATH=/", time()+3600, "/");
		break;
	case "startInterview":
		header("Content-Type: application/json");
		$data = [
			"id" => 12345,
			"startTime" => null,
			"endTime" => null,
			"iter" => 0,
			"languageID" => 1,
			"interviewTests" => [1,5],
			"conditionalTests" => null,
			"subjectID" => null,
			"displayResults" => 0
		];
		echo(json_encode($data));
		break;
	case "getQuestion":
		header("Content-Type: application/json");
		$data = [
			// "questionID" => 14,
			"questionID" => -1,
			"questionNumber" => 2,
			"questionDescription" => "In the past 2 weeks, how much of the time did you feel depressed?",
			"questionAnswers" => [
				[
				"answerOrdinal" => 1,
				"answerDescription" => "None of the time",
				"answerWeight" => 1.0
				],
				[
				"answerOrdinal" => 2,
				"answerDescription" => "A little of the time",
				"answerWeight" => 2.0
				],
				[
				"answerOrdinal" => 3,
				"answerDescription" => "Some of the time",
				"answerWeight" => 3.0
				],
				[
				"answerOrdinal" => 4,
				"answerDescription" => "Most of the time",
				"answerWeight" => 4.0
				],
				[
				"answerOrdinal" => 5,
				"answerDescription" => "All of the time",
				"answerWeight" => 5.0
				]
			],
			"questionAudioID" => 14,
			"questionSymptom" => null
		];
		echo(json_encode($data));
		break;
	case "submitAnswer":
		http_response_code(200);
		break;
	case "endInterview":
		header("Location: http://www.cat-mh.com/");
		http_response_code(302);
		setcookie("JSESSIONID", "ymOPw72ci6jeBibjJq3ca3np.ip-172-31-24-54; path=/interview; secure; HttpOnly", time()+3600, "/");
		break;
	case "getResults":
		header("Content-Type: application/json");
		$data = [
			"interviewId" => 12346,
			"subjectId" => "0002",
			"startTime" => 1484538912297,
			"endTime" => 1484539170177,
			"tests" => [
			[
			"type" => "MDD","label" => "Major Depressive Disorder","diagnosis" => "positive","confidence" => 99.3,
			"severity" => null,"category" => null,"precision" => null,"prob" => null,"percentile" => null,
			"items" => [["questionId" => 925,"response" => 3,"duration" => 5.002],
			["questionId" => 927,"response" => 4,"duration" => 53.666],
			["questionId" => 922,"response" => 5,"duration" => 8.997],
			["questionId" => 924,"response" => 5,"duration" => 6.828]
			]
			],
			[
			"type" => "DEP","label" => "Depression","diagnosis" => null,"confidence" => null,"severity" => 87.5,
			"category" => "severe","precision" => 4.9,"prob" => 0.999,"percentile" => 92.5,
			"items" => [["questionId" => 5,"response" => 5,"duration" => 0.0],
			["questionId" => 9,"response" => 3,"duration" => 0.0],
			["questionId" => 16,"response" => 5,"duration" => 0.0],
			["questionId" => 117,"response" => 4,"duration" => 0.0],
			["questionId" => 41,"response" => 4,"duration" => 56.013],
			["questionId" => 240,"response" => 5,"duration" => 18.217],
			["questionId" => 386,"response" => 4,"duration" => 7.611],
			["questionId" => 84,"response" => 5,"duration" => 35.978],
			["questionId" => 384,"response" => 4,"duration" => 8.323],
			["questionId" => 288,"response" => 4,"duration" => 9.088],
			["questionId" => 313,"response" => 4,"duration" => 10.97],
			["questionId" => 279,"response" => 4,"duration" => 7.057],
			["questionId" => 262,"response" => 4,"duration" => 6.165],
			["questionId" => 146,"response" => 5,"duration" => 9.789],
			["questionId" => 341,"response" => 4,"duration" => 7.552]
			]
			]
			]
		];
		echo(json_encode($data));
		break;
	case "getInterviewStatus":
		header('Content-Type: application/json');
		$data = [
			"interviewValid" => true,
			"credentialsValid" => true,
			"startTime" => null,
			"endTime" => null,
			"inProgress" => false
		];
		echo(json_encode($data));
		break;
	case "breakLock":
		header("Location: " . "https://www.cat-mh.com/interview/secure/index.html");
		http_response_code(302);
		break;
}