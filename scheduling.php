<?php
require_once APP_PATH_DOCROOT . 'ProjectGeneral/header.php';

?>

<div class="card card-body w-50">
	<h3>Schedule a Sequence</h3>
	<div class="dropdown">
		<label class="pr-3">1. Choose a Sequence:</label>
		<button class="btn btn-primary dropdown-toggle" type="button" id="dropdownMenuButton" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
			Sequences
		</button>
		<div class="dropdown-menu" aria-labelledby="dropdownMenuButton">
			<?php
			$seq_names = $module->getProjectSetting('sequence');
			foreach ($seq_names as $i => $name) {
				echo "<a class=\"dropdown-item\" href=\"#\">$name</a>";
			}
			?>
		</div>
	</div>
	
</div>

<div class="card card-body w-75 mt-3">
	<h3>Scheduled Sequences</h3>

</div>

<div class="card card-body w-50 mt-3">
	<h3>Reminder Emails</h3>

</div>



<?php
$js_url = $module->getUrl('js/scheduling.js');
echo "<script type='text/javascript' src='$js_url'></script>";
require_once APP_PATH_DOCROOT . 'ProjectGeneral/footer.php';
?>