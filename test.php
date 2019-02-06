<?php
$headers = getallheaders();
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $headers['applicationid'] === 'VU_Portal') {
	$action = $_GET['action'];
	if ($action == 'create') {
		exit('{
			"interviews": [
				{
					"interviewID":12345,
					"identifier":"a9b3",
					"signature":"1zrd4f"
				},
				{
					"interviewID":12356,
					"identifier":"3mp8",
					"signature":"bx5t8v"
				}
			]
		}');
	} elseif ($action === 'status') {
		exit('{
			"interviewValid":true,
			"credentialsValid":true,
			"startTime":null,
			"endTime":null,
			"inProgress":false
		}');
	}
	
	// $payload = file_get_contents('php://input');
	// exit('{"message": "incorrect application id: ' . $payload . '"}');
	// $json = json_decode(file_get_contents('php://input'), true);
	// if ($json['organizationID'] === 114) {
		
	} else {
		exit('{"message": "incorrect application id: ' . $json['organizationID'] . '"}');
	}
}
exit('{"message": "request failed"}');

// echo("<pre>");
// foreach(getallheaders() as $name => $header) {
	// echo("$name:\n$header\n\n");
// }
// echo("</pre>");