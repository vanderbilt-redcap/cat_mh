$(document).ready(
	function() {
		CATMH.datatable = $('#records').DataTable({
			ajax: CATMH.ajax_url,
			pageLength: 25,
			columnDefs: [
				{className: 'dt-center', targets: '_all'}
			]
		});
	
		$('body').on('mousedown touchstart', 'button.review', function() {
			var data = {
				rid: $(this).attr('data-rid'),
				seq: $(this).attr('data-seq'),
				date: $(this).attr('data-date')
			}
			$.ajax({
				type: "POST",
				url: CATMH.scheduling_ajax_url,
				data: post_data,
				success: function(response) {
					if (CATMH.debug)
						// console.log('reviewInterview ajax returned successfully', response)
					if (response.error) {
						alert(response.error)
					}
					if (response.reminderSettings) {
						console.log('reminder_settings RECEIVED', response.reminderSettings)
						CATMH.reminderSettings = response.reminderSettings
						CATMH.updateReminderInputs()
					}
				},
				dataType: 'json'
			})
		})
	}
);