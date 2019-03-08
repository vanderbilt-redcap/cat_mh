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
		<div id='showInterviewsPage'>
			<h1>Select an interview</h1>
			
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
			
			<button id='beginInterview' type='button' class='disabled' onclick='catmh.startInterview()'>Begin</button>
		<div>
		<script src="https://code.jquery.com/jquery-3.3.1.min.js"></script>
		<?php
			// // pull all interview info from logs
			// $subjectID = $_GET['sid'];
			// $result = $module->queryLogs("select interview, instrument, recordID, status where subjectID='$subjectID' order by timestamp desc");
			// $interviews = [];
			// while($row = db_fetch_assoc($result)) {
				// $interview = json_decode($row['interview'], true);
				// $interview['status'] = $row['status'];
				// $interview['recordID'] = $row['recordID'];
				// $interview['instrument'] = $row['instrument'];
				// $interviews[] = $interview;
			// }
			
			// // give js this info
			// echo "
		// <script type='text/javascript'>
			// $(function() {
				// catmh.interviews = JSON.parse('" . json_encode($interviews) . "');
				// catmh.showInterviews();
			// })
		// </script>
// ";
		?>
		<script src="<?php echo($module->getUrl('js/base.js')); ?>"></script>
	</body>
</html>