$(function() {
	$("#results").DataTable({
		dom: "Bfrtip",
		buttons: [
			'copy', 'csv', 'excel', 'print'
		]
	});
});