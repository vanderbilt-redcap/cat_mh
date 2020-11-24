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
		'sa' => "sa",
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
		'sa' => "Substance Abuse",
		'ptsd' => "Post-Traumatic Stress Disorder",
		'c-ssrs' => "C-SSRS Suicide Screen",
		'ss' => "Suicide Scale",
		'phq-9' => "PHQ-9",
		'sdoh' => "Social Determinants of Health",
		'a/adhd' => "Adult ADHD",
		'psy-s' => "Psychosis - Self-Report"
	];
	public $dashboardColumns = [
		'Record ID',
		'Completed',
		'Within Window',
		'Date Scheduled',
		'Date to Complete',
		'Date Taken',
		'Elapsed Time',
		'Missed Surveys',
		'Acknowledged'
	];
	
	public $api_host_name = "test.cat-mh.com";		// test
	// public $api_host_name = "www.cat-mh.com";	// non-test
	
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
		
		$subjectID = $this->generateSubjectID();
		
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
		header('Location: ' . $page, true, 302);
		$this->exitAfterHook();
	}
	
	// crons
	public function emailer_cron($cronInfo=null, $current_time=null) {
		$originalPid = $_GET['pid'];
		foreach($this->framework->getProjectsWithModuleEnabled() as $localProjectId) {
			$_GET['pid'] = $localProjectId;
			
			if (empty($current_time))
				$current_time = time();
			
			// $this->llog("emailer_cron current_time date: " . date("Y-m-d H:i:s", $current_time));
			// $this->sendInvitations($current_time);
			
			$result = $this->queryLogs("SELECT timestamp WHERE message='cron_ran_today'");
			$cron_ran_today = null;
			while ($row = db_fetch_assoc($result)) {
				$date1 = date("Y-m-d");
				$date2 = date("Y-m-d", strtotime($row['timestamp']));
				if ($date1 == $date2) {
					$cron_ran_today = true;
					break;
				}
			}
			if (!$cron_ran_today) {
				\REDCap::logEvent("CAT-MH External Module", "Ran 'emailer_cron' method today", NULL, NULL, NULL, $this->getProjectId());
				$this->log("cron_ran_today");
			}
		}
		$_GET['pid'] = $originalPid;
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
	
	public function getSequenceIndex($seq_name) {
		foreach ($this->getProjectSetting('sequence') as $i => $name) {
			if ($name === $seq_name)
				return $i;
		}
		return false;
	}
	
	public function getTestLabel($seq_name, $test) {
		$test = strtolower(preg_replace("[\W]", "", $test));
		$abbrev = $this->convertTestAbbreviation[$test];
		
		$index = $this->getSequenceIndex($seq_name);
		$label = $this->testTypes[$abbrev];
		$alt_label = $this->getProjectSetting($test . "_label")[$index];
		if (empty($alt_label)) {
			return $label;
		}
		
		return $alt_label;
	}
	
	public function getInterview($sequence="", $sched_dt="", $sid="") {
		if (empty($sequence))
			$sequence = $_GET['sequence'];
		if (empty($sched_dt))
			$sched_dt = $_GET['sched_dt'];
		if (empty($sid))
			$sid = $_GET['sid'];
		$sid = preg_replace("/\W|_/", '', $sid);
		
		$record = $this->getRecordBySID($sid);
		$data = json_decode(reset(reset($record))['cat_mh_data']);
		foreach ($data->interviews as $interview) {
			if ($interview->scheduled_datetime == $sched_dt AND $interview->sequence == $sequence) {
				$interview->subjectID = $sid;
				return $interview;
			}
		}
	}
	
	public function makeInterview() {
		// If no sequence given in url parameters, default to first sequence configured
		$projectSettings = $this->getProjectSettings();
		$sequence = $_GET['sequence'];
		$sid = $_GET['sid'];
		$sched_dt = $_GET['sched_dt'];
		
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
		$interview['subjectID'] = $sid;
		
		if (!isset($interview['moduleError'])) {
			// save newly created interview info in redcap
			$data = $this->getRecordBySID($sid);
			$rid = array_keys($data)[0];
			$eid = array_keys($data[$rid])[0];
			$catmh_data = json_decode($data[$rid][$eid]['cat_mh_data'], true);
			
			// remove previous unfinished interviews with same seq/datetime
			$max_i = count($catmh_date['interviews']) - 1;
			for ($i = $max_i; $i > 0; $i--) {
				if ($interview['sequence'] == $sequence and $interview['scheduled_datetime'] == $sched_dt and $interview['status'] != 4)
					array_splice($catmh_date['interviews'], $i, 1);
			}
			
			$interview2 = [
				"sequence" => $sequence,
				"scheduled_datetime" => $sched_dt,
				"interviewID" => $interview['interviewID'],
				"identifier" => $interview['identifier'],
				"signature" => $interview['signature'],
				"types" => $interview['types'],
				"labels" => $interview['labels'],
				"status" => 1,
				"timestamp" => time(),
				"subjectID" => $sid
			];
			$catmh_data['interviews'][] = $interview2;
			
			$data[$rid][$eid]['cat_mh_data'] = json_encode($catmh_data);
			$result = \REDCap::saveData($this->getProjectId(), 'array', $data);
			if (!empty($result['errors'])) {
				echo("<pre>");
				echo("Errors saving to REDCap:\n");
				print_r($result);
				echo("<pre>");
				return false;
			}
			return $interview2;
		} else {
			echo("CAT-MH encountered an error with the API:<br />" . $interview['moduleMessage']);
			return false;
		}
	}
	
	public function getSequenceStatus($record, $seq_name, $datetime) {
		$params = [
			"project_id" => $this->getProjectId(),
			"return_format" => "json",
			"records" => $record,
			"fields" => ["cat_mh_data", "record_id"]
		];
		$data = json_decode(\REDCap::getData($params));
		$catmh = json_decode($data[0]->cat_mh_data);
		$interviews = $catmh->interviews;
		
		foreach ($interviews as $i => $interview) {
			if ($interview->sequence == $seq_name and $interview->scheduled_datetime == $datetime) {
				return $interview->status;
			}
		}
		return false;
	}
	
	function initRecord(&$record) {
		if (gettype($record) !== 'object')
			throw new \Exception("First argument to sendEmails must be an object -- type: " . gettype($record));
		if (empty($rid = $record->{$this->getRecordIdField()}))
			throw new \Exception("\$record argument is missing a record ID field (in initRecord)");
		
		$record->subjectid = $this->generateSubjectID();
		$record->cat_mh_data = json_encode(["interviews" => []]);
		$data = json_encode([$record]);
		$save_results = \REDCap::saveData($this->getProjectId(), 'json', $data, 'overwrite');
		\REDCap::logEvent("CAT-MH External Module", "Initialized subject ID and CAT-MH interview data field for record: $rid", NULL, NULL, NULL, $this->getProjectId());
	}
	
	public function llog($text) {
		// echo "<pre>$text\n</pre>";
		
		if ($this->log_ran) {
			file_put_contents("C:/vumc/log.txt", "$text\n", FILE_APPEND);
		} else {
			file_put_contents("C:/vumc/log.txt", "starting CAT_MH_CHA log:\n$text\n");
			$this->log_ran = true;
		}
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
		return $this->removeLogs("name='$seq_name' AND offset='$offset' AND time_of_day='$time_of_day'");
	}
	
	public function cleanMissingSeqsFromSchedule() {
		$result = $this->queryLogs("SELECT message, name, offset, time_of_day, sent WHERE message='scheduleSequence'");
		$valid_seq_names = $this->getProjectSetting('sequence');
		while ($row = db_fetch_array($result)) {
			$seq_name = $row['name'];
			if (array_search($seq_name, $valid_seq_names, true) === false) {
				// this is no longer a valid sequence to be scheduled since it was taken out of configuration
				$this->llog("this is no longer a valid sequence to be scheduled since it was taken out of configuration : $seq_name");
				$this->removeLogs("message='scheduleSequence' AND name='$seq_name'");
			}
		}
	}
	
	public function getScheduledSequences() {
		if (!$this->sequences) {
			$this->cleanMissingSeqsFromSchedule();
			
			$result = $this->queryLogs("SELECT message, name, offset, time_of_day, sent WHERE message='scheduleSequence'");
			
			$sequences = [];
			while ($row = db_fetch_array($result)) {
				$sequences[] = ['', $row['name'], $row['offset'], $row['time_of_day']];
			}
			
			$this->sequences = $sequences;
		}
		
		return $this->sequences;
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
		
		$sid = $_GET['sid'];
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
		
		$seq = urlencode($_GET['sequence']);
		$sched_dt = urlencode($_GET['sched_dt']);
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
		$this->llog("sendInvitations");
		if (empty($enrollment_field_name = $this->getProjectSetting('enrollment_field')))
			return;
		
		// fetch all records
		$params = [
			'project_id' => $this->getProjectId(),
			'return_format' => 'json',
			'fields' => [
				$this->getRecordIdField(),
				"$enrollment_field_name",
				'subjectid',
				'catmh_email'
			]
		];
		$data = json_decode(\REDCap::getData($params));
		
		$this->llog("sendInvitations data: " . print_r($data, true));
		
		// prepare email invitation using project settings
		$from_address = $this->getProjectSetting('email-from');
		if (empty($from_address)) {
			global $project_contact_email;
			$from_address = $project_contact_email;
		}
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
		
		$email = new \Message();
		$this->llog("sendInvitations setting from address: " . $from_address);
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
			
			// validate record values
			if (empty($record->catmh_email)) {
				$result_log_message .= "No emails sent -- empty [catmh_email] field.";
				continue;
			}
			if (empty($rid = $record->{$this->getRecordIdField()})) {
				$result_log_message .= "No emails sent -- missing Record ID.";
				continue;
			}
			if (!$enrollment_timestamp = strtotime($record->{$enrollment_field_name})) {
				$result_log_message .= "No emails sent -- Couldn't convert enrollment date/time to a valid timestamp integer. Enrollment Date/Time: " . json_encode($record->{$enrollment_field_name});
				continue;
			}
			if (empty($sid = $record->subjectid)) {
				// create cat_mh_data and subjectid
				$this->initRecord($record);
				if (empty($sid = $record->subjectid))
					throw new \Exception("Couldn't create [subjectid] field value.");
			}
			
			$invitations_to_send = $this->getInvitationsDue($record, $current_time);
			if (empty($invitations_to_send)) {
				// $result_log_message .= "No emails sent -- no invitations due."; // trivial case
				$this->llog("sendInvitations - no invitations due for record $rid");
				continue;
			}
			$this->llog("sendInvitations - invitations due for record $rid:\n" . print_r($invitations_to_send, true));
			
			// at least one participant with invitations to send
			$actually_log_message = true;
			
			// make urls and links to pipe into email body
			$sequences_already_included = [];
			$urls = [];
			$links = [];
			$base_url = $this->getUrl("interview.php") . "&NOAUTH&sid=$sid";
			foreach ($invitations_to_send as $sequence_name => $invitation) {
				if (!isset($sequences_already_included[$sequence_name])) {
					$sequences_already_included[$sequence_name] = true;
					$seq_url = $base_url . "&seq=" . urlencode($sequence_name) . "&sched_dt=" . urlencode($invitation->sched_dt);
					$seq_link = "<a href=\"$seq_url\">CAT-MH Interview - $sequence_name</a>";
					$urls[] = $seq_url;
					$links[] = $seq_link;
				}
			}
			
			// prepare email body by replacing [interview-links] and [interview-urls] (or appending)
			$participant_email_body = $email_body;
			if ($append_links) {
				$participant_email_body .= "<br>" . implode($links, "<br>");
			} else {
				$participant_email_body = str_replace("[interview-links]", implode($links, "<br>"), $participant_email_body);
			}
			if ($append_urls) {
				$participant_email_body .= "<br>" . implode($urls, "<br>");
			} else {
				$participant_email_body = str_replace("[interview-urls]", implode($urls, "<br>"), $participant_email_body);
			}
			$email->setBody($participant_email_body);
			$this->llog("sendInvitations - setting email to address for record $rid:\n" . $record->catmh_email);
			$email->setTo($record->catmh_email);
			
			$success = $email->send();
			if ($success) {
				$result_log_message .= "Record $rid: Sent interview invitation email\n";
				foreach($invitations_to_send as $invitation) {
					$this->log('invitationSent', (array) $invitation);
				}
			} else {
				$result_log_message .= "Record $rid: Failed to send email (" . $email->ErrorInfo . ")\n";
			}
		}
		
		if ($actually_log_message) {
			$this->llog("CAT-MH External Module:\n " . $result_log_message);
			\REDCap::logEvent("CAT-MH External Module", $result_log_message, NULL, NULL, NULL, $this->getProjectId());
		}
	}
	
	public function getInvitationsDue($record, $current_time) {
		$rid = $record->{$this->getRecordIdField()};
		$this->llog("getInvitationsDue - $rid, $current_time");
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
			// $this->llog("about to use enrollment_timestamp: $enrollment_timestamp");
			$enroll_date = date("Y-m-d", $enrollment_timestamp);
			// $this->llog("about to use enroll_date: $enroll_date");
			$enroll_and_time = "$enroll_date " . $time_of_day;
			// $this->llog("about to use enroll_and_time: $enroll_and_time");
			// $this->llog("about to use offset: $offset");
			$sched_time = strtotime("+$offset days", strtotime($enroll_and_time));
			$first_sched_time = $sched_time;
			
			// check if interview is completed
			if ($this->getSequenceStatus($rid, $name, $sched_time) == 4) {
				$this->llog("getInvitationsDue sequence $name already complete");
				continue;
			}
			
			// if no invitation sent, send one
			$sent_count = $this->countLogs("message=? AND record=? AND sequence=? AND offset=? AND time_of_day=?", [
				'invitationSent',
				$rid,
				$name,
				$offset,
				$time_of_day
			]);
			
			// create invitation object
			$invitation = new \stdClass();
			$invitation->record = $rid;
			$invitation->sequence = $name;
			$invitation->offset = $offset;
			$invitation->time_of_day = $time_of_day;
			$invitation->sched_dt = $first_sched_time;
			
			$this->llog("getInvitationsDue comparing sched_time $sched_time with current timestamp $current_time, used enroll_date ($enroll_date), sent_count: $sent_count, seq: " . print_r($seq, true));
			if ($sched_time <= $current_time && $sent_count === 0) {
				$invites[] = $invitation;
				$this->llog("getInvitationsDue - added invitation [$name] => $first_sched_time");
			}
			
			// send reminders if applicable
			if ($reminder_settings->enabled) {
				$frequency = $reminder_settings->frequency;
				$duration = $reminder_settings->duration;
				$delay = $reminder_settings->delay;
				for ($offset = $delay; $offset <= $delay + $duration - 1; $offset += $frequency) {
					// recalculate timestamp with reminder offset, to see if current time is after it
					$sched_time = strtotime("+$offset days", strtotime($enroll_and_time));
					$sent_count = $this->countLogs("message=? AND record=? AND sequence=? AND offset=? AND time_of_day=?", [
						'invitationSent',
						$rid,
						$name,
						$offset,
						$time_of_day
					]);
					$this->llog("getInvitationsDue comparing sched_time $sched_time with current timestamp $current_time for reminder - sent_count: $sent_count, seq: " . print_r($seq, true));
					if ($sched_time <= $current_time && $sent_count === 0) {
						$invites[] = $invitation;
						$this->llog("getInvitationsDue - added reminder invitation [$name] => $first_sched_time");
					}
				}
			}
		}
		
		return $invites;
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
		$curlArgs['address'] = $this->testAPI ? $testAddress : "https://" . $this->api_host_name . "/portal/secure/interview/createInterview";
		
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
				$out['labels'][] = $this->getTestLabel($_GET['sequence'], $arr['type']);
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
		$curlArgs['address'] = $this->testAPI ? $testAddress : "https://" . $this->api_host_name . "/interview/signin";
		
		// send request via curl
		$curl = $this->curl($curlArgs);
		
		if (isset($curl['cookies']['JSESSIONID']) and isset($curl['cookies']['AWSELB'])) {
			// update redcap record data
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
				$out['moduleMessage'] = "Errors saving to REDCap:\n" . print_r($result, true);
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
			$out['moduleMessage'] = "REDCap couldn't get authorization values from logged interview data -- please contact REDCap administrator.\n<br />$e";
			return $out;
		}
		
		$curlArgs = [];
		$curlArgs['headers'] = [
			"Accept: application/json",
			"Cookie: JSESSIONID=" . $authValues['jsessionid'] . "; AWSELB=" . $authValues['awselb']
		];
		$testAddress = "http://localhost/redcap/redcap_v8.10.2/ExternalModules/?prefix=cat_mh&page=testEndpoint&pid=" . $this->getProjectId() . "&action=" . __FUNCTION__;
		$curlArgs['address'] = $this->testAPI ? $testAddress : "https://" . $this->api_host_name . "/interview/rest/interview";
		
		// send request via curl
		$curl = $this->curl($curlArgs);
		
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
		$curlArgs['address'] = $this->testAPI ? $testAddress : "https://" . $this->api_host_name . "/interview/rest/interview/test/question";
		
		// send request via curl
		$curl = $this->curl($curlArgs);
		$out['curl'] = ["body" => $curl["body"]];
		
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
		$curlArgs['address'] = $this->testAPI ? $testAddress : "https://" . $this->api_host_name . "/interview/rest/interview/test/question";
		
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
			$out['moduleMessage'] = "REDCap couldn't get authorization values from logged interview data -- please contact REDCap administrator.\n<br />$e";
		}
		
		$curlArgs = [];
		$curlArgs['headers'] = [
			"Accept: application/json",
			"Cookie: JSESSIONID=" . $authValues['jsessionid'] . "; AWSELB=" . $authValues['awselb']
		];
		$testAddress = "http://localhost/redcap/redcap_v8.10.2/ExternalModules/?prefix=cat_mh&page=testEndpoint&pid=" . $this->getProjectId() . "&action=" . __FUNCTION__;
		$curlArgs['address'] = $this->testAPI ? $testAddress : "https://" . $this->api_host_name . "/interview/signout";
		
		// send request via curl
		$curl = $this->curl($curlArgs);
		
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
		$curlArgs['address'] = $this->testAPI ? $testAddress : "https://" . $this->api_host_name . "/interview/rest/interview/results?itemLevel=1";
		
		// send request via curl
		$curl = $this->curl($curlArgs);
		$out['curl'] = ["body" => $curl["body"]];
		
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
		
		// get project/system configuration information
		$config = $this->getInterviewConfig($args['instrument']);
		
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
		$curlArgs['address'] = $this->testAPI ? $testAddress : "https://" . $this->api_host_name . "/portal/secure/interview/status";
		
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
		$curlArgs['address'] = $this->testAPI ? $testAddress : "https://" . $this->api_host_name . "/interview/secure/breakLock";
		
		// send request via curl
		$curl = $this->curl($curlArgs);
		
		// get location
		preg_match_all('/^Location:\s([^\n]*)$/m', $curl['response'], $matches);
		$location = trim($matches[1][0]);
		
		if ($curl['info']['http_code'] == 302 and $location == "https://" . $this->api_host_name . "/interview/secure/index.html") {
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

if ($_SERVER['REQUEST_METHOD'] == "POST") {
	$catmh = new CAT_MH_CHA();
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
			
			if ($out['success']) {
				$catmh->sendProviderEmail();
			}
			
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