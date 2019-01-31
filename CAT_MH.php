<?php
namespace VICTR\REDCAP\CAT_MH;


# basic process for interacting with the CAT-MH API:
# create an interview -- send cat-mh details like user's name, language, and which tests to administer
# start interview session -- send request to start interview with ids/signature, get back jSessionId and awsElb keys
# get the first question, collect user's answer, and submit
# if 200/OK, get next question until questionId == -1
# at that point, interview is over, send json to api to retrieve results (with per-item info)
# save those in redcap

# optionally, sometimes a user may signout of an interview, sign back in (same as unblock interview), or terminate interview early


class CAT_MH extends \ExternalModules\AbstractExternalModule {
	public function __construct() {
		parent::__construct();
		// Other code to run when object is instantiated
	}
	
	# provide button for user to click to send them to interview page after they've read the last page of the survey submission document
	public function redcap_survey_complete(int $project_id, string $record = NULL, string $instrument, int $event_id, int $group_id = NULL, string $survey_hash, int $response_id = NULL, int $repeat_instance = 1) {
		$this->fromSurvey = [
			"pid" => $project_id,
			"record" => $record,
			"eid" => $event_id
		];
		$page = $this->getUrl("interview.php");
		echo "Click below to begin your CAT-MH screening interview.<br />";
		echo "
		<button id='catmh_button'>Begin Interview</button>
		<script>
			var btn = document.getElementById('catmh_button')
			btn.addEventListener('click', function() {
				window.location.assign('$page')
			})
		</script>";
	}
	
	# this function will get interview json string from CAT-MH server via CAT-MH API
	# arguments:
	#	array($userFirstName, $userLastName, $subjectId, $numberOfInterviews, $language, $tests)
	public function createInterview($args = NULL) {
		# get mock interview object for testing
		return $this->createMockInterview();
		
		# use API
		// ...
	}
	
	# after interview is started, get questions one at a time
	# arguments:
	#	array($jSessionId, $awsElb)
	public function getNextQuestion($args = NULL) {
		# for testing
		return $this->getNextMockQuestion();
		
		# zf questionID == -1, test over
	}
	
	# after interview is started, get questions one at a time
	# arguments:
	#	array($jSessionId, $awsElb)
	public function retrieveResults($args = NULL) {
		# for testing
		return $this->retrieveMockResults();
	}
	
	# start interview is what the API docs refer to as administering the interview
	# arguments:
	#	array($identifier, $signature, $interviewId)
	public function startInterview($args = NULL) {
		# for testing
		return $this->startMockInterview();
		
		# use API
		// ...
	}
	
	# this function sends answer to previously gotten question to CAT-MH API server
	# arguments:
	#	array($jSessionId, $awsElb)
	public function submitAnswer() {
		
	}
	
	# end current interview session
	# arguments:
	#	array($jSessionId, $awsElb)
	public function terminateInterview($args = NULL) {
		# for testing
		return $this->terminateMockInterview();
		
		# use API
		// ...
	}
	
	# this function will attempt to unlock a locked interview (in-progress interviews lock after 30 min of inactivity)
	# arguments:
	#	array($jSessionId, $awsElb)
	public function unlockInterview($args = NULL) {
		# use API
		// ...
	}
	
	////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
	# MOCK VERSIONS OF FUNCTIONS for testing interface without CAT-MH API credentials
	public function createMockInterview() {
		$response = '{
			"interviews": [
				{
					"interviewID": 12345,
					"identifier": "a9b3",
					"signature": "1zrd4f"
				},
				{
					"interviewID": 12356,
					"identifier": "3mp8",
					"signature": "bx5t8v"
				}
			]
		}';
		$interview = json_decode($response);
		return $interview;
	}
	
	public function getNextMockQuestion() {
		$response = '{
			"questionID":14,
			"questionNumber":2,
			"questionDescription":"In the past 2 weeks, how much of the time did you feel depressed?",
			"questionAnswers":[
			{
			"answerOrdinal":1,
			"answerDescription":"None of the time",
			"answerWeight":1.0
			},
			{
			"answerOrdinal":2,
			"answerDescription":"A little of the time",
			"answerWeight":2.0
			},
			{
			"answerOrdinal":3,
			"answerDescription":"Some of the time",
			"answerWeight":3.0
			},
			{
			"answerOrdinal":4,
			"answerDescription":"Most of the time",
			"answerWeight":4.0
			},
			{
			"answerOrdinal":5,
			"answerDescription":"All of the time",
			"answerWeight":5.0
			}
			],
			"questionAudioID":14,
			"questionSymptom":null
		}';
		return json_decode($response);
	}
	
	public function retrieveMockResults() {
		$response = '{
			"interviewId":12346,
			"subjectId":"0002",
			"startTime": 1484538912297,
			"endTime": 1484539170177,
			"tests":[
				{
				"type":"MDD","label":"Major Depressive Disorder","diagnosis":"positive","confidence":99.3,
				"severity":null,"category":null,"precision":null,"prob":null,"percentile":null,
				"items":[{"questionId":925,"response":3,"duration":5.002},
				{"questionId":927,"response":4,"duration":53.666},
				{"questionId":922,"response":5,"duration":8.997},
				{"questionId":924,"response":5,"duration":6.828}
				]
				},
				{
				"type":"DEP","label":"Depression","diagnosis":null,"confidence":null,"severity":87.5,
				"category":"severe","precision":4.9,"prob":0.999,"percentile":92.5,
				"items":[{"questionId":5,"response":5,"duration":0.0},
				{"questionId":9,"response":3,"duration":0.0},
				{"questionId":16,"response":5,"duration":0.0},
				{"questionId":117,"response":4,"duration":0.0},
				{"questionId":41,"response":4,"duration":56.013},
				{"questionId":240,"response":5,"duration":18.217},
				{"questionId":386,"response":4,"duration":7.611},
				{"questionId":84,"response":5,"duration":35.978},
				{"questionId":384,"response":4,"duration":8.323},
				{"questionId":288,"response":4,"duration":9.088},
				{"questionId":313,"response":4,"duration":10.97},
				{"questionId":279,"response":4,"duration":7.057},
				{"questionId":262,"response":4,"duration":6.165},
				{"questionId":146,"response":5,"duration":9.789},
				{"questionId":341,"response":4,"duration":7.552}
				]
				}
			]
		}';
		return json_decode($response);
	}
	
	public function startMockInterview() {
		$response = '{
			"id":12345,
			"startTime":null,
			"endTime":null,
			"iter":0,
			"languageID":1,
			"interviewTests":[1,5],
			"conditionalTests":null,
			"subjectID":null,
			"displayResults":0
		}';
		return json_decode($response);
	}
	
	public function submitMockAnswer() {
		
	}
	
	public function terminateMockInterview() {
		
	}
	
	public function unblockMockInterview() {
		
	}
}