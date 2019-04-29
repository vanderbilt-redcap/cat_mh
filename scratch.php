<?php
	// hooks
	public function redcap_survey_complete($project_id, $record, $instrument, $event_id, $group_id, $survey_hash, $response_id, $repeat_instance) {
		// if user did not consent, then do not forward to interview, instead notify them that they were rejected and that they may now close this window
		$userConsent = \REDCap::getData($project_id, 'array', $record, 'consent')[$record][$event_id]['consent'];
		if ($userConsent != 1) {
			echo("You did not consent to the adaptive testing interview and may now close this tab/window.");
			// delete record that REDCap created
			$tpk = \Records::getTablePK($module->getProjectId());
			$ret = \Records::deleteRecord($record, $tpk, null, null, null, null, null, "CAT-MH module removed record for consent==0", true);
			return;
		}
		
		// generate subject ID
		
		// determine if this survey is configured to forward to cat-mh testing interview
		$input = [];
		$input['project_id'] = $project_id;
		$input['event_id'] = $event_id;
		$input['instrument'] = $instrument;
		$input['recordID'] = $record;
		
		$out = $this->createInterviews($input);
		
		if (isset($out['moduleError'])) $this->log('catmhError', ['output' => json_encode($out)]);
		
		if ($out['success'] == true) {
			// redirect
			$page = $this->getUrl("interview.php") . "&NOAUTH&sid=" . $out['config']['subjectID'];
			
			// save newly created subjectID
			$data = [
				$record => [
					$event_id => [
						"subjectid" => $out['config']['subjectID']
					]
				]
			];
			
			\REDCap::saveData($project_id, 'array', $data);
			
			header('Location: ' . $page, true, 302);
		}
	}
	
	// utility
	public function getAuthValues($args) {
		$result = $this->queryLogs("select JSESSIONID, AWSELB where subjectID='{$args['subjectID']}' and interviewID={$args['interviewID']}");
		$values = db_fetch_assoc($result);
		if (isset($values['JSESSIONID']) and isset($values['AWSELB'])) return $values;
		return false;
	}
	
	public function getInterviewConfig($instrumentName) {
		// given the instrument name, we create and return an array that we will turn into JSON and send to CAT-MH to request createInterviews
		$config = [];
		$pid = $this->getProjectId();
		$projectSettings = $this->getProjectSettings();
		$result = $this->query('select form_name, form_menu_description from redcap_metadata where form_name="' . $instrumentName . '" and project_id=' . $pid . ' and form_menu_description<>""');
		$record = db_fetch_assoc($result);
		foreach ($projectSettings['survey_instrument']['value'] as $settingsIndex => $instrumentRealName) {
			if ($instrumentRealName == $record['form_name']) {
				
				
				// tests array
				$tests = [];
				$testTypeKeys = array_keys($this->convertTestAbbreviation);
				foreach ($testTypeKeys as $j => $testAbbreviation) {
					if ($projectSettings[$testAbbreviation]['value'][$settingsIndex] == 1) {
						$tests[] = ["type" => $this->convertTestAbbreviation[$testAbbreviation]];
					}
				}
				
				$config['subjectID'] = $subjectID;
				$config['tests'] = $tests;
				$config['organizationid'] = $this->getSystemSetting('organizationid');
				$config['applicationid'] = $this->getSystemSetting('applicationid');
				// $config['instrumentDisplayName'] = $instrumentDisplayName;
				$config['instrumentRealName'] = $record['form_name'];
				$config['language'] = $projectSettings['language']['value'][$settingsIndex] == 2 ? 2 : 1;
				return $config;
			}
		}
		return false;
	}
	
	public function getNextInterview($args) {
		// get patient data
		
		
		
		$subjectID = $args['subjectID'];
		$result = $this->queryLogs("select subjectID, recordID, interviewID, status, instrument, identifier, signature, types, labels
			where subjectID='$subjectID'");
		// don't query for auth details, as this interview info goes back to client
		$interview = db_fetch_assoc($result);
		if (gettype($interview) == "array") return $interview;
		return false;
	}
	
	public function createPatientRecord($args, $json, $interviewConfig, $types, $labels) {
		// create the CAT-MH specific portion of the record and encode to json
		$catmh_data = json_encode([
			"tests" => [
				$args['instrument'] => [
					"types" => $types,
					"labels" => $labels
				]
			],
			"interviews" => [
				"interviewID" => $json['interviews'][0]["interviewID"],
				"identifier" => $json['interviews'][0]["identifier"],
				"signature" => $json['interviews'][0]["signature"],
				"status" => "created",
				"tstamp" => time(),
				"interview_link" => $this->getUrl("interview.php") . "&NOAUTH&sid=" . $interviewConfig['subjectID'],
				"instrument" => $args['instrument']
			]
		]);
		
		$newPatient = [
			$args['recordID'] => [
				$args['event_id'] => [
					'cat_mh_data' => $catmh_data
				]
			]
		];
		
		$result = \REDCap::saveData($args['project_id'], 'array', $newPatient);
		if (!empty($result['errors'])) throw new \Exception("REDCap gave errors when trying to save new patient info:\n<br />" . print_r($result, true));
		return $result;
	}

	//api
	// CAT-MH API methods
	public function createInterview($args) {
		// args needed: instrument, recordID
		$out = [];
		if ($this->debug) $out['args'] = $args;
		
		// get project/system configuration information
		$interviewConfig = $this->getInterviewConfig($args['instrument']);
		if ($this->debug) {
			$out['config'] = $interviewConfig;
		} else {
			$out['config'] = [
				"subjectID" => $interviewConfig['subjectID'],
				"instrumentRealName" => $interviewConfig['instrumentRealName']
			];
		}
		
		if ($interviewConfig === false) {
			$out['moduleError'] = true;
			$out['moduleMessage'] = "Failed to create interview -- couldn't find interview settings for this instrument: " . $args['instrument'];
			return $out;
		};
		
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
			foreach ($interviewConfig['tests'] as $arr) {
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
		// args needed: subjectID, instrument, recordID, identifier, signature, interviewID
		$args['recordID'] = intval($args['recordID']);
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
			$this->removeLogs("subjectID='{$args['subjectID']}' and interviewID={$args['interviewID']}");
			$args['JSESSIONID'] = $curl['cookies']['JSESSIONID'];
			$args['AWSELB'] = $curl['cookies']['AWSELB'];
			$args['tstamp'] = time();
			$args['status'] = 1;
			$args['types'] = json_encode($args['types'], JSON_UNESCAPED_SLASHES);
			$args['labels'] = json_encode($args['labels'], JSON_UNESCAPED_SLASHES);
			$this->log("authInterview", $args);
			$out['success'] = true;
		} else {
			$out['moduleError'] = true;
			$out['moduleMessage'] = "REDCap failed to retrieve authorization details from the CAT-MH API server for the interview.";
		}
		
		return $out;
	}
	
	public function startInterview($args) {
		// args required: subjectID, interviewID
		$out = [];
		if ($this->debug) $out['args'] = $args;
		
		$authValues = $this->getAuthValues($args);
		if ($authValues === false) {
			$out['moduleError'] = true;
			$out['moduleMessage'] = "REDCap couldn't get authorization values from logged interview data -- please contact REDCap administrator.";
		}
		
		$curlArgs = [];
		$curlArgs['headers'] = [
			"Accept: application/json",
			"Cookie: JSESSIONID=" . $authValues['JSESSIONID'] . "; AWSELB=" . $authValues['AWSELB']
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
			$out['success'] = true;
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
		
		$authValues = $this->getAuthValues($args);
		if ($authValues === false) {
			$out['moduleError'] = true;
			$out['moduleMessage'] = "REDCap couldn't get authorization values from logged interview data -- please contact REDCap administrator.";
		}
		
		$curlArgs = [];
		$curlArgs['headers'] = [
			"Accept: application/json",
			"Cookie: JSESSIONID=" . $authValues['JSESSIONID'] . "; AWSELB=" . $authValues['AWSELB']
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
		
		$authValues = $this->getAuthValues($args);
		if ($authValues === false) {
			$out['moduleError'] = true;
			$out['moduleMessage'] = "REDCap couldn't get authorization values from logged interview data -- please contact REDCap administrator.";
		}
		
		// build request headers and body
		$curlArgs = [];
		$curlArgs['headers'] = [
			"Content-Type: application/json",
			"Cookie: JSESSIONID=" . $authValues['JSESSIONID'] . "; AWSELB=" . $authValues['AWSELB']
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
		
		$authValues = $this->getAuthValues($args);
		if ($authValues === false) {
			$out['moduleError'] = true;
			$out['moduleMessage'] = "REDCap couldn't get authorization values from logged interview data -- please contact REDCap administrator.";
		}
		
		$curlArgs = [];
		$curlArgs['headers'] = [
			"Accept: application/json",
			"Cookie: JSESSIONID=" . $authValues['JSESSIONID'] . "; AWSELB=" . $authValues['AWSELB']
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
				$this->removeLogs("subjectID='" . $args['subjectID'] . "' and interviewID=" . $args['interviewID']);
				$args['tstamp'] = time();
				$args['status'] = 2;
				$args['types'] = json_encode($args['types'], JSON_UNESCAPED_SLASHES);
				$args['labels'] = json_encode($args['labels'], JSON_UNESCAPED_SLASHES);
				// put auth values in db as well
				$this->log("endInterview", array_merge($authValues, $args));
				$out['success'] = true;
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
		
		$authValues = $this->getAuthValues($args);
		if ($authValues === false) {
			$out['moduleError'] = true;
			$out['moduleMessage'] = "REDCap couldn't get authorization values from logged interview data -- please contact REDCap administrator.";
		}
		
		$curlArgs = [];
		$curlArgs['headers'] = [
			"Accept: application/json",
			"Cookie: JSESSIONID=" . $authValues['JSESSIONID'] . "; AWSELB=" . $authValues['AWSELB']
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
		
		// need config to see if we should send results back to user or not
		$keepResults = [];
		$projectSettings = $this->getProjectSettings();
		// get instrument's display name
		$query = $this->query('select form_name, form_menu_description
			from redcap_metadata
			where form_name="' . $args['instrument'] . '" and project_id=' . $this->getProjectId() . ' and form_menu_description<>""');
		$record = db_fetch_assoc($query);
		$displayName = $record['form_menu_description'];
		
		foreach ($projectSettings['survey_instrument']['value'] as $settingsIndex => $instrumentName) {
			if ($instrumentName == $displayName) {
				foreach($args['types'] as $testType) {
					if ($projectSettings[$testType . '_show_results']['value'][$settingsIndex] == 1) {
						$keepResults[$testType] = true;
					}
				}
				break;
			}
		}
		// now remove results from curl response as necessary
		$results = json_decode($curl['body'], true);
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
				
				// update interview status in db/logs
				// $this->removeLogs("subjectID='{$args['subjectID']}' and interviewID={$args['interviewID']}");
				$args['tstamp'] = time();
				$args['status'] = 3;
				$args['results'] = $curl['body'];
				$args['types'] = json_encode($args['types'], JSON_UNESCAPED_SLASHES);
				$args['labels'] = json_encode($args['labels'], JSON_UNESCAPED_SLASHES);
				// put auth values in db as well
				$this->log("getResults", array_merge($authValues, $args));
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
		
		$authValues = $this->getAuthValues($args);
		if ($authValues === false) {
			$out['moduleError'] = true;
			$out['moduleMessage'] = "REDCap couldn't get authorization values from logged interview data -- please contact REDCap administrator.";
		}
		
		// build request headers and body
		$curlArgs = [];
		$curlArgs['headers'] = [
			"Cookie: JSESSIONID=" . $authValues['JSESSIONID'] . "; AWSELB=" . $authValues['AWSELB']
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
?>