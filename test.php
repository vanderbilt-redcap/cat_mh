<?php
$action = $_REQUEST['action'];
	
if ($action === 'auth') {
	exit('{"word": "auth"}');
} elseif ($action === 'break') {
	$data = [];
	$data['word'] = "break";
	$data['cookie'] = json_encode($_COOKIE);
	$data['headers'] = json_encode(getallheaders());
	exit(json_encode($data));
} elseif ($action == 'create') {
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
} elseif ($action === 'init') {
	exit('{"word": "init"}');
} elseif ($action === 'getQuestion') {
	exit('{"word": "getQuestion"}');
} elseif ($action === 'getStatus') {
	exit('{
		"interviewValid":true,
		"credentialsValid":true,
		"startTime":null,
		"endTime":null,
		"inProgress":false
	}');
} elseif ($action === 'results') {
	exit('{"word": "results"}');
} elseif ($action === 'submit') {
	exit('{"word": "submit"}');
} elseif ($action === 'terminate') {
	exit('{"word": "terminate"}');
}
exit('{}');