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
		$("#submitAnswer").before(`
			<button data-ordinal='` + answer.answerOrdinal + `' data-weight='` + answer.answerWeight + `' class='answerSelector'>` + answer.answerDescription + `</button>`);
	});
	$("button.answerSelector").on('focus', function() {
		$("#submitAnswer").removeClass('disabled');
		catmh.lastAnswerSelected = $(this).attr('data-ordinal');
	}).on('blur', function() {
		$("#submitAnswer").addClass('disabled');
	});
}
catmh.setInterviewOptions = function() {
	$("ul").empty()
	catmh.interviews.forEach(test => {
		$("ul").append(`
				<li>
					<button type='button' class='interviewSelector'>` + test['label'] + `</button>
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

catmh.authInterview = function() {
	$("#loader span").text("Authorizing the interview...");
	if (catmh.startingInterview) {
		return;
	} else {
		catmh.startingInterview = true
	}
	catmh.currentInterview = catmh.interviews[catmh.lastInterviewSelected];
	let data = {
		action: 'authInterview',
		args: catmh.currentInterview
	};
	
	// show loader
	$("body > div:visible").fadeOut(100, function() {
		$("#loader").fadeIn(100);
	})
	
	// authInterview first
	$.ajax({
		type: "POST",
		url: window.location.href.replace('interview', 'CAT_MH'),
		data: JSON.stringify(data),
		contentType: 'application/json',
		complete: function(xhr) {
			catmh.lastXhr = xhr;
			catmh.lastResponse = JSON.parse(xhr.responseText);
			if (catmh.lastResponse.success == true) {
				catmh.startingInterview = null;
				catmh.currentInterview.status = 1;
				catmh.startInterview(catmh.currentInterview);
			}
		}
	});
}
catmh.startInterview = function () {
	$("#loader span").text("Initializing the interview...");
	let data = {
		action: 'startInterview',
		args: catmh.currentInterview
	};
	
	$.ajax({
		type: "POST",
		url: window.location.href.replace('interview', 'CAT_MH'),
		data: JSON.stringify(data),
		contentType: 'application/json',
		complete: function(xhr) {
			let obj = JSON.parse(xhr.responseText);
			if (obj.success == true) {
				let obj2 = JSON.parse(obj.response);
				catmh.currentInterview.JSESSIONID = obj2.JSESSIONID;
				catmh.currentInterview.AWSELB = obj2.AWSELB;
				$("#loader span").text("Fetching the first question...");
				catmh.getQuestion();
			}
		}
	});
}
catmh.getQuestion = function() {
	let data = {
		action: 'getQuestion',
		args: catmh.currentInterview
	};
	
	$.ajax({
		type: "POST",
		url: window.location.href.replace('interview', 'CAT_MH'),
		data: JSON.stringify(data),
		contentType: 'application/json',
		complete: function(xhr) {
			let obj = JSON.parse(xhr.responseText);
			if (obj.success == true) {
				catmh.currentQuestion = JSON.parse(obj.response);
				
				// set question text
				$(".question").text(catmh.currentQuestion.questionNumber + '. ' + catmh.currentQuestion.questionDescription);
				
				// set answer options
				catmh.setAnswerOptions(catmh.currentQuestion.questionAnswers);
				$("body > div:visible").fadeOut(100, function() {
					$("#interviewTest").fadeIn(100);
					catmh.questionDisplayTime = +new Date()
				});
			}
			if (obj.needResults) {
				// todo, fetch results and send user to results screen
			}
		}
	});
}
catmh.submitAnswer = function() {
	let now = +new Date();
	catmh.currentInterview.questionID = parseInt(catmh.currentQuestion.questionID);
	catmh.currentInterview.response = parseInt(catmh.lastAnswerSelected);
	catmh.currentInterview.duration = now - catmh.questionDisplayTime;
	let data = {
		action: 'submitAnswer',
		args: catmh.currentInterview
	};
	
	$.ajax({
		type: "POST",
		url: window.location.href.replace('interview', 'CAT_MH'),
		data: JSON.stringify(data),
		contentType: 'application/json',
		complete: function(xhr) {
			let obj = JSON.parse(xhr.responseText);
			if (obj.success == true) {
				catmh.getQuestion();
			}
		}
	});
}
catmh.getResults = function() {
	let data = {
		action: 'getResults',
		args: catmh.currentInterview
	};
	
	$.ajax({
		type: "POST",
		url: window.location.href.replace('interview', 'CAT_MH'),
		data: JSON.stringify(data),
		contentType: 'application/json',
		complete: function(xhr) {
			let obj = JSON.parse(xhr.responseText);
			if (obj.success == true) {
				let testResults = JSON.parse(obj.response);
			}
		}
	});
}

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
function getMicrotime() {
    var s,
        now = (Date.now ? Date.now() : new Date().getTime()) / 1000;
    s = now | 0
    return (Math.round((now - s) * 1000) / 1000) + ' ' + s
}