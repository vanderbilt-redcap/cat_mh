$(function() {
	console.log('document ready');
	
	$('#calendar').datetimepicker({
		inline: true,
		dateFormat: "yy-mm-dd"
	})
	
	$("#seq_schedule").DataTable({
		pageLength: 50
	});
})

$('body').on('click', '.dropdown-menu a', function() {
	var dd = $(this).parent().siblings("button");
	dd.text($(this).text());
	$(".btn:first-child").val($(this).text());
})