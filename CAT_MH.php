<?php

// to do:
// Change the external module config option for which survey to trigger from text to dropdown

namespace VICTR\REDCAP\CAT_MH;

class CAT_MH extends \ExternalModules\AbstractExternalModule {
	public function __construct() {
		parent::__construct();
		// Other code to run when object is instantiated
	}
	
	# provide button for user to click to send them to interview page after they've read the last page of the survey submission document
	public function redcap_survey_complete($project_id, $record, $instrument, $event_id, $group_id, $survey_hash, $response_id, $repeat_instance) {
		# only execute for surveys specified in configuration
		$projectSettings = $this->getProjectSettings();
		$result = $this->query('select form_name, form_menu_description from redcap_metadata where form_name="' . $instrument . '" and project_id=' . $project_id . ' and form_menu_description<>""');
		$record = db_fetch_assoc($result);
		foreach ($projectSettings['survey_instrument'] as $settingsIndex => $instrumentDisplayName) {
			if ($instrumentDisplayName == $record['form_menu_description']) {
				# use log to associate this record id with this settingsIndex
				break;
			}
		}
		
		if ($record['form_menu_description'] == $targetInstrument) {
			$page = $this->getUrl("interview.php");
			echo "Click to begin your CAT-MH screening interview.<br />";
			echo "
			<button id='catmh_button'>Begin Interview</button>
			<script>
				var btn = document.getElementById('catmh_button')
				btn.addEventListener('click', function() {
					window.location.assign('$page' + '&amp;record=' + $record + '&amp;eid=' + $event_id)
				})
			</script>";
		} else {
			echo("<pre>
\$response_id: $response_id
\$record: " . print_r($record, true) . "
\$record['form_menu_description']: " . $record['form_menu_description'] . "
\$targetInstrument: $targetInstrument
			</pre>");
		}
		
		// exit("<pre>" . print_r($record['form_menu_description'], true) . "</pre>");
	}
	
	// CAT-MH API methods
	public function createInterviews() {
		$projectSettings = $this->getProjectSettings();
		
		// $json = '{
			// "organizationID": 114,
			// "userFirstName": "Automated",
			// "userLastName": "Creation",
			// "subjectID": 1234,
			// "numberOfInterviews": 1,
			// "language": 1,
			// "tests": [{"type": "mdd"}]
		// }';
		// $headers = [
			// "applicationid: VU_Portal",
			// "Accept: application/json",
			// "Content-Type: application/json"
		// ];
		// $ch = curl_init();
		// curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
		// curl_setopt($ch, CURLOPT_URL, "https://www.cat-mh.com/portal/secure/interview/createInterview");
		// curl_setopt($ch, CURLINFO_HEADER_OUT, true);
		// curl_setopt($ch, CURLOPT_POST, true);
		// curl_setopt($ch, CURLOPT_VERBOSE, true);
		// curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
		// curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		// $server_output = curl_exec($ch);
		// $err_status = curl_error($ch);
		// $err_no = curl_errno($ch);
		// $cInfo = curl_getinfo($ch);
		// curl_close ($ch);
		
		exit("<pre>" . print_r($projectSettings, true) . "</pre>");
		
		// $this->log("cat_mh module asked CAT-MH API server to create interviews", [
			// "curl_getinfo" => $cInfo,
			// "curl_exec" => $server_output
		// ]);
	}
}

if (!$catmh) {
	$catmh = new CAT_MH();
}

if ($_GET['action'] == 'create') {
	
}