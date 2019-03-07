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
		<?php
			// pull all interview records from logs
			$subjectID = $_GET['sid'];
			$result = $module->queryLogs("select interview, instrument, recordID, status where subjectID='$subjectID' order by timestamp desc");
			$interviews = [];
			while($row = db_fetch_assoc($result)) {
				$interview = json_decode($row['interview'], true);
				$interview['status'] = $row['status'];
				$interview['recordID'] = $row['recordID'];
				$interview['instrument'] = $row['instrument'];
				print_r($interview);
			}
			// exit("<script type='text/javascript'>
				// $(function() {
					// catmh.showInterviews('" . json_encode($interviews) . "');
				// })
			// </script>");
			
			// // if there's a test they started but not finished, try to open it back up
			// foreach ($interviews as $interview) {
				// if ($interview['status'] == 1) {
					
				// }
			// }
			// upon failure, or if none open, and interviews unstarted remain -> ask to pick from list of remaining tests (showing those completed)
			
			
			// if they click to start a test, startInterview and getQuestion to begin
			// upon ending test, show results (if config allows) and give back button
			
			// // old html
			// <div id='loaderDiv'>
				// <small class='grayText' id="loadMessage">Creating your interview...</small>
				// <div id="loader"></div>
			// </div>
			// <div id='interview'>
				// <span class='bigText' id="question">1. In the last two weeks, have you felt down in the dumps?</span>
				// <ul id="testAnswers"></ul>
				// <button type='button' onclick='catmh.submitAnswer'>Submit</button>
			// </div>
			// <div id='error'><span class='bigText'></span></div>
		?>
		<script src="https://code.jquery.com/jquery-3.3.1.min.js"></script>
		<script src="<?php echo($module->getUrl('js/base.js')); ?>"></script>
	</body>
</html>