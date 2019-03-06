<?php
	$recordID = $_GET['rid'];
	$projectID = $_GET['pid'];
	$subjectID = $_GET['sid'];
	$sql = "select interviews where subjectID='$subjectID' order by timestamp desc";
	$result = $module->queryLogs($sql);
	echo(print_r($result) . "<br />");
	if (db_num_rows($result) > 0) {
		$interviews = json_decode(db_fetch_assoc($result)['interviews'], true);
	} else {
		// todo: better error message when no interviews found
		exit("CAT-MH didn't find any more interviews for you to take.");
	}
	$input = [];
	$_SESSION['identifier'] = 'abc';
	$_SESSION['signature'] = 'def';
	$_SESSION['interviewID'] = 123;
	$out = $module->authInterview($input);
?>
<!doctype html>
<html lang="en">
	<head>
		<!-- Required meta tags -->
		<meta charset="utf-8">
		<meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
		
		<link rel="stylesheet" href="<?php echo($module->getUrl('css/cat_mh.css')); ?>">
		<title>CAT-MH Interview</title>
	</head>
	<body>
		<div id='loaderDiv'>
			<small class='grayText' id="loadMessage">Creating your interview...</small>
			<div id="loader"></div>
		</div>
		<div id='interview'>
			<span class='bigText' id="question">1. In the last two weeks, have you felt down in the dumps?</span>
			<ul id="testAnswers"></ul>
			<button type='button' onclick='catmh.submitAnswer'>Submit</button>
		</div>
		<div id='error'><span class='bigText'></span></div>
		<script src="https://code.jquery.com/jquery-3.3.1.min.js"></script>
		<script src="<?php echo($module->getUrl('js/cat_mh.js')); ?>"></script>
	</body>
</html>