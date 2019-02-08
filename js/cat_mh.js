var catmh = {
	debug: false,
	apiDetails: JSON.parse($('#apiDetails').html()),
	subjectID: (Math.random().toString(36)+'00000000000000000').slice(2, 16+2)
	// members from API: interviews, currentInterview
}

// presentation functions


// api related functions
catmh.authInterview = function() {
	let url = catmh.debug ? "?prefix=cat_mh&page=test&pid=" + catmh.pid + "&action=auth" : "https://www.cat-mh.com/interview/signin";
	$.ajax({
		method: "POST",
		url: url,
		data: {
			j_username: catmh.currentInterview.identifier,
			j_password: catmh.currentInterview.signature,
			interviewID: catmh.currentInterview.interviewID
		},
		dataType: 'json',
		complete: function(request, status) {
			if (request.status == 302) {
				$('#diagnostic').html('<pre>SUCCESS:\n' + JSON.stringify(request) + '</pre>')
			} else {
				$('#diagnostic').html('<pre>REQUEST:\n' + JSON.stringify(request) + '</pre>')
			}
		}
	});
}
catmh.breakLock = function() {
	let url = catmh.debug ? "?prefix=cat_mh&page=test&pid=" + catmh.pid + "&action=break" : "https://www.cat-mh.com/interview/secure/breakLock";
	$.ajax({
		method: "POST",
		url: url,
		headers: {
			Accept: 'application/json'
		},
		dataType: 'json',
		// beforeSend: function() {
			// catmh.currentInterview.JSESSIONID = 'abc123';
			// catmh.currentInterview.AWSELB = '456xyz';
			// document.cookie = `JSESSIONID=${catmh.currentInterview.JSESSIONID};`;
			// document.cookie = `AWSELB=${catmh.currentInterview.AWSELB};`;
		// },
		complete: function(request, status) {
			if (request.status == 302) {
				$('#diagnostic').html('<pre>SUCCESS:\n' + JSON.stringify(request) + '</pre>')
			} else {
				$('#diagnostic').html('<pre>REQUEST:\n' + JSON.stringify(request) + '</pre>')
			}
		}
	});
}
catmh.createInterviews = function() {
	let timeToFadeOut = 150;
	let url = catmh.debug ? "?prefix=cat_mh&page=test&pid=" + catmh.pid + "&action=create" : "https://www.cat-mh.com/portal/secure/interview/createInterview";
	$.ajax({
		method: "POST",
		url: url,
		headers: {
			applicationid: catmh.apiDetails.applicationid
		},
		data: {
			organizationID: catmh.apiDetails.organizationID,
			userFirstName: "Automated",
			userLastName: "Creation",
			subjectID: catmh.subjectID,
			numberOfInterviews: 1,
			language: 1,
			tests: ['mdd']
		},
		dataType: 'json',
		beforeSend: function(request, settings) {
			request.withCredentials = true;
		},
		success: function(data) {
			$('#loader').fadeOut(timeToFadeOut, function() {
				// debug check
				$('#diagnostic').html('<pre>DATA:\n' + JSON.stringify(data, null, 2) + '</pre>')
				
				// store received interview data in catmh object
				catmh.interviews = data.interviews
				catmh.currentInterview = catmh.interviews[0];
				
				// catmh.authInterview();
			});
		},
		error: function(request, status, thrown) {
			$('#loader').fadeOut(timeToFadeOut, function() {
				// $('#content').hide();
				$('#diagnostic').html('<pre>ERROR:\n' + JSON.stringify(request) + '</pre>')
				// $('#error').css("display", "flex").hide().fadeIn(250);
			});
		}
	});
}
catmh.initInterview = function() {
	let url = catmh.debug ? "?prefix=cat_mh&page=test&pid=" + catmh.pid + "&action=init" : "https://www.cat-mh.com/interview/rest/interview";
	$.ajax({
		method: "GET",
		url: url,
		headers: {
			Accept: 'application/json'
		},
		dataType: 'json',
		success: function(data) {
			// debug check
			$('#diagnostic').html('<pre>DATA:\n' + JSON.stringify(data, null, 2) + '</pre>')
			// if (data.id > 0) {
				
			// }
		},
		error: function(request, status, thrown) {
			$('#diagnostic').html('<pre>ERROR:\n' + JSON.stringify(request) + '</pre>')
		}
	});
}
catmh.getInterviewStatus = function() {
	let url = catmh.debug ? "?prefix=cat_mh&page=test&pid=" + catmh.pid + "&action=getStatus" : "https://www.cat-mh.com/portal/secure/interview/status";
	$.ajax({
		method: "POST",
		url: url,
		headers: {
			applicationid: catmh.apiDetails.applicationid
		},
		data: {
			organizationID: catmh.apiDetails.organizationID,
			interviewID: catmh.currentInterview.interviewID,
			identifier: catmh.currentInterview.identifier,
			signature: catmh.currentInterview.signature
		},
		dataType: 'json',
		success: function(data) {
			// debug check
			$('#diagnostic').html('<pre>DATA:\n' + JSON.stringify(data, null, 2) + '</pre>')
		},
		error: function(request, status, thrown) {
			$('#diagnostic').html('<pre>ERROR:\n' + JSON.stringify(request) + '</pre>')
		}
	});
}
catmh.getNextQuestion = function() {
	let url = catmh.debug ? "?prefix=cat_mh&page=test&pid=" + catmh.pid + "&action=getQuestion" : "https://www.cat-mh.com/interview/rest/interview/test/question";
	$.ajax({
		method: "GET",
		url: url,
		headers: {
			Accept: 'application/json'
		},
		dataType: 'json',
		success: function(data) {
			// debug check
			$('#diagnostic').html('<pre>DATA:\n' + JSON.stringify(data, null, 2) + '</pre>')
			catmh.lastQuestionID = data.questionID;
		},
		error: function(request, status, thrown) {
			$('#diagnostic').html('<pre>ERROR:\n' + JSON.stringify(request) + '</pre>')
		}
	});
}
catmh.retrieveResults = function() {
	let url = catmh.debug ? "?prefix=cat_mh&page=test&pid=" + catmh.pid + "&action=results" : "https://www.cat-mh.com/interview/rest/interview/results?itemLevel=1";
	$.ajax({
		method: "GET",
		url: url,
		headers: {
			Accept: 'application/json'
		},
		dataType: 'json',
		success: function(data) {
			// debug check
			$('#diagnostic').html('<pre>DATA:\n' + JSON.stringify(data, null, 2) + '</pre>')
		},
		error: function(request, status, thrown) {
			$('#diagnostic').html('<pre>ERROR:\n' + JSON.stringify(request) + '</pre>')
		}
	});
}
catmh.submitAnswer = function() {
	let url = catmh.debug ? "?prefix=cat_mh&page=test&pid=" + catmh.pid + "&action=results" : "https://www.cat-mh.com/interview/rest/interview/test/question";
	$.ajax({
		method: "POST",
		url: url,
		headers: {
			Accept: 'application/json'
		},
		dataType: 'json',
		data: {
			questionID: catmh.lastQuestionID,
			response: 1,
			duration: Math.random() * (2000 - 200) + 200,
			curT1: 0,
			curT2: 0,
			curT3: 0
		},
		complete: function(request, status) {
			if (request.status == 200) {
				$('#diagnostic').html('<pre>SUCCESS:\n' + JSON.stringify(request) + '</pre>')
			} else {
				$('#diagnostic').html('<pre>REQUEST:\n' + JSON.stringify(request) + '</pre>')
			}
		}
	});
}
catmh.terminateInterview = function() {
	let url = catmh.debug ? "?prefix=cat_mh&page=test&pid=" + catmh.pid + "&action=terminate" : "https://www.cat-mh.com/interview/signout";
	$.ajax({
		method: "GET",
		url: url,
		headers: {
			Accept: 'application/json'
		},
		dataType: 'json',
		complete: function(request, status) {
			if (request.status == 302) {
				$('#diagnostic').html('<pre>SUCCESS:\n' + JSON.stringify(request) + '</pre>')
			} else {
				$('#diagnostic').html('<pre>REQUEST:\n' + JSON.stringify(request) + '</pre>')
			}
		}
	});
}

// module logic functions


// utility functions
catmh.findGetParameter = function(parameterName) {
	var result = null,
		tmp = [];
	location.search
		.substr(1)
		.split("&")
		.forEach(function (item) {
		  tmp = item.split("=");
		  if (tmp[0] === parameterName) result = decodeURIComponent(tmp[1]);
		});
	return result;
}


$(function() {
	catmh.pid = catmh.findGetParameter("pid");
	// if (catmh.debug) {
		$('#diagnostic').show();
	// } else {
		
	// }
	
	// call to CAT-MH API to create interview
	// catmh.createInterviews();
})