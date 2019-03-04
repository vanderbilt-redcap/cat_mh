<!doctype html>
<html lang="en">
	<head>
		<!-- Required meta tags -->
		<meta charset="utf-8">
		<meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
		
		<link rel="stylesheet" href="<?php echo $module->getUrl("css/cat_mh.css");?>">
		<title>CAT-MH Interview</title>
	</head>
	<body>
		<!--
		<div id='error'>
			<span>There was an error :(<br><br>Please try again or contact your REDCap system administrator.</span>
		</div>
		<div id='loader'>
			<span>Your interview is being created now.</span>
			<div class='spinner'></div>
		</div>
		-->
		<div id='content'></div>
		<div id='buttons'>
			<button onclick="catmh.authInterview()">authInterview</button>
			<button onclick="catmh.breakLock()">breakLock</button>
			<button onclick="catmh.createInterviews()">createInterviews</button>
			<button onclick="catmh.initInterview()">initInterview</button>
			<button onclick="catmh.getInterviewStatus()">getInterviewStatus</button>
			<button onclick="catmh.getNextQuestion()">getNextQuestion</button>
			<button onclick="catmh.retrieveResults()">retrieveResults</button>
			<button onclick="catmh.submitAnswer()">submitAnswer</button>
			<button onclick="catmh.terminateInterview()">terminateInterview</button>
		</div>
		<script src="https://code.jquery.com/jquery-3.3.1.min.js"></script>
		<script src="<?php echo $module->getUrl("js/cat_mh.js");?>"></script>
	</body>
</html>


