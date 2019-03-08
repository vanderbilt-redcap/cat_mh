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
catmh.showInterviews = function() {
	let page = $("body");
	page.empty();
	// add header
	page.append("<h3>Select an interview</h3>");
	// add interview selector buttons and labels
	page.append("<ul></ul>");
	catmh.interviews.forEach(function(interview) {
		let button = "<button></button>";
	});
	// add begin button
	
}
catmh.showResults = function (results) {}
catmh.startInterview = function () {
	console.log('hiding');
	// $("#showInterviewsPage").hide();
}
catmh.submitAnswer = function() {}


// on ready
$(function() {
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