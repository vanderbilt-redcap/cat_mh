<?php
namespace VICTR\REDCAP\CAT_MH;

class CAT_MH extends \ExternalModules\AbstractExternalModule {
	// public $testAPI = true;
	// public $debug = true;
	public $convertTestAbbreviation = [
		'mdd' => "mdd",
		'dep' => "dep",
		'anx' => "anx",
		'mhm' => "m/hm",
		'pdep' => "p-dep",
		'panx' => "p-anx",
		'pmhm' => "p-m/hm",
		'sa' => "sa",
		'ptsd' => "ptsd",
		'cssrs' => "c-ssrs",
		'ss' => "ss"
	];
	public $testTypes = [
		'mdd' => "Major Depressive Disorder",
		'dep' => "Depression",
		'anx' => "Anxiety Disorder",
		'm/hm' => "Mania/Hypomania",
		'p-dep' => "Depression (Perinatal)",
		'p-anx' => "Anxiety Disorder (Perinatal)",
		'p-m/hm' => "Mania/Hypomania (Perinatal)",
		'sa' => "Substance Abuse",
		'ptsd' => "Post-Traumatic Stress Disorder",
		'c-ssrs' => "C-SSRS Suicide Screen",
		'ss' => "Suicide Scale"
	];
	
	// hooks
	public function redcap_survey_complete($project_id, $record, $instrument, $event_id, $group_id, $survey_hash, $response_id, $repeat_instance) {
		// if user did not consent, then do not forward to interview, instead notify them that they were rejected and that they may now close this window
		$userConsent = \REDCap::getData($project_id, 'array', $record, 'consent')[$record][$event_id]['consent'];
		if ($userConsent != 1) {
			echo("You did not consent to the adaptive testing interview and may now close this tab/window.");
			// delete record that REDCap created
			$tpk = \Records::getTablePK($project_id);
			$ret = \Records::deleteRecord($record, $tpk, null, null, null, null, null, "CAT-MH module removed record for consent==0", true);
			return;
		}
		
		// generate subject ID
		$subjectID = "";
		$sidDomain = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ1234567890";
		$domainLength = strlen($sidDomain);
		for ($i = 0; $i < 32; $i++) {
			$subjectID .= $sidDomain[rand(0, $domainLength - 1)];
		}
		
		// save newly created subjectID
		$data = [
			$record => [
				$event_id => [
					"subjectid" => $subjectID,
					"cat_mh_data" => json_encode([
						"interviews" => []
					])
				]
			]
		];
		\REDCap::saveData($project_id, 'array', $data);
		
		// If no sequence given in url parameters, default to first sequence configured
		$sequence = $_GET['sequence'];
		if (!isset($sequence)) {
			$projectSettings = $this->getProjectSettings();
			$sequence = $projectSettings['sequence']['value'][0];
		}
		if ($sequence == NULL) {
			echo("There are no CAT-MH tests configured. Please contact your administrator and have them configure the CAT-MH module in REDCap.");
			return;
		}
		
		// finally redirect survey participant
		$page = $this->getUrl("interview.php") . "&NOAUTH&sid=" . $subjectID . "&sequence=$sequence";
		redirect($page);
	}
	
	// crons
	public function cronEmail() {
		$originalPid = $_GET['pid'];
		foreach($this->framework->getProjectsWithModuleEnabled() as $localProjectId){
			$_GET['pid'] = $localProjectId;
			
			\REDCap::logEvent("Running Email Scheduler", time(), NULL, NULL, NULL, $this->getProjectId());
			// determine number of days that have elapsed
			$daysElapsed = $this->getProjectSetting('days-elapsed');
			if (!isset($daysElapsed)) {
				$daysElapsed = 0;
				$this->setProjectSetting('days-elapsed', 0);
			}
			$daysElapsed = intval($daysElapsed);

			// determine which sequences to send emails for
			$urls = [];
			$settings = $this->getProjectSettings();
			// echo("<pre>");
			// print_r($settings);
			// echo("</pre>");
			foreach ($settings['sequence']['value'] as $i => $sequence) {
				$period_every = $settings['periodicity-every']['value'][$i];
				$period_end = $settings['periodicity-end']['value'][$i];
				if (isset($period_every) and isset($period_end)) {
					$period_every = intval($period_every);
					$period_end = intval($period_end);
					if (($daysElapsed % $period_every) == 0 and $daysElapsed <= $period_end and $daysElapsed != 0) {
						$urls[] = $this->getUrl("interview.php") . "&NOAUTH&sequence=$sequence";
						// echo ($this->getUrl("interview.php") . "&NOAUTH&sequence=$sequence <br />");
					}
				}
			}

			$emailSender = $settings['email-sender']['value'];
			$emailSubject = $settings['email-subject']['value'];
			$emailBody = $settings['email-body']['value'];

			if (empty($urls) or !isset($emailSender) or !isset($emailSubject) or !isset($emailBody)) {
				// increment daysElapsed
				$this->setProjectSetting('days-elapsed', $daysElapsed + 1);
				$this->exitAfterHook();
			}

			// prepare email body by replacing [interview-links] and [interview-urls]
			$emailBody = str_replace("[interview-urls]", implode($urls, "\r\n"), $emailBody);
			foreach($urls as $i => $url) {
				$urls[$i] = "<a href=\"$url\">CAT-MH Interview Link</a>";
			}
			$emailBody = str_replace("[interview-links]", implode($urls, "\r\n"), $emailBody);

			// we have links to send so for each participant with a listed email, invite to take interview(s)
			$data = \REDCap::getData($this->getProjectId(), 'array');
			foreach($data as $rid => $record) {
				$eid = array_keys($record)[0];
				$addressTo = $record[$eid]['participant_email'];
				if (isset($addressTo)) {
					foreach($urls as $url) {
						$success = \REDCap::email($addressTo, $emailSender, $emailSubject, $emailBody);
						if ($success === false) {
							\REDCap::logEvent("Failed Sending Interview Email", "$addressTo, $emailSender, $emailSubject, $emailBody", NULL, $rid, $eid, $this->getProjectId());
						} else {
							\REDCap::logEvent("Sent Interview Email", "$addressTo, $emailSender, $emailSubject, $emailBody", NULL, $rid, $eid, $this->getProjectId());
						}
					}
				}
			}
			// increment daysElapsed
			$this->setProjectSetting('days-elapsed', $daysElapsed + 1);
		}
		$_GET['pid'] = $originalPid;
		
		return "The CAT-MH external module cron method 'cronEmail' ran successfully";
	}
	
	//utility
	public function curl($args) {
		// required args:
		// address
		
		// optional args:
		// post, headers, body
		
		// initialize return/output array
		$output = [];
		// $output['args'] = $args;
		
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $args['address']);
		if (isset($args['headers'])) {
			curl_setopt($ch, CURLOPT_HTTPHEADER, $args['headers']);
		}
		if (isset($args['post'])) {
			curl_setopt($ch, CURLOPT_POST, true);
			curl_setopt($ch, CURLOPT_POSTFIELDS, $args['body']);
		}
		curl_setopt($ch, CURLOPT_HEADER, true);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		$output['response'] = curl_exec($ch);
		$output['info'] = curl_getinfo($ch);
		$rawHeaders = substr($output['response'], 0, $output['info']['header_size']);
		$output['body'] = substr($output['response'], $output['info']['header_size']);
		$output['errorNumber'] = curl_errno($ch);
		$output['error'] = curl_error($ch);
		curl_close ($ch);
		
		// get cookies
		preg_match_all('/^Set-Cookie:\s*([^;]*)/mi', $output['response'], $matches);
		$cookies = array();
		foreach($matches[1] as $item) {
			parse_str($item, $cookie);
			$cookies = array_merge($cookies, $cookie);
		}
		$output['cookies'] = $cookies;
		
		// get headers as arrays
		function extractHeaders($headerContent) {
			$headers = [];
			
			// Split the string on every "double" new line.
			$arrRequests = explode("\r\n\r\n", $headerContent);
			
			// Loop of response headers. The "count() -1" is to 
			//avoid an empty row for the extra line break before the body of the response.
			for ($index = 0; $index < count($arrRequests) -1; $index++) {
				foreach (explode("\r\n", $arrRequests[$index]) as $i => $line)
				{
					if ($i === 0)
						$headers[$index]['http_code'] = $line;
					else
					{
						list ($key, $value) = explode(': ', $line);
						$headers[$index][$key] = $value;
					}
				}
			}
			return $headers;
		}
		
		$output['headers'] = extractHeaders($rawHeaders);
		return $output;
	}
	
	public function getAuthValues($args) {
		// args should have: subjectID, interviewID, identifier, signature
		try {
			$data = $this->getRecordBySID($args['subjectID']);
			$rid = array_keys($data)[0];
			$eid = array_keys($data[$rid])[0];
			$catmh_data = json_decode($data[$rid][$eid]['cat_mh_data'], true);
			foreach($catmh_data['interviews'] as $i => $interview) {
				if ($interview['interviewID'] == $args['interviewID'] and $interview['signature'] == $args['signature'] and $interview['identifier'] == $args['identifier']) {
					return [
						"jsessionid" => $interview['jsessionid'],
						"awselb" => $interview['awselb']
					];
				}
			}
		} catch (\Exception $e) {
			echo("REDCap couldn't get authorization values from logged interview data -- please contact REDCap administrator.<br />$e");
		}
	}
	
	public function getRecordBySID($sid) {
		$pid = $this->getProjectId();
		$data = \REDCap::getData($pid, 'array', NULL, NULL, NULL, NULL, NULL, NULL, NULL, "[subjectid]=\"$sid\"");
		return $data;
	}
	
	public function newInterview($sid, $sequence = "") {
		// If no sequence given in url parameters, default to first sequence configured
		$projectSettings = $this->getProjectSettings();
		if ($sequence == "") {
			$sequence = $projectSettings['sequence']['value'][0];
		}
		if ($sequence == NULL) {
			echo("There are no CAT-MH tests configured. Please contact your administrator and have them configure the CAT-MH module in REDCap.");
			return;
		}
		
		// get system configuration details
		$args = [];
		$args['organizationid'] = $this->getSystemSetting('organizationid');
		$args['applicationid'] = $this->getSystemSetting('applicationid');
		if (!isset($args['organizationid']) or !isset($args['organizationid'])) {
			echo("Cannot create a new interview. Please have the REDCap administrator configure the application and organization IDs for CAT-MH use.");
			return;
		}
		$args['subjectID'] = $sid;
		
		// determine sequence tests and language
		foreach ($projectSettings['sequence']['value'] as $i => $seq) {
			if ($seq == $sequence) {
				// tests array
				$args['tests'] = [];
				$testTypeKeys = array_keys($this->convertTestAbbreviation);
				foreach ($testTypeKeys as $j => $testAbbreviation) {
					if ($projectSettings[$testAbbreviation]['value'][$i] == 1) {
						$args['tests'][] = ["type" => $this->convertTestAbbreviation[$testAbbreviation]];
					}
				}
				$args['language'] = $projectSettings['language']['value'][$i] == 2 ? 2 : 1;
			}
		}
		
		$interview = $this->createInterview($args);
		
		if (!isset($interview['moduleError'])) {
			// save newly created interview info in redcap
			$data = $this->getRecordBySID($sid);
			$rid = array_keys($data)[0];
			$eid = array_keys($data[$rid])[0];
			$catmh_data = json_decode($data[$rid][$eid]['cat_mh_data'], true);
			$catmh_data['interviews'][] = [
				"sequence" => $sequence,
				"interviewID" => $interview['interviewID'],
				"identifier" => $interview['identifier'],
				"signature" => $interview['signature'],
				"types" => $interview['types'],
				"labels" => $interview['labels'],
				"status" => 1,
				"timestamp" => time()
			];
			$data[$rid][$eid]['cat_mh_data'] = json_encode($catmh_data);
			$result = \REDCap::saveData($this->getProjectId(), 'array', $data);
			if (!empty($result['errors'])) {
				echo("<pre>");
				echo("Errors saving to REDCap:\n");
				print_r($result);
				echo("<pre>");
				return false;
			}
		} else {
			echo("CAT-MH encountered an error with the API:<br />" . $interview['moduleMessage']);
			return false;
		}
		
		return $interview;
	}
	
	// CAT-MH API methods
	public function createInterview($args) {
		// args needed: applicationid, organizationid, subjectID, language, tests[]
		$out = [];
		
		// build request headers and body
		$curlArgs = [];
		$curlArgs['headers'] = [
			"applicationid: " . $args['applicationid'],
			"Accept: application/json",
			"Content-Type: application/json"
		];
		$curlArgs['body'] = json_encode([
			"organizationID" => intval($args['organizationid']),
			"userFirstName" => "Automated",
			"userLastName" => "Creation",
			"subjectID" => $args['subjectID'],
			"numberOfInterviews" => 1,
			// "numberOfInterviews" => sizeof($interviewConfig['tests']),
			"language" => intval($args['language']),
			"tests" => $args['tests']
		]);
		$curlArgs['post'] = true;
		$testAddress = "http://localhost/redcap/redcap_v8.10.2/ExternalModules/?prefix=cat_mh&page=testEndpoint&pid=" . $this->getProjectId() . "&action=" . __FUNCTION__;
		$curlArgs['address'] = $this->testAPI ? $testAddress : "https://www.cat-mh.com/portal/secure/interview/createInterview";
		
		if ($this->debug) $out['curlArgs'] = $curlArgs;
		
		// send request via curl
		$curl = $this->curl($curlArgs);
		if ($this->debug) $out['curl'] = $curl;
		
		// handle response
		try {
			// extract json
			$json = json_decode($curl['body'], true);
			$out['interviewID'] = $json['interviews'][0]['interviewID'];
			$out['identifier'] = $json['interviews'][0]['identifier'];
			$out['signature'] = $json['interviews'][0]['signature'];
			
			// create types and labels arrays
			$out['types'] = [];
			$out['labels'] = [];
			foreach ($args['tests'] as $arr) {
				$out['types'][] = $arr['type'];
				$out['labels'][] = $this->testTypes[$arr['type']];
			}
			
			$out['success'] = true;
		} catch (\Exception $e) {
			$out['moduleError'] = true;
			$out['moduleMessage'] = "REDCap couldn't get interview information from CAT-MH API." . "<br />\n" . $e;
		}
		return $out;
	}
	
	public function authInterview($args) {
		// args needed: subjectID, identifier, signature, interviewID
		
		$args['interviewID'] = intval($args['interviewID']);
		$out = [];
		if ($this->debug) $out['args'] = $args;
		
		// build request headers and body
		$curlArgs = [];
		$curlArgs['headers'] = [
			"Content-Type: application/x-www-form-urlencoded"
		];
		$curlArgs['body'] = "j_username=" . $args['identifier'] . "&" .
			"j_password=" . $args['signature'] . "&" .
			"interviewID=" . $args['interviewID'];
		$curlArgs['post'] = true;
		$testAddress = "http://localhost/redcap/redcap_v8.10.2/ExternalModules/?prefix=cat_mh&page=testEndpoint&pid=" . $this->getProjectId() . "&action=" . __FUNCTION__;
		$curlArgs['address'] = $this->testAPI ? $testAddress : "https://www.cat-mh.com/interview/signin";
		if ($this->debug) $out['curlArgs'] = $curlArgs;
		
		// send request via curl
		$curl = $this->curl($curlArgs);
		if ($this->debug) $out['curl'] = $curl;
		
		if (isset($curl['cookies']['JSESSIONID']) and isset($curl['cookies']['AWSELB'])) {
			// update redcap record data
			
			// echo($curl['cookies']['JSESSIONID'] . "<br />");
			// echo($curl['cookies']['AWSELB'] . "<br />");
			// echo(print_r($args, true) . "<br />");
			
			$data = $this->getRecordBySID($args['subjectID']);
			$rid = array_keys($data)[0];
			$eid = array_keys($data[$rid])[0];
			$catmh_data = json_decode($data[$rid][$eid]['cat_mh_data'], true);
			
			foreach($catmh_data['interviews'] as $i => $interview) {
				if (intval($interview['interviewID']) == $args['interviewID'] and $interview['signature'] == $args['signature'] and $interview['identifier'] == $args['identifier']){
					$catmh_data['interviews'][$i]['jsessionid'] = $curl['cookies']['JSESSIONID'];
					$catmh_data['interviews'][$i]['awselb'] = $curl['cookies']['AWSELB'];
				}
			}
			
			$data[$rid][$eid]['cat_mh_data'] = json_encode($catmh_data);
			$result = \REDCap::saveData($this->getProjectId(), 'array', $data);
			
			if (!empty($result['errors'])) {
				$out['moduleError'] = true;
				$out['moduleMessage'] = "Errors saving to REDCap:<br />" . print_r($result, true) . "<br />sid:<br />" . $args['subjectID'];
			} else {
				$out['success'] = true;
			}
		} else {
			$out['moduleError'] = true;
			$out['moduleMessage'] = "REDCap failed to retrieve authorization details from the CAT-MH API server for the interview.";
		}
		
		return $out;
	}
	
	public function startInterview($args) {
		// args required: subjectID, interviewID, identifier, signature
		$out = [];
		if ($this->debug) $out['args'] = $args;
		
		try {
			$authValues = $this->getAuthValues($args);
			if (!isset($authValues['jsessionid']) or !isset($authValues['awselb'])) {
				throw new \Exception("Auth values not set.");
			}
		} catch (\Exception $e) {
			$out['moduleError'] = true;
			$out['moduleMessage'] = "REDCap couldn't get authorization values from logged interview data -- please contact REDCap administrator.\n<br />$e";
			return $out;
		}
		
		$curlArgs = [];
		$curlArgs['headers'] = [
			"Accept: application/json",
			"Cookie: JSESSIONID=" . $authValues['jsessionid'] . "; AWSELB=" . $authValues['awselb']
		];
		$testAddress = "http://localhost/redcap/redcap_v8.10.2/ExternalModules/?prefix=cat_mh&page=testEndpoint&pid=" . $this->getProjectId() . "&action=" . __FUNCTION__;
		$curlArgs['address'] = $this->testAPI ? $testAddress : "https://www.cat-mh.com/interview/rest/interview";
		if ($this->debug) $out['curlArgs'] = $curlArgs;
		
		// send request via curl
		$curl = $this->curl($curlArgs);
		if ($this->debug) $out['curl'] = $curl;
		
		// handle response
		try {
			$json = json_decode($curl['body'], true);
			if (gettype($json) != 'array') throw new \Exception("json error");
			
			// update timestamp and status for this interview
			$data = $this->getRecordBySID($args['subjectID']);
			$rid = array_keys($data)[0];
			$eid = array_keys($data[$rid])[0];
			$catmh_data = json_decode($data[$rid][$eid]['cat_mh_data'], true);
			
			foreach($catmh_data['interviews'] as $i => $interview) {
				if ($interview['interviewID'] == $args['interviewID'] and $interview['signature'] == $args['signature'] and $interview['identifier'] == $args['identifier']) {
					$catmh_data['interviews'][$i]['status'] = 2;
					$catmh_data['interviews'][$i]['timestamp'] = time();
				}
			}
			
			$data[$rid][$eid]['cat_mh_data'] = json_encode($catmh_data);
			$result = \REDCap::saveData($this->getProjectId(), 'array', $data);
			if (!empty($result['errors'])) {
				$out['moduleError'] = true;
				$out['moduleMessage'] = "Errors saving to REDCap:" . print_r($result, true);
			} else {
				$out['success'] = true;
			}
			
			if ($json['id'] > 0) {
				$out['getFirstQuestion'] = true;
			} else {
				$out['terminateInterview'] = true;
			}
		} catch (\Exception $e) {
			$out['moduleError'] = true;
			$out['moduleMessage'] = "REDCap failed to start the interview via the CAT-MH API.";
		}
		return $out;
	}
	
	public function getQuestion($args) {
		// args required: JSESSIONID, AWSELB
		$out = [];
		if ($this->debug) $out['args'] = $args;
		
		try {
			$authValues = $this->getAuthValues($args);
			if (!isset($authValues['jsessionid']) or !isset($authValues['awselb'])) {
				throw new \Exception("Auth values not set.");
			}
		} catch (\Exception $e) {
			$out['moduleError'] = true;
			$out['moduleMessage'] = "REDCap couldn't get authorization values from logged interview data -- please contact REDCap administrator.\n<br />$e";
		}
		
		$curlArgs = [];
		$curlArgs['headers'] = [
			"Accept: application/json",
			"Cookie: JSESSIONID=" . $authValues['jsessionid'] . "; AWSELB=" . $authValues['awselb']
		];
		$testAddress = "http://localhost/redcap/redcap_v8.10.2/ExternalModules/?prefix=cat_mh&page=testEndpoint&pid=" . $this->getProjectId() . "&action=" . __FUNCTION__;
		$curlArgs['address'] = $this->testAPI ? $testAddress : "https://www.cat-mh.com/interview/rest/interview/test/question";
		if ($this->debug) $out['curlArgs'] = $curlArgs;
		
		// send request via curl
		$curl = $this->curl($curlArgs);
		if ($this->debug) {
			$out['curl'] = $curl;
		} else {
			$out['curl'] = ["body" => $curl["body"]];
		}
		
		// handle response
		try {
			$json = json_decode($curl['body'], true);
			if (gettype($json) != 'array') throw new \Exception("json error");
			$out['success'] = true;
			if ($json['questionID'] < 0) {
				$out['needResults'] = true;
			}
		} catch (\Exception $e) {
			$out['moduleError'] = true;
			$out['moduleMessage'] = "REDCap failed to retrieve the next question from the CAT-MH API server.";
		}
		return $out;
	}
	
	public function submitAnswer($args) {
		// need args: JSESSIONID, AWSELB, questionID, response, duration
		$out = [];
		if ($this->debug) $out['args'] = $args;
		
		try {
			$authValues = $this->getAuthValues($args);
			if (!isset($authValues['jsessionid']) or !isset($authValues['awselb'])) {
				throw new \Exception("Auth values not set.");
			}
		} catch (\Exception $e) {
			$out['moduleError'] = true;
			$out['moduleMessage'] = "REDCap couldn't get authorization values from logged interview data -- please contact REDCap administrator.\n<br />$e";
		}
		
		// build request headers and body
		$curlArgs = [];
		$curlArgs['headers'] = [
			"Content-Type: application/json",
			"Cookie: JSESSIONID=" . $authValues['jsessionid'] . "; AWSELB=" . $authValues['awselb']
		];
		$curlArgs['body'] = json_encode([
			"questionID" => intval($args['questionID']),
			"response" => intval($args['response']),
			"duration" => intval($args['duration']),
			"curT1" => 0,
			"curT2" => 0,
			"curT3" => 0
		]);
		$args['questionID'] = null;
		$args['response'] = null;
		$args['duration'] = null;
		
		$curlArgs['post'] = true;
		$testAddress = "http://localhost/redcap/redcap_v8.10.2/ExternalModules/?prefix=cat_mh&page=testEndpoint&pid=" . $this->getProjectId() . "&action=" . __FUNCTION__;
		$curlArgs['address'] = $this->testAPI ? $testAddress : "https://www.cat-mh.com/interview/rest/interview/test/question";
		if ($this->debug) $out['curlArgs'] = $curlArgs;
		
		// send request via curl
		$curl = $this->curl($curlArgs);
		if ($this->debug) $out['curl'] = $curl;
		
		if ($curl['info']['http_code'] == 200) {
			$out['success'] = true;
		} else {
			$out['moduleError'] = true;
			$out['moduleMessage'] = "REDCap submitted answer but got back a non-OK response from the CAT-MH API server.";
		}
		return $out;
	}
	
	public function endInterview($args) {
		// need args: JSESSIONID, AWSELB
		$out = [];
		if ($this->debug) $out['args'] = $args;
		
		try {
			$authValues = $this->getAuthValues($args);
			if (!isset($authValues['jsessionid']) or !isset($authValues['awselb'])) {
				throw new \Exception("Auth values not set.");
			}
		} catch (\Exception $e) {
			$out['moduleError'] = true;
			$out['moduleMessage'] = "REDCap couldn't get authorization values from logged interview data -- please contact REDCap administrator.\n<br />$e";
		}
		
		$curlArgs = [];
		$curlArgs['headers'] = [
			"Accept: application/json",
			"Cookie: JSESSIONID=" . $authValues['jsessionid'] . "; AWSELB=" . $authValues['awselb']
		];
		$testAddress = "http://localhost/redcap/redcap_v8.10.2/ExternalModules/?prefix=cat_mh&page=testEndpoint&pid=" . $this->getProjectId() . "&action=" . __FUNCTION__;
		$curlArgs['address'] = $this->testAPI ? $testAddress : "https://www.cat-mh.com/interview/signout";
		if ($this->debug) $out['curlArgs'] = $curlArgs;
		
		// send request via curl
		$curl = $this->curl($curlArgs);
		if ($this->debug) $out['curl'] = $curl;
		
		// handle response
		try {
			if ($curl['cookies']['JSESSIONID'] == $authValues['JSESSIONID'] and $curl['info']['http_code'] == 302) {
				// update redcap record data
				$data = $this->getRecordBySID($args['subjectID']);
				$rid = array_keys($data)[0];
				$record = $data[$rid];
				$eid = array_keys($record)[0];
				$catmh_data = json_decode($record[$eid], true);
				
				foreach($catmh_data['interviews'] as $i => $interview) {
					if ($interview['interviewID'] == $args['interviewID'] and $interview['signature'] == $args['signature'] and $interview['identifier'] == $args['identifier']) {
						$interview['status'] = 3;
						$interview['timestamp'] = time();
					}
				}
				
				$data[$rid][$eid]['cat_mh_data'] = json_encode($catmh_data);
				$result = \REDCap::saveData($this->getProjectId(), 'array', $data);
				if (!empty($result['errors'])) {
					$out['moduleError'] = true;
					$out['moduleMessage'] = "Errors saving to REDCap:" . print_r($result, true);
				} else {
					$out['success'] = true;
				}
			}
		} catch (\Exception $e) {
			$out['moduleError'] = true;
			$out['moduleMessage'] = "REDCap failed to end the interview via the CAT-MH API server.";
		}
		return $out;
	}
	
	public function getResults($args) {
		// need args: JSESSIONID, AWSELB
		$out = [];
		if ($this->debug) $out['args'] = $args;
		
		try {
			$authValues = $this->getAuthValues($args);
			if (!isset($authValues['jsessionid']) or !isset($authValues['awselb'])) {
				throw new \Exception("Auth values not set.");
			}
		} catch (\Exception $e) {
			$out['moduleError'] = true;
			$out['moduleMessage'] = "REDCap couldn't get authorization values from logged interview data -- please contact REDCap administrator.\n<br />$e";
		}
		
		$curlArgs = [];
		$curlArgs['headers'] = [
			"Accept: application/json",
			"Cookie: JSESSIONID=" . $authValues['jsessionid'] . "; AWSELB=" . $authValues['awselb']
		];
		$testAddress = "http://localhost/redcap/redcap_v8.10.2/ExternalModules/?prefix=cat_mh&page=testEndpoint&pid=" . $this->getProjectId() . "&action=" . __FUNCTION__;
		$curlArgs['address'] = $this->testAPI ? $testAddress : "https://www.cat-mh.com/interview/rest/interview/results?itemLevel=1";
		if ($this->debug) $out['curlArgs'] = $curlArgs;
		
		// send request via curl
		$curl = $this->curl($curlArgs);
		if ($this->debug) {
			$out['curl'] = $curl;
		} else {
			$out['curl'] = ["body" => $curl["body"]];
		}
		
		// decode curl body
		$results = json_decode($curl['body'], true);
		
		// update redcap record data
		$data = $this->getRecordBySID($args['subjectID']);
		$rid = array_keys($data)[0];
		$eid = array_keys($data[$rid])[0];
		$catmh_data = json_decode($data[$rid][$eid]['cat_mh_data'], true);
		
		foreach($catmh_data['interviews'] as $i => $interview) {
			if ($interview['interviewID'] == $args['interviewID'] and $interview['signature'] == $args['signature'] and $interview['identifier'] == $args['identifier']) {
				$catmh_data['interviews'][$i]['results'] = $results;
				$catmh_data['interviews'][$i]['status'] = 4;
				$catmh_data['interviews'][$i]['timestamp'] = time();
				$sequence = $catmh_data['interviews'][$i]['sequence'];
				$testTypes = $catmh_data['interviews'][$i]['types'];
			}
		}
		
		$data[$rid][$eid]['cat_mh_data'] = json_encode($catmh_data);
		$result = \REDCap::saveData($this->getProjectId(), 'array', $data);
		if (!empty($result['errors'])) {
			$out['moduleError'] = true;
			$out['moduleMessage'] = "Errors saving to REDCap:" . print_r($result, true);
			return $out;
		}
		
		// need config to see if we should send results back to user or not
		$keepResults = [];
		$projectSettings = $this->getProjectSettings();
		foreach ($projectSettings['sequence']['value'] as $j => $seqName) {
			if ($sequence == $seqName) {
				foreach($testTypes as $testType) {
					if ($projectSettings[$testType . '_show_results']['value'][$j] == 1) {
						$keepResults[$testType] = true;
					}
				}
				break;
			}
		}
		
		// now remove results from curl response as necessary
		foreach ($results['tests'] as &$test) {
			$abbreviation = strtolower($test['type']);
			if ($keepResults[$abbreviation] !== true) {
				$test['diagnosis'] = "The results for this test have been saved in REDCap for your test provider to review.";
				$test['confidence'] = null;
				$test['severity'] = null;
				$test['category'] = null;
				$test['precision'] = null;
				$test['prob'] = null;
				$test['percentile'] = null;
			}
		}
		
		// handle response
		try {
			$json = json_decode($curl['body'], true);
			if ($json['interviewId'] > 0) {
				$out['success'] = true;
				$out['results'] = json_encode($results);
				$out['keepResults'] = json_encode($keepResults);
			} else {
				throw new \Exception("bad or no json");
			}
		} catch (\Exception $e) {
			$out['moduleError'] = true;
			$out['moduleMessage'] = "REDCap failed to retrieve test results via the CAT-MH API server.";
		}
		
		return $out;
	}
	
	public function getInterviewStatus($args) {
		// need args: applicationid, organizationID, interviewID, identifier, signature
		$out = [];
		if ($this->debug) $out['args'] = $args;
		
		// get project/system configuration information
		$config = $this->getInterviewConfig($args['instrument']);
		if ($this->debug) $out['config'] = $config;
		
		if ($interviewConfig === false) {
			$out['moduleError'] = true;
			$out['moduleMessage'] = "Failed to create interview -- couldn't find interview settings for this instrument: " . $args['instrument'];
			return $out;
		};
		
		// build request headers and body
		$curlArgs = [];
		$curlArgs['headers'] = [
			"applicationid: " . $config['applicationid'],
			"Accept: application/json",
			"Content-Type: application/json"
		];
		$curlArgs['body'] = [
			"organizationID" => intval($config['organizationid']),
			"interviewID" => intval($args['interviewID']),
			"identifier" => $args['identifier'],
			"signature" => $args['signature']
		];
		$curlArgs['post'] = true;
		$testAddress = "http://localhost/redcap/redcap_v8.10.2/ExternalModules/?prefix=cat_mh&page=testEndpoint&pid=" . $this->getProjectId() . "&action=" . __FUNCTION__;
		$curlArgs['address'] = $this->testAPI ? $testAddress : "https://www.cat-mh.com/portal/secure/interview/status";
		
		if ($this->debug) $out['curlArgs'] = $curlArgs;
		
		// send request via curl
		$curl = $this->curl($curlArgs);
		if ($this->debug) $out['curl'] = $curl;
		
		// handle response
		try {
			$json = json_decode($curl['body'], true);
			if (gettype($json) == 'array') {
				$out['success'] = true;
			} else {
				throw new \Exception("bad or no json in curl body response");
			}
		} catch (\Exception $e) {
			$out['moduleError'] = true;
			$out['moduleMessage'] = "REDCap failed to get interview status from the CAT-MH API server.";
		}
		return $out;
	}
	
	public function breakLock($args) {
		// need args: JSESSONID, AWSELB
		$out = [];
		if ($this->debug) $out['args'] = $args;
		
		try {
			$authValues = $this->getAuthValues($args);
			if (!isset($authValues['jsessionid']) or !isset($authValues['awselb'])) {
				throw new \Exception("Auth values not set.");
			}
		} catch (\Exception $e) {
			$out['moduleError'] = true;
			$out['moduleMessage'] = "REDCap couldn't get authorization values from logged interview data -- please contact REDCap administrator.\n<br />$e";
		}
		
		// build request headers and body
		$curlArgs = [];
		$curlArgs['headers'] = [
			"Cookie: JSESSIONID=" . $authValues['jsessionid'] . "; AWSELB=" . $authValues['awselb']
		];
		$curlArgs['body'] = [];
		$curlArgs['post'] = true;
		$testAddress = "http://localhost/redcap/redcap_v8.10.2/ExternalModules/?prefix=cat_mh&page=testEndpoint&pid=" . $this->getProjectId() . "&action=" . __FUNCTION__;
		$curlArgs['address'] = $this->testAPI ? $testAddress : "https://www.cat-mh.com/interview/secure/breakLock";
		if ($this->debug) $out['curlArgs'] = $curlArgs;
		
		// send request via curl
		$curl = $this->curl($curlArgs);
		if ($this->debug) $out['curl'] = $curl;
		
		// get location
		preg_match_all('/^Location:\s([^\n]*)$/m', $curl['response'], $matches);
		$location = trim($matches[1][0]);
		if ($this->debug) $out['location'] = trim($matches[1][0]);
		
		if ($curl['info']['http_code'] == 302 and $location == "https://www.cat-mh.com/interview/secure/index.html") {
			$out['success'] = true;
		} else {
			$out['moduleError'] = true;
			$out['moduleMessage'] = "This interview is locked and REDCap was unable to break the lock via the CAT-MH API.";
		}
		
		return $out;
	}
	
}

if ($_SERVER['REQUEST_METHOD'] == "POST") {
	$catmh = new CAT_MH();
	$json = json_decode(file_get_contents("php://input"), true);
	if (isset($json['args']['interviewID'])) $json['args']['interviewID'] = db_escape($json['args']['interviewID']);
	if (isset($json['args']['subjectID'])) $json['args']['subjectID'] = db_escape($json['args']['subjectID']);
	if (isset($json['args']['instrument'])) $json['args']['instrument'] = db_escape($json['args']['instrument']);
	if (isset($json['args']['recordID'])) $json['args']['recordID'] = db_escape($json['args']['recordID']);
	if (isset($json['args']['identifier'])) $json['args']['identifier'] = db_escape($json['args']['identifier']);
	if (isset($json['args']['signature'])) $json['args']['signature'] = db_escape($json['args']['signature']);
	if (isset($json['args']['questionID'])) $json['args']['questionID'] = db_escape($json['args']['questionID']);
	if (isset($json['args']['response'])) $json['args']['response'] = db_escape($json['args']['response']);
	if (isset($json['args']['duration'])) $json['args']['duration'] = db_escape($json['args']['duration']);
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
	$action = db_escape($json['action']);
	switch ($action) {
		// case 'createInterview':
			// $out['receivedJson'] = json_encode($json);
			// $out = $catmh->createInterview($json['args']);
			// echo json_encode($out);
			// break;
		case 'authInterview':
			$out['receivedJson'] = json_encode($json);
			$out = $catmh->authInterview($json['args']);
			echo json_encode($out);
			break;
		case 'startInterview':
			$out['receivedJson'] = json_encode($json);
			$out = $catmh->startInterview($json['args']);
			echo json_encode($out);
			break;
		case 'getQuestion':
			$out['receivedJson'] = json_encode($json);
			$out = $catmh->getQuestion($json['args']);
			echo json_encode($out);
			break;
		case 'submitAnswer':
			$out['receivedJson'] = json_encode($json);
			$out = $catmh->submitAnswer($json['args']);
			echo json_encode($out);
			break;
		case 'endInterview':
			$out['receivedJson'] = json_encode($json);
			$out = $catmh->endInterview($json['args']);
			echo json_encode($out);
			break;
		case 'getResults':
			$out['receivedJson'] = json_encode($json);
			$out = $catmh->getResults($json['args']);
			echo json_encode($out);
			break;
		case 'getInterviewStatus':
			$out['receivedJson'] = json_encode($json);
			$out = $catmh->getInterviewStatus($json['args']);
			echo json_encode($out);
			break;
		case 'breakLock':
			$out['receivedJson'] = json_encode($json);
			$out = $catmh->breakLock($json['args']);
			echo json_encode($out);
			break;
	}
}