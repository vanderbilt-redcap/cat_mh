var catmh = {};
// catmh.bridgeUrl = window.location.href;
// catmh.bridgeUrl = catmh.bridgeUrl.replace('interview', 'CAT_MH_CHA');
// catmh.auto_take_interview = true;

catmh.init = function() {
	// console.log('catmh.init -- interview.status:', catmh.interview.status);
	if (catmh.interview.status == 1) {
		catmh.setInterviewOptions();
	} else if (catmh.interview.status == 2) {
		catmh.getQuestion();
	} else if (catmh.interview.status == 3) {
		catmh.getResults();
	} else if (catmh.interview.status == 4) {
		$("#interviewResults").empty()
		$("#interviewResults").append("<h1>This computerized questionnaire has already been completed. Thank you!</h1>")
		$("body > div:visible").fadeOut(100, function() {
			$("#interviewResults").fadeIn(100);
		});
	}
	
	if (catmh.kcat_error) {
		catmh.showError(catmh.kcat_error)
	}
	
	// helps implement progress meter
	catmh.testTypesSeen = [];
}

catmh.setAnswerOptions = function(answers) {
	$(".answerSelector").remove()
	answers.forEach(answer => {
		$("#submitAnswer").before("<button data-ordinal='" + answer.answerOrdinal + "' data-weight='" + answer.answerWeight + "' class='answerSelector'>" + answer.answerDescription + "</button>");
	});
	$("button.answerSelector").on('touchstart mousedown', function() {
		$("#submitAnswer").removeClass('disabled');
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
		$("ol").append("<li class='interviewLabel'>" + label + "</li>");
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
		$("table").append("\
				<tr>\
					<td>" + (test.label) + "</td>\
					<td>" + (test.diagnosis===null ? 'N/A' : test.diagnosis) + "</td>\
					<td>" + (test.confidence===null ? 'N/A' : test.confidence + '%') + "</td>\
					<td>" + (test.severity===null ? 'N/A' : test.severity + '%') + "</td>\
					<td>" + (test.category===null ? 'N/A' : test.category) + "</td>\
					<td>" + (test.precision===null ? 'N/A' : test.precision + '%') + "</td>\
					<td>" + (test.prob===null ? 'N/A' : (test.prob.toPrecision(3)*100) + '%') + "</td>\
					<td>" + (test.percentile===null ? 'N/A' : test.percentile + '%') + "</td>\
				</tr>\
		");
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
	
    // Update user to inform them we're trying to find a question instead of showing "can't find interview"
    catmh.showError("Interview already started, attempting to retrieve next question.");

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
					
					// set question note (timeframe text)
					if (typeof(catmh.currentQuestion.questionNote) == 'string') {
						$("div#questionNote").empty();
						$("div#questionNote").append("<span>" + catmh.currentQuestion.questionNote + "</span>");
					}
					// hide div if no question note present
					if ($("div#questionNote").is(":empty")) {
						$("div#questionNote").hide();
					} else {
						$("div#questionNote").show();
					}
					
					// set question text
					var question_text = catmh.currentQuestion.questionDescription;
					if (catmh.interview.hide_question_number == false) {
						question_text = catmh.currentQuestion.questionNumber + '. ' + question_text;
					}
					$(".question").text(question_text);
					// $(".question").text(catmh.currentQuestion.questionNumber + '. ' + catmh.currentQuestion.questionDescription);
					
					// set answer options
					catmh.setAnswerOptions(catmh.currentQuestion.questionAnswers);
					catmh.questionDisplayTime = +new Date();
					$("body > div:visible").fadeOut(100, function() {
						$("#interviewTest").fadeIn(100);
					});
					
					// update interview progress meter
					if (typeof(catmh.lastResponse.question_test_types) == 'object') {
						// console.log('question type(s):', JSON.stringify(catmh.lastResponse.question_test_types));
						catmh.updateProgressMeter();
					}
					
					if (catmh.auto_take_interview) {
						var answer_index = Math.floor(Math.random()*$('.answerSelector').length);
						$('.answerSelector').eq(answer_index).trigger('mousedown');
						catmh.submitAnswer();
					}
				}
			} else {
				catmh.showError(catmh.lastResponse.moduleMessage);
			}
		}
	});
}
catmh.updateProgressMeter = function() {
	var question_tests = JSON.stringify(catmh.lastResponse.question_test_types);
	var current_test_index = 0;
	// console.log('question_tests', question_tests);
	
	if (catmh.testTypesSeen.indexOf(question_tests) < 0) {
		catmh.testTypesSeen.push(question_tests);
	}
	current_test_index = catmh.testTypesSeen.length - 1;
	
	// console.log('current_test_index', current_test_index);
	$('div#progress_meter img').each(function(i, icon) {
		if (i < current_test_index) {
			$(icon).attr('src', catmh.progress_meter_circle_urls.green);
		} else if (i == current_test_index) {
			$(icon).attr('src', catmh.progress_meter_circle_urls.blue);
		} else {
			$(icon).attr('src', catmh.progress_meter_circle_urls.gray);
		}
	});
}
catmh.submitAnswer = function() {
	// console.log('answer');
	let i = $('.answerSelector.selected').index('.answerSelector');
	if (i < 0) return;
	i++;
	
	$("#submitAnswer").addClass('disabled')
	$("#submitAnswer").blur()
	
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
	// console.log('end');
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
	})
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
