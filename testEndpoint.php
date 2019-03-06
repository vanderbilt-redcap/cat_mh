<?php
$json = json_decode(file_get_contents("php://input"), true);
$action = '';
if (isset($_GET['action'])) $action = $_GET['action'];
if (isset($json['action'])) $action = $json['action'];

if ($action == 'createInterview') {
	$mockAuthValues = [
		"interviews" => [
			0 => [
				'interviewID' => 123,
				'identifier' => 'asdjfw98ej',
				'signature' => '98j4gjiog'
			]
		]
	];
	exit(json_encode($mockAuthValues));
}