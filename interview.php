<?php
$sequence = $_GET['sequence'];
$sched_dt = $_GET['sched_dt'];
$sid = $_GET['sid'];
$sid = preg_replace("/\W|_/", '', $sid);
$kcat = $_GET['kcat'];
$module->llog("\$module->getSequence($sequence, $sched_dt, $sid, $kcat)");
$interview = $module->getSequence($sequence, $sched_dt, $sid, $kcat);
if (empty($interview)) {
	if (empty($kcat)) {
		$interview = $module->makeInterview();
	} else {
		// K-CAT paired interviews must be created at invite time (or at least some time before interiew.php)
		$kcat_error = "The CAT-MH module couldn't find the K-CAT interview requested. Please contact your program administrator with this message.";
	}
}

if (!empty($interview->results->tests)) {
	$seq = $_GET['sequence'];
	foreach ($interview->results->tests as $test) {
		$test->label = $module->getTestLabel($seq, $test->type);
	}
}

?>
<!doctype html>
<html lang="en">
	<head>
		<!-- Required meta tags -->
		<meta charset="utf-8">
		<meta name="viewport" content="width=device-width, initial-scale=1.0, shrink-to-fit=yes">
		
		<link rel="stylesheet" href="<?php
			$ref = $module->getUrl('css/base.css');
			// $ref = str_replace("localhost", "192.168.0.15", $ref);
			echo($ref);
		?>">
		<title>CAT-MH Interview</title>
	</head>
	<body>
		<div id='interviewSelect'>
			<h1>Your interview today will include the following tests:</h1>
			<h2 id='missingInterviewsNote'>REDCap didn't find an interview for you.</h2>
			<ol>
			</ol>
			<span id='buttonInstructions' style='display: none;'>Click below to begin your interview.</span>
			<button id='beginInterview' style='display: none;' type='button' class='submit' onMouseDown='catmh.authInterview()'>Begin</button>
		</div>
		<div id='interviewTest'>
			<div id='questionNote'></div>
			<span class='question'></span>
			<button id='submitAnswer' type='button' class='disabled submit'>Submit</button>
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
			<span>Your test results have been stored in the REDCap database. You may now close this window or tab.</span>
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
			// determine if this interview should hide question numbers
			$seq_name = urldecode($_GET['sequence']);
			$seq_index = array_search($seq_name, $module->getProjectSetting('sequence'));
			$hide_this_seq = $module->getProjectSetting('hide_question_number')[$seq_index];
			if (empty($hide_this_seq)) {
				$hide_this_seq = 'false';
			} else {
				$hide_this_seq = 'true';
			}
			
			// give js this info
			echo "
<script type='text/javascript'>
	$(function() {
		$('body > div').css('display', 'flex');
		$('body > div').css('display', 'none');
		$('#interviewSelect').css('display', 'flex');
		
		catmh.kcat_error = \"" . strval($kcat_error) . "\";
		if (catmh.kcat_error)
			catmh.showError(catmh.kcat_error)
		
		catmh.interview = " . json_encode($interview) . ";
		if (typeof(catmh.interview) == 'object') {
			catmh.interview.hide_question_number = $hide_this_seq;
			catmh.init();
			// catmh.setInterviewOptions();
		}
		
		$('#submitAnswer').on('mousedown', catmh.submitAnswer);
		$('body').on('mousedown', '.answerSelector', function() {
			$('.answerSelector').removeClass('selected');
			$(this).addClass('selected');
		});
	})
</script>
			";
		?>
		<script type="text/javascript"><?php echo file_get_contents($module->getUrl('js/base.js')); ?></script>
	</body>
</html>