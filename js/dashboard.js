$(document).ready(
	function() {
		// append time as parameter to ajax call
		// var dash_time = "";
		var url = window.location.href
		var dash_time_param = '';
		var dash_time = null;
		if (url.search('&dash_time=') != -1) {
			var match = url.match(/[?&]dash_time=([^&]+)/);
			if (match) {
				dash_time = match[1]
				dash_time_param = '&dash_time=' + encodeURIComponent(match[1])
			}
			
		}
		
		$('#dash_time').datetimepicker({
			inline: true,
			dateFormat: "yy-mm-dd",
			onSelect: function(date) {
				console.log('date selected: ', date)
				if (url.search('&dash_time=') == -1) {
					window.location.href += '&dash_time=' + encodeURIComponent(date);
				} else {
					window.location.href = url.replace(/[?&]dash_time=([^&]+)/, '&dash_time=' + encodeURIComponent(date))
				}
			},
			beforeShowDay: function(date) {
				if (dash_time && date.toISOString().split('T')[0] == dash_time) {
					return [true, 'dash_time_date']
				}
				return [true, '']
			}
		})
		
		// custom column sorting for 'Completed' and 'Acknowledged'
		$.fn.dataTable.ext.order['dom-checkbox'] = function  ( settings, col ) {
			console.log('sorting cboxes');
			return this.api().column( col, {order:'index'} ).nodes().map( function ( td, i ) {
				return $('input', td).prop('checked') ? '1' : '0';
			} );
		}
		CATMH.completion_ordering = [
			'blue',
			'red',
			'gray',
			'yellow',
			'green'
		];
		jQuery.extend(jQuery.fn.dataTableExt.oSort,{
			"completion_status-asc": function(a,b){
				var a_color_index;
				var a_match = a.match(/data-color='([^']+)'/);
				if (a_match) {
					a_color_index = CATMH.completion_ordering.indexOf(a_match[1]);
				}
				
				var b_color_index;
				var b_match = b.match(/data-color='([^']+)'/);
				if (b_match) {
					b_color_index = CATMH.completion_ordering.indexOf(b_match[1]);
				}
				
				if (a_color_index > b_color_index){
					return 1;
				} else if (a_color_index < b_color_index){
					return -1;
				}
				
				return 0;
			},
			"completion_status-desc": function(a,b){
				var a_color_index;
				var a_match = a.match(/data-color='([^']+)'/);
				if (a_match) {
					a_color_index = CATMH.completion_ordering.indexOf(a_match[1]);
				}
				
				var b_color_index;
				var b_match = b.match(/data-color='([^']+)'/);
				if (b_match) {
					b_color_index = CATMH.completion_ordering.indexOf(b_match[1]);
				}
				
				if (a_color_index > b_color_index){
					return -1;
				} else if (a_color_index < b_color_index){
					return 1;
				}
				
				return 0;
			}
		});
		
		CATMH.datatable = $('#records').DataTable({
			ajax: CATMH.dashboard_ajax_url + dash_time_param,
			pageLength: 25,
			columnDefs: [
				{className: 'dt-center', targets: '_all'},
				{type: 'completion_status', targets: 2},
				{targets: 9, orderDataType: 'dom-checkbox'}
			],
			initComplete: function() {
				$('.ack_cbox').each(function(i, val) {
					if ($(this).attr('data-checked') === 'true') {
						$(this).prop('checked', true);
					} else {
						$(this).prop('checked', false);
					}
				})
				// re-order and re-draw
				CATMH.datatable.order([
					[9, 'asc'],
					[0, 'asc'],
					[4, 'asc'],
				]);
				CATMH.datatable.draw();
			}
		});
	
		$('body').on('change', '.ack_cbox', function() {
			console.log('ack_cbox changed, ajax url: ' + CATMH.acknowledge_ajax_url)
			var data = {
				rid: $(this).attr('data-rid'),
				seq: $(this).attr('data-seq'),
				date: $(this).attr('data-date'),
				acknowledged: $(this).prop('checked')
			}
			var row = $(this).closest('tr')
			
			$.ajax({
				type: "POST",
				url: CATMH.acknowledge_ajax_url + dash_time_param,
				data: data,
				complete: function(response) {
					// console.log('reviewInterview ajax returned successfully. responseText:', response.responseText)
					if (response.responseJSON) {
						var data = response.responseJSON
						console.log('response data:', data)
						if (data.error) {
							alert(data.error)
						} else if (data.success) {
							// update status icon color
							row.find('img.fstatus').attr('src', CATMH.icon_urls[data.color])
							row.find('img.fstatus').attr('data-color', data.color)
						}
					}
				},
				dataType: 'json'
			})
		})
	}
);