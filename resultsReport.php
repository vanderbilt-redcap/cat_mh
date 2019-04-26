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
					<th>Subject ID</th>
					<th>Record ID</th>
					<th>Date</th>
					<th>Instrument</th>
					<th>Test Type</th>
					<th>Diagnosis</th>
					<th>Confidence</th>
					<th>Severity</th>
					<th>Category</th>
					<th>Precision</th>
					<th>Probability</th>
					<th>Percentile</th>
				</tr>
			</thead>
			<tbody>
<?php
	$query = $module->queryLogs("select subjectID, recordID, interviewID, tstamp, instrument, labels, results
		where status=3 and results!='' order by tstamp desc");
	while ($interview = db_fetch_assoc($query)) {
		$results = json_decode($interview['results'], true);
		foreach ($results['tests'] as $index => $test) {
			echo("
				<tr>
					<td>{$interview['subjectID']}</td>
					<td>{$interview['recordID']}</td>
					<td>" . date("m-d-Y", $interview['tstamp']) . "</td>
					<td>{$interview['instrument']}</td>
					<td>{$test['label']}</td>
					<td>{$test['diagnosis']}</td>
					<td>{$test['confidence']}</td>
					<td>{$test['severity']}</td>
					<td>{$test['category']}</td>
					<td>{$test['precision']}</td>
					<td>{$test['prob']}</td>
					<td>{$test['percentile']}</td>
				</tr>");
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