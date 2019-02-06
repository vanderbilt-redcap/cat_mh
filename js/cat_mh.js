var catmh = {
	debug: true,
	apiDetails: JSON.parse($('div.apiDetails').html()),
	subjectID: (Math.random().toString(36)+'00000000000000000').slice(2, 16+2)
	// members from API: interviews
}

// presentation functions


// api related functions
catmh.authInterview = function(which) {
	
}

catmh.breakLock = function() {
	
}

catmh.createInterviews = function() {
	let timeToFadeOut = 150;
	$.ajax({
		method: "POST",
		// url: "https://www.cat-mh.com/portal/secure/interview/createInterview",
		url: "?prefix=cat_mh&page=test&pid=25&action=create",
		data: {
			organizationID: catmh.apiDetails.organizationID,
			userFirstName: "Automated",
			userLastName: "Creation",
			subjectID: catmh.subjectID,
			numberOfInterviews: 1,
			language: 1,
			tests: ['mdd']
		},
		headers: {
			applicationid: catmh.apiDetails.applicationid
		},
		dataType: 'json',
		success: function(data) {
			$('div.loaderText').fadeOut(timeToFadeOut);
			$('div.loader').fadeOut(timeToFadeOut, function() {
				// debug check
				// $('.content').prepend('<pre>DOCUMENT:\n' + JSON.stringify(data.interviews) + '</pre>')
				
				// store received interview data in catmh object
				catmh.interviews = data.interviews
				catmh.authInterview(1);
			});
		},
		error: function(request, status, thrown) {
			$('div.loaderText').fadeOut(timeToFadeOut);
			$('div.loader').fadeOut(timeToFadeOut, function() {
				// $('.errorContainer').prepend("")
				$('.errorContainer').css("display", "flex").hide().fadeIn(150);
			});
		}
	});
}

catmh.initInterview = function() {
	
}

catmh.getInterviewStatus = function(which = 0) {
	let interview = catmh.interviews[which];
	
	$.ajax({
		method: "POST",
		// url: "https://www.cat-mh.com/portal/secure/interview/status",
		url: "?prefix=cat_mh&page=test&pid=25&action=status",
		data: {
			organizationID: catmh.apiDetails.organizationID,
			interviewID: interview.interviewID,
			identifier: interview.identifier,
			signature: interview.signature
		},
		headers: {
			applicationid: catmh.apiDetails.applicationid
		},
		dataType: 'json',
		success: function(data) {
			$('div.loaderText').fadeOut(timeToFadeOut);
			$('div.loader').fadeOut(timeToFadeOut, function() {
				// debug check
				// $('.content').prepend('<pre>DOCUMENT:\n' + JSON.stringify(data.interviews) + '</pre>')
				
				// store received interview data in catmh object
				catmh.interviews = data.interviews
				catmh.authInterview(1);
			});
		},
		error: function(request, status, thrown) {
			$('.content').fadeOut(150, function() {
				$('.errorContainer').css("display", "flex").hide().fadeIn(150);
			});
		}
	});
}

catmh.getNextQuestion = function() {
	
}

catmh.retrieveResults = function() {
	
}

catmh.submitAnswer = function() {
	
}

catmh.terminateInterview = function() {
	
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
	$('.loader').css("display", "flex").hide().fadeIn(1500);
	
	// call to CAT-MH API to create interview
	catmh.createInterview();
})