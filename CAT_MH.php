<?php
namespace VICTR\REDCAP\CAT_MH;

class CAT_MH extends \ExternalModules\AbstractExternalModule {
	public function __construct() {
		parent::__construct();
		// Other code to run when object is instantiated
	}
	
	# provide button for user to click to send them to interview page after they've read the last page of the survey submission document
	public function redcap_survey_complete(int $project_id, string $record = NULL, string $instrument, int $event_id, int $group_id = NULL, string $survey_hash, int $response_id = NULL, int $repeat_instance = 1) {
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
}