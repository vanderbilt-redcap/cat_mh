<?php
if ($_SERVER['REQUEST_METHOD'] != "POST") {
	exit();
}

$json = json_decode(file_get_contents("php://input"), true);
// escape interview data
if (isset($json['args']['interviewID'])) $json['args']['interviewID'] = db_escape($json['args']['interviewID']);
if (isset($json['args']['subjectID'])) $json['args']['subjectID'] = db_escape($json['args']['subjectID']);
if (isset($json['args']['instrument'])) $json['args']['instrument'] = db_escape($json['args']['instrument']);
if (isset($json['args']['recordID'])) $json['args']['recordID'] = db_escape($json['args']['recordID']);
if (isset($json['args']['identifier'])) $json['args']['identifier'] = db_escape($json['args']['identifier']);
if (isset($json['args']['signature'])) $json['args']['signature'] = db_escape($json['args']['signature']);
if (isset($json['args']['questionID'])) $json['args']['questionID'] = db_escape($json['args']['questionID']);
if (isset($json['args']['response'])) $json['args']['response'] = db_escape($json['args']['response']);
if (isset($json['args']['duration'])) $json['args']['duration'] = db_escape($json['args']['duration']);
if (isset($json['args']['kcat'])) $json['args']['kcat'] = db_escape($json['args']['kcat']);
if (isset($json['args']['types'])) {
	foreach ($json['args']['types'] as &$type) {
		$type = db_escape($type);
	}
}
if (isset($json['args']['labels'])) {
	foreach ($json['args']['labels'] as &$label) {
		$label = db_escape($label);
	}
}

// determine action via POST 'action' value
$action = db_escape($json['action']);
switch ($action) {
	case 'authInterview':
		$out['receivedJson'] = json_encode($json);
		$out = $module->authInterview($json['args']);
		exit(json_encode($out));
	case 'startInterview':
		$out['receivedJson'] = json_encode($json);
		$out = $module->startInterview($json['args']);
		exit(json_encode($out));
	case 'getQuestion':
		$out['receivedJson'] = json_encode($json);
		$out = $module->getQuestion($json['args']);
		exit(json_encode($out));
	case 'submitAnswer':
		$out['receivedJson'] = json_encode($json);
		$out = $module->submitAnswer($json['args']);
		exit(json_encode($out));
	case 'endInterview':
		$out['receivedJson'] = json_encode($json);
		$out = $module->endInterview($json['args']);
		exit(json_encode($out));
	case 'getResults':
		$out['receivedJson'] = json_encode($json);
		$out = $module->getResults($json['args']);
		if ($out['success']) {
			$module->sendProviderEmail();
		}
		exit(json_encode($out));
	case 'getInterviewStatus':
		$out['receivedJson'] = json_encode($json);
		$out = $module->getInterviewStatus($json['args']);
		exit(json_encode($out));
	case 'breakLock':
		$out['receivedJson'] = json_encode($json);
		$out = $module->breakLock($json['args']);
		exit(json_encode($out));
}