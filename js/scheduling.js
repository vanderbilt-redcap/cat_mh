CATMH = {}

CATMH.addSelectCheckboxes = function() {
	// add select checkboxes for each #seq_schedule tbody row
	$("#seq_schedule tbody tr").each(function() {
		$(this).find('td:first').html("<input type='checkbox'>")
	})
}

// initialize schedule (datatable) and calendar (datetimepicker)
$(function() {
	$('#calendar').datetimepicker({
		inline: true,
		dateFormat: "yy-mm-dd"
	})
	
	
	CATMH.schedule = $("#seq_schedule").DataTable({
		data: CATMH.scheduledSequences,
		pageLength: 50,
		columnDefs: [
			{className: 'dt-center', targets: '_all'}
		],
		order: [[1, 'asc']]
	})
	
	// add select checkboxes for each sequence in table
	if (CATMH.scheduledSequences.length) {
		CATMH.addSelectCheckboxes()
	}
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
		return
	}
	
	var	chosen_datetime = $("#calendar").val()
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
			if (response.error) {
				alert(response.error)
			}
			if (response.sequences) {
				response.sequences.forEach(function(row, i) {
					row[0] = "<input type='checkbox'>"
				})
				CATMH.schedule.clear()
				CATMH.schedule.rows.add(response.sequences)
				CATMH.schedule.draw()
			}
		},
		dataType: 'json'
	})
})

// send user's interval scheduling request to the server
$('body').on('click', '#scheduleByInterval', function() {
	if (CATMH.debug)
		console.log('scheduleByInterval')
	
	if (!CATMH.selectedSequence) {
		alert('Please select a sequence')
		return
	}
	
	var post_data = {
		sequence: CATMH.selectedSequence,
		schedulingMethod: "interval",
		frequency: $("#frequency").val(),
		duration: $("#duration").val(),
		delay: $("#delay").val(),
		time_of_day: $("#time_of_day").val()
	}
	console.log("post_data", post_data)
	
	$.ajax({
		type: "POST",
		url: CATMH.scheduling_ajax_url,
		data: post_data,
		success: function(response) {
			if (CATMH.debug)
				console.log('scheduleByCalendar ajax returned successfully', response)
			if (response.error) {
				alert(response.error)
			}
			if (response.sequences) {
				response.sequences.forEach(function(row, i) {
					row[0] = "<input type='checkbox'>"
				})
				CATMH.schedule.clear()
				CATMH.schedule.rows.add(response.sequences)
				CATMH.schedule.draw()
			}
		},
		dataType: 'json'
	})
})
