CATMH = {}

// CATMH.selectCheckbox = "<input type='checkbox' class='sequence_cbox'>"

// CATMH.addSelectCheckboxes = function() {
	// // add select checkboxes for each #seq_schedule tbody row
	// $("#seq_schedule tbody tr").each(function() {
		// $(this).find('td:first').html(CATMH.selectCheckbox)
	// })
// }

CATMH.rebuildSequencesTable = function(sequences) {
	CATMH.schedule.clear()
	CATMH.schedule.rows.add(sequences)
	CATMH.schedule.draw()
	$("#deleteScheduledSequence").attr('disabled', true)
}

CATMH.submitReminderSettings = function() {
	var post_data = {
		schedulingMethod: 'setReminderSettings',
		enabled: $("#reminders_cbox:checked").val(),
		frequency: $("#reminder_frequency").val(),
		duration: $("#reminder_duration").val(),
		delay: $("#reminder_delay").val()
	}
	// console.log('reminder_settings sent', post_data)
	
	$.ajax({
		type: "POST",
		url: CATMH.scheduling_ajax_url,
		data: post_data,
		success: function(response) {
			if (CATMH.debug)
				// console.log('scheduleSingle ajax returned successfully', response)
			if (response.error) {
				alert(response.error)
			}
			if (response.reminderSettings) {
				// console.log('reminder_settings RECEIVED', response.reminderSettings)
				CATMH.reminderSettings = response.reminderSettings
				CATMH.updateReminderInputs()
			}
		},
		dataType: 'json'
	})
}

CATMH.updateReminderInputs = function() {
	var settings = CATMH.reminderSettings
	$("#reminder_frequency").val(settings.frequency)
	$("#reminder_duration").val(settings.duration)
	$("#reminder_delay").val(settings.delay)
	$("#reminder_frequency").val(settings.frequency)
	$("#reminder_duration").val(settings.duration)
	$("#reminder_delay").val(settings.delay)
	
	if (settings.enabled == true) {
		$("#reminders_cbox").prop('checked', true)
		$("#reminder_frequency").attr('disabled', false)
		$("#reminder_duration").attr('disabled', false)
		$("#reminder_delay").attr('disabled', false)
	} else {
		$("#reminders_cbox").prop('checked', false)
		$("#reminder_frequency").attr('disabled', true)
		$("#reminder_duration").attr('disabled', true)
		$("#reminder_delay").attr('disabled', true)
	}
}

// initialize schedule (datatable)
$(function() {
	CATMH.schedule = $("#seq_schedule").DataTable({
		data: CATMH.scheduledSequences,
		pageLength: 50,
		columnDefs: [
			{className: 'dt-center', targets: '_all'}
		],
		order: [[2, 'asc'], [3, 'asc']]
	})
	
	// update inputs with reminderSettings given in initial response:
	CATMH.updateReminderInputs()
})

// show the user which sequence they selected from the dropdown
$('body').on('click', '.dropdown-menu a', function() {
	var dd = $(this).parent().siblings("button")
	dd.text($(this).text())
	$(".btn:first-child").val($(this).text())
	CATMH.selectedSequence = $(this).text()
})

// send user's single scheduling request to the server
$('body').on('click', '#scheduleSingle', function() {
	if (!CATMH.selectedSequence || CATMH.selectedSequence == '') {
		alert('Please select a sequence')
		return
	}
	
	var	chosen_offset = $("#offset").val()
	var post_data = {
		sequence: CATMH.selectedSequence,
		offset: chosen_offset,
		time_of_day: $("#time_of_day_b").val(),
		schedulingMethod: "single"
	}
	
	$.ajax({
		type: "POST",
		url: CATMH.scheduling_ajax_url,
		data: post_data,
		success: function(response) {
			// if (CATMH.debug)
				// console.log('scheduleSingle ajax returned successfully', response)
			if (response.error) {
				alert(response.error)
			}
			if (response.sequences) {
				CATMH.rebuildSequencesTable(response.sequences)
			}
		},
		dataType: 'json'
	})
})

// send user's interval scheduling request to the server
$('body').on('click', '#scheduleInterval', function() {
	if (!CATMH.selectedSequence || CATMH.selectedSequence == '') {
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
	// console.log("post_data", post_data)
	
	$.ajax({
		type: "POST",
		url: CATMH.scheduling_ajax_url,
		data: post_data,
		success: function(response) {
			if (CATMH.debug)
				// console.log('scheduleInterval ajax returned successfully', response)
			if (response.error) {
				alert(response.error)
			}
			if (response.sequences) {
				CATMH.rebuildSequencesTable(response.sequences)
			}
		},
		dataType: 'json'
	})
})

// detect user select a scheduled sequence
$('body').on('change', '.sequence_cbox', function() {
	if ($(".sequence_cbox:checked").length) {
		$("#deleteScheduledSequence").attr('disabled', false)
	} else {
		$("#deleteScheduledSequence").attr('disabled', true)
	}
})

// delete scheduled sequences
$('body').on('click', '#deleteScheduledSequence', function() {
	if ($(".sequence_cbox:checked").length == 0)
		return
	
	var post_data = {
		sequencesToDelete: [],
		schedulingMethod: 'delete'
	}
	
	// add sequences selected for deleting to post_data
	$(".sequence_cbox:checked").each(function(i, e) {
		var row = $(this).closest('tr')
		var seq = {
			name: row.children('td:eq(1)').html(),
			offset: row.children('td:eq(2)').html(),
			time_of_day: row.children('td:eq(3)').html()
		}
		post_data.sequencesToDelete.push(seq)
	})
	
	// console.log("post_data", post_data)
	
	$.ajax({
		type: "POST",
		url: CATMH.scheduling_ajax_url,
		data: post_data,
		success: function(response) {
			if (CATMH.debug)
				// console.log('deleteScheduledSequence ajax returned successfully', response)
			if (response.error) {
				alert(response.error)
			}
			if (response.sequences) {
				CATMH.rebuildSequencesTable(response.sequences)
			}
		},
		dataType: 'json'
	})
})

// send request to server to set reminder settings
$('body').on('input', '.reminder_setting', function() {
	CATMH.submitReminderSettings()
})