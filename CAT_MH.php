<?php
namespace VICTR\REDCAP\CAT_MH;

class CAT_MH extends \ExternalModules\AbstractExternalModule {
	public function __construct() {
		parent::__construct();
		// Other code to run when object is instantiated
	}
	
	# provide button for user to click to send them to interview page after they've read the last page of the survey submission document
	public function redcap_survey_complete($project_id, $record, $instrument, $event_id, $group_id, $survey_hash, $response_id, $repeat_instance) {
		$page = $this->getUrl("interview.php");
		echo "Click to begin your CAT-MH screening interview.<br />";
		echo "
		<button id='catmh_button'>Begin Interview</button>
		<script>
			var btn = document.getElementById('catmh_button')
			btn.addEventListener('click', function() {
				window.location.assign('$page' + '&record=' + $record + '&eid=' + $event_id)
			})
		</script>";
	}
	
	// CAT-MH API methods
	public function createInterviews() {
		// request creation of CAT-MH interviews according to external module settings
		
	}
}

if (!$catmh) {
	$catmh = new CAT_MH();
}

if ($_GET['action'] == 'create') {
	
}