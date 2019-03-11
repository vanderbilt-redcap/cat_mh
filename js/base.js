var catmh = {};
catmh.testTypes = {
	mdd: "Major Depressive Disorder",
	dep: "Depression",
	anx: "Anxiety Disorder",
	mhm: "Mania/Hypomania",
	pdep: "Depression (Perinatal)",
	panx: "Anxiety Disorder (Perinatal)",
	pmhm: "Mania/Hypomania (Perinatal)",
	sa: "Substance Abuse",
	ptsd: "Post-Traumatic Stress Disorder",
	cssrs: "C-SSRS Suicide Screen",
	ss: "Suicide Scale"
};
catmh.testStatuses = [
	'incomplete',
	'in progress',
	'complete',
	'complete'
];

catmh.setAnswerOptions = function(answers) {
	$(".answerSelector").remove()
	answers.forEach(answer => {
		$("#submitAnswer").insertBefore(`
			<button class='answerSelector'>` + answer + `</button>`);
	});
	$("button.answerSelector").on('focus', function() {
		$("#submitAnswer").removeClass('disabled');
	}).on('blur', function() {
		$("#submitAnswer").addClass('disabled');
	});
}
catmh.setInterviewOptions = function() {
	$("ul").empty()
	catmh.interviews.forEach(test => {
		$("ul").append(`
				<li>
					<button type='button' class='interviewSelector'>` + catmh.testTypes[test['type']] + `</button>
					<span class='interviewLabel'>(` + catmh.testStatuses[test['status']] + `)</span>
				</li>`);
	});
	$("button.interviewSelector").on('focus', function() {
		$("#beginInterview").removeClass('disabled');
		catmh.lastInterviewSelected = $('.interviewSelector').index(this);
	}).on('blur', function() {
		$("#beginInterview").addClass('disabled');
	});
}
catmh.showError = function(message) {
	$("body > div:visible").fadeOut(150, function() {
		$("#error span").empty().append(message);
		$("#error").fadeIn(100);
	});
}
catmh.showInterviews = function() {
	$("body > div:visible").fadeOut(150, function() {
		$("#interviewSelect").fadeIn(100);
	});
}
catmh.showResults = function (results) {
	$("body > div:visible").fadeOut(150, function() {
		$("#interviewResults").fadeIn(100);
	});
}
catmh.startInterview = function () {
	let sid = getUrlParameter('sid');
	let interview = catmh.interviews[catmh.lastInterviewSelected]
	let data = {
		sid: sid,
		action: 'startInterview',
		args: {
			
		}
	};
	$.ajax({
		type: "POST",
		url: window.location.href.replace('interview', 'CAT_MH'),
		data: JSON.stringify(data),
		contentType: 'application/json',
		dataType: 'text',
		complete: function(result) {
			// $('.question').empty().append(result);
			console.log(result.responseText);
		}
	});
	$("body > div:visible").fadeOut(150, function() {
		$("#interviewTest").fadeIn(100);
	});
}
catmh.submitAnswer = function() {}

var getUrlParameter = function getUrlParameter(sParam) {
    var sPageURL = window.location.search.substring(1),
        sURLVariables = sPageURL.split('&'),
        sParameterName,
        i;

    for (i = 0; i < sURLVariables.length; i++) {
        sParameterName = sURLVariables[i].split('=');

        if (sParameterName[0] === sParam) {
            return sParameterName[1] === undefined ? true : decodeURIComponent(sParameterName[1]);
        }
    }
};