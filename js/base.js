var catmh = {};

catmh.setAnswerOptions = function(answers) {
	$("#testAnswers").empty()
	answers.forEach(answer => {
		$("#testAnswers").append(`<li>
			<input type='radio' name='answer' value='` + answer + `'><span class='answer'>` + answer + `</span>
		</li>`);
	});
}
catmh.showError = function(message) {
	$("body div:not(#error)").fadeOut(150, function() {
		$("#error span").empty().append(message);
		$("#error").fadeIn(100);
	});
}
catmh.showInterviews = function() {
	$("body div").fadeOut(150, function() {
		$("#interviewSelect").fadeIn(100);
	});
}
catmh.showResults = function (results) {
	$("body div").fadeOut(150, function() {
		$("#interviewResults").fadeIn(100);
	});
}
catmh.startInterview = function () {
	$("#interviewSelect, #interviewResults, #error").fadeOut(150, function() {
		$("#interviewTest").fadeIn(100);
	});
}
catmh.submitAnswer = function() {}


// on ready
$(function() {
	$("body div").css('display', 'flex');
	$("#error, #interviewTest, #interviewResults").css('display', 'none');
	// $("#interview").css('display', 'flex').hide();
	// console.log(catmh.interviews);
	// setTimeout(function() {
		// $("#loaderDiv").fadeOut(100, function() {
			// catmh.setAnswerOptions(['Never', 'Sometimes', 'Moderately', 'Very Much', 'Always']);
			// $("#interview").fadeIn(100);
		// });
	// }, 500);
});
$("button.interviewSelector").on('focus', function() {
	$("#beginInterview").removeClass('disabled');
	// $("#beginInterview").prop('disabled', false);
}).on('blur', function() {
	$("#beginInterview").addClass('disabled');
	// $("#beginInterview").prop('disabled', true);
});