<?php

// to do:
// Change the external module config option for which survey to trigger from text to dropdown
// specify results field
// http://localhost/redcap/redcap_v8.10.2/ExternalModules/?prefix=cat_mh&page=test&pid=25

namespace VICTR\REDCAP\CAT_MH;

class CAT_MH extends \ExternalModules\AbstractExternalModule {
	
	public $logAPICalls = true;
	public $testAPI = true;
	
	public function __construct() {
		parent::__construct();
		// Other code to run when object is instantiated
	}
	
	
	public function redcap_survey_complete($project_id, $record, $instrument, $event_id, $group_id, $survey_hash, $response_id, $repeat_instance) {
		// provide button for user to click to send them to interview page after they've read the last page of the survey submission document
		$pid = $this->getProjectId();
		$input = [];
		$input['projectSettings'] = $this->getInterviewConfig($pid, $instrument);
		$subjectID = $input['projectSettings']['subjectID'];
		$input['subjectID'] = $subjectID ;
		$out = $this->createInterview($input);
		
		if ($instrument == $input['projectSettings']['instrumentRealName']) {
			if (isset($out['success'])) {
				$page = $this->getUrl("interview.php") . "&rid=" . $record . "&sid=" . $subjectID;
				echo("Click to begin your CAT-MH screening interview.<br />");
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
				if (isset($out['moduleError'])) echo($out['moduleError']);
				echo("<br />");
				echo("Please contact the REDCap system administration.");
			}
		}
	}
	
	// CAT-MH API methods
	public function createInterview($args) {
		$out = [];
		$projectSettings = $args['projectSettings'];
		$subjectID = $args['subjectID'];
		if ($projectSettings['organizationid'] == "") {
			$out['moduleMessage'] = "The 'organizationid' value is missing from the CAT-MH external module's system-level configuration settings. This is a required value for interview creation.";
		}
		if ($projectSettings['applicationid'] == "") {
			$out['moduleMessage'] = "The 'applicationid' value is missing from the CAT-MH external module's system-level configuration settings. This is a required value for interview creation.";
		}
		if ($projectSettings['language'] == "") {
			$out['moduleMessage'] = "The 'language' value is missing from the CAT-MH external module's project-level configuration settings. This is a required value for interview creation.";
		}
		if (empty($projectSettings['tests'])) {
			$out['moduleMessage'] = "The 'tests' array is empty in the CAT-MH external module's project-level configuration settings. Creating interview requires this array to be non-empty.";
		}
		if ($subjectID == "") {
			$out['moduleMessage'] = "The subjectID value is missing from the createInterview function call -- cannot proceed.";
		}
		if (isset($out['moduleMessage'])) {
			$out['moduleError'] = true;
			return $out;
		}
		
		// build request headers and body
		$requestHeaders = [
			"applicationid: " . $projectSettings['applicationid'],
			"Accept: application/json",
			"Content-Type: application/json"
		];
		$requestBody = [
			"organizationID" => intval($projectSettings['organizationid']),
			"userFirstName" => "Automated",
			"userLastName" => "Creation",
			"subjectID" => $subjectID,
			"numberOfInterviews" => 1,
			"language" => intval($projectSettings['language']),
			"tests" => []
		];
		foreach ($projectSettings['tests'] as $testAbbreviation) {
			$requestBody['tests'][] = ["type" => $testAbbreviation];
		}
		
		$out['requestHeaders'] = $requestHeaders;
		
		// send request via curl
		$ch = curl_init();
		if ($this->testAPI) {
			$requestBody['action'] = 'createInterview';
			curl_setopt($ch, CURLOPT_URL, "http://localhost/redcap/redcap_v8.10.2/ExternalModules/?prefix=cat_mh&page=testEndpoint&pid=" . $this->getProjectId());
		} else {
			curl_setopt($ch, CURLOPT_URL, "https://www.cat-mh.com/portal/secure/interview/createInterview");
		}
		curl_setopt($ch, CURLOPT_HTTPHEADER, $requestHeaders);
		curl_setopt($ch, CURLOPT_POST, true);
		$out['requestBody'] = $requestBody;
		$requestBody = json_encode($requestBody);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $requestBody);
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
			if ($response['interviews'][0]['interviewID'] > 0) {
				$json = json_encode($response['interviews']);
				if ($json !== false) {
					$this->log("createdInterviews", [
						'subjectID' => $subjectID,
						'timestamp' => time(),
						'interviews' => $json
					]);
					$out['success'] = true;
				}
			}
		} catch (Exception $e) {
			$out['moduleError'] = true;
			$out['moduleMessage'] = "CAT-MH module couldn't gather interviews array from CAT-MH API server response.";
		}
		
		// handle logging
		$this->logAPICall(__FUNCTION__, $args, $out);
		
		return $out;
	}
	
	public function authInterview($args) {
		$out = [];
		if (!isset($_SESSION['identifier'])) {
			$out['moduleMessage'] = "The CAT-MH module couldn't find an interview identifier in \$_SESSION array.";
		}
		if (!isset($_SESSION['signature'])) {
			$out['moduleMessage'] = "The CAT-MH module couldn't find an interview signature in \$_SESSION array.";
		}
		if (isset($out['moduleMessage'])) {
			$out['moduleError'] = true;
			return $out;
		}
		
		// build request headers and body
		$requestBody = "j_username=" . $_SESSION['identifier'] . "&" .
			"j_password=" . $_SESSION['signature'] . "&" .
			"interviewID=" . $_SESSION['interviewID'];
		$requestHeaders = [
			"Content-Type: application/x-www-form-urlencoded"
		];
		
		// curl request
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_HTTPHEADER, $requestHeaders);
		if ($this->testAPI) {
			$requestBody  .= "&action=" . __FUNCTION__;
			curl_setopt($ch, CURLOPT_URL, "http://localhost/redcap/redcap_v8.10.2/ExternalModules/?prefix=cat_mh&page=testEndpoint&pid=25");
		} else {
			curl_setopt($ch, CURLOPT_URL, "https://www.cat-mh.com/interview/signin");
		}
		// curl_setopt($ch, CURLINFO_HEADER_OUT, true);
		curl_setopt($ch, CURLOPT_HEADER, true);
		curl_setopt($ch, CURLOPT_VERBOSE, true);
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $requestBody);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		$out['response'] = curl_exec($ch);
		$out['errorNumber'] = curl_errno($ch);
		$out['error'] = curl_error($ch);
		$out['info'] = curl_getinfo($ch);
		curl_close ($ch);
		
		// get cookies and location from server response
		preg_match_all('/^Set-Cookie:\s*([^;]*)/mi', $out['response'], $matches);
		$cookies = array();
		foreach($matches[1] as $item) {
			parse_str($item, $cookie);
			$cookies = array_merge($cookies, $cookie);
		}
		
		preg_match_all('/^Location:\s([^\n]*)$/m', $out['response'], $matches);
		
		$out['cookies'] = $cookies;
		$out['location'] = $matches[1][0];
		
		if ($out['info']['http_code'] == 302) {
			if (isset($cookies['AWSELB'])) $_SESSION['AWSELB'] = $cookies['AWSELB'];
			if (isset($cookies['JSESSIONID'])) $_SESSION['JSESSIONID'] = $cookies['JSESSIONID'];
			$_SESSION['authResponse'] = $out['response'];	// holds startTime, id, interviewTests and more -- mostly useful as diagnostic or to confirm expected interview settings
			$out['success'] = true;
		} else {
			$out['moduleError'] = true;
			$out['moduleMessage'] = "CAT-MH module sent authorization request for interview but did not get a 302 in return.";
		}
		
		// handle logging
		$this->logAPICall(__FUNCTION__, $args, $out);
		
		return $out;
	}
	
	public function startInterview($args) {
		$out = [];
		if (!isset($_SESSION['JSESSIONID']) or !isset($_SESSION['AWSELB'])) {
			$out['moduleError'] = true;
			$out['moduleMessage'] = "The CAT-MH module can't start the interview because it's missing authorization values required by the CAT-MH API.";
			return $out;
		}
		
		// build request headers and body
		$requestHeaders = [
			"Accept: application/json",
			"Cookie: JSESSIONID=" . $_SESSION['JSESSIONID'] . "; AWSELB=" . $_SESSION['AWSELB']
		];
		
		// curl request
		$ch = curl_init();
		if ($this->testAPI) {
			curl_setopt($ch, CURLOPT_URL, "http://localhost/redcap/redcap_v8.10.2/ExternalModules/?prefix=cat_mh&page=testEndpoint&pid=25&action=startInterview");
		} else {
			curl_setopt($ch, CURLOPT_URL, "https://www.cat-mh.com/interview/rest/interview");
		}
		curl_setopt($ch, CURLOPT_HTTPHEADER, $requestHeaders);
		curl_setopt($ch, CURLOPT_VERBOSE, true);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		$out['response'] = curl_exec($ch);
		$out['errorNumber'] = curl_errno($ch);
		$out['error'] = curl_error($ch);
		$out['info'] = curl_getinfo($ch);
		curl_close ($ch);
		
		$response = json_decode($out['response'], true);
		if ($response['id'] > 0) {
			// request first question
			$out['success'] = 'true';
			$out['shouldRequestFirstQuestion'] = true;
		} else {
			// signout
			$out['needSignout'] = true;
		}
		
		// handle logging
		$this->logAPICall(__FUNCTION__, $args, $out);
		
		return $out;
	}
	
	public function getInterviewStatus($args) {
		$out = [];
		if (!isset($_SESSION['identifier'])) {
			$out['moduleMessage'] = "The CAT-MH module couldn't find an interview identifier in \$_SESSION array.";
		}
		if (!isset($_SESSION['signature'])) {
			$out['moduleMessage'] = "The CAT-MH module couldn't find an interview signature in \$_SESSION array.";
		}
		if (!isset($_SESSION['interviewID'])) {
			$out['moduleMessage'] = "The CAT-MH module couldn't find an interview ID in \$_SESSION array.";
		}
		if (!isset($args['projectSettings'])) {
			$out['moduleMessage'] = "The CAT-MH module found no project settings in call to function getInterviewStatus.";
		}
		if (isset($out['moduleMessage'])) {
			$out['moduleError'] = true;
			return $out;
		}
		
		$projectSettings = $args['projectSettings'];
		
		// build request headers and body
		$requestHeaders = [
			"applicationid: " . $projectSettings['applicationid'],
			"Accept: application/json",
			"Content-Type: application/json"
		];
		$requestBody = [
			"organizationID" => intval($projectSettings['organizationid']),
			"interviewID" => $_SESSION['interviewID'],
			"identifier" => $_SESSION['identifier'],
			"signature" => $_SESSION['signature']
		];
		
		// curl request
		$ch = curl_init();
		if ($this->testAPI) {
			$requestBody['action'] = __FUNCTION__;
			curl_setopt($ch, CURLOPT_URL, "http://localhost/redcap/redcap_v8.10.2/ExternalModules/?prefix=cat_mh&page=testEndpoint&pid=25");
		} else {
			curl_setopt($ch, CURLOPT_URL, "https://www.cat-mh.com/portal/secure/interview/status");
		}
		curl_setopt($ch, CURLOPT_HTTPHEADER, $requestHeaders);
		curl_setopt($ch, CURLOPT_VERBOSE, true);
		$requestBody = json_encode($requestBody);
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $requestBody);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		$out['response'] = curl_exec($ch);
		$out['errorNumber'] = curl_errno($ch);
		$out['error'] = curl_error($ch);
		$out['info'] = curl_getinfo($ch);
		curl_close ($ch);
		
		$json = json_decode($out['response'], true);
		if (isset($json['interviewValid'])) {
			// response is in $json
			$out['success'] = true;
		} else {
			$out['moduleError'] = true;
			$out['moduleMessage'] = "CAT-MH module sent interview status request but did not receive a response in return.";
		}
		
		// handle logging
		$this->logAPICall(__FUNCTION__, $args, $out);
		
		return $out;
	}
	
	public function getQuestion($args) {
		$out = [];
		if (!isset($_SESSION['JSESSIONID']) or !isset($_SESSION['AWSELB'])) {
			$out['moduleError'] = true;
			$out['moduleMessage'] = "The CAT-MH module can't start the interview because it's missing authorization values required by the CAT-MH API.";
			return $out;
		}
		
		// build request headers and body
		$requestHeaders = [
			"Accept: application/json",
			"Cookie: JSESSIONID=" . $_SESSION['JSESSIONID'] . "; AWSELB=" . $_SESSION['AWSELB']
		];
		
		// curl request
		$ch = curl_init();
		if ($this->testAPI) {
			curl_setopt($ch, CURLOPT_URL, "http://localhost/redcap/redcap_v8.10.2/ExternalModules/?prefix=cat_mh&page=testEndpoint&pid=25&action=getQuestion");
		} else {
			curl_setopt($ch, CURLOPT_URL, "https://www.cat-mh.com/interview/rest/interview/test/question");
		}
		curl_setopt($ch, CURLOPT_HTTPHEADER, $requestHeaders);
		curl_setopt($ch, CURLOPT_VERBOSE, true);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		$out['response'] = curl_exec($ch);
		$out['errorNumber'] = curl_errno($ch);
		$out['error'] = curl_error($ch);
		$out['info'] = curl_getinfo($ch);
		curl_close ($ch);
		
		$response = json_decode($out['response'], true);
		if ($response['questionID'] > 0) {
			// display this question
			$out['success'] = true;
		} else {
			echo("qid: <br />" . var_dump($response['questionID']));
			// interview is over, retrieve results
			$out['needResults'] = true;
		}
		
		// handle logging
		$this->logAPICall(__FUNCTION__, $args, $out);
		
		return $out;
	}
	
	public function submitAnswer($args) {
		$out = [];
		if (!isset($_SESSION['JSESSIONID']) or !isset($_SESSION['AWSELB'])) {
			$out['moduleMessage'] = "The CAT-MH module can't submit the answer because it's missing authorization values required by the CAT-MH API.";
		}
		foreach (['questionID', 'response', 'duration'] as $key) {
			if (!isset($args[$key])) $out['moduleMessage'] = "The CAT-MH module can't submit the answer because it's missing the $key.";
		}
		if (isset($out['moduleMessage'])) {
			$out['moduleError'] = true;
			return $out;
		}
		
		// build request headers and body
		$requestHeaders = [
			"Content-Type: application/json",
			"Cookie: JSESSIONID=" . $_SESSION['JSESSIONID'] . "; AWSELB=" . $_SESSION['AWSELB']
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
		if ($this->testAPI) {
			$requestBody['action'] = __FUNCTION__;
			curl_setopt($ch, CURLOPT_URL, "http://localhost/redcap/redcap_v8.10.2/ExternalModules/?prefix=cat_mh&page=testEndpoint&pid=25");
		} else {
			curl_setopt($ch, CURLOPT_URL, "https://www.cat-mh.com/interview/rest/interview/test/question");
		}
		curl_setopt($ch, CURLOPT_HTTPHEADER, $requestHeaders);
		curl_setopt($ch, CURLOPT_VERBOSE, true);
		$requestBody = json_encode($requestBody);
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $requestBody);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		$out['response'] = curl_exec($ch);
		$out['errorNumber'] = curl_errno($ch);
		$out['error'] = curl_error($ch);
		$out['info'] = curl_getinfo($ch);
		curl_close ($ch);
		
		if ($out['info']['http_code'] == 200) {
			// request next question
			$out['success'] = true;
		} else {
			$out['moduleError'] = true;
			$out['moduleMessage'] = "CAT-MH module sent authorization request for interview but did not get a 302 in return.";
		}
		
		// handle logging
		$this->logAPICall(__FUNCTION__, $args, $out);
		
		return $out;
	}
	
	public function getResults($args) {
		$out = [];
		if (!isset($_SESSION['JSESSIONID']) or !isset($_SESSION['AWSELB'])) {
			$out['moduleError'] = true;
			$out['moduleMessage'] = "The CAT-MH module can't start the interview because it's missing authorization values required by the CAT-MH API.";
			return $out;
		}
		
		// build request headers and body
		$requestHeaders = [
			"Accept: application/json",
			"Cookie: JSESSIONID=" . $_SESSION['JSESSIONID'] . "; AWSELB=" . $_SESSION['AWSELB']
		];
		
		// curl request
		$ch = curl_init();
		if ($this->testAPI) {
			curl_setopt($ch, CURLOPT_URL, "http://localhost/redcap/redcap_v8.10.2/ExternalModules/?prefix=cat_mh&page=testEndpoint&pid=25&action=getResults");
		} else {
			curl_setopt($ch, CURLOPT_URL, "https://www.cat-mh.com/interview/rest/interview/results?itemLevel=1");
		}
		curl_setopt($ch, CURLOPT_HTTPHEADER, $requestHeaders);
		curl_setopt($ch, CURLOPT_VERBOSE, true);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		$out['response'] = curl_exec($ch);
		$out['errorNumber'] = curl_errno($ch);
		$out['error'] = curl_error($ch);
		$out['info'] = curl_getinfo($ch);
		curl_close ($ch);
		
		$response = json_decode($out['response'], true);
		if (!empty($response) and gettype($response) == 'array') {
			// have results
			$out['success'] = true;
		} else {
			// failure
			$out['moduleError'] = true;
			$out['moduleMessage'] = "The CAT-MH module failed to retrieve results for this interview: " . $_SESSION['interviewID'];
		}
		
		// handle logging
		$this->logAPICall(__FUNCTION__, $args, $out);
		
		return $out;
	}
	
	public function endInterview($args) {
		$out = [];
		if (!isset($_SESSION['JSESSIONID']) or !isset($_SESSION['AWSELB'])) {
			$out['moduleError'] = true;
			$out['moduleMessage'] = "The CAT-MH module can't end the interview because it's missing authorization values required by the CAT-MH API.";
			return $out;
		}
		
		// build request headers and body
		$requestHeaders = [
			"Cookie: JSESSIONID=" . $_SESSION['JSESSIONID'] . "; AWSELB=" . $_SESSION['AWSELB']
		];
		
		// curl request
		$ch = curl_init();
		if ($this->testAPI) {
			curl_setopt($ch, CURLOPT_URL, "http://localhost/redcap/redcap_v8.10.2/ExternalModules/?prefix=cat_mh&page=testEndpoint&pid=25&action=endInterview");
		} else {
			curl_setopt($ch, CURLOPT_URL, "https://www.cat-mh.com/interview/signout");
		}
		curl_setopt($ch, CURLOPT_HEADER, true);
		curl_setopt($ch, CURLOPT_HTTPHEADER, $requestHeaders);
		curl_setopt($ch, CURLOPT_VERBOSE, true);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		$out['response'] = curl_exec($ch);
		$out['errorNumber'] = curl_errno($ch);
		$out['error'] = curl_error($ch);
		$out['info'] = curl_getinfo($ch);
		curl_close ($ch);
		
		preg_match_all('/^Set-Cookie:\s*([^;]*)/mi', $out['response'], $matches);
		$cookies = array();
		foreach($matches[1] as $item) {
			parse_str($item, $cookie);
			$cookies = array_merge($cookies, $cookie);
		}
		preg_match_all('/^Location:\s([^\n]*)$/m', $out['response'], $matches);
		
		$out['cookies'] = $cookies;
		$out['location'] = $matches[1][0];
		
		if ($out['info']['http_code'] == 302 and isset($cookies['JSESSIONID'])) {
			// successfully terminated interview
			$out['success'] = true;
		} else {
			// failure
			$out['moduleError'] = true;
			$out['moduleMessage'] = "The CAT-MH module failed to retrieve results for this interview: " . $_SESSION['interviewID'];
		}
		
		// handle logging
		$this->logAPICall(__FUNCTION__, $args, $out);
		
		return $out;
	}
	
	public function breakLock($args) {
		$out = [];
		if (!isset($_SESSION['JSESSIONID']) or !isset($_SESSION['AWSELB'])) {
			$out['moduleMessage'] = "The CAT-MH module can't submit the answer because it's missing authorization values required by the CAT-MH API.";
			$out['moduleError'] = true;
			return $out;
		}
		
		// build request headers and body
		$requestBody = [];
		$requestHeaders = [
			"Cookie: JSESSIONID=" . $_SESSION['JSESSIONID'] . "; AWSELB=" . $_SESSION['AWSELB']
		];
		
		// curl request
		$ch = curl_init();
		if ($this->testAPI) {
			$requestBody['action'] = __FUNCTION__;
			curl_setopt($ch, CURLOPT_URL, "http://localhost/redcap/redcap_v8.10.2/ExternalModules/?prefix=cat_mh&page=testEndpoint&pid=25");
		} else {
			curl_setopt($ch, CURLOPT_URL, "https://www.cat-mh.com/interview/secure/breakLock");
		}
		curl_setopt($ch, CURLOPT_HTTPHEADER, $requestHeaders);
		$requestBody = json_encode($requestBody);
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $requestBody);
		curl_setopt($ch, CURLOPT_VERBOSE, true);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		$out['response'] = curl_exec($ch);
		$out['errorNumber'] = curl_errno($ch);
		$out['error'] = curl_error($ch);
		$out['info'] = curl_getinfo($ch);
		curl_close ($ch);
		
		if ($out['info']['http_code'] == 302) {
			// interview lock broken
			$out['success'] = true;
		} else {
			$out['moduleError'] = true;
			$out['moduleMessage'] = "CAT-MH module tried to break the lock for the interview but did not get a 302 from the CAT-MH API.";
		}
		
		// handle logging
		$this->logAPICall(__FUNCTION__, $args, $out);
		
		return $out;
	}
	
	
	# auxilliary methods
	public function getInterviewConfig($pid, $instrumentName) {
		# given the instrument name, we create and return an array that we will turn into JSON and send to CAT-MH to request createInterview
		$interviewConfig = [];
		$projectSettings = $this->getProjectSettings();
		$result = $this->query('select form_name, form_menu_description from redcap_metadata where form_name="' . $instrumentName . '" and project_id=' . $pid . ' and form_menu_description<>""');
		$record = db_fetch_assoc($result);
		foreach ($projectSettings['survey_instrument']['value'] as $settingsIndex => $instrumentDisplayName) {
			if ($instrumentDisplayName == $record['form_menu_description']) {
				$interviewConfig['organizationid'] = $this->getSystemSetting('organizationid');
				$interviewConfig['applicationid'] = $this->getSystemSetting('applicationid');
				$interviewConfig['userFirstName'] = "Automated";
				$interviewConfig['userLastName'] = "Creation";
				
				# create random subject ID
				$sidDomain = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ1234567890";
				$domainLength = strlen($sidDomain);
				$subjectID = "";
				for ($i = 0; $i < 32; $i++) {
					$subjectID .= $sidDomain[rand(0, $domainLength - 1)];
				}
				
				$interviewConfig['subjectID'] = $subjectID;
				$interviewConfig['numberOfInterviews'] = 1;
				$interviewConfig['language'] = $projectSettings['language']['value'][$settingsIndex] == 2 ? 2 : 1;
				$interviewConfig['tests'] = [];
				$testTypes = ['mdd', 'dep', 'anx', 'mhm', 'pdep', 'panx', 'pmhm', 'sa', 'ptsd', 'cssrs', 'ss'];
				foreach ($testTypes as $j => $testAbbreviation) {
					if ($projectSettings[$testAbbreviation]['value'][$settingsIndex] == 1) {
						$interviewConfig['tests'][] = $testAbbreviation;
					}
				}
				
				# store instrument display name to compare at end of survey
				$interviewConfig['instrumentDisplayName'] = $instrumentDisplayName;
				$interviewConfig['instrumentRealName'] = $record['form_name'];
				
				return $interviewConfig;
			}
		}
	}
	
	public function logAPICall($functionName, $args, $out) {
		if ($this->logAPICalls === true) {
			$this->log("CAT-MH external module called API function $functionName()", [
				"function args" => json_encode($args),
				"function output" => json_encode($out)
			]);
		}
	}
}

if (!$catmh) {
	$catmh = new CAT_MH();
}

if ($_GET['action'] == 'create') {
	
}