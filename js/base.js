var catmh = {};
catmh.bridgeUrl = window.location.href;
catmh.bridgeUrl = catmh.bridgeUrl.replace('interview', 'CAT_MH');
// catmh.bridgeUrl = catmh.bridgeUrl.replace('&NOAUTH', '');

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
	}).on('blur', function() {
		$("#submitAnswer").addClass('disabled');
	});
}
catmh.setInterviewOptions = function() {
	$("ol").empty();
	$("#missingInterviewsNote").hide();
	if (typeof catmh.interview !== 'object') {
		$("#missingInterviewsNote").show();
		return;
	}
	
	$("#buttonInstructions, #beginInterview").css('display', 'inherit');
	catmh.interview.labels.forEach(label => {
		$("ol").append(`
				<li class='interviewLabel'>${label}</li>`);
	});
}

catmh.showError = function(message) {
	$("body > div:visible").fadeOut(100, function() {
		$("#error span").text(message);
		$("#error").fadeIn(100);
	});
}
catmh.showResults = function() {
	$("table > tr:not(first-child").remove();
	catmh.testResults.tests.forEach(function(test) {
		// if (test.hideResults == true) {
			// $("table").append(`
					// <tr>
						// <td>${test.label}</td>
						// <td>The results for this test have been saved in REDCap for your test provider to review.</td>
						// <td></td>
						// <td></td>
						// <td></td>
						// <td></td>
						// <td></td>
						// <td></td>
					// </tr>
// `);
		// } else {
			$("table").append(`
					<tr>
						<td>${test.label}</td>
						<td>${test.diagnosis==null ? 'N/A' : test.diagnosis}</td>
						<td>${test.confidence==null ? 'N/A' : test.confidence + '%'}</td>
						<td>${test.severity==null ? 'N/A' : test.severity + '%'}</td>
						<td>${test.category==null ? 'N/A' : test.category}</td>
						<td>${test.precision==null ? 'N/A' : test.precision + '%'}</td>
						<td>${test.prob==null ? 'N/A' : (test.prob.toPrecision(3)*100) + '%'}</td>
						<td>${test.percentile==null ? 'N/A' : test.percentile + '%'}</td>
					</tr>
`);
		// }
	});
	$("body > div:visible").fadeOut(100, function() {
		$("#interviewResults").fadeIn(100);
	});
}

catmh.authInterview = function() {
	// console.log('auth');
	if (typeof catmh.interview !== 'object') return;
	$("#loader span").text("Authorizing the interview...");
	let data = {
		action: 'authInterview',
		args: catmh.interview
	};
	
	// show loader
	$("body > div:visible").fadeOut(100, function() {
		$("#loader").fadeIn(100);
	})
	
	// authInterview first
	$.ajax({
		type: "POST",
		url: catmh.bridgeUrl,
		data: JSON.stringify(data),
		contentType: 'application/json',
		complete: function(xhr) {
			catmh.lastXhr = xhr;
			catmh.lastResponse = JSON.parse(xhr.responseText);
			if (catmh.lastResponse.success == true) {
				catmh.startInterview();
			} else {
				catmh.showError(catmh.lastResponse.moduleMessage);
			}
		}
	});
}
catmh.startInterview = function () {
	// console.log('start');
	$("#loader span").text("Initializing the interview...");
	let data = {
		action: 'startInterview',
		args: catmh.interview
	};
	
	// console.log("sending startInterview request");
	$.ajax({
		type: "POST",
		url: catmh.bridgeUrl,
		data: JSON.stringify(data),
		contentType: 'application/json',
		complete: function(xhr) {
			catmh.lastXhr = xhr;
			catmh.lastResponse = JSON.parse(xhr.responseText);
			if (catmh.lastResponse.success == true) {
				$("#loader span").text("Fetching the first question...");
				catmh.getQuestion();
			} else {
				catmh.showError(catmh.lastResponse.moduleMessage);
			}
		}
	});
}
catmh.getQuestion = function() {
	// console.log('question');
	let data = {
		action: 'getQuestion',
		args: catmh.interview
	};
	
	// console.log("sending getQuestion request");
	$.ajax({
		type: "POST",
		url: catmh.bridgeUrl,
		data: JSON.stringify(data),
		contentType: 'application/json',
		complete: function(xhr) {
			catmh.lastXhr = xhr;
			catmh.lastResponse = JSON.parse(xhr.responseText);
			if (catmh.lastResponse.success == true) {
				if (catmh.lastResponse.needResults) {
					$("#loader span").text("Test complete. Retrieving results.");
					catmh.getResults();
				} else {
					catmh.currentQuestion = JSON.parse(catmh.lastResponse.curl.body);
					
					// set question text
					$(".question").text(catmh.currentQuestion.questionNumber + '. ' + catmh.currentQuestion.questionDescription);
					
					// set answer options
					catmh.setAnswerOptions(catmh.currentQuestion.questionAnswers);
					catmh.questionDisplayTime = +new Date();
					$("body > div:visible").fadeOut(100, function() {
						$("#interviewTest").fadeIn(100);
					});
				}
			} else {
				catmh.showError(catmh.lastResponse.moduleMessage);
			}
		}
	});
}
catmh.submitAnswer = function() {
	// console.log('answer');
	let i = $('.answerSelector:focus').index('.answerSelector');
	if (i < 0) return;
	i++;
	
	let now = +new Date();
	catmh.interview.questionID = parseInt(catmh.currentQuestion.questionID);
	catmh.interview.response = parseInt(i);
	catmh.interview.duration = now - catmh.questionDisplayTime;
	let data = {
		action: 'submitAnswer',
		args: catmh.interview
	};
	
	// console.log("sending submitAnswer request");
	$.ajax({
		type: "POST",
		url: catmh.bridgeUrl,
		data: JSON.stringify(data),
		contentType: 'application/json',
		complete: function(xhr) {
			catmh.lastXhr = xhr;
			catmh.lastResponse = JSON.parse(xhr.responseText);
			if (catmh.lastResponse.success == true) {
				catmh.getQuestion();
			} else {
				catmh.showError(catmh.lastResponse.moduleMessage);
			}
		}
	});
	catmh.interview.questionID = null;
	catmh.interview.response = null;
	catmh.interview.duration = null;
	
	$("#loader span").text("Fetching the next question...");
	$("body > div:visible").fadeOut(100, function() {
		$("#loader").fadeIn(100);
	});
}
catmh.getResults = function() {
	// console.log('results');
	let data = {
		action: 'getResults',
		args: catmh.interview
	};
	
	// console.log("sending getResults request");
	$.ajax({
		type: "POST",
		url: catmh.bridgeUrl,
		data: JSON.stringify(data),
		contentType: 'application/json',
		complete: function(xhr) {
			catmh.lastXhr = xhr;
			catmh.lastResponse = JSON.parse(xhr.responseText);
			if (catmh.lastResponse.success == true) {
				catmh.testResults = JSON.parse(catmh.lastResponse.results);
				catmh.showResults();
				catmh.endInterview();
			} else {
				catmh.showError(catmh.lastResponse.moduleMessage);
			}
		}
	});
}
catmh.endInterview = function() {
	console.log('end');
	let data = {
		action: 'endInterview',
		args: catmh.interview
	};
	
	// console.log("sending endInterview request");
	$.ajax({
		type: "POST",
		url: catmh.bridgeUrl,
		data: JSON.stringify(data),
		contentType: 'application/json',
		complete: function(xhr) {
			catmh.lastXhr = xhr;
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
}
