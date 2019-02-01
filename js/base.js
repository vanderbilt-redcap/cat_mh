$(function() {
	var catmh = {
		debug: true
	};
	
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
	catmh.pid = catmh.findGetParameter('pid');
	catmh.record = catmh.findGetParameter('record');
	catmh.user = JSON.parse($('#userInfo').html());
	
	if (catmh.debug) {
		// fetch mock interview from redcap
		let url = window.location.href;
		let i = url.indexOf('ExternalModules/?');
		url = url.slice(0, i+16) + '?prefix=cat_mh&page=&pid=' + catmh.pid;
		console.log(url);
	} else {
		// fetch interview from CAT-MH server
	}
	
	// click handler for nav buttons
	// $("nav div").on("click", function(e) {
		// if ($(this).hasClass('selected')) return;
		
		// $("nav div").removeClass('selected');
		// $(this).addClass('selected');
		
		// ajax requested screen
		// var url = window.location.origin + "/plugins/nitin/index.php";
		// $.ajax({
			// url: "index.php",
			// data: { screen : $(this).text() },
			// dataType: "html",
			// success : function(data) {
				// $("#content").html(data);
				// $(".dataTable").DataTable({
					// paging: false,
					// info: false,
					// searching: false
				// });
			// },
			// fail : function(data) {
				// $("#content").html("<pre>There was an error:\n" + data + "</pre>")
			// }
		// })
	// })
})

// console.log()