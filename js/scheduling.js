$(function() {
	console.log('document ready');
})

$('body').on('click', '.dropdown-menu a', function() {
	var dd = $(this).parent().siblings("button");
	dd.text($(this).text());
	$(".btn:first-child").val($(this).text());
	
	// if (dd[0] == $("#coachDropdown")[0]) {
		// DPP.coach = dd.text();
	// } else if (dd[0] == $("#cohortDropdown")[0]) {
		// DPP.cohort = dd.text();
	// }
})