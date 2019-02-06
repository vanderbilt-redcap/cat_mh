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
		<!-- always hidden -->
		<div class='apiDetails' style='display: none'>{"applicationid": "VU_Portal", "organizationID": 114}</div>
		<div class='errorContainer'>
			<span>There was an error :(<br><br>Please try again or contact your REDCap system administrator.</span>
		</div>
		<div class='loader'>
			<span>Your interview is being created now.</span>
			<div class='spinner'></div>
		</div>
		<pre class='diagnostic'></pre>
		<div class='content'>
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


