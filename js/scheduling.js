CATMH = {}

// initialize schedule (datatable) and calendar (datetimepicker)
$(function() {
	$('#calendar').datetimepicker({
		inline: true,
		dateFormat: "yy-mm-dd"
	})
	
	$("#seq_schedule").DataTable({
		pageLength: 50
	})
})

// show the user which sequence they selected from the dropdown
$('body').on('click', '.dropdown-menu a', function() {
	var dd = $(this).parent().siblings("button")
	dd.text($(this).text())
	$(".btn:first-child").val($(this).text())
	CATMH.selectedSequence = $(this).text()
})

// send user's calendar scheduling request to the server
$('body').on('click', '#scheduleByCalendar', function() {
	if (CATMH.debug)
		console.log('scheduleByCalendar')
	
	if (!CATMH.selectedSequence) {
		alert('Please select a sequence')
		return;
	}
	
	var	chosen_datetime = $("#calendar").val();
	var post_data = {
		sequence: CATMH.selectedSequence,
		datetime: chosen_datetime,
		schedulingMethod: "calendar"
	}
	
	$.ajax({
		type: "POST",
		url: CATMH.scheduling_ajax_url,
		data: post_data,
		success: function(response) {
			if (CATMH.debug)
				console.log('scheduleByCalendar ajax returned successfully', response)
		}
	})
})

// send user's interval scheduling request to the server
$('body').on('click', '#scheduleByInterval', function() {
	if (CATMH.debug)
		console.log('scheduleByInterval')
	
	if (!CATMH.selectedSequence) {
		alert('Please select a sequence')
		return;
	}
})
