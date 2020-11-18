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
	$params = [
		"project_id" => $module->getProjectId(),
		"return_format" => 'array',
		"fields" => ['cat_mh_data']
	];
	
	// filter by record, sequence, and datetime if applicable
	$recordFilter = $_GET['record'];
	if (!empty($recordFilter))
		$params['records'] = $recordFilter;
	if (isset($_GET['seq']))
		$seqFilter = $_GET['seq'];
	if (isset($_GET['sched_dt']))
		$schedFilter = $_GET['sched_dt'];
	
	// $data = \REDCap::getData($module->getProjectId(), 'array');
	$data = \REDCap::getData($params);
	foreach($data as $rid => $record) {
		$eid = array_keys($record)[0];
		$catmh = json_decode($data[$rid][$eid]['cat_mh_data'], true);
		foreach($catmh['interviews'] as $i => $interview) {
			$seq_ok = (empty($seqFilter) or $seqFilter == $interview['sequence']);
			$sched_ok = (empty($schedFilter) or $schedFilter == $interview['scheduled_datetime']);
			
			if ($interview['status'] == "4" and $interview['results'] != NULL and $sched_ok and $seq_ok) {
				foreach($interview['results']['tests'] as $j => $test) {
					$url = $module->getUrl("interview.php") . "&NOAUTH&sid=" . $record[$eid]['subjectid'] . "&sequence=". $interview['sequence'];
					echo("
					<tr>
						<td>{$rid}</td>
						<td>" . $interview['scheduled_datetime'] . "</td>
						<td>" . date("Y-m-d H:i", $interview['timestamp']) . "</td>
						<td>{$interview['sequence']}</td>
						<td>{$test['label']}</td>
						<td>{$test['diagnosis']}</td>
						<td>{$test['confidence']}</td>
						<td>{$test['severity']}</td>
						<td>{$test['category']}</td>
						<td>{$test['precision']}</td>
						<td>{$test['prob']}</td>
						<td>{$test['percentile']}</td>
						<td>" . ($interview['reviewed'] ? "Y" : "N") . "</td>
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
		<script src="<?php echo $module->getUrl('js/results.js'); ?>"></script>
	</body>
</html>
<?php
require_once APP_PATH_DOCROOT . 'ProjectGeneral/footer.php';
?>