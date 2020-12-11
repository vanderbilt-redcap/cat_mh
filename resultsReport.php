<?php
require_once APP_PATH_DOCROOT . 'ProjectGeneral/header.php';
?>
<!doctype html>
<html lang="en">
	<head>
		<!-- Required meta tags -->
		<meta charset="utf-8">
		<meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
		
		<link rel="stylesheet" href="//cdn.datatables.net/1.10.19/css/jquery.dataTables.min.css">
		<link rel="stylesheet" href="<?php echo($module->getUrl('css/report.css')); ?>">
		<title>CAT-MH Interview Results</title>
	</head>
	<body>
		<h2>CAT-MH Interview Results</h2>
		<table style='width:90%' id='results'>
			<thead>
				<tr>
					<th>Record ID</th>
					<th>Date Scheduled</th>
					<th>Date Taken</th>
					<th>Sequence</th>
					<th>Test Type</th>
					<th>Diagnosis</th>
					<th>Confidence</th>
					<th>Severity</th>
					<th>Category</th>
					<th>Precision</th>
					<th>Probability</th>
					<th>Percentile</th>
					<th>Reviewed</th>
				</tr>
			</thead>
			<tbody>
<?php
	// get all record IDs
	$params = [
		"project_id" => $module->getProjectId(),
		"return_format" => 'array',
		"fields" => [$module->getRecordIDField()]
	];
	
	// filter by record, sequence, and datetime if applicable
	$recordFilter = $_GET['record'];
	if (!empty($recordFilter))
		$params['records'] = $recordFilter;
	if (isset($_GET['seq']))
		$seqFilter = $_GET['seq'];
	if (isset($_GET['sched_dt']))
		$schedFilter = $_GET['sched_dt'];
	
	$data = \REDCap::getData($params);
	foreach($data as $rid => $record) {
		// get this patients interviews
		$interviews = $module->getInterviewsByRecordID($rid);
		
		foreach($interviews as $i => $interview) {
			$sequence_name = $interview->sequence;
			
			// k-cat support
			// if ($kcat_index = $module->getKCATSequenceIndex($sequence_name) !== false) {
				// $kcat = ;
			// }
			
			$sequence_datetime = $interview->scheduled_datetime;
			$seq_ok = (empty($seqFilter) or $seqFilter == $sequence_name);
			$sched_ok = (empty($schedFilter) or $schedFilter == $sequence_datetime);
			$sid = $module->getSubjectID($rid);
			if ($interview->status == "4" and !empty($interview->results) and $sched_ok and $seq_ok) {
				foreach($interview->results->tests as $j => $test) {
					// make reviewed checkbox
					$test_name = $test->label;
					$test_reviewed = $test->reviewed ? 'true' : 'false';
					$reviewed_cbox = "<input type='checkbox' class='reviewed_cbox' data-test='$test_name' data-sid='$sid' data-seq='$sequence_name' data-date='$sequence_datetime' data-kcat='{$interview->kcat}' data-checked='$test_reviewed'>";
					
					echo("
					<tr>
						<td>{$rid}</td>
						<td>$sequence_datetime</td>
						<td>" . date("Y-m-d H:i", $interview->timestamp) . "</td>
						<td>$sequence_name</td>
						<td>$test_name</td>
						<td>{$test->diagnosis}</td>
						<td>{$test->confidence}</td>
						<td>{$test->severity}</td>
						<td>{$test->category}</td>
						<td>{$test->precision}</td>
						<td>{$test->prob}</td>
						<td>{$test->percentile}</td>
						<td>$reviewed_cbox</td>
					</tr>");
				}
			}
		}
	}
?>
			</tbody>
		</table>
		<script src="https://code.jquery.com/jquery-3.3.1.js"></script>
		<script src="https://cdn.datatables.net/1.10.19/js/jquery.dataTables.min.js"></script>
		<script src="https://cdn.datatables.net/buttons/1.5.6/js/dataTables.buttons.min.js"></script>
		<script src="https://cdn.datatables.net/buttons/1.5.6/js/buttons.flash.min.js"></script>
		<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.1.3/jszip.min.js"></script>
		<script src="https://cdn.datatables.net/buttons/1.5.6/js/buttons.html5.min.js"></script>
		<script src="https://cdn.datatables.net/buttons/1.5.6/js/buttons.print.min.js"></script>
		<script type='text/javascript'>
			CATMH = {}
			CATMH.review_ajax_url = "<?php echo $module->getUrl('ajax/review_ajax.php'); ?>"
		</script>
		<script src="<?php echo $module->getUrl('js/results.js'); ?>"></script>
	</body>
</html>
<?php
require_once APP_PATH_DOCROOT . 'ProjectGeneral/footer.php';
?>