<?php
require_once APP_PATH_DOCROOT . 'ProjectGeneral/header.php';
$export_url = $module->getUrl('php/export_interviews.php');
$delete_url = $module->getUrl('php/delete_interviews.php');
?>
<h1>Select Interview Data</h1>
<p>Choose a number below to select interview data older than [n] number of months.</p>
<p>Chooose '0' to select all interview data within this project.</p>
<input id="months" type="number" value="0" min="0" max="361">
<br>
<small id="months_selected">Current selection: All interview data.</small>
<h1>Export</h1>
<button id="export" type="button" class="btn btn-primary">Export Selected Interview Data</button>
<h1>Delete</h1>
<button id="delete" type="button" class="btn btn-primary" onclick="$('#delete_modal').modal('show')">Delete Selected Interview Data</button>

<!-- modal -->
<div id="delete_modal" class="modal" tabindex="-1" role="dialog">
	<div class="modal-dialog" role="document">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title">Delete Interview Data</h5>
				<button type="button" class="close" data-dismiss="modal" aria-label="Close">
					<span aria-hidden="true">&times;</span>
				</button>
			</div>
				<div class="modal-body">
				<p>Are you SURE you want to delete patient interview data? This process is IRREVERSIBLE. If you still want to continue, type "DELETE" in the box below before clicking the DELETE button.</p>
				<input id="check_delete" type="text">
			</div>
			<div class="modal-footer">
				<button type="button" class="btn btn-primary" id="delete_interviews" aria-disabled="true" disabled>Delete</button>
				<button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
			</div>
		</div>
	</div>
</div>

<script type="text/javascript">
	var CATMH = {};
	$('body').on('click', 'input#months', function(event) {
		var months = $(event.target).val();
		if (months == 0) {
			$("small#months_selected").text("Current selection: All interview data.");
		} else {
			$("small#months_selected").text("Current selection: Interviews with a scheduled datetime of more than " + months + " months ago.");
		}
	});
	$('body').on('change input', 'input#check_delete', function(event) {
		var delete_button = $("button#delete_interviews");
		var text = $(event.target).val();
		var disabled = delete_button.attr('disabled');
		if (text === "DELETE") {
			if (delete_button.attr('disabled')) {
				delete_button.removeAttr('disabled', false);
				delete_button.removeAttr('aria-disabled', false);
			}
		} else {
			if (!delete_button.attr('disabled')) {
				delete_button.attr('disabled', true);
			}
			if (!delete_button.attr('disabled')) {
				delete_button.attr('aria-disabled', true);
			}
		}
	});
	$('body').on('click', 'button#export', function(event) {
		var months = $("input#months").val();
		window.location = '<?= $export_url ?>' + "&months=" + Number(months);
	});
	$('body').on('click', 'button#delete_interviews', function(event) {
		$(".modal").modal("hide");
		var months = $("input#months").val();
		$.post('<?= $delete_url ?>', {
			months: months
		}, function(data) {
			data = JSON.parse(data);
			if (data.success) {
				alert("Successfully deleted " + Number(data.deleted) + " interviews (" + Number(data.failures) + " failures).");
			} else {
				alert("An error occurred while deleting interview data.");
			}
		});
	});
</script>

<?php
require_once APP_PATH_DOCROOT . 'ProjectGeneral/footer.php';
?>