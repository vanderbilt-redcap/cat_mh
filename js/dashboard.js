$(document).ready(
	function() {
		CATMH.datatable = $('#records').DataTable({
			ajax: CATMH.ajax_url,
			pageLength: 25,
			columnDefs: [
				{className: 'dt-center', targets: '_all'}
			]
		});
	}
);