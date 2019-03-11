<!doctype html>
<html lang="en">
	<head>
		<!-- Required meta tags -->
		<meta charset="utf-8">
		<meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
		
		<link rel="stylesheet" href="<?php echo($module->getUrl('css/base.css')); ?>">
		<title>CAT-MH Interview</title>
	</head>
	<body>
		<div id='interviewSelect'>
			<h1>Select an interview to take:</h1>
			
			<ul>
				<li>
					<button type='button' class='interviewSelector'>Depression</button>
					<span class='interviewLabel'>(incomplete)</span>
				</li>
				<li>
					<button type='button' class='interviewSelector'>Anxiety Disorder (Perinatal)</button>
					<span class='interviewLabel'>(incomplete)</span>
				</li>
			</ul>
			
			<button id='beginInterview' type='button' class='disabled submit' onclick='catmh.startInterview()'>Begin</button>
		</div>
		<div id='interviewTest'>
			<span class='question'>1 - In the last 2 weeks, have you felt in the dumps?</span>
			<button class='answerSelector'>Never</button>
			<button class='answerSelector'>Sometimes</button>
			<button class='answerSelector'>Moderately</button>
			<button class='answerSelector'>Frequently</button>
			<button class='answerSelector'>Always</button>
			<button id='submitAnswer' type='button' class='disabled submit' onclick='catmh.showResults()'>Submit</button>
		</div>
		<div id='interviewResults'>
			<h2>Your interview is complete.</h2>
			<h3>Interview results:</h3>
			<div>
				<!-- possible columns: label, diagnosis, confidence, severity, category, precision, prob, percentile -->
				<table>
					<tr>
						<th>Test Type</th>
						<th>Diagnosis</th>
						<th>Confidence</th>
						<th>Category</th>
						<th>Precision</th>
						<th>Probability</th>
					</tr>
					<tr>
						<td>Depression</td>
						<td>Positive</td>
						<td>99.3%</td>
						<td>N/A</td>
						<td>N/A</td>
						<td>N/A</td>
					</tr>
					<tr>
						<td>Anxiety Disorder (Perinatal)</td>
						<td>N/A</td>
						<td>N/A</td>
						<td>58.3%</td>
						<td>Mild</td>
						<td>88.5%</td>
					</tr>
				</table>
			</div>
			<button type='button' onclick='catmh.showInterviews()'>Back</button>
		</div>
		<div id='error'>
			<span></span>
		</div>
		<script src="https://code.jquery.com/jquery-3.3.1.min.js"></script>
		<?php
			// pull all interview info from logs
			$subjectID = $_GET['sid'];
			$result = $module->queryLogs("select interview, instrument, recordID, status where subjectID='$subjectID' order by timestamp desc");
			$interviews = [];
			while($row = db_fetch_assoc($result)) {
				$interview = json_decode($row['interview'], true);
				$interview['status'] = $row['status'];
				$interview['recordID'] = $row['recordID'];
				$interview['instrument'] = $row['instrument'];
				$interviews[] = $interview;
			}
			
			// give js this info
			echo "
<script type='text/javascript'>
	$(function() {
		catmh.interviews = JSON.parse('" . json_encode($interviews) . "');
		$('body > div').css('display', 'flex');
		$('body > div:not(#interviewSelect').css('display', 'none');
		catmh.setInterviewOptions();
	})
</script>
			";
		?>
		<script src="<?php echo($module->getUrl('js/base.js')); ?>"></script>
	</body>
</html>