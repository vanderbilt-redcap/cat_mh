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
	$("#error span").empty().append(message);
	$("#loader").hide()
	$("#interview").fadeOut(200, function() {
		$("#error").fadeIn(100);
	});
}
catmh.showInterviews = function(interviews) {
	$("body").empty().append("<span>" + interviews + "</span>");
}
catmh.showResults = function (results) {}
catmh.startInterview = function (interview) {}
catmh.submitAnswer = function() {}


// on ready
$(function() {
	$("#interview").css('display', 'flex').hide();
	// setTimeout(function() {
		// $("#loaderDiv").fadeOut(100, function() {
			// catmh.setAnswerOptions(['Never', 'Sometimes', 'Moderately', 'Very Much', 'Always']);
			// $("#interview").fadeIn(100);
		// });
	// }, 500);
});