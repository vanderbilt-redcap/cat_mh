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
				],
				1 => [
					"interviewID" => 456,
					"identifier" => 'ghi',
					"signature" => 'jkl',
				],
			]
		];
		header("Content-Type: application/json");
		echo(json_encode($data));
		break;
}