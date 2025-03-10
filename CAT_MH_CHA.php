<?php
namespace VICTR\REDCAP\CAT_MH_CHA;

class CAT_MH_CHA extends \ExternalModules\AbstractExternalModule {
	public $convertTestAbbreviation = [
		'mdd' => "mdd",
		'dep' => "dep",
		'anx' => "anx",
		'mhm' => "m/hm",
		'pdep' => "p-dep",
		'panx' => "p-anx",
		'pmhm' => "p-m/hm",
		'sud' => "sud",
		'sa' => 'sa',
		'ptsd' => "ptsd",
		'cssrs' => "c-ssrs",
		'ss' => "ss",
		'phq9' => "phq-9",
		'aadhd' => "a/adhd",
		'sdoh' => "sdoh",
		'psys' => "psy-s"
	];
	public $testTypes = [
		'mdd' => "Major Depressive Disorder",
		'dep' => "Depression",
		'anx' => "Anxiety Disorder",
		'm/hm' => "Mania/Hypomania",
		'p-dep' => "Depression (Perinatal)",
		'p-anx' => "Anxiety Disorder (Perinatal)",
		'p-m/hm' => "Mania/Hypomania (Perinatal)",
		'sud' => "Substance Use Disorder",
		'sa' => "Substance Abuse",
		'ptsd' => "Post-Traumatic Stress Disorder",
		'c-ssrs' => "C-SSRS Suicide Screen",
		'ss' => "Suicide Scale",
		'phq-9' => "PHQ-9",
		'sdoh' => "Social Determinants of Health",
		'a/adhd' => "Adult ADHD",
		'psy-s' => "Psychosis - Self-Report"
	];
	public $kcat_primary_tests = [
		'c/age' => "Child/Age",
		'c/anx' => "Child/Anxiety",
		'c/mania' => "Child/Mania",
		'c/odd' => "Child/Opp. Defiant Disorder",
		'c/adhd' => "Child/ADHD",
		'c/dep' => "Child/Depression",
		'c/cd' => "Child/Conduct Disorder"
	];
	public $kcat_optional_primary_tests = [
		'c/ss' => "Child/Suicide Scale"
	];
	public $kcat_secondary_tests = [
		'p/info' => "Parent/Info",
		'p/anx' => "Parent/Anxiety",
		'p/mania' => "Parent/Mania",
		'p/odd' => "Parent/Opp. Defiant Disorder",
		'p/adhd' => "Parent/ADHD",
		'p/dep' => "Parent/Depression",
		'p/cd' => "Parent/Conduct Disorder"
	];
	public $dashboardColumns = [
		'Record ID',
		'Sequence',
		'Completed',
		'Within Window',
		'Date Scheduled',
		'Date to Complete',
		'Date Taken',
		'Elapsed Time',
		'Missed Surveys',
		'Acknowledged'
	];
	public $interviewStatusIconURLs = [
		'red' => APP_PATH_IMAGES . 'circle_red.png',
		'gray' => APP_PATH_IMAGES . 'circle_gray.png',
		'yellow' => APP_PATH_IMAGES . 'circle_yellow.png',
		'green' => APP_PATH_IMAGES . 'circle_green_tick.png'
		// blue added in __construct
	];
	
	private $clearedExpiredSeqByProject = [];
	private $cachedSequences = [];
	private $cachedInterviews = [];
	
	public function getInterviewStatusIconURLs($color) {
		if(!array_key_exists('blue',$this->interviewStatusIconURLs)) {
			$this->interviewStatusIconURLs['blue'] = $this->getUrl("images/circle_blue.png");
		}
		
		if(array_key_exists($color, $this->interviewStatusIconURLs)) {
			return $this->interviewStatusIconURLs[$color];
		}
		return NULL;
	}
	
	// hooks
	public function redcap_survey_complete($project_id, $record, $instrument, $event_id, $group_id, $survey_hash, $response_id, $repeat_instance) {
		$on_complete_surveys = $this->getProjectSetting('invite-on-survey-complete');
		$filter_fields = $this->getProjectSetting('filter_fields');
		$rid_field_name = $this->getRecordIdField();
		
		// $this->llog("cat-mh redcap_survey_complete called with args:\n" . print_r(func_get_args(), true));
		if (empty($record)) {
			return;
		}
		
		// check to see if this is a survey configured to auto-invite participants upon completion
		$survey_index = array_search($instrument, $on_complete_surveys, true);
		if ($survey_index === false) {
			// it's not
			// $this->llog("cat-mh redcap_survey_complete -- returning early: not a configured instrument");
			return;
		}
		// it is
		
		if (empty($enrollment_field_name = $this->getProjectSetting('enrollment_field'))) {
			// $this->llog("cat-mh redcap_survey_complete -- returning early: no enrollment_field configured");
			return;
		}
		
		$param_fields = [
			$rid_field_name,
			$enrollment_field_name,
			'subjectid'
		];
			
		// check to see if any of this record's filter fields are non-empty -- if so, do not invite to first scheduled interview
		if (!empty($filter_fields)) {
			$param_fields = array_merge($param_fields, $filter_fields);
		}
		
		$data = json_decode(\REDCap::getData($project_id, 'json', $record, $param_fields));
		$record_obj = $data[0];
		foreach ($filter_fields as $fieldname) {
			if (empty($record_obj->$fieldname)) {
				// $this->llog("cat-mh redcap_survey_complete -- returning early: detected empty filter_field $fieldname");
				return;
			}
		}
		
		// get or make subjectid
		if (empty($subjectid = $record_obj->subjectid))
			$subjectid = $this->initRecord($record_obj);
		if (empty($subjectid)) {
			// $this->llog("cat-mh redcap_survey_complete -- returning early: couldn't establish subjectid");
			return;
		}
		
		// checks passed: invite participant to take interview
		$sequences = $this->getScheduledSequences();
		$first_seq = $sequences[0];
		if (empty($first_seq)) {
			// $this->llog("cat-mh redcap_survey_complete -- returning early: couldn't determine first scheduled sequence\n");
			// $this->llog("scheduled seqs: " . print_r($sequences, true));
			return;
		}
		$seq_name = $first_seq[1];
		$seq_offset = $first_seq[2];
		$seq_time_of_day = $first_seq[3];
		
		// make link to first scheduled sequence
		$enrollment_timestamp = strtotime($record_obj->$enrollment_field_name);
		if (empty($enrollment_timestamp)) {
			// $this->llog("cat-mh redcap_survey_complete -- returning early: couldn't determine first scheduled sequence");
			return;
		}
		$enroll_date = date("Y-m-d", $enrollment_timestamp);
		// $this->llog("enroll_date: $enroll_date");
		$enroll_and_time = "$enroll_date " . $seq_time_of_day;
		// $this->llog("enroll_and_time: $enroll_and_time");
		$sched_time = strtotime("+$seq_offset days", strtotime($enroll_and_time));
		// $this->llog("sched_time: $sched_time");
		$first_sched_datetime = date("Y-m-d H:i", $sched_time);
		// $this->llog("first_sched_datetime: $first_sched_datetime");
		$interview_url = $this->getUrl("interview.php") . "&NOAUTH&sid=$subjectid&sequence=" . urlencode($seq_name) . "&sched_dt=" . urlencode($first_sched_datetime);
		
		// redirect
		header('Location: ' . $interview_url, true, 302);
		$this->exitAfterHook();
		
		// echo "<br><br><h5>You may now take the first scheduled interview of the program by following the link below:</h5><br>";
		// echo "<a href='$interview_url' style='font-size: 16px;'>CAT-MH Interview $seq_name</a>";
		// echo "<br><br><h6>Alternatively you may visit the URL directly:</h6><br><span>$interview_url</span>";
	}
	
	// crons
	public function emailer_cron($cronInfo=null, $current_time=null) {
		$originalPid = htmlentities($_GET['pid'], ENT_QUOTES, 'UTF-8');
		foreach($this->framework->getProjectsWithModuleEnabled() as $localProjectId) {
			$_GET['pid'] = $localProjectId;

			// avoid cross-project contamination
			$this->cachedInterviews = [];
			$this->sendInvitations(time());
			
			$today_ymd = date("Y-m-d");
			$result = $this->queryLogs("SELECT timestamp WHERE message=? AND date_ymd=?", ['cron_ran_today', $today_ymd]);
			if ($result && $result->num_rows === 0) {	// make sure we only log this message once a day
				\REDCap::logEvent("CAT-MH External Module", "Ran 'emailer_cron' method today", NULL, NULL, NULL, $this->getProjectId());
				$this->log("cron_ran_today", ['date_ymd' => $today_ymd]);
			}
			
			// clear reminderSettings cache
			unset($this->reminderSettings);
		}
		$_GET['pid'] = $originalPid;
	}
	
	//utility
	public function extractCURLHeaders($headerContent) {
		// get headers as arrays
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
		
		$output['headers'] = $this->extractCURLHeaders($rawHeaders);
		return $output;
	}
	
	public function getAuthValues($args) {
		// args should have: subjectID, interviewID, identifier, signature
		// $this->llog("getAuthValues: calling with arguments: " . print_r($args, true));
		$interview = $this->getInterview($args['subjectID'], $args['interviewID'], $args['identifier'], $args['signature'], $args['kcat']);
		if (empty($interview)) {
			echo("REDCap couldn't get authorization values from logged interview data -- please contact REDCap administrator.");
		} else {
			return [
				'jsessionid' => $interview->jsessionid,
				'awselb' => $interview->awselb
			];
		}
	}
	
	public function getRecordBySID($sid) {
		$sid = preg_replace("/\W|_/", '', $sid);
		$pid = $this->getProjectId();
		$data = \REDCap::getData($pid, 'array', NULL, NULL, NULL, NULL, NULL, NULL, NULL, "[subjectid]=\"$sid\"");
		return $data;
	}
	
	public function getRecordIDBySID($sid) {
		$sid = preg_replace("/\W|_/", '', $sid);
		$ridfield = $this->framework->getRecordIDField();
		$params = [
			"project_id" => $this->getProjectId(),
			"return_format" => 'json',
			"fields" => ["subjectid", $ridfield],
			"filterLogic" => "[subjectid]='$sid'"
		];
		$data = json_decode(\REDCap::getData($params));
		if (isset($data[0]) and !empty($data[0]->$ridfield)) {
			return $data[0]->$ridfield;
		}
		return false;
	}
	
	public function getSubjectID($record_id) {
		$redcap_data_table = $this->framework->getDataTable($this->framework->getProjectId());
		$r = $this->query("SELECT value FROM $redcap_data_table WHERE record = ? AND field_name='subjectid' AND project_id = ?", [
			$record_id,
			$this->getProjectId()
		]);
		return db_fetch_assoc($r)['value'];
	}
	
	public function getSequenceIndex($seq_name) {
		foreach ($this->getProjectSetting('sequence') as $i => $name) {
			if ($name === $seq_name)
				return $i;
		}
		return false;
	}
	
	public function getKCATTestLabel($seq_name, $test) {
		$index = $this->getKCATSequenceIndex($seq_name);
		$test_underscore = str_replace('/', '_', $test);
		if (!empty($alt_label = $this->getProjectSetting($test_underscore . '_label')[$index]))
			return $alt_label;
		
		$labels = array_merge(
			$this->kcat_primary_tests,
			$this->kcat_optional_primary_tests,
			$this->kcat_secondary_tests
		);
		return $labels[$test];
	}
	
	public function getTestLabel($seq_name, $test) {
		$test = strtolower($test);
		
		if ($this->getKCATSequenceIndex($seq_name) !== false)
			return $this->getKCATTestLabel($seq_name, $test);
		
		$test = preg_replace("[\W]", "", $test);
		
		$abbrev = $this->convertTestAbbreviation[$test];
		
		$index = $this->getSequenceIndex($seq_name);
		$label = $this->testTypes[$abbrev];
		$alt_label = $this->getProjectSetting($test . "_label")[$index];
		if (empty($alt_label)) {
			return $label;
		}
		
		return $alt_label;
	}
	
	public function makeInterview() {
		// If no sequence given in url parameters, default to first sequence configured
		$projectSettings = $this->getProjectSettings();
		$sequence = htmlentities(urldecode($_GET['sequence']), ENT_QUOTES, 'UTF-8');
		$sid = htmlentities($_GET['sid'], ENT_QUOTES, 'UTF-8');
		$sched_dt = htmlentities(urldecode($_GET['sched_dt']), ENT_QUOTES, 'UTF-8');
		
		// get system configuration details
		$args = [];
		$args['organizationid'] = $this->getSystemSetting('organizationid');
		$args['applicationid'] = $this->getSystemSetting('applicationid');
		if (!isset($args['organizationid']) or !isset($args['organizationid'])) {
			echo("Cannot create a new interview. Please have the REDCap administrator configure the application and organization IDs for CAT-MH use.");
			return;
		}
		
		if ($valid_sid = $this->validateSubjectId($sid)) {
			$args['subjectID'] = $valid_sid;
		} else {
			echo("Cannot create a new interview due to invalid subjectID! Please have the REDCap administrator configure the application and organization IDs for CAT-MH use.");
			return;
		}
		
		// determine timeframeID
		$seq_index = array_search(htmlentities($_GET['sequence'], ENT_QUOTES, 'UTF-8'), $this->getProjectSetting('sequence'));
		$timeframeID = $this->getProjectSetting('timeframe')[$seq_index];
		if (!empty($timeframeID)) {
			$args['timeframeID'] = $timeframeID;
		}
		
		// determine sequence tests and language
		foreach ($projectSettings['sequence'] as $i => $seq) {
			if ($seq == $sequence) {
				// tests array
				$args['tests'] = [];
				$testTypeKeys = array_keys($this->convertTestAbbreviation);
				foreach ($testTypeKeys as $j => $testAbbreviation) {
					// filter 'sa' test types out of args[tests]
					if ($projectSettings[$testAbbreviation][$i] == 1 and $this->convertTestAbbreviation[$testAbbreviation] != 'sa') {
						$args['tests'][] = ["type" => $this->convertTestAbbreviation[$testAbbreviation]];
					}
				}
				$args['language'] = $projectSettings['language'][$i] == 2 ? 2 : 1;
			}
		}
		
		$interview = $this->createInterview($args);
		$interview['subjectID'] = $valid_sid;
		
		if(array_key_exists("moduleError", $interview) && $interview['moduleError']) {
			echo("CAT-MH encountered an error with the API:<br />" . $interview['moduleMessage']);
			return false;
		}
		
		$new_interview = [
			"sequence" => $sequence,
			"scheduled_datetime" => $sched_dt,
			"interviewID" => $interview['interviewID'],
			"identifier" => $interview['identifier'],
			"signature" => $interview['signature'],
			"types" => $interview['types'],
			"labels" => $interview['labels'],
			"status" => 1,
			"timestamp" => time(),
			"subjectID" => $valid_sid
		];
		$log_id = $this->updateInterview($new_interview);
		
		if (!$log_id) {
			echo("CAT-MH encountered an error with the API:<br />" . $interview['moduleMessage']);
			return false;
		} else {
			return $new_interview;
		}
	}
	
	public function buildQuestionTestMap() {
		$this->questionTestMap = [];
		$file_path = $this->getModulePath() . "data/questionID_testType.csv";
		$file = fopen($file_path, 'r');
		if ($file === false) {
			throw new \Exception("The CAT-MH module couldn't open questionID_testType.csv to construct a question/test map array.");
		}
		while ($line = fgetcsv($file)) {
			$questionID = $line[0];
			$test_short_name = strtolower($line[1]);
			$this->questionTestMap[$questionID] = [$test_short_name];
			
			// adhd and a/adhd questions get pulled from same item bank
			// same for p-anx, p-dep, p-m/hm, they get pulled from the general test item banks
			if ($test_short_name == 'c/adhd') {
				$this->questionTestMap[$questionID][] = 'a/adhd';
			}
			if ($test_short_name == 'dep') {
				$this->questionTestMap[$questionID][] = 'p-dep';
			}
			if ($test_short_name == 'anx') {
				$this->questionTestMap[$questionID][] = 'p-anx';
			}
			if ($test_short_name == 'm/hm') {
				$this->questionTestMap[$questionID][] = 'p-m/hm';
			}
		}
	}
	
	// K-CAT methods
	public function getKCATSequenceIndex($seq_name) {	// or return false if not a kcat sequence
		if (empty($this->kcat_seq_names)) {
			$this->kcat_seq_names = $this->getProjectSetting('kcat_sequence');
			if (gettype($this->kcat_seq_names) != 'array')
				$this->kcat_seq_names = [];
		}
		if (gettype($seq_name) != "string") {
			return false;
			// throw new \Exception("getKCATSequenceIndex first argument must be a string, was type: " . gettype($seq_name));
		}
		
		// $this->llog("\$this->kcat_seq_names: " . print_r($this->kcat_seq_names, true));
		return array_search($seq_name, $this->kcat_seq_names, true);
	}
	
	public function getKCATTests($seq_name, $which_of_pair) {
		// return test types depending on if which_of_pair is primary or secondary
		// also takes into account which optional primary test(s) (like c/ss) should be included
		if ($which_of_pair == 'primary') {
			$tests = array_keys($this->kcat_primary_tests);
			
			// include c/ss?
			$seq_index = $this->getKCATSequenceIndex($seq_name);
			if($this->getProjectSetting('include_css')[$seq_index])
				$tests[] = 'c/ss';
			
		} elseif ($which_of_pair == 'secondary') {
			$tests = array_keys($this->kcat_secondary_tests);
		} else {
			throw new \Exception("CAT-MH module's 'getKCATTests' method expected \$which_of_pair argument to be 'primary' or 'secondary', but it was: " . json_encode($which_of_pair));
		}
		return $tests;
	}
	
	public function getKCATTestLabels($tests, $seq_name, $which_of_pair) {
		if (empty($tests) or gettype($tests) != 'array')
			throw new \Exception("The CAT-MH module 'getKCATTestLabels' expects it's only argument to be a non-empty array of test abbreviations (like 'c/anx'). Instead the argument was: " . json_encode($tests));
		
		$labels = [];
		$seq_index = $this->getKCATSequenceIndex($seq_name);
		
		if ($seq_index === false)
			throw new \Exception("'$seq_name' is not a valid name for a configured K-CAT interview sequence");
		
		if ($which_of_pair == 'primary') {
			foreach ($tests as $test_index => $test_abbrev) {
				$test_underscore = str_replace('/', '_', $test_abbrev);
				$alt_label = $this->getProjectSetting($test_underscore . "_label")[$seq_index];
				if (empty($alt_label)) {
					$label = $this->kcat_primary_tests[$test_abbrev];
				} else {
					$label = $alt_label;
				}

				// handle optional primary test abbrev
				if ($test_abbrev == 'c/ss') {
					$alt_label = $this->getProjectSetting($test_underscore . "_label")[$seq_index];
					if (empty($alt_label)) {
						$label = $this->kcat_optional_primary_tests[$test_abbrev];
					} else {
						$label = $alt_label;
					}
				}
				
				if (empty($label))
					throw new \Exception("The CAT-MH module couldn't find a label for test type: $test_abbrev");
				
				$labels[$test_index] = $label;
			}
		} elseif ($which_of_pair == 'secondary') {
			foreach ($tests as $test_index => $test_abbrev) {
				$test_underscore = str_replace('/', '_', $test_abbrev);
				$alt_label = $this->getProjectSetting($test_underscore . "_label")[$seq_index];
				if (empty($alt_label)) {
					$label = $this->kcat_secondary_tests[$test_abbrev];
				} else {
					$label = $alt_label;
				}
				
				if (empty($label))
					throw new \Exception("The CAT-MH module couldn't find a label for test type: $test_abbrev");
				
				$labels[$test_index] = $label;
			}
		} else {
			throw new \Exception("CAT-MH module's 'getKCATTestLabels' method expected \$which_of_pair argument to be 'primary' or 'secondary', but it was: " . json_encode($which_of_pair));
		}
		
		return $labels;
	}
	
	public function makeKCATInterviews($sid, $sequence, $sched_dt) {
		$result = $this->createInterviewPair($sid, $sequence);
		$time_now = time();
		
		// $this->llog('createInterviewPair ersult: ' . print_r($result, true));
		
		// make primary interview object
		$primary = $result['primary'];
		$primary->kcat = 'primary';
		$primary->subjectID = $sid;
		$primary->sequence = $sequence;
		$primary->scheduled_datetime = $sched_dt;
		$primary->status = 1;
		$primary->timestamp = $time_now;
		$primary->types = $this->getKCATTests($sequence, 'primary');
		$primary->labels = $this->getKCATTestLabels($primary->types, $sequence, 'primary');
		if (empty($this->updateInterview($primary)))
			throw new \Exception("The CAT-MH module failed to create primary interview");
		
		// make secondary interview object
		$secondary = $result['secondary'];
		$secondary->kcat = 'secondary';
		$secondary->subjectID = $sid;
		$secondary->sequence = $sequence;
		$secondary->scheduled_datetime = $sched_dt;
		$secondary->status = 1;
		$secondary->timestamp = $time_now;
		$secondary->types = $this->getKCATTests($sequence, 'secondary');
		$secondary->labels = $this->getKCATTestLabels($secondary->types, $sequence, 'secondary');
		if (empty($this->updateInterview($secondary)))
			throw new \Exception("The CAT-MH module failed to create primary interview");
		
		return [
			'primaryInterview' => $primary,
			'secondaryInterview' => $secondary
		];
	}
	
	public function getSequenceStatus($record, $seq_name, $datetime, $kcat=null) {
		$interviews = $this->getInterviewsByRecordID($record);
		foreach ($interviews as $i => $interview) {
			if (empty($kcat)) {
				if ($interview->sequence == $seq_name and $interview->scheduled_datetime == $datetime) {
					return $interview->status;
				}
			} else {
				if ($interview->sequence == $seq_name and $interview->scheduled_datetime == $datetime and $interview->kcat == $kcat) {
					return $interview->status;
				}
			}
		}
		return false;
	}
	
	public function initRecord(&$record) {
		if (gettype($record) !== 'object')
			throw new \Exception("First argument to sendEmails must be an object -- type: " . gettype($record));
		if (empty($rid = $record->{$this->getRecordIdField()}))
			throw new \Exception("\$record argument is missing a record ID field (in initRecord)");
		
		$record->subjectid = $this->generateSubjectID();
		$data = json_encode([$record]);
		$save_results = \REDCap::saveData($this->getProjectId(), 'json', $data, 'overwrite');
		\REDCap::logEvent("CAT-MH External Module", "Initialized CAT-MH subjectID for record: $rid", NULL, NULL, NULL, $this->getProjectId());
		return $record->subjectid;
	}
	
	public function llog($text) {
		// if (!$this->local_env)
			// return;
		// echo "<pre>$text\n</pre>";
		
		// $this->log_ran = true;
		
		// if ($this->log_ran) {
			// file_put_contents("C:/vumc/log.txt", "$text\n", FILE_APPEND);
		// } else {
			// file_put_contents("C:/vumc/log.txt", date('c') . "\n" . "starting CAT_MH_CHA log:\n$text\n");
			// $this->log_ran = true;
		// }
	}
	
	// interview data object/log functions
	public function getSequence($sequence, $scheduled_datetime, $subjectID, $kcat=null) {
		if (!empty($kcat)) {
			$result = $this->queryLogs("SELECT interview WHERE message = ? AND sequence = ? AND scheduled_datetime = ? AND subjectid = ? AND kcat = ?", [
				'catmh_interview', $sequence, $scheduled_datetime, $subjectID, $kcat
			]);
		} else {
			$result = $this->queryLogs("SELECT interview WHERE message = ? AND sequence = ? AND scheduled_datetime = ? AND subjectid = ?", [
				'catmh_interview', $sequence, $scheduled_datetime, $subjectID
			]);
		}
		
		// return $interview or false;
		$interview = json_decode(db_fetch_assoc($result)['interview']);
		if (empty($interview))
			return false;
		return $interview;
	}
	
	public function getInterview($subjectID, $interviewID, $identifier, $signature, $kcat=null) {
		// queryLogs, convert interview object to array
		if (!empty($kcat)) {
			$result = $this->queryLogs("SELECT interview, timestamp WHERE message='catmh_interview' AND subjectid = ? AND interviewID = ? AND identifier = ? AND signature = ? AND kcat = ?", [
				$subjectID, $interviewID, $identifier, $signature, $kcat
			]);
		} else {
			$result = $this->queryLogs("SELECT interview, timestamp WHERE message='catmh_interview' AND subjectid = ? AND interviewID = ? AND identifier = ? AND signature = ?", [
				$subjectID, $interviewID, $identifier, $signature
			]);
		}
		$db_result = db_fetch_assoc($result);
		
		if (empty($db_result))
			return false;
		// $this->llog("getInterview: fetched db_result: " . print_r($db_result, true));
		
		$interview = json_decode($db_result['interview']);
		$interview->db_timestamp = $db_result['timestamp'];
		
		return $interview;
	}
	
	public function updateInterview($interview) {
		if (gettype($interview) == 'array')
			$interview = (object) $interview;
		
		if (empty($interview->update_id)) {
			$interview->update_id = 1;
		} else {
			$interview->update_id = $interview->update_id + 1;
		}
		
		// $this->llog('updating interview:  ' . print_r($interview, true));
		
		// build parameters array
		$rid = $this->getRecordIDBySID($interview->subjectID);
		
		if (!$rid) {
			throw new \Exception("The CAT-MH module was unabled to get the record ID for a given subject ID.");
		}
		
		$parameters = [
			"subjectid" => $interview->subjectID,
			"sequence" => $interview->sequence,
			"interviewID" => $interview->interviewID,
			"identifier" => $interview->identifier,
			"signature" => $interview->signature,
			"scheduled_datetime" => $interview->scheduled_datetime,
			"interview" => json_encode($interview),
			"update_id" => $interview->update_id
		];
		$parameters["record_id"] = $rid;
		if ($interview->kcat)
			$parameters['kcat']= $interview->kcat;
		
		// assert all params are present
		foreach($parameters as $name => $value) {
			if(empty($value))
				throw new \Exception("Can't update interview with empty $name parameter");
		}
		
		// fetch existing interview with these parameters (if it exists)
		$existing_interview = $this->getInterview($interview->subjectID, $interview->interviewID, $interview->identifier, $interview->signature, $interview->kcat);
		
		// log with message 'catmh_interview'
		$log_id = $this->log('catmh_interview', $parameters);
		// $this->llog("updateInterview: added catmh_interview module log message (log_id: $log_id)");
		
		// success:
			// remove old interview data
			// then return log_id
		// fail:
			// logEvent, revert, return false
		if (!empty($log_id)) {
			if ($existing_interview) {
				$this->removeLogs("message = ? AND subjectid = ? AND interviewID = ? AND identifier = ? AND signature = ? AND (update_id < ? OR update_id is NULL)", [
					'catmh_interview',
					$existing_interview->subjectID,
					$existing_interview->interviewID,
					$existing_interview->identifier,
					$existing_interview->signature,
					$interview->update_id	// this is what ensures previous intervew objects are removed
				]);
				// $this->llog("updateInterview: called removeLogs with args " . print_r([
					// 'catmh_interview',
					// $existing_interview->subjectID,
					// $existing_interview->interviewID,
					// $existing_interview->identifier,
					// $existing_interview->signature,
					// $existing_interview->update_id
				// ], true));
			}
			return $log_id;
		}
		
		if (!empty($existing_interview)) {
			// revert
			$log_id = $this->updateInterview($existing_interview);
			if (empty($log_id)) {
				\REDCap::logEvent("CAT-MH External Module", "Record $rid: Failed to save interview object AND failed to revert to old interview data (updateInterview)", NULL, NULL, NULL, $this->getProjectId());
			} else {
				\REDCap::logEvent("CAT-MH External Module", "Record $rid: Failed to save interview object but succesfully reverted to old interview data (updateInterview)", NULL, NULL, NULL, $this->getProjectId());
				return $log_id;
			}
		} else {
			\REDCap::logEvent("CAT-MH External Module", "Record $rid: Failed to save new interview object! (updateInterview)", NULL, NULL, NULL, $this->getProjectId());
			return false;
		}
	}
	
	public function getInterviewsByRecordID($record_id) {
		if(array_key_exists($record_id,$this->cachedInterviews)) {
			return $this->cachedInterviews[$record_id];
		}
		$interviews = [];
		
		$result = $this->queryLogs("SELECT interview WHERE message='catmh_interview' AND record_id = ?", [$record_id]);
		while ($row = db_fetch_assoc($result)) {
			$interviews[] = json_decode($row['interview']);
		}
		
		$this->cachedInterviews[$record_id] = $interviews;
		
		if (!empty($interviews))
			return $interviews;
	}
	
	// scheduling
	public function scheduleSequence($seq_name, $offset, $time_of_day) {
		// ensure not duplicate scheduled
		$result = $this->queryLogs("SELECT message, name, offset, time_of_day WHERE message='scheduleSequence' AND name=? AND offset=? AND time_of_day=?", [
			$seq_name,
			$offset,
			$time_of_day
		]);
		if ($result->num_rows != 0) {
			return [false, "This sequence is already scheduled for this date/time"];
		}
		
		$log_id = $this->log("scheduleSequence", [
			"name" => $seq_name,
			"offset" => $offset,
			"time_of_day" => $time_of_day
		]);
		
		if (!empty($log_id)) {
			return [true, $log_id];
		} else {
			return [false, "CAT-MH module failed to schedule sequence (log insertion failed)"];
		}
	}
	
	public function unscheduleSequence($seq_name, $offset, $time_of_day) {
		// removes associated invitations AND reminders
		// $this->llog("unscheduleSequence: $seq_name, $offset, $time_of_day");
		return $this->removeLogs("name = ? AND offset = ? AND time_of_day = ?", [
			$seq_name,
			$offset,
			$time_of_day
		]);
	}
	
	public function cleanMissingSeqsFromSchedule() {
		## Only run once per project
		if(array_key_exists("pid", $_GET) && array_key_exists($_GET['pid'], $this->clearedExpiredSeqByProject)) {
			return;
		}
		$result = $this->queryLogs("SELECT message, name, offset, time_of_day, sent WHERE message='scheduleSequence'");
		
		$valid_seq_names = array_merge(
			$this->getProjectSetting('sequence'),
			$this->getProjectSetting('kcat_sequence') ?? []
		);
		
		while ($row = db_fetch_array($result)) {
			$seq_name = $row['name'];
			if (array_search($seq_name, $valid_seq_names, true) === false) {
				// this is no longer a valid sequence to be scheduled since it was taken out of configuration
				// $remove_count = $this->countLogs("message='scheduleSequence' AND name = ?", [$seq_name]);
				// $this->llog("CLEANING $remove_count MISSING SEQS FROM SCHEDULE");
				$this->removeLogs("message='scheduleSequence' AND name = ?", [$seq_name]);
			}
		}
		## Store completed status
		if(array_key_exists("pid", $_GET)) {
			$this->clearedExpiredSeqByProject[$_GET['pid']] = 1;
		}
	}
	
	public function getScheduledSequences() {
		$this->cleanMissingSeqsFromSchedule();
		
		if(array_key_exists("pid", $_GET) && array_key_exists($_GET['pid'], $this->cachedSequences)) {
			return $this->cachedSequences[$_GET['pid']];
		}
		$result = $this->queryLogs("SELECT message, name, offset, time_of_day, sent WHERE message='scheduleSequence'");
		
		$sequences = [];
		while ($row = db_fetch_array($result)) {
			$sequences[] = ["<input type='checkbox' class='sequence_cbox'>", htmlspecialchars($row['name'], ENT_QUOTES), htmlspecialchars($row['offset'], ENT_QUOTES), htmlspecialchars($row['time_of_day'], ENT_QUOTES)];
		}
		
		if(array_key_exists("pid", $_GET)) {
			$this->cachedSequences[$_GET["pid"]] = $sequences;
		}
		
		return $sequences;
	}
	
	// reminders
	public function setReminderSettings($settings) {
		$this->removeLogs("message='reminderSettings'");
		return $this->log("reminderSettings", (array) $settings);
	}
	
	public function getReminderSettings() {
		if (!isset($this->reminderSettings)) {
			$this->reminderSettings = db_fetch_assoc($this->queryLogs("SELECT message, enabled, frequency, duration, delay WHERE message='reminderSettings'"));
		}
		return $this->reminderSettings;
	}
	
	// email invitations
	public function sendProviderEmail() {
		// feature enabled?
		if (empty($this->getProjectSetting('send-provider-emails')))
			return false;
		
		$sid = htmlentities($_GET['sid'], ENT_QUOTES, 'UTF-8');
		$rid = $this->getRecordIDBySID($sid);
		
		// get provider email address
		$params = [
			"project_id" => $this->getProjectId(),
			"return_format" => 'json',
			"fields" => ["catmh_provider_email", "subjectid"],
			"filterLogic" => "[subjectid]='$sid'"
		];
		$data = json_decode(\REDCap::getData($params));
		if (isset($data[0]) and !empty($data[0]->catmh_provider_email)) {
			$provider_address = $data[0]->catmh_provider_email;
		} else {
			return false;
		}
		
		$message_body = "You're receiving this automated message because a patient has completed a CAT-MH interview sequence.<br>";
		
		$seq = urlencode(htmlentities($_GET['sequence'], ENT_QUOTES, 'UTF-8'));
		$sched_dt = urlencode(htmlentities($_GET['sched_dt'], ENT_QUOTES, 'UTF-8'));
		$email = new \Message();
		$from_address = $this->getProjectSetting('email-from');
		if (empty($from_address)) {
			global $project_contact_email;
			$from_address = $project_contact_email;
		}
		$email->setFrom($from_address);
		$email->setTo($provider_address);
		$email->setSubject("CAT-MH Interview Completed by Patient");
		
		// append link to results
		$link = "<a href='" . $this->getURL('resultsReport.php') . "&record=$rid&seq=$seq&sched_dt=$sched_dt'>View Patient Interview Results<a/>";
		$message_body .= "<br>$link";
		
		$email->setBody($message_body);
		$success = $email->send();
		if ($success) {
			\REDCap::logEvent("CAT-MH External Module", "Record $rid: Successfully sent provider email upon interview completion", NULL, NULL, NULL, $this->getProjectId());
		} else {
			\REDCap::logEvent("CAT-MH External Module", "Record $rid: Failed to send provider email upon interview completion (" . $email->ErrorInfo . ")", NULL, NULL, NULL, $this->getProjectId());
		}
	}
	
	public function sendInvitations($current_time) {
		if (empty($enrollment_field_name = $this->getProjectSetting('enrollment_field')))
			return;
		
		// $this->llog("sendInvitations:");
		if ($this->getProjectSetting('disable_invites'))
			return;
		
		// $this->llog("passed disable_invites check");
		$this->cleanMissingSeqsFromSchedule();
		
		$catmh_email_field_name = $this->getProjectSetting('participant_email_field');
		if (empty($catmh_email_field_name))
			$catmh_email_field_name = 'catmh_email';
		
		// fetch all records
		$param_fields = [
			$this->getRecordIdField(),
			"$enrollment_field_name",
			'subjectid',
			$catmh_email_field_name
		];
		
		// add filter_fields to getData request
		if (!empty($filter_fields = $this->getProjectSetting('filter-fields')))
			$param_fields = array_merge($param_fields, $filter_fields);
		
		$params = [
			'project_id' => $this->getProjectId(),
			'return_format' => 'json',
			'fields' => $param_fields
		];
		$data = json_decode(\REDCap::getData($params));
		// $this->llog("fetched record data (" . count($data) . " records)");
		
		// prepare email invitation using project settings
		$from_address = $this->getProjectSetting('email-from');
		if (empty($from_address)) {
			global $project_contact_email;
			$from_address = $project_contact_email;
		}
		
		// validation
		if (empty($from_address)) {
			// TODO: also add alert to scheduling page
			\REDCap::logEvent("CAT-MH External Module", "Can't send invitations without configuring a 'from' email address for the module", NULL, NULL, NULL, $this->getProjectId());
			return;
		}
		if (empty($email_subject = $this->getProjectSetting('email-subject')))
			$email_subject = "CAT-MH Interview Invitation";
		$email_body = $this->getProjectSetting('email-body');
		// if there's no [interview-urls/links] then remember not to replace, but to append links/urls
		if (strpos($email_body, "[interview-links]") === false)
			$append_links = true;
		if (strpos($email_body, "[interview-urls]") === false)
			$append_urls = true;
		
		// $this->llog("passed email configuration validation");
		$email = new \Message();
		$email->setFrom($from_address);
		$email->setSubject($email_subject);
		
		// prepare redcap log message
		$actually_log_message = false;
		$result_log_message = "Sending scheduled sequence invitations\n";
		$result_log_message .= "Email Subject: " . $email_subject . "\n";
		$result_log_message .= "Record-level information:\n";
		
		// iterate over records, sending email invitations
		foreach ($data as $record) {
			// TODO: possible to iterate over more than just records here? repeatable forms, other events?
			$rid_name = $this->getRecordIdField();
			$record_id = $record->$rid_name;
			
			// validate record values
			$empty_filter_field = false;
			foreach ($filter_fields as $fieldname) {	// check that this record's filter fields are true or abort
				if (empty($record->$fieldname)) {
					$empty_filter_field = $fieldname;
					break;
				}
			}
			if ($empty_filter_field) {
				// $this->llog("record $record_id empty filter field $empty_filter_field");
				$result_log_message .= "Record '$record_id' - No emails sent, filter_field [$empty_filter_field] is empty.";
				continue;
			}
			if (empty($record->$catmh_email_field_name)) {
				// $this->llog("Record '$record_id' - No emails sent -- empty [$catmh_email_field_name] field.");
				$result_log_message .= "Record '$record_id' - No emails sent -- empty [$catmh_email_field_name] field.";
				continue;
			}
			if (empty($rid = $record->{$this->getRecordIdField()})) {
				// $this->llog("Record '$record_id' - No emails sent -- missing Record ID.");
				$result_log_message .= "Record '$record_id' - No emails sent -- missing Record ID.";
				continue;
			}
			if (!$enrollment_timestamp = strtotime($record->{$enrollment_field_name})) {
				// $this->llog("Record '$record_id' - No emails sent -- Couldn't convert enrollment date/time to a valid timestamp integer. Enrollment Date/Time: " . json_encode($record->{$enrollment_field_name}));
				$result_log_message .= "Record '$record_id' - No emails sent -- Couldn't convert enrollment date/time to a valid timestamp integer. Enrollment Date/Time: " . json_encode($record->{$enrollment_field_name});
				continue;
			}
			if (empty($sid = $record->subjectid)) {
				continue;
			}
			
			$invitations_to_send = $this->getInvitationsDue($record, $current_time);
			if (empty($invitations_to_send)) {
				// $result_log_message .= "No emails sent -- no invitations due."; // trivial case
				// $this->llog("no invites due");
				continue;
			}
			
			// at least one participant with invitations to send
			$actually_log_message = true;
			
			// make urls and links to pipe into email body
			$urls = [];
			$links = [];
			$base_url = $this->getUrl("interview.php") . "&NOAUTH&sid=$sid";
			foreach ($invitations_to_send as $name_and_time => $invitation) {
				$seq_name = $invitation->sequence;
				$seq_date = date("Y-m-d H:i", $invitation->sched_dt);
				$month_day_only = date("m/d", strtotime($seq_date));
				
				// handle K-CAT interviews differently, generate two links, not just one
				if ($invitation->kcat) {
					$prim_seq_url = $base_url . "&sequence=" . urlencode($seq_name) . "&sched_dt=" . urlencode($seq_date) . "&kcat=primary";
					$prim_seq_link = "<a href=\"$prim_seq_url\">CAT-MH Interview - $seq_name ($month_day_only) - Child</a>";
					$sec_seq_url = $base_url . "&sequence=" . urlencode($seq_name) . "&sched_dt=" . urlencode($seq_date) . "&kcat=secondary";
					$sec_seq_link = "<a href=\"$sec_seq_url\">CAT-MH Interview - $seq_name ($month_day_only) - Parent</a>";
					$urls[] = $prim_seq_url;
					$urls[] = $sec_seq_url;
					$links[] = $prim_seq_link;
					$links[] = $sec_seq_link;
				} else {
					$seq_url = $base_url . "&sequence=" . urlencode($seq_name) . "&sched_dt=" . urlencode($seq_date);
					$seq_link = "<a href=\"$seq_url\">CAT-MH Interview - $seq_name ($month_day_only)</a>";
					$urls[] = $seq_url;
					$links[] = $seq_link;
				}
			}
			
			// prepare email body by replacing [interview-links] and [interview-urls] (or appending)
			$participant_email_body = $email_body;
			if ($append_links) {
				$participant_email_body .= "<br>" . implode("<br>", $links);
			} else {
				$participant_email_body = str_replace("[interview-links]", implode("<br>", $links), $participant_email_body);
			}
			if ($append_urls) {
				$participant_email_body .= "<br>" . implode("<br>", $urls);
			} else {
				$participant_email_body = str_replace("[interview-urls]", implode("<br>", $urls), $participant_email_body);
			}
			$email->setBody($participant_email_body);
			$email->setTo($record->$catmh_email_field_name);
			
			$success = $email->send();
			if ($success) {
				$result_log_message .= "Record '$record_id' - Sent interview invitation email to address: " . $record->$catmh_email_field_name . "\n";
				foreach($invitations_to_send as $invitation) {
					$this->log('invitationSent', (array) $invitation);
				}
			} else {
				$result_log_message .= "Record '$record_id' - Failed to send email (" . $email->ErrorInfo . ")\n";
			}
		}
		
		if ($actually_log_message) {
			\REDCap::logEvent("CAT-MH External Module", $result_log_message, NULL, NULL, NULL, $this->getProjectId());
		}
	}
	
	public function getInvitationsDue($record, $current_time) {
		$rid = $record->{$this->getRecordIdField()};
		// return an array with sequence names as keys, values as scheduled_datetimes
		$enrollment_timestamp = strtotime($record->{$this->getProjectSetting('enrollment_field')});
		
		// determine which sequence invitations and reminders we need to email to this participant
		$invites = [];
		$sequences = $this->getScheduledSequences();
		$reminder_settings = (object) $this->getReminderSettings();
		
		// // let's recall which all invitations have already been sent (includes initial invitations AND reminders)
		// $prev_sent = $this->rememberSentInvitations($rid);
		
		// each scheduled sequence is an event to send email invitations, plus each reminder event after
		foreach ($sequences as $seq_i => $seq) {
			$name = $seq[1];
			$offset = $seq[2];
			$time_of_day = $seq[3];
			
			// check scheduled event
			$enroll_date = date("Y-m-d", $enrollment_timestamp);
			$enroll_and_time = "$enroll_date " . $time_of_day;
			$sched_time = strtotime("+$offset days", strtotime($enroll_and_time));
			$first_sched_time = $sched_time;
			
			// check if interview is completed
			if ($this->getSequenceStatus($rid, $name, date("Y-m-d H:i", $sched_time)) == 4) {
				continue;
			}
			
			// is this sequence a K-CAT sequence? If so, create both interviews now if not yet created
			$kcat = $this->getKCATSequenceIndex($name) !== false;
			if ($kcat) {
				$sid = $this->getSubjectID($rid);
				$sched_time_ymd = date("Y-m-d H:i", $first_sched_time);
				$existingKCAT = $this->countLogs("message = ? AND sequence = ? AND scheduled_datetime = ? AND subjectid = ?", [
					'catmh_interview',
					$name,
					$sched_time_ymd,
					$sid
				]);
				if ($existingKCAT == 0) {
					$this->makeKCATInterviews($sid, $name, $sched_time_ymd);
				}
			}
			
			// if no invitation sent, send one
			$sent_count = $this->countLogs("message=? AND record=? AND sequence=? AND offset=? AND time_of_day=?", [
				'invitationSent',
				$rid,
				$name,
				$offset,
				$time_of_day
			]);
			
			$reminders_sent = $this->countLogs("message=? AND record=? AND sequence=? AND sched_dt = ? AND reminder='1'", [
				'invitationSent',
				$rid,
				$name,
				$first_sched_time
			]);
			
			// create invitation object
			$invitation = new \stdClass();
			$invitation->record = $rid;
			$invitation->sequence = $name;
			$invitation->offset = $offset;
			$invitation->time_of_day = $time_of_day;
			$invitation->sched_dt = $first_sched_time;
			$invitation->kcat = $kcat;
			
			if ($sched_time <= $current_time && $sent_count == 0 && $reminders_sent == 0) {
				$invites["$name $first_sched_time"] = $invitation;
			}
			
			// send reminders if applicable
			if ($reminder_settings->enabled) {
				$frequency = (int) $reminder_settings->frequency;
				$duration = (int) $reminder_settings->duration;
				$delay = (int) $reminder_settings->delay;
				
				$reminder_sent = false;
					
				// iterate over possible reminders from largest offset to smallest
				// log older reminders as ignored (ignoreReminder log message) if newer reminders get sent
				// this ensures that multiple reminders don't get sent repeatedly (like when an admin changes reminder settings)
				for ($reminder_offset = $delay + $duration - 1; $reminder_offset >= $delay; $reminder_offset -= $frequency) {
					// recalculate timestamp with reminder offset, to see if current time is after it
					$this_offset = $reminder_offset + $offset;
					$sched_time = strtotime("+$this_offset days", strtotime($enroll_and_time));
					
					// log message to indicate to the module that a reminder with a larger offset has already been sent
					if ($reminder_sent) {
						$reminder_invitation = clone $invitation;
						$reminder_invitation->offset = $this_offset;
						$reminder_invitation->reminder = true;
						$this->log('ignoreReminder', (array) $reminder_invitation);
						continue;
					}
					
					$sent_count = $this->countLogs("message=? AND record=? AND sequence=? AND offset=? AND time_of_day=?", [
						'invitationSent',
						$rid,
						$name,
						$this_offset,
						$time_of_day
					]);
					
					$ignore_count = $this->countLogs("message=? AND record=? AND sequence=? AND offset=? AND time_of_day=?", [
						'ignoreReminder',
						$rid,
						$name,
						$this_offset,
						$time_of_day
					]);
					
					if ($sched_time <= $current_time && $sent_count == 0 && $ignore_count == 0 && !$reminder_sent) {
						$reminder_invitation = clone $invitation;
						$reminder_invitation->offset = $this_offset;
						$reminder_invitation->reminder = true;
						$invites["$name $first_sched_time"] = $reminder_invitation;
						
						// setting to true will make module ignore all reminders with lower offsets
						$reminder_sent = true;
					}
				}
			}
		}
		
		return $invites;
	}
	
	// CAT-MH API methods
	public function getAPIUrl() {
		if ($this->getProjectSetting('use_test_api')) {
			return "https://test.cat-mh.com";
		} else {
			return "https://www.cat-mh.com";
		}
	}
	
	public function validateSubjectId($sid) {
		// remove non-alphanumeric characters
		$sid = preg_replace("/\W|_/", "", $sid);
		
		// check for an existing, matching subjectid
		$get_params = [
			"project_id" => $this->getProjectId(),
			"return_format" => "json",
			"fields" => "subjectid",
			"filterLogic" => "[subjectid] = '$sid'"
		];
		$data = json_decode(\REDCap::getData($get_params));
		$found_sid = $data[0]->subjectid;
		
		// if it matches the given subjectid, return the retrieved subjectid
		if ($found_sid === $sid) {
			return $found_sid;
		}
		
		// otherwise return false
		return false;
	}
	
	public function createInterview($args) {
		// args needed: applicationid, organizationid, subjectID, language, timeframeID, tests[]
		$out = [];
		
		// $this->llog("args used to create interview: " . print_r($args, true));
		
		// build request headers and body
		$curlArgs = [];
		$curlArgs['headers'] = [
			"applicationid: " . $args['applicationid'],
			"Accept: application/json",
			"Content-Type: application/json"
		];
		$curlBody = [
			"organizationID" => intval($args['organizationid']),
			"userFirstName" => "Automated",
			"userLastName" => "Creation",
			"subjectID" => $args['subjectID'],
			"numberOfInterviews" => 1,
			// "numberOfInterviews" => sizeof($interviewConfig['tests']),
			"language" => intval($args['language']),
			"timeframeID" => $args['timeframeID'],
			"tests" => $args['tests']
		];
		// prevent sending 0 as timeframeID
		if (empty($args['timeframeID'])) {
			unset ($curlBody['timeframeID']);
		}
		
		$curlArgs['body'] = json_encode($curlBody);
		$curlArgs['post'] = true;
		$curlArgs['address'] = $this->getAPIUrl() . "/portal/secure/interview/createInterview";
		
		// send request via curl
		$curl = $this->curl($curlArgs);
		
		// show error if cURL error occured
		if (!empty($curl['error'])) {
			$out['moduleError'] = true;
			$out['moduleMessage'] = "REDCap couldn't get interview information from CAT-MH API." . "<br />\n" . $curl['error'];
			return $out;
		}
		
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
				$out['labels'][] = $this->getTestLabel(htmlentities($_GET['sequence'], ENT_QUOTES, 'UTF-8'), $arr['type']);
			}
			
			$out['success'] = true;
		} catch (\Exception $e) {
			$out['moduleError'] = true;
			$out['moduleMessage'] = "REDCap couldn't get interview information from CAT-MH API." . "<br />\n" . $e;
		}
		return $out;
	}
	
	public function createInterviewPair($subjectID, $sequence_name) {
		$out = [];
		
		// validate sequence is KCAT
		$seq_index = $this->getKCATSequenceIndex($sequence_name);
		if ($seq_index === false)
			throw new \Exception("Cannot create a new interview pair since this sequence ($sequence_name) isn't configured to be a paired interview.");
		
		// validate subjectID
		if (!$this->getRecordIDBySID($subjectID))
			throw new \Exception("Cannot create a new interview pair since this subjectID ($subjectID) isn't associated with an existing record.");
		
		// ensure system configured
		$orgID = $this->getSystemSetting('organizationid');
		$appID = $this->getSystemSetting('applicationid');
		if (empty($appID) or empty($orgID)) {
			throw new \Exception("Cannot create a new interview pair. Please have the REDCap administrator configure the system-level application and organization IDs for CAT-MH use.");
			return;
		}
		
		// build request headers and body
		$curlArgs = [];
		$curlArgs['headers'] = [
			"applicationid: " . $appID,
			"Accept: application/json",
			"Content-Type: application/json"
		];
		$curlArgs['body'] = [
			"organizationID" => intval($orgID),
			"userFirstName" => "Automated",
			"userLastName" => "Creation",
			"subjectID" => $subjectID,
			"language" => 1,
			"pairType" => 1,
			"primaryTests" => []
		];
		
		// will this interview need optional primary test?
		if ($this->getProjectSetting('include_css')[$seq_index]) {
			$optional_test = new \stdClass();
			$optional_test->type = 'c/ss';
			$curlArgs['body']['primaryTests'][] = $optional_test;
		}
		
		$curlArgs['body'] = json_encode($curlArgs['body']);
		
		$curlArgs['post'] = true;
		$curlArgs['address'] = $this->getAPIUrl() . "/portal/secure/interview/create-pair";
		
		// send request via curl
		$curl = $this->curl($curlArgs);
		
		// show error if cURL error occured
		if (!empty($curl['error'])) {
			throw new \Exception("REDCap couldn't get interview pair information from CAT-MH API." . "<br />\n" . $curl['error']);
		}
		
		// handle response
		try {
			// extract json
			$response = json_decode($curl['body']);
			// $this->llog("creating interviwe pair, catmh response: " . print_r($response, true));
			
			$primary = new \stdClass();
			$primary->interviewID = $response->primaryInterviewID;
			$primary->identifier = $response->primaryIdentifier;
			$primary->signature = $response->primarySignature;
			
			$secondary = new \stdClass();
			$secondary->interviewID = $response->secondaryInterviewID;
			$secondary->identifier = $response->secondaryIdentifier;
			$secondary->signature = $response->secondarySignature;
			
			return [
				'primary' => $primary,
				'secondary' => $secondary
			];
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
		
		// build request headers and body
		$curlArgs = [];
		$curlArgs['headers'] = [
			"Content-Type: application/x-www-form-urlencoded"
		];
		$curlArgs['body'] = "j_username=" . $args['identifier'] . "&" .
			"j_password=" . $args['signature'] . "&" .
			"interviewID=" . $args['interviewID'];
		$curlArgs['post'] = true;
		$curlArgs['address'] = $this->getAPIUrl() . "/interview/signin";
		
		// send request via curl
		$curl = $this->curl($curlArgs);
		
		if (!empty($curl['cookies']['JSESSIONID']) and !empty($curl['cookies']['AWSELB'])) {
			// update security values in interview object
			$interview = $this->getInterview($args['subjectID'], $args['interviewID'], $args['identifier'], $args['signature'], $args['kcat']);
			$interview->jsessionid = $curl['cookies']['JSESSIONID'];
			$interview->awselb = $curl['cookies']['AWSELB'];
			// $this->llog("authInterview: updating interview: " . print_r($interview, true));
			$result = $this->updateInterview($interview);
			
			if (empty($result)) {
				$out['moduleError'] = true;
				$out['moduleMessage'] = "Errors saving authorization values to REDCap. Please contact your program administrator.";
			} else {
				$out['success'] = true;
			}
		} else {
			$out['moduleError'] = true;
			$out['moduleMessage'] = "REDCap failed to retrieve authorization details from the CAT-MH API server for the interview." . "<br>" . json_encode($out, JSON_UNESCAPED_SLASHES + JSON_PRETTY_PRINT);
		}
		
		return $out;
	}
	
	public function startInterview($args) {
		// args required: subjectID, interviewID, identifier, signature
		$out = [];
		
		try {
			$authValues = $this->getAuthValues($args);
			if (!isset($authValues['jsessionid']) or !isset($authValues['awselb'])) {
				throw new \Exception("Auth values not set.");
			}
		} catch (\Exception $e) {
			$out['moduleError'] = true;
			$out['moduleMessage'] = "REDCap couldn't get authorization values from logged interview data -- please contact REDCap administrator.\n<br />";
			return $out;
		}
		
		$curlArgs = [];
		$curlArgs['headers'] = [
			"Accept: application/json",
			"Cookie: JSESSIONID=" . $authValues['jsessionid'] . "; AWSELB=" . $authValues['awselb']
		];
		$curlArgs['address'] = $this->getAPIUrl() . "/interview/rest/interview";
		
		// send request via curl
		$curl = $this->curl($curlArgs);
		
		// handle response
		try {
			$json = json_decode($curl['body'], true);
			if (gettype($json) != 'array') throw new \Exception("json error");
			
			// update timestamp and status for this interview
			$interview = $this->getInterview($args['subjectID'], $args['interviewID'], $args['identifier'], $args['signature'], $args['kcat']);
			$interview->status = 2;
			$interview->timestamp = time();
			$result = $this->updateInterview($interview);
			
			if (empty($result)) {
				$out['moduleError'] = true;
				$out['moduleMessage'] = "Errors saving to REDCap. Please contact your program administrator.";
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
		
		try {
			// $this->llog("getQuestion: getting authvalues in getQuestion");
			$authValues = $this->getAuthValues($args);
			if (empty($authValues['jsessionid']) or empty($authValues['awselb'])) {
				$out['moduleError'] = true;
				$out['moduleMessage'] = "REDCap failed to retrieve the interview authorization data. Please refresh the page in a few moments to try again -- if this error persists, please contact the REDCap administrator.";
			}
		} catch (\Exception $e) {
			$out['moduleError'] = true;
			$out['moduleMessage'] = "REDCap couldn't get authorization values from logged interview data -- please contact REDCap administrator.\n<br />";
		}
		
		$curlArgs = [];
		$curlArgs['headers'] = [
			"Accept: application/json",
			"Cookie: JSESSIONID=" . $authValues['jsessionid'] . "; AWSELB=" . $authValues['awselb']
		];
		$curlArgs['address'] = $this->getAPIUrl() . "/interview/rest/interview/test/question";
		
		// send request via curl
		$curl = $this->curl($curlArgs);
		$out['curl'] = ["body" => $curl["body"]];
		
		// handle response
		try {
			$json = json_decode($curl['body'], true);
			if (strpos($curl['body'], "CAT-MH&trade; Timeout Error") === false) {
				if (gettype($json) != 'array') throw new \Exception("json error");
			} else {
				// timed out, need to send another auth request
				$auth_out = $this->authInterview($args);
				
				if ($auth_out['success']) {
					return $this->getQuestion($args);
				}
			}
			$out['success'] = true;
			
			$questionID = $json['questionID'];
			if ($questionID < 0) {
				$out['needResults'] = true;
			} else {
				// append to response which test type(s) this question belongs to so progress meter can update in the interview interface
				$this->buildQuestionTestMap();
				$out['question_test_types'] = $this->questionTestMap[$questionID];
				$out['qid'] = $questionID;
			}
		} catch (\Exception $e) {
			// $this->llog('exception in getQuestion: ' . $e);
			$out['moduleError'] = true;
			$out['moduleMessage'] = "REDCap failed to retrieve the next question from the CAT-MH API server. This interview may have expired.";
		}
		return $out;
	}
	
	public function submitAnswer($args) {
		// need args: JSESSIONID, AWSELB, questionID, response, duration
		$out = [];
		
		try {
			$authValues = $this->getAuthValues($args);
			if (!isset($authValues['jsessionid']) or !isset($authValues['awselb'])) {
				throw new \Exception("Auth values not set.");
			}
		} catch (\Exception $e) {
			$out['moduleError'] = true;
			$out['moduleMessage'] = "REDCap couldn't get authorization values from logged interview data -- please contact REDCap administrator.\n<br />";
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
		$curlArgs['address'] = $this->getAPIUrl() . "/interview/rest/interview/test/question";
		
		// send request via curl
		$curl = $this->curl($curlArgs);
		
		if ($curl['info']['http_code'] == 200) {
			$out['success'] = true;
		} else {
			$out['moduleError'] = true;
			$out['moduleMessage'] = "REDCap submitted answer but got back a non-OK response from the CAT-MH API server. Try refreshing the page to continue your interview.";
		}
		return $out;
	}
	
	public function endInterview($args) {
		// need args: JSESSIONID, AWSELB
		$out = [];
		
		try {
			$authValues = $this->getAuthValues($args);
			if (!isset($authValues['jsessionid']) or !isset($authValues['awselb'])) {
				throw new \Exception("Auth values not set.");
			}
		} catch (\Exception $e) {
			$out['moduleError'] = true;
			$out['moduleMessage'] = "REDCap couldn't get authorization values from logged interview data -- please contact REDCap administrator.\n<br />";
		}
		
		$curlArgs = [];
		$curlArgs['headers'] = [
			"Accept: application/json",
			"Cookie: JSESSIONID=" . $authValues['jsessionid'] . "; AWSELB=" . $authValues['awselb']
		];
		$curlArgs['address'] = $this->getAPIUrl() . "/interview/signout";
		
		// send request via curl
		$curl = $this->curl($curlArgs);
		
		// handle response
		try {
			if ($curl['cookies']['JSESSIONID'] == $authValues['JSESSIONID'] and $curl['info']['http_code'] == 302) {
				// update redcap record data
				$data = $this->getRecordBySID($args['subjectID']);
				$rid = array_keys($data)[0];
				if(isset($data[$rid]) && is_array($data[$rid])) {
					$record = $data[$rid];
					$eid = array_keys($record)[0];
					if(is_array($record[$eid]) && array_key_exists("cat_mh_data", $record[$eid])) {
						$catmh_data = json_decode($record[$eid]["cat_mh_data"], true);
					}
				}
				
				if(empty($catmh_data)) {
					$catmh_data = [];
				}
				
				if(array_key_exists("interviews", $catmh_data) && is_array($catmh_data["interviews"])) {
					foreach($catmh_data['interviews'] as $i => &$interview) {
						if ($interview['interviewID'] == $args['interviewID'] and $interview['signature'] == $args['signature'] and $interview['identifier'] == $args['identifier']) {
							$interview['status'] = 3;
							$interview['timestamp'] = time();
						}
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
		
		try {
			$authValues = $this->getAuthValues($args);
			if (!isset($authValues['jsessionid']) or !isset($authValues['awselb'])) {
				throw new \Exception("Auth values not set.");
			}
		} catch (\Exception $e) {
			$out['moduleError'] = true;
			$out['moduleMessage'] = "REDCap couldn't get authorization values from logged interview data -- please contact REDCap administrator.\n<br />";
		}
		
		$curlArgs = [];
		$curlArgs['headers'] = [
			"Accept: application/json",
			"Cookie: JSESSIONID=" . $authValues['jsessionid'] . "; AWSELB=" . $authValues['awselb']
		];
		$curlArgs['address'] = $this->getAPIUrl() . "/interview/rest/interview/results?itemLevel=1";
		
		// send request via curl
		$curl = $this->curl($curlArgs);
		$out['curl'] = ["body" => $curl["body"]];
		
		// decode curl body
		$results = json_decode($curl['body'], true);
		
		// update redcap record data
		$interview = $this->getInterview($args['subjectID'], $args['interviewID'], $args['identifier'], $args['signature'], $args['kcat']);
		$interview->results = $results;
		$interview->status = 4;
		$interview->timestamp = time();
		
		$result = $this->updateInterview($interview);
		$sequence = $interview->sequence;
		$testTypes = $interview->types;
		
		if (empty($result)) {
			$out['moduleError'] = true;
			$out['moduleMessage'] = "Errors saving to REDCap. Please contact your program administrator.";
			return $out;
		}
		
		// need config to see if we should send results back to user or not
		$keepResults = [];
		$projectSettings = $this->getProjectSettings();
		
		## Module not configured
		if(!isset($projectSettings['sequence'])) {
			$out['moduleError'] = true;
			$out['moduleMessage'] = "Module not configured with a sequence";
			return $out;
		}
		
		foreach ($projectSettings['sequence'] as $j => $seqName) {
			if ($sequence == $seqName) {
				foreach($testTypes as $testType) {
					if ($projectSettings[$testType . '_show_results'][$j] == 1) {
						$keepResults[$testType] = true;
					}
				}
				break;
			}
		}
		
		// now remove results from curl response as necessary
		foreach ($results['tests'] as &$test) {
			$abbreviation = strtolower($test['type']);
			$test['label'] = $this->getTestLabel($sequence, $abbreviation);
			
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
		$curlArgs['address'] = $this->getAPIUrl() . "/portal/secure/interview/status";
		
		// send request via curl
		$curl = $this->curl($curlArgs);
		
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
		
		try {
			$authValues = $this->getAuthValues($args);
			if (!isset($authValues['jsessionid']) or !isset($authValues['awselb'])) {
				throw new \Exception("Auth values not set.");
			}
		} catch (\Exception $e) {
			$out['moduleError'] = true;
			$out['moduleMessage'] = "REDCap couldn't get authorization values from logged interview data -- please contact REDCap administrator.\n<br />";
		}
		
		// build request headers and body
		$curlArgs = [];
		$curlArgs['headers'] = [
			"Cookie: JSESSIONID=" . $authValues['jsessionid'] . "; AWSELB=" . $authValues['awselb']
		];
		$curlArgs['body'] = [];
		$curlArgs['post'] = true;
		$curlArgs['address'] = $this->getAPIUrl() . "/interview/secure/breakLock";
		
		// send request via curl
		$curl = $this->curl($curlArgs);
		
		// get location
		preg_match_all('/^Location:\s([^\n]*)$/m', $curl['response'], $matches);
		$location = trim($matches[1][0]);
		
		if ($curl['info']['http_code'] == 302 and $location == $this->getAPIUrl() . "/interview/secure/index.html") {
			$out['success'] = true;
		} else {
			$out['moduleError'] = true;
			$out['moduleMessage'] = "This interview is locked and REDCap was unable to break the lock via the CAT-MH API.";
		}
		
		return $out;
	}
	
	private function generateSubjectID() {
		// generate subject ID
		$subjectID = "";
		$sidDomain = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ1234567890";
		$domainLength = strlen($sidDomain);
		for ($i = 0; $i < 32; $i++) {
			$subjectID .= $sidDomain[rand(0, $domainLength - 1)];
		}
		return $subjectID;
	}
}
