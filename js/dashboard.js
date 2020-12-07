$(document).ready(
	function() {
		jQuery.extend(jQuery.fn.dataTableExt.oSort,{
			"ack-col-asc": function(a,b){
				if (a == 'Y' && b != 'Y'){
					return 1;
				} else if (b == 'Y' && a != 'Y'){
					return -1;
				} else {
					return 0;
				}
			},
			"ack-col-desc": function(a,b){
				if (a == 'Y' && b != 'Y'){
					return -1;
				} else if (b == 'Y' && a != 'Y'){
					return 1;
				} else {
					return 0;
				}
			}
		});
		CATMH.datatable = $('#records').DataTable({
			ajax: CATMH.dashboard_ajax_url,
			pageLength: 25,
			columnDefs: [
				{className: 'dt-center', targets: '_all'},
				{type: 'ack-col', targets: 8}
			],
			order: [
				[8, 'asc'],
				[0, 'asc'],
				[3, 'asc'],
			]
		});
	
		$('body').on('mousedown touchstart', 'button.review', function() {
			var data = {
				rid: $(this).attr('data-rid'),
				seq: $(this).attr('data-seq'),
				date: $(this).attr('data-date')
			}
			var button = this
			var row = $(this).closest('tr')
			$.ajax({
				type: "POST",
				url: CATMH.review_ajax_url,
				data: data,
				complete: function(response) {
					// console.log('reviewInterview ajax returned successfully. responseText:', response.responseText)
					if (response.responseJSON) {
						var data = response.responseJSON
						// console.log('responseJSON:', data)
						if (data.error) {
							alert(data.error)
						} else if (data.success) {
							$(button).replaceWith('Y')
							row.find('img.fstatus').attr('src', CATMH.circle_blue_url)
						}
					}
				},
				dataType: 'json'
			})
		})
	}
);