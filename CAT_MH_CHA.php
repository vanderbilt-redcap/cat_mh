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
	
	// public $debug = true;
	// public $api_host_name = "test.cat-mh.com";		// test
	public $api_host_name = "www.cat-mh.com";	// non-test
	
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
	public function emailer_cron() {
		$originalPid = $_GET['pid'];
		foreach($this->framework->getProjectsWithModuleEnabled() as $localProjectId) {
			$_GET['pid'] = $localProjectId;
			$this->sendScheduledSequenceEmails();
			$this->sendReminderEmails();
			
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
		$pid = $this->getProjectId();
		$data = \REDCap::getData($pid, 'array', NULL, NULL, NULL, NULL, NULL, NULL, NULL, "[subjectid]=\"$sid\"");
		return $data;
	}
	
	public function getRecordIDBySID($sid) {
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
	
	public function getInterview() {
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
		$this->llog("interview from module->createInterview(\$args): " . print_r($interview, true));
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
			
			$catmh_data['interviews'][] = [
				"sequence" => $sequence,
				"scheduled_datetime" => $sched_dt,
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
	
	function llog($text) {
		if ($this->debug !== true)
			return;
		echo "<pre>$text\n</pre>";
	}
	
	// dashboard
	function getDashboardColumns() {
		$columns = ['Record ID'];
		
		// append a column name for each scheduled sequence
		$seqs = $this->getScheduledSequences();
		foreach ($seqs as $i => $seq) {
			$columns[] = $seq[2] . "<br>" . $seq[1];
		}
		
		return $columns;
	}
	
	// scheduling
	function scheduleSequence($seq_name, $datetime) {
		// ensure not duplicate scheduled
		$result = $this->queryLogs("SELECT message, name, scheduled_datetime WHERE message='scheduleSequence' AND name='$seq_name' AND scheduled_datetime='$datetime'");
		if ($result->num_rows != 0) {
			return [false, "This sequence is already scheduled for this date/time"];
		}
		
		$log_id = $this->log("scheduleSequence", [
			"name" => $seq_name,
			"scheduled_datetime" => $datetime,
			"sent" => false
		]);
		
		if (!empty($log_id)) {
			return [true, $log_id];
		} else {
			return [false, "CAT-MH module failed to schedule sequence (log insertion failed)"];
		}
	}
	
	function unscheduleSequence($seq_name, $datetime) {
		// removes associated invitations AND reminders
		return $this->removeLogs("name='$seq_name' AND scheduled_datetime='$datetime'");
	}
	
	function markScheduledSequenceAsSent($sequence) {
		$this->llog("marking sequence as sent: " . print_r($sequence, true));
		
		$log_id = $sequence['log_id'];
		$datetime = $sequence['scheduled_datetime'];
		$name = $sequence['name'];
		
		$this->removeLogs("message='scheduleSequence' AND log_id=$log_id");
		$this->log("scheduleSequence", [
			"name" => $name,
			"scheduled_datetime" => $datetime,
			"sent" => true
		]);
		return true;
	}
	
	function getScheduledSequences() {
		$result = $this->queryLogs("SELECT message, name, scheduled_datetime, sent WHERE message='scheduleSequence' ORDER BY scheduled_datetime asc");
		
		$sequences = [];
		while ($row = db_fetch_array($result)) {
			$sequences[] = ['', $row['scheduled_datetime'], $row['name']];
		}
		
		// $this->llog('scheduled seqs: ' . print_r($sequences, true));
		
		return $sequences;
	}
	
	function sendScheduledSequenceEmails() {
		// determine which sequences need to be sent this minute
		$ymd_hi = date("Y-m-d H:i");
		$seq_logs = $this->queryLogs("SELECT message, name, scheduled_datetime, sent, log_id WHERE message='scheduleSequence' and sent=false ORDER BY scheduled_datetime desc");
		
		$sequences = [];
		$sequenceURLs = [];
		
		// add sequences that
			// 1 - haven't been sent
			// 2 - whose scheduled_datetime are before time() (now)
		$ts_now = time();
		while ($row = db_fetch_assoc($seq_logs)) {
			$seq_sched_ts = strtotime($row['scheduled_datetime']);
			if ($row['message'] == 'scheduleSequence' and !$row['sent'] and $ts_now > $seq_sched_ts) {
				$sequences[] = $row['name'];
				$sequenceURLs[] = $this->getUrl("interview.php") . "&NOAUTH&sequence=" . urlencode($row['name']) . "&sched_dt=" . urlencode($row['scheduled_datetime']);
				$this->markScheduledSequenceAsSent($row);
			}
		}
		
		// $this->llog("\$sequences: " . print_r($sequences, true));
		
		// return early if there are no sequences to send invitations for
		if (empty($sequences))
			return;
		
		// prepare email invitation using project settings
		$email = new \Message();
		$from_address = $this->getProjectSetting('email-from');
		if (empty($from_address)) {
			global $project_contact_email;
			$from_address = $project_contact_email;
		}
		$email->setFrom($from_address);
		
		$email_subject = "CAT-MH Interview Invitation";
		if (!empty($this->getProjectSetting('email-subject')))
			$email_subject = $this->getProjectSetting('email-subject');
		$email->setSubject($email_subject);
		
		$email_body = $this->getProjectSetting('email-body');
		
		// prepare redcap log message
		$result_log_message = "Sending scheduled sequence invitations ($ymd_hi)\n";
		$result_log_message .= "Sequences: " . implode(" ", $sequences) . "\n";
		$result_log_message .= "Email Subject: " . $email_subject . "\n";
		
		// if there's no [interview-urls/links] then remember not to replace, but to append links/urls
		$append_links = false;
		if (strpos($email_body, "[interview-links]") === false)
			$append_links = true;
		$append_urls = false;
		if (strpos($email_body, "[interview-urls]") === false)
			$append_urls = true;
		
		// iterate through participants
		$params = [
			"project_id" => $this->getProjectId(),
			"return_format" => "array",
			"fields" => ["catmh_email", "cat_mh_data", "record_id", "subjectid"]
		];
		$data = \REDCap::getData($this->getProjectId(), 'array');
		foreach($data as $rid => $record) {
			unset($subjectID, $eid, $addressTo, $cat_mh_data, $save_results, $participantURLs, $participantLinks, $success);
			
			// ensure we have an email value to use
			$eid = array_keys($record)[0];
			$email_address_field = 'catmh_email';
			$addressTo = $record[$eid][$email_address_field];
			if (empty($addressTo)) {
				$result_log_message .= "Record $rid: Skipping participant (empty [$email_address_field] field)\n";
				continue;
			}
			$email->setTo($addressTo);
			
			// generate subject ID and interviews for [cat_mh_data] if missing
			if (empty($record[$eid]['subjectid'])) {
				$subjectID = $this->generateSubjectID();
				$cat_mh_data = json_encode(["interviews" => []]);
				$record[$eid]["subjectid"] = $subjectID;
				$record[$eid]["cat_mh_data"] = $cat_mh_data;
				$save_results = \REDCap::saveData($this->getProjectId(), 'array', [$record]);
			}
			if (empty($subjectID))
				$subjectID = $record[$eid]["subjectid"];
			// ensure we have a subjectID by this point
			if (empty($subjectID)) {
				$result_log_message .= "Record $rid: Skipping participant (failed to generate required subjectID)\n";
				continue;
			}
			
			// prepare participant-specific interview URLs and links
			$participantURLs = [];
			$participantLinks = [];
			foreach($sequenceURLs as $i => $url) {
				$url_with_sid = $url . "&sid=$subjectID";
				$participantURLs[$i] = $url_with_sid;
				$participantLinks[$i] = "<a href=\"$url_with_sid\">CAT-MH Interview ($i)</a>";
			}
			
			// prepare email body by replacing [interview-links] and [interview-urls] (or appending)
			$participant_email_body = $email_body;
			if ($append_links) {
				$participant_email_body .= "<br>" . implode($participantLinks, "<br>");
			} else {
				$participant_email_body = str_replace("[interview-links]", implode($participantLinks, "<br>"), $participant_email_body);
			}
			if ($append_urls) {
				$participant_email_body .= "<br>" . implode($participantURLs, "<br>");
			} else {
				$participant_email_body = str_replace("[interview-urls]", implode($participantURLs, "<br>"), $participant_email_body);
			}
			$email->setBody($participant_email_body);
			
			$success = $email->send();
			// $this->llog("success: " . print_r($success, true));
			if ($success) {
				$result_log_message .= "Record $rid: Sent interview invitation email\n";
			} else {
				$result_log_message .= "Record $rid: Failed to send email (" . $email->ErrorInfo . ")\n";
			}
		}
		\REDCap::logEvent("CAT-MH External Module", $result_log_message, NULL, NULL, NULL, $this->getProjectId());
	}
	
	function setReminderSettings($settings) {
		$this->removeLogs("message='reminderSettings'");
		return $this->log("reminderSettings", (array) $settings);
	}
	
	function getReminderSettings() {
		return db_fetch_assoc($this->queryLogs("SELECT message, enabled, frequency, duration, delay WHERE message='reminderSettings'"));
	}
	
	function clearQueuedReminderEmails() {
		return $this->removeLogs("message='scheduleReminder' and sent=false");		// need to keep log sent reminders
	}
	
	function queueAllReminderEmails() {
		// use reminder email settings to queue
		$rem_settings = $this->getReminderSettings();
		$this->llog("reminder settings:" . print_r($rem_settings, true));
		
		// check if reminder emails disabled
		if (empty($rem_settings['enabled']) or empty($rem_settings['duration']) or empty($rem_settings['frequency']))
			return;
		
		$sequences = $this->getScheduledSequences();
		// $this->llog("sequences:" . print_r($sequences, true));
		
		foreach ($sequences as $seq_i => $seq_arr) {
			$seq_datetime = $seq_arr[1];
			$seq_name = $seq_arr[2];
			
			$this->llog("setting reminder emails for seq/datetime: $seq_name / $seq_datetime");
			
			// delay at least 1 day
			$delay = 1;
			if (!empty($rem_settings['delay'])) {
				$delay = (int) $rem_settings['delay'];
			}
			
			for ($day_offset = $delay; $day_offset < $rem_settings['duration'] + $delay; $day_offset += $rem_settings['frequency']) {
				$next_datetime = date("Y-m-d H:i", strtotime($seq_datetime . " +" . $day_offset . " days"));
				$this->llog("\$next_datetime: $next_datetime");
				
				// if a reminder for this sequence/sched_time/rem_time already exists, skip
				$result = $this->queryLogs("SELECT message, name, scheduled_datetime, reminder_datetime WHERE message='scheduleReminder' and name='$seq_name' and scheduled_datetime='$seq_datetime' and reminder_datetime='$next_datetime'");
				if ($result->num_rows != 0) {
					$this->llog('skipping a reminder log due to one existing: ' . print_r($result, true));
					continue;
				}
				
				$log_id = $this->log("scheduleReminder", [
					"name" => $seq_name,
					"scheduled_datetime" => $seq_datetime,
					"reminder_datetime" => $next_datetime,
					"sent" => false
				]);
				if (!$log_id) {
					$this->llog("error scheduling reminder");
				}
			}
		}
		
		$result = $this->queryLogs("SELECT message WHERE message='scheduleReminder'");
		while ($reminder = db_fetch_assoc($result)) {
			"reminder set: " . print_r($reminder, true);
		}
	}
	
	function markReminderEmailAsSent($reminder) {
		$this->llog("marking reminder as sent: " . print_r($reminder, true));
		
		$log_id = $reminder['log_id'];
		$sched_time = $reminder['scheduled_datetime'];
		$reminder_time = $reminder['reminder_datetime'];
		$name = $reminder['name'];
		
		$this->removeLogs("message='scheduleReminder' AND log_id=$log_id");
		$this->log("scheduleReminder", [
			"name" => $name,
			"scheduled_datetime" => $sched_time,
			"reminder_datetime" => $reminder_time,
			"sent" => true
		]);
		return true;
	}
	
	function sendProviderEmail() {
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
	
	function sendReminderEmails() {
		// determine which sequences need to be sent this minute
		$ymd_hi = date("Y-m-d H:i");
		$reminders = $this->queryLogs("SELECT message, name, scheduled_datetime, reminder_datetime, sent, log_id WHERE message='scheduleReminder' and sent=false ORDER BY timestamp desc");
		
		$sequences = [];
		$sequenceURLs = [];
		$sequenceScheduledDatetimes = [];
		$ts_now = time();
		while ($row = db_fetch_assoc($reminders)) {
			// make double sure
			if ($row['message'] == 'scheduleReminder' and !$row['sent'] and $ts_now > strtotime($row['reminder_datetime'])) {
				$sequences[] = $row['name'];
				$sequenceScheduledDatetimes[] = $row['scheduled_datetime'];
				$sequenceURLs[] = $this->getUrl("interview.php") . "&NOAUTH&sequence=" . urlencode($row['name']) . "&sched_dt=" . urlencode($row['scheduled_datetime']);
				$this->markReminderEmailAsSent($row);
			}
		}
		
		// $this->llog("\$sequences: " . print_r($sequences, true));
		
		// return early if there are no sequences to send invitations for
		if (empty($sequences)) {
			return;
		}
		
		// prepare email invitation using project settings
		$email = new \Message();
		$from_address = $this->getProjectSetting('email-from');
		if (empty($from_address)) {
			global $project_contact_email;
			$from_address = $project_contact_email;
		}
		$email->setFrom($from_address);
		
		$email_subject = "CAT-MH Interview Reminder";
		if (!empty($this->getProjectSetting('reminder-email-subject')))
			$email_subject = $this->getProjectSetting('reminder-email-subject');
		$email->setSubject($email_subject);
		
		$email_body = $this->getProjectSetting('reminder-email-body');
		
		// prepare redcap log message
		$result_log_message = "Sending reminder emails ($ymd_hi)\n";
		$result_log_message .= "Sequences: " . implode(" ", $sequences) . "\n";
		$result_log_message .= "Email Subject: " . $email_subject . "\n";
		
		// if there's no [interview-urls/links] then remember not to replace, but to append links/urls
		$append_links = false;
		if (strpos($email_body, "[interview-links]") === false)
			$append_links = true;
		$append_urls = false;
		if (strpos($email_body, "[interview-urls]") === false)
			$append_urls = true;
		
		// iterate through participants
		$params = [
			"project_id" => $this->getProjectId(),
			"return_format" => "array",
			"fields" => ["catmh_email", "cat_mh_data", "record_id", "subjectid"]
		];
		$data = \REDCap::getData($this->getProjectId(), 'array');
		foreach($data as $rid => $record) {
			unset($subjectID, $eid, $addressTo, $cat_mh_data, $save_results, $participantURLs, $participantLinks, $success);
			
			// ensure we have an email value to use
			$eid = array_keys($record)[0];
			$email_address_field = 'catmh_email';
			$addressTo = $record[$eid][$email_address_field];
			if (empty($addressTo)) {
				$result_log_message .= "Record $rid: Skipping participant (empty [$email_address_field] field)\n";
				continue;
			}
			$email->setTo($addressTo);
			
			// generate subject ID and interviews for [cat_mh_data] if missing
			if (empty($record[$eid]['subjectid'])) {
				$subjectID = $this->generateSubjectID();
				$cat_mh_data = json_encode(["interviews" => []]);
				$record[$eid]["subjectid"] = $subjectID;
				$record[$eid]["cat_mh_data"] = $cat_mh_data;
				$save_results = \REDCap::saveData($this->getProjectId(), 'array', [$record]);
			}
			if (empty($subjectID))
				$subjectID = $record[$eid]["subjectid"];
			// ensure we have a subjectID by this point
			if (empty($subjectID)) {
				$result_log_message .= "Record $rid: Skipping participant (failed to generate required subjectID)\n";
				continue;
			}
			
			// prepare participant-specific interview URLs and links
			$participantURLs = [];
			$participantLinks = [];
			foreach($sequenceURLs as $i => $url) {
				// skip this participant if they've already completed this interview sequence
				if ($this->sequenceCompleted($rid, $sequences[$i], $sequenceScheduledDatetimes[$i]) == false) {
					$url_with_sid = $url . "&sid=$subjectID";
					$participantURLs[$i] = $url_with_sid;
					$participantLinks[$i] = "<a href=\"$url_with_sid\">CAT-MH Interview ($i)</a>";
				}
			}
			
			if (empty($participantURLs)) {
				$result_log_message .= "Record $rid: Skipping participant (all sequences completed)\n";
				continue;
			}
			
			// prepare email body by replacing [interview-links] and [interview-urls] (or appending)
			$participant_email_body = $email_body;
			if ($append_links) {
				$participant_email_body .= "<br>" . implode($participantLinks, "<br>");
			} else {
				$participant_email_body = str_replace("[interview-links]", implode($participantLinks, "<br>"), $participant_email_body);
			}
			if ($append_urls) {
				$participant_email_body .= "<br>" . implode($participantURLs, "<br>");
			} else {
				$participant_email_body = str_replace("[interview-urls]", implode($participantURLs, "<br>"), $participant_email_body);
			}
			$email->setBody($participant_email_body);
			
			$success = $email->send();
			// $this->llog("success: " . print_r($success, true));
			if ($success) {
				$result_log_message .= "Record $rid: Sent interview invitation email\n";
			} else {
				$result_log_message .= "Record $rid: Failed to send email (" . $email->ErrorInfo . ")\n";
			}
		}
		\REDCap::logEvent("CAT-MH External Module", $result_log_message, NULL, NULL, NULL, $this->getProjectId());
	}
	
	function sequenceCompleted($record, $seq_name, $datetime) {
		$params = [
			"project_id" => $this->getProjectId(),
			"return_format" => "json",
			"records" => $record,
			"fields" => ["cat_mh_data", "record_id"]
		];
		$data = json_decode(\REDCap::getData($params));
		$catmh = json_decode($data[0]->cat_mh_data);
		$interviews = $catmh->interviews;
		
		$this->llog("interviews: " . print_r($interviews, true));
		foreach ($interviews as $i => $interview) {
			if ($interview->sequence == $seq_name and $interview->scheduled_datetime == $datetime) {
				if ($interview->status == 4) {
					$this->llog("seeing if sequence complete: $record, $seq_name, $datetime - TRUE");
					return true;
				}
			}
		}
		$this->llog("seeing if sequence complete: $record, $seq_name, $datetime - FALSE");
		return false;
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
		
		if ($this->debug) $out['curlArgs'] = $curlArgs;
		
		// send request via curl
		$curl = $this->curl($curlArgs);
		if ($this->debug) $out['curl'] = $curl;
		
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
				
				// see if we need to apply alternate label from project level settings
				$stripped = array_search($arr['type'], $this->convertTestAbbreviation, true);
				$alt_label_arr = $this->getProjectSetting($stripped . "_label");
				if (!empty($alt_label_arr[0])) {
					$out['labels'][] = $alt_label_arr[0];
				} else {
					$out['labels'][] = $this->testTypes[$arr['type']];
				}
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
		$curlArgs['address'] = $this->testAPI ? $testAddress : "https://" . $this->api_host_name . "/interview/signin";
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
			$out['moduleMessage'] = "REDCap failed to retrieve authorization details from the CAT-MH API server for the interview." . "<br>" . json_encode($out, JSON_UNESCAPED_SLASHES + JSON_PRETTY_PRINT);
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
		$curlArgs['address'] = $this->testAPI ? $testAddress : "https://" . $this->api_host_name . "/interview/rest/interview";
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
		$curlArgs['address'] = $this->testAPI ? $testAddress : "https://" . $this->api_host_name . "/interview/rest/interview/test/question";
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
		$curlArgs['address'] = $this->testAPI ? $testAddress : "https://" . $this->api_host_name . "/interview/rest/interview/test/question";
		if ($this->debug) $out['curlArgs'] = $curlArgs;
		
		// send request via curl
		$curl = $this->curl($curlArgs);
		if ($this->debug) $out['curl'] = $curl;
		
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
		$curlArgs['address'] = $this->testAPI ? $testAddress : "https://" . $this->api_host_name . "/interview/signout";
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
		$curlArgs['address'] = $this->testAPI ? $testAddress : "https://" . $this->api_host_name . "/interview/rest/interview/results?itemLevel=1";
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
			// convert test label if alt label is configured
			if (!empty($alt_label = $this->getProjectSetting($test['type'] . "_label")[0])) {
				$test['label'] = $alt_label;
			}
			
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
		$curlArgs['address'] = $this->testAPI ? $testAddress : "https://" . $this->api_host_name . "/portal/secure/interview/status";
		
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
		$curlArgs['address'] = $this->testAPI ? $testAddress : "https://" . $this->api_host_name . "/interview/secure/breakLock";
		if ($this->debug) $out['curlArgs'] = $curlArgs;
		
		// send request via curl
		$curl = $this->curl($curlArgs);
		if ($this->debug) $out['curl'] = $curl;
		
		// get location
		preg_match_all('/^Location:\s([^\n]*)$/m', $curl['response'], $matches);
		$location = trim($matches[1][0]);
		if ($this->debug) $out['location'] = trim($matches[1][0]);
		
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
	
	// $catmh->llog("json: " . print_r($json, true));
	
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