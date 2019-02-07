var catmh = {
	debug: true,
	apiDetails: JSON.parse($('div.apiDetails').html()),
	subjectID: (Math.random().toString(36)+'00000000000000000').slice(2, 16+2)
	// members from API: interviews, currentInterview
}

// presentation functions


// api related functions
catmh.authInterview = function() {
	$.ajax({
		method: "POST",
		// url: "https://www.cat-mh.com/interview/signin",
		url: "?prefix=cat_mh&page=test&pid=" + catmh.pid + "&action=auth",
		data: {
			j_username: catmh.currentInterview.identifier,
			j_password: catmh.currentInterview.signature,
			interviewID: catmh.currentInterview.interviewID
		},
		dataType: 'json',
		success: function(data) {
			// debug check
			// data.cookie = document.cookie;
			$('.diagnostic').html('<pre>DATA:\n' + JSON.stringify(data, null, 2) + '</pre>')
		},
		error: function(request, status, thrown) {
			
		}
	});
}

catmh.breakLock = function() {
	$.ajax({
		method: "POST",
		// url: "https://www.cat-mh.com/interview/secure/breakLock",
		url: "?prefix=cat_mh&page=test&pid=" + catmh.pid + "&action=break",
		headers: {
			Accept: 'application/json'
		},
		dataType: 'json',
		beforeSend: function() {
			catmh.currentInterview.JSESSIONID = 'abc123';
			catmh.currentInterview.AWSELB = '456xyz';
			document.cookie = `JSESSIONID=${catmh.currentInterview.JSESSIONID};`;
			document.cookie = `AWSELB=${catmh.currentInterview.AWSELB};`;
		},
		success: function(data) {
			// debug check
			$('.diagnostic').html('<pre>DATA:\n' + JSON.stringify(data, null, 2) + '</pre>')
		},
		error: function(request, status, thrown) {
			
		}
	});
}

catmh.createInterviews = function() {
	let timeToFadeOut = 150;
	$.ajax({
		method: "POST",
		// url: "https://www.cat-mh.com/portal/secure/interview/createInterview",
		url: "?prefix=cat_mh&page=test&pid=" + catmh.pid + "&action=create",
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
		success: function(data) {
			$('.loader').fadeOut(timeToFadeOut, function() {
				// debug check
				$('.diagnostic').html('<pre>DATA:\n' + JSON.stringify(data, null, 2) + '</pre>')
				
				// store received interview data in catmh object
				catmh.interviews = data.interviews
				catmh.currentInterview = catmh.interviews[0];
				// catmh.authInterview();
			});
		},
		error: function(request, status, thrown) {
			$('div.loader').fadeOut(timeToFadeOut, function() {
				// $('.errorContainer').prepend("")
				$('.errorContainer').css("display", "flex").hide().fadeIn(150);
			});
		}
	});
}

catmh.initInterview = function() {
	$.ajax({
		method: "GET",
		// url: "https://www.cat-mh.com/interview/rest/interview",
		url: "?prefix=cat_mh&page=test&pid=" + catmh.pid + "&action=init",
		headers: {
			Accept: 'application/json'
		},
		dataType: 'json',
		beforeSend: function() {
			catmh.currentInterview.JSESSIONID = 'abc123';
			catmh.currentInterview.AWSELB = '456xyz';
			document.cookie = `JSESSIONID=${catmh.currentInterview.JSESSIONID};`;
			document.cookie = `AWSELB=${catmh.currentInterview.AWSELB};`;
		},
		success: function(data) {
			// debug check
			$('.diagnostic').html('<pre>DATA:\n' + JSON.stringify(data, null, 2) + '</pre>')
		},
		error: function(request, status, thrown) {
			
		}
	});
}

catmh.getInterviewStatus = function() {
	$.ajax({
		method: "POST",
		// url: "https://www.cat-mh.com/portal/secure/interview/status",
		url: "?prefix=cat_mh&page=test&pid=" + catmh.pid + "&action=getStatus",
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
			$('.diagnostic').html('<pre>DATA:\n' + JSON.stringify(data, null, 2) + '</pre>')
		},
		error: function(request, status, thrown) {
			
		}
	});
}

catmh.getNextQuestion = function() {
	$.ajax({
		method: "GET",
		// url: "https://www.cat-mh.com/interview/rest/interview/test/question",
		url: "?prefix=cat_mh&page=test&pid=" + catmh.pid + "&action=getQuestion",
		headers: {
			Accept: 'application/json'
		},
		dataType: 'json',
		beforeSend: function() {
			catmh.currentInterview.JSESSIONID = 'abc123';
			catmh.currentInterview.AWSELB = '456xyz';
			document.cookie = `JSESSIONID=${catmh.currentInterview.JSESSIONID};`;
			document.cookie = `AWSELB=${catmh.currentInterview.AWSELB};`;
		},
		success: function(data) {
			// debug check
			$('.diagnostic').html('<pre>DATA:\n' + JSON.stringify(data, null, 2) + '</pre>')
		},
		error: function(request, status, thrown) {
			
		}
	});
}

catmh.retrieveResults = function() {
	$.ajax({
		method: "GET",
		// url: "https://www.cat-mh.com/interview/rest/interview/results?itemLevel=1",
		url: "?prefix=cat_mh&page=test&pid=" + catmh.pid + "&action=results",
		headers: {
			Accept: 'application/json'
		},
		dataType: 'json',
		beforeSend: function() {
			catmh.currentInterview.JSESSIONID = 'abc123';
			catmh.currentInterview.AWSELB = '456xyz';
			document.cookie = `JSESSIONID=${catmh.currentInterview.JSESSIONID};`;
			document.cookie = `AWSELB=${catmh.currentInterview.AWSELB};`;
		},
		success: function(data) {
			// debug check
			$('.diagnostic').html('<pre>DATA:\n' + JSON.stringify(data, null, 2) + '</pre>')
		},
		error: function(request, status, thrown) {
			
		}
	});
}

catmh.submitAnswer = function() {
	$.ajax({
		method: "POST",
		// url: "https://www.cat-mh.com/interview/rest/interview/test/question",
		url: "?prefix=cat_mh&page=test&pid=" + catmh.pid + "&action=submit",
		headers: {
			Accept: 'application/json'
		},
		dataType: 'json',
		beforeSend: function() {
			catmh.currentInterview.JSESSIONID = 'abc123';
			catmh.currentInterview.AWSELB = '456xyz';
			document.cookie = `JSESSIONID=${catmh.currentInterview.JSESSIONID};`;
			document.cookie = `AWSELB=${catmh.currentInterview.AWSELB};`;
		},
		data: {
			questionID: 1,
			response: 1,
			duration: 452,
			curT1: 0,
			curT2: 0,
			curT3: 0
		},
		success: function(data) {
			// debug check
			$('.diagnostic').html('<pre>DATA:\n' + JSON.stringify(data, null, 2) + '</pre>')
		},
		error: function(request, status, thrown) {
			
		}
	});
}

catmh.terminateInterview = function() {
	$.ajax({
		method: "GET",
		// url: "https://www.cat-mh.com/interview/signout",
		url: "?prefix=cat_mh&page=test&pid=" + catmh.pid + "&action=terminate",
		headers: {
			Accept: 'application/json'
		},
		dataType: 'json',
		beforeSend: function() {
			catmh.currentInterview.JSESSIONID = 'abc123';
			catmh.currentInterview.AWSELB = '456xyz';
			document.cookie = `JSESSIONID=${catmh.currentInterview.JSESSIONID};`;
			document.cookie = `AWSELB=${catmh.currentInterview.AWSELB};`;
		},
		success: function(data) {
			// debug check
			$('.diagnostic').html('<pre>DATA:\n' + JSON.stringify(data, null, 2) + '</pre>')
		},
		error: function(request, status, thrown) {
			
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
	
	// $('.loader').css("display", "flex").hide().fadeIn(1500);
	
	// call to CAT-MH API to create interview
	// catmh.createInterviews();
})