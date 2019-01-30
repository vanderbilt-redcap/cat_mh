<?php
namespace VICTR\REDCAP\CAT_MH;

class CAT_MH extends \ExternalModules\AbstractExternalModule {
	public function __construct() {
		parent::__construct();
		// Other code to run when object is instantiated
	}
	
	public function redcap_survey_complete( int $project_id, string $record = NULL, string $instrument, int $event_id, int $group_id = NULL, string $survey_hash, int $response_id = NULL, int $repeat_instance = 1 ) {
		$page = 
		echo "Click below to begin your CAT-MH screening interview.";
		echo "
		<button id='catmh_button'>Begin Interview</button>
		<script>
			var btn = document.getElementByID('catmh_button')
			btn.addEventListener('click', function() {
				document.location.href = '<?php echo $page?>'
			})
		</script>";
	}
}