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
			<h1>Your interview today will include the following tests:</h1>
			<h2 id='missingInterviewsNote'>REDCap didn't find an interview for you.</h2>
			<ol>
			</ol>
			<span>Click below to begin your interview.</span>
			<button id='beginInterview' type='button' class='submit' onMouseDown='catmh.authInterview()'>Begin</button>
		</div>
		<div id='interviewTest'>
			<span class='question'></span>
			<button id='submitAnswer' type='button' class='disabled submit' onMouseDown='catmh.submitAnswer()'>Submit</button>
		</div>
		<div id='interviewResults'>
			<h2>Your interview is complete.</h2>
			<h3>Interview results:</h3>
			<div>
				<table>
					<tr>
						<th>Test Type</th>
						<th>Diagnosis</th>
						<th>Confidence</th>
						<th>Severity</th>
						<th>Category</th>
						<th>Precision</th>
						<th>Probability</th>
						<th>Percentile</th>
					</tr>
				</table>
			</div>
			<button type='button' onclick='catmh.refreshInterviews()'>Back</button>
		</div>
		<div id='error'>
			<span></span>
		</div>
		<div id='loader'>
			<span class='loadText'>Fetching the next question...</span>
			<div class='spinner'></div>
		</div>
		<script src="https://code.jquery.com/jquery-3.3.1.min.js"></script>
		<?php
			// pull all interview info from logs
			$subjectID = $_GET['sid'];
			$result = $module->queryLogs("select subjectID, recordID, interviewID, status, timestamp, instrument, identifier, signature, type, label
				where subjectID='$subjectID' order by timestamp desc");
			$interviews = [];
			while($row = db_fetch_assoc($result)) {
				$interviews[] = $row;
			}
			
			// give js this info
			echo "
<script type='text/javascript'>
	$(function() {
		catmh.interviews = JSON.parse('" . json_encode($interviews) . "');
		catmh.interviews.sort(function(a, b) {
			if (parseInt(a.interviewID) < parseInt(b.interviewID)) {
				return -1;
			} else {
				return 1;
			}
		});
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