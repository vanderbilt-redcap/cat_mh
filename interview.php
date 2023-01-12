<?php
$interview_ajax_url = $module->getUrl('php/interview_ajax.php');
$sequence = htmlentities($_GET['sequence'], ENT_QUOTES, 'UTF-8');
$seq_index = array_search($sequence, $module->getProjectSetting('sequence'));
$sched_dt = htmlentities($_GET['sched_dt'], ENT_QUOTES, 'UTF-8');
$sid = htmlentities($_GET['sid'], ENT_QUOTES, 'UTF-8');
$sid = preg_replace("/\W|_/", '', $sid);
$kcat = htmlentities($_GET['kcat'], ENT_QUOTES, 'UTF-8');
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
	$seq = htmlentities($_GET['sequence'], ENT_QUOTES, 'UTF-8');
	foreach ($interview->results->tests as $test) {
		$test->label = $module->getTestLabel($seq, $test->type);
	}
}

$circle_images = [
	"green" => $module->getUrl("images/circle_green.png"),
	"gray" => $module->getUrl("images/circle_gray.png"),
	"blue" => $module->getUrl("images/circle_blue.png")
];

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

		        <?php
		        // Show Progress
		        if (! $module->getProjectSetting('hide_progress_bar')[$seq_index]) { 

			?><div id='interviewProgress'>
				<span>Interview Progress</span>
				<div id='progress_meter'><?php
				$interview_types = gettype($interview) == 'array' ? $interview['types'] : $interview->types;
				foreach ($interview_types as $index => $test) {
					if (
						($test == 'a/adhd' and in_array('c/adhd', $interview_types)) OR
						($test == 'p-dep' and in_array('dep', $interview_types)) OR
						($test == 'p-anx' and in_array('anx', $interview_types)) OR
						($test == 'p-m/hm' and in_array('m/hm', $interview_types))
					) {
						// a/adhd and c/adhd questions come from same ATT CAT-MH item bank, same for perinatal and non-perinatal questions
						// so the interview interface can't determine between these test types
						// therefore, combine them into 1 test icon circle in the progress meter
						continue;
					}
					$module->llog("interview test index $index -> $test");
					if ($index === 0) {
						echo "<img src='{$circle_images['blue']}' alt='Test ".htmlspecialchars($index, ENT_QUOTES)." progress indicator'>";
					} else {
						echo "<img src='{$circle_images['gray']}' alt='Test ".htmlspecialchars($index, ENT_QUOTES)." progress indicator'>";
					}
				}
				?></div>
			</div>
			<?php
			}
			?>

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
		<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
		<script type="text/javascript" src="<?= $module->getUrl('js/base.js') ?>"></script>
		<?php
			// determine if this interview should hide question numbers
			$seq_name = htmlentities(urldecode($_GET['sequence']), ENT_QUOTES, 'UTF-8');
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
		catmh.bridgeUrl = '$interview_ajax_url&NOAUTH';
		catmh.progress_meter_circle_urls = {
			gray: '{$circle_images['gray']}',
			green: '{$circle_images['green']}',
			blue: '{$circle_images['blue']}'
		}
		
		catmh.kcat_error = \"" . htmlspecialchars(strval($kcat_error), ENT_QUOTES) . "\";
		if (catmh.kcat_error)
			catmh.showError(catmh.kcat_error)
		
		catmh.interview = " . (json_encode($interview) ?: "false"). ";
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
		
		// catmh.auto_take_interview = true;
	})
</script>
			";
		?>
	</body>
</html>