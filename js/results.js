$(function() {
	// custom column sorting for 'Reviewed'
	$.fn.dataTable.ext.order['dom-checkbox'] = function  ( settings, col ) {
		// console.log('sorting cboxes');
		return this.api().column( col, {order:'index'} ).nodes().map( function ( td, i ) {
			return $('input', td).prop('checked') ? '1' : '0';
		} );
	}
	
	CATMH.datatable = $("#results").DataTable({
		dom: "Blfrtip",
		lengthMenu: [
			[10, 50, 100, -1],
			[10, 25, 50, "All"]
		],
		buttons: [
			'copy', 'csv', 'excel', 'print'
		],
		columnDefs: [
			{targets: 13, orderDataType: 'dom-checkbox'}
		],
		order: [
			[13, 'asc'],
			[0, 'asc']
		]
	});
	
	$('body').on('change', '.reviewed_cbox', function() {
		// console.log('ack_cbox changed, ajax url: ' + CATMH.acknowledge_ajax_url)
		var data = {
			sid: $(this).attr('data-sid'),
			seq: $(this).attr('data-seq'),
			date: $(this).attr('data-date'),
			test: $(this).attr('data-test'),
			kcat: $(this).attr('data-kcat'),
			reviewed: $(this).prop('checked')
		}
		
		$.ajax({
			type: "POST",
			url: CATMH.review_ajax_url,
			data: data,
			complete: function(response) {
				// console.log('reviewInterview ajax returned successfully. responseText:', response.responseText)
				if (response.responseJSON) {
					var data = response.responseJSON
					// console.log('response data:', data)
					if (data.error) {
						alert(data.error)
					}
				}
			},
			dataType: 'json'
		})
	})
});