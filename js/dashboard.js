$(document).ready(
	function() {
		CATMH.datatable = $('#records').DataTable({
			ajax: CATMH.ajax_url,
			pageLength: 25,
			columnDefs: [
				{className: 'dt-center', targets: '_all'}
			],
			order: [
				[0, 'asc'],
				[3, 'asc']
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
				url: CATMH.ajax_url,
				data: data,
				always: function(response) {
					// if (CATMH.debug)
						console.log('reviewInterview ajax returned successfully', response)
					if (response.error) {
						alert(response.error)
					}
				},
				dataType: 'json'
			})
		})
	}
);