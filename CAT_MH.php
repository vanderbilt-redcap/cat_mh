<?php
namespace VICTR\REDCAP\CAT_MH;

class CAT_MH extends \ExternalModules\AbstractExternalModule {
	public $testAPI = true;
	
	// hooks
	public function redcap_survey_complete($project_id, $record, $instrument, $event_id, $group_id, $survey_hash, $response_id, $repeat_instance) {
		// provide button for user to click to send them to interview page after they've read the last page of the survey submission document
		$input = [];
		$input['instrument'] = $instrument;
		$input['recordID'] = $record;
		$out = $this->createInterviews($input);
		
		if ($out !== false) {
			if ($instrument == $out['config']['instrumentRealName']) {
				echo("Click to begin your CAT-MH screening interview.<br />");
				$page = $this->getUrl("interview.php") . "&rid=" . $record . "&sid=" . $subjectID;
				echo("
				<button id='catmh_button'>Begin Interview</button>
				<script>
					var btn = document.getElementById('catmh_button')
					btn.addEventListener('click', function() {
						window.location.assign('$page')
					})
				</script>");
			} else {
				echo("There was an error in creating your CAT-MH interview:<br />");
				if (isset($out['moduleError'])) echo($out['moduleMessage'] . "<br />");
				echo("Please contact your REDCap system administrator.");
			}
		}
	}
	
	// utility
	public function getInterviewConfig($instrumentName) {
		// given the instrument name, we create and return an array that we will turn into JSON and send to CAT-MH to request createInterviews
		$config = [];
		$pid = $this->getProjectId();
		$projectSettings = $this->getProjectSettings();
		$result = $this->query('select form_name, form_menu_description from redcap_metadata where form_name="' . $instrumentName . '" and project_id=' . $pid . ' and form_menu_description<>""');
		$record = db_fetch_assoc($result);
		foreach ($projectSettings['survey_instrument']['value'] as $settingsIndex => $instrumentDisplayName) {
			if ($instrumentDisplayName == $record['form_menu_description']) {
				// create random subject ID
				$subjectID = "";
				$sidDomain = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ1234567890";
				$domainLength = strlen($sidDomain);
				for ($i = 0; $i < 32; $i++) {
					$subjectID .= $sidDomain[rand(0, $domainLength - 1)];
				}
				
				// tests array
				$tests = []
				$testTypes = ['mdd', 'dep', 'anx', 'mhm', 'pdep', 'panx', 'pmhm', 'sa', 'ptsd', 'cssrs', 'ss'];
				foreach ($testTypes as $j => $testAbbreviation) {
					if ($projectSettings[$testAbbreviation]['value'][$settingsIndex] == 1) {
						$tests[] = ["type" => $testAbbreviation];
					}
				}
				
				$config['subjectID'] = $subjectID;
				$config['tests'] = $tests;
				$config['organizationid'] = $this->getSystemSetting('organizationid');
				$config['applicationid'] = $this->getSystemSetting('applicationid');
				$config['instrumentDisplayName'] = $instrumentDisplayName;
				$config['instrumentRealName'] = $record['form_name'];
				$config['language'] = $projectSettings['language']['value'][$settingsIndex] == 2 ? 2 : 1;
				return $config;
			}
		}
		return false;
	}
	
	// CAT-MH API methods
	public function createInterviews($args) {
		// args needed: instrument, recordID
		$out = [];
		$interviewConfig = $this->getInterviewConfig($args['instrument']);
		$out['config'] = $interviewConfig;
		if ($interviewConfig === false) return false;
		
		// build request headers and body
		$requestHeaders = [
			"applicationid: " . $interviewConfig['applicationid'],
			"Accept: application/json",
			"Content-Type: application/json"
		];
		$requestBody = [
			"organizationID" => intval($interviewConfig['organizationid']),
			"userFirstName" => "Automated",
			"userLastName" => "Creation",
			"subjectID" => $interviewConfig['subjectID'],
			"numberOfInterviews" => 1,
			"language" => intval($interviewConfig['language']),
			"tests" => $interviewConfig['tests']
		];
		
		// send request via curl
		$ch = curl_init();
		$testAddress = "http://localhost/redcap/redcap_v8.10.2/ExternalModules/?prefix=cat_mh&page=testEndpoint&pid=" . $this->getProjectId() . "&action=createInterviews";
		$address = $this->testAPI ? $testAddress : "https://www.cat-mh.com/portal/secure/interview/createInterview";
		curl_setopt($ch, CURLOPT_URL, $address);
		curl_setopt($ch, CURLOPT_HTTPHEADER, $requestHeaders);
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($requestBody));
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_VERBOSE, true);
		$out['response'] = curl_exec($ch);
		$out['errorNumber'] = curl_errno($ch);
		$out['error'] = curl_error($ch);
		$out['info'] = curl_getinfo($ch);
		curl_close ($ch);
		
		// handle response
		$response = json_decode($out['response'], true);
		try {
			foreach ($response['interviews'] as $interview) {
				$params = [
					'subjectID' => $interviewConfig['subjectID'],
					'timestamp' => time(),
					'instrument' => $args['instrument'],
					'recordID' =>  $args['recordID'],
					'interview' => json_encode($interview),
					'status' => 0
				];
				$this->log("createInterviews", $params);
			}
			$out['success'] = true;
		} catch (Exception $e) {
			$out['moduleError'] = true;
			$out['moduleMessage'] = "REDCap couldn't get interview information from CAT-MH API.";
		}
		return $out;
	}
	
	public function authInterview($args) {
		// args needed: subjectID, instrument, recordID, interview[identifier, signature, interviewID*]
		$out = [];
		
		// build request headers and body
		$requestBody = "j_username=" . $args['interview']['identifier'] . "&" .
			"j_password=" . $args['interview']['signature'] . "&" .
			"interviewID=" . $args['interview']['interviewID'];
		$requestHeaders = [
			"Content-Type: application/x-www-form-urlencoded"
		];
		
		// send request via curl
		$ch = curl_init();
		$testAddress = "http://localhost/redcap/redcap_v8.10.2/ExternalModules/?prefix=cat_mh&page=testEndpoint&pid=" . $this->getProjectId() . "&action=authInterview";
		$address = $this->testAPI ? $testAddress : "https://www.cat-mh.com/interview/signin";
		curl_setopt($ch, CURLOPT_URL, $address);
		curl_setopt($ch, CURLOPT_HTTPHEADER, $requestHeaders);
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $requestBody);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_VERBOSE, true);
		$out['response'] = curl_exec($ch);
		$out['errorNumber'] = curl_errno($ch);
		$out['error'] = curl_error($ch);
		$out['info'] = curl_getinfo($ch);
		curl_close ($ch);
		
		// get cookies and location from server response
		preg_match_all('/^Set-Cookie:\s*([^;]*)/mi', $out['response'], $matches);
		$out['cookies'] = array();
		foreach($matches[1] as $item) {
			parse_str($item, $cookie);
			$out['cookies'] = array_merge($out['cookies'], $cookie);
		}
		preg_match_all('/^Location:\s([^\n]*)$/m', $out['response'], $matches);
		$out['location'] = $matches[1][0];
		
		// handle response
		if ($out['info']['http_code'] == 302) {
			try {
				$out['AWSELB'] = $cookies['AWSELB'];
				$out['JSESSIONID'] = $cookies['JSESSIONID'];
				$this->removeLogs("where subjectID='" . $args['subjectID'] . "' and interview='" . json_encode($args['interview']) . "'");
				
				$args['interview']['JSESSIONID'] = $out['JSESSIONID'];
				$args['interview']['AWSELB'] = $out['AWSELB'];
				$params = [
					'subjectID' => $args['subjectID'],
					'timestamp' => time(),
					'instrument' => $args['instrument'],
					'recordID' =>  $args['recordID'],
					'interview' => json_encode($interview),
					'status' => 1
				];
				$this->log("authInterview", $params);
				$out['success'] = true;
			} catch (Exception $e) {
				$out['moduleError'] = true;
				$out['moduleMessage'] = "REDCap couldn't read authorization details from CAT-MH API server response.";
			}
		} elseif($out['location'] == 'https://www.cat-mh.com/interview/secure/errorInProgress.html' {
			$result = $this->breakLock($args);
			if ($result['success'] == true) {
				$out = $this->authInterview($args)
				$out['lockBreakSuccess'] = true;
				return $out;
			} else {
				$out['moduleError'] = true;
				$out['moduleMessage'] = "REDCap sent authorization request for an in-progress interview but failed to break the CAT-MH API's interview lock.";
			}
		} else {
			$out['moduleError'] = true;
			$out['moduleMessage'] = "REDCap failed to authorize the interview.";
		}
		return $out;
	}
	
	public function startInterview($args) {
		// args required: JSESSIONID, AWSELB
		$out = [];
		
		$requestHeaders = [
			"Accept: application/json",
			"Cookie: JSESSIONID=" . $args['JSESSIONID'] . "; AWSELB=" . $args['AWSELB']
		];
		// curl request
		$ch = curl_init();
		$testAddress = "http://localhost/redcap/redcap_v8.10.2/ExternalModules/?prefix=cat_mh&page=testEndpoint&pid=" . $this->getProjectId() . "&action=startInterview";
		$address = $this->testAPI ? $testAddress : "https://www.cat-mh.com/interview/rest/interview";
		curl_setopt($ch, CURLOPT_HTTPHEADER, $requestHeaders);
		curl_setopt($ch, CURLOPT_VERBOSE, true);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		$out['response'] = curl_exec($ch);
		$out['errorNumber'] = curl_errno($ch);
		$out['error'] = curl_error($ch);
		$out['info'] = curl_getinfo($ch);
		curl_close ($ch);
		
		// handle response
		$response = json_decode($out['response'], true);
		try {
			if ($response['id'] > 0) {
				$out['success'] = true;
				$out['shouldRequestFirstQuestion'] = true;
			} elseif (isset($response['id'])) {
				$out['success'] = true;
				$out['shouldSignOut'] = true;
			}
		} catch (Exception $e) {
			$out['moduleError'] = true;
			$out['moduleMessage'] = "REDCap failed to start the interview via the CAT-MH API.";
		}
		return $out;
	}
	
	public function getQuestion($args) {
		// args required: JSESSIONID, AWSELB
		$out = [];
		
		// build request headers and body
		$requestHeaders = [
			"Accept: application/json",
			"Cookie: JSESSIONID=" . $args['JSESSIONID'] . "; AWSELB=" . $args['AWSELB']
		];
		
		// curl request
		$ch = curl_init();
		$testAddress = "http://localhost/redcap/redcap_v8.10.2/ExternalModules/?prefix=cat_mh&page=testEndpoint&pid=" . $this->getProjectId() . "&action=getQuestion";
		$address = $this->testAPI ? $testAddress : "https://www.cat-mh.com/interview/rest/interview/test/question";
		curl_setopt($ch, CURLOPT_HTTPHEADER, $requestHeaders);
		curl_setopt($ch, CURLOPT_VERBOSE, true);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		$out['response'] = curl_exec($ch);
		$out['errorNumber'] = curl_errno($ch);
		$out['error'] = curl_error($ch);
		$out['info'] = curl_getinfo($ch);
		curl_close ($ch);
		
		// handle response
		$response = json_decode($out['response'], true);
		try {
			if ($response['questionID'] > 0) {
				$out['success'] = true;
				$out['question'] = $response;
			} elseif {
				// interview is over, retrieve results
				$out['success'] = true;
				$out['needResults'] = true;
			}
		} catch (Exception $e) {
			$out['moduleError'] = true;
			$out['moduleMessage'] = "REDCap failed to retrieve the next question from the CAT-MH API server.";
		}
		
		return $out;
	}
	
	public function submitAnswer($args) {
		// need args: JSESSIONID, AWSELB, questionID, response, duration
		$out = [];
		
		// build request headers and body
		$requestHeaders = [
			"Content-Type: application/json",
			"Cookie: JSESSIONID=" . $args['JSESSIONID'] . "; AWSELB=" . $args['AWSELB']
		];
		$requestBody = [
			"questionID" => $args['questionID'],
			"response" => $args['response'],
			"duration" => $args['duration'],
			"curT1" => 0,
			"curT2" => 0,
			"curT3" => 0
		];
		
		// curl request
		$ch = curl_init();
		$testAddress = "http://localhost/redcap/redcap_v8.10.2/ExternalModules/?prefix=cat_mh&page=testEndpoint&pid=" . $this->getProjectId() . "&action=submitAnswer";
		$address = $this->testAPI ? $testAddress : "https://www.cat-mh.com/interview/rest/interview/test/question";
		curl_setopt($ch, CURLOPT_HTTPHEADER, $requestHeaders);
		curl_setopt($ch, CURLOPT_VERBOSE, true);
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($requestBody));
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		$out['response'] = curl_exec($ch);
		$out['errorNumber'] = curl_errno($ch);
		$out['error'] = curl_error($ch);
		$out['info'] = curl_getinfo($ch);
		curl_close ($ch);
		
		// handle response
		try {
			if ($out['info']['http_code'] == 200) {
				// request next question
				$out['success'] = true;
			} else {
				throw new Exception('error');
			}
		} catch (Exception $e) {
			$out['moduleError'] = true;
			$out['moduleMessage'] = "REDCap submitted answer but got back a non-OK response from the CAT-MH API server.";
		}
		
		return $out;
	}
	
	public function endInterview($args) {
		// need args: JSESSIONID, AWSELB
		$out = [];
		
		// build request headers and body
		$requestHeaders = [
			"Cookie: JSESSIONID=" . $args['JSESSIONID'] . "; AWSELB=" . $args['AWSELB']
		];
		
		// curl request
		$ch = curl_init();
		$testAddress = "http://localhost/redcap/redcap_v8.10.2/ExternalModules/?prefix=cat_mh&page=testEndpoint&pid=" . $this->getProjectId() . "&action=endInterview";
		$address = $this->testAPI ? $testAddress : "https://www.cat-mh.com/interview/signout";
		curl_setopt($ch, CURLOPT_HEADER, true);
		curl_setopt($ch, CURLOPT_HTTPHEADER, $requestHeaders);
		curl_setopt($ch, CURLOPT_VERBOSE, true);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		$out['response'] = curl_exec($ch);
		$out['errorNumber'] = curl_errno($ch);
		$out['error'] = curl_error($ch);
		$out['info'] = curl_getinfo($ch);
		curl_close ($ch);
		
		// handle response
		preg_match_all('/^Set-Cookie:\s*([^;]*)/mi', $out['response'], $matches);
		$cookies = array();
		foreach($matches[1] as $item) {
			parse_str($item, $cookie);
			$cookies = array_merge($cookies, $cookie);
		}
		preg_match_all('/^Location:\s([^\n]*)$/m', $out['response'], $matches);
		
		$out['cookies'] = $cookies;
		$out['location'] = $matches[1][0];
		
		try {
			if ($out['info']['http_code'] == 302 and isset($cookies['JSESSIONID'])) {
				// successfully terminated interview
				$out['success'] = true;
			} else {
				throw new Exception('');
			}
		} catch (Exception $e) {
			// failure
			$out['moduleError'] = true;
			$out['moduleMessage'] = "REDCap failed to end the interview via the CAT-MH API server.";
		}
		
		return $out;
	}
	
	public function getResults($args) {
		// need args: JSESSIONID, AWSELB
		$out = [];
		
		// build request headers and body
		$requestHeaders = [
			"Accept: application/json",
			"Cookie: JSESSIONID=" . $args['JSESSIONID'] . "; AWSELB=" . $args['AWSELB']
		];
		
		// curl request
		$ch = curl_init();
		$testAddress = "http://localhost/redcap/redcap_v8.10.2/ExternalModules/?prefix=cat_mh&page=testEndpoint&pid=" . $this->getProjectId() . "&action=getResults";
		$address = $this->testAPI ? $testAddress : "https://www.cat-mh.com/interview/rest/interview/results?itemLevel=1";
		curl_setopt($ch, CURLOPT_HTTPHEADER, $requestHeaders);
		curl_setopt($ch, CURLOPT_VERBOSE, true);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		$out['response'] = curl_exec($ch);
		$out['errorNumber'] = curl_errno($ch);
		$out['error'] = curl_error($ch);
		$out['info'] = curl_getinfo($ch);
		curl_close ($ch);
		
		// handle response
		$response = json_decode($out['response'], true);
		try {
			if ($response['interviewId'] > 0) {
				$out['success'] = true;
			} else {
				throw new Exception ('response malformed');
			}
		} catch (Exception $e) {
			$out['moduleError'] = true;
			$out['moduleMessage'] = "REDCap failed to get results for CAT-MH interview.";
		}
		return $out;
	}
	
	public function getInterviewStatus($args) {
		// need args: JSESSONID, AWSELB
		$out = [];
		
		// build request headers and body
		$requestHeaders = [
			"Cookie: JSESSIONID=" . $args['JSESSIONID'] . "; AWSELB=" . $args['AWSELB']
		];
		
		// curl request
		$ch = curl_init();
		$testAddress = "http://localhost/redcap/redcap_v8.10.2/ExternalModules/?prefix=cat_mh&page=testEndpoint&pid=" . $this->getProjectId() . "&action=getInterviewStatus";
		$address = $this->testAPI ? $testAddress : "https://www.cat-mh.com/interview/signout";
		curl_setopt($ch, CURLOPT_HEADER, true);
		curl_setopt($ch, CURLOPT_HTTPHEADER, $requestHeaders);
		curl_setopt($ch, CURLOPT_VERBOSE, true);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		$out['response'] = curl_exec($ch);
		$out['errorNumber'] = curl_errno($ch);
		$out['error'] = curl_error($ch);
		$out['info'] = curl_getinfo($ch);
		curl_close ($ch);
		
		// handle response
		preg_match_all('/^Set-Cookie:\s*([^;]*)/mi', $out['response'], $matches);
		$cookies = array();
		foreach($matches[1] as $item) {
			parse_str($item, $cookie);
			$cookies = array_merge($cookies, $cookie);
		}
		preg_match_all('/^Location:\s([^\n]*)$/m', $out['response'], $matches);
		
		$out['cookies'] = $cookies;
		$out['location'] = $matches[1][0];
		
		try {
			if ($out['info']['http_code'] == 302 and isset($cookies['JSESSIONID'])) {
				$out['success'] = true;
			} else {
				throw new Exception("error");
			}
		} catch (Exception $e) {
			$out['moduleError'] = true;
			$out['moduleMessage'] = "REDCap failed to get interview status from the CAT-MH API server.";
		}
		
		return $out;
	}
	
	public function breakLock($args) {
		// need args: JSESSONID, AWSELB
		$out = [];
		
		// build request headers and body
		$requestBody = [];
		$requestHeaders = [
			"Cookie: JSESSIONID=" . $args['JSESSIONID'] . "; AWSELB=" . $args['AWSELB']
		];
		
		// curl request
		$ch = curl_init();
		$testAddress = "http://localhost/redcap/redcap_v8.10.2/ExternalModules/?prefix=cat_mh&page=testEndpoint&pid=" . $this->getProjectId() . "&action=breakLock";
		$address = $this->testAPI ? $testAddress : "https://www.cat-mh.com/interview/secure/breakLock";
		curl_setopt($ch, CURLOPT_HTTPHEADER, $requestHeaders);
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($requestBody));
		curl_setopt($ch, CURLOPT_VERBOSE, true);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		$out['response'] = curl_exec($ch);
		$out['errorNumber'] = curl_errno($ch);
		$out['error'] = curl_error($ch);
		$out['info'] = curl_getinfo($ch);
		curl_close ($ch);
		
		// handle response
		try {
			if ($out['info']['http_code'] == 302) {
				// interview lock broken
				$out['success'] = true;
			} else {
				throw new Exception("error");
			}
		} catch (Exception $e) {
			$out['moduleError'] = true;
			$out['moduleMessage'] = "This interview is locked and REDCap was unable to break the lock via the CAT-MH API.";
		}
		
		return $out;
	}
	
}