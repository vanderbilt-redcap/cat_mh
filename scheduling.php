<?php

require_once APP_PATH_DOCROOT . 'ProjectGeneral/header.php';

?>
<script type='text/javascript' src="//cdn.datatables.net/1.10.24/js/jquery.dataTables.min.js"></script>
<link rel='stylesheet' href='//cdn.datatables.net/1.10.24/css/jquery.dataTables.min.css'>
<?php
	if (empty($module->getProjectSetting('enrollment_field'))) {
		echo '
<div class="alert alert-warning w-50" role="alert">
	<h5>The CAT-MH module does not have an Enrollment Field configured!</h5>
	
	<p>No invitations or reminder emails will be sent to participants until an Enrollment Field has been chosen via the External Modules page\'s Configure modal.</p>
</div>';
	}
	if ($module->getProjectSetting('disable_invites')) {
		echo '
<div class="alert alert-info w-50" role="alert">
	<h5>Automatic invitation and reminder emails are disabled in the CAT-MH module configuration</h5>
</div>';
	}
?>
<div class="card card-body w-75">
	<h3>Schedule a Sequence</h3>
	<div class="dropdown">
		<label class="pr-3">Choose a Sequence:</label>
		<button class="btn btn-primary dropdown-toggle" type="button" id="dropdownMenuButton" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
			Sequences
		</button>
		<div class="dropdown-menu" aria-labelledby="dropdownMenuButton">
			<?php
			// non-kcat sequences
			$seq_names = $module->getProjectSetting('sequence');
			foreach ($seq_names as $i => $name) {
				if (!empty($name))
					echo "<a class=\"dropdown-item\" href=\"#\">$name</a>";
			}
			
			// kcat
			$seq_names = $module->getProjectSetting('kcat_sequence');
			foreach ($seq_names as $i => $name) {
				if (!empty($name))
					echo "<a class=\"dropdown-item\" href=\"#\">$name</a>";
			}
			?>
		</div>
	</div>
	<div class='row mt-3'>
		<div class='col-6'>
			<h5>Schedule (Interval)</h5>
			<form>
				<div class="form-group">
					<label for="frequency">Invitation frequency (number of days)</label>
					<input type="text" class="form-control w-25" id="frequency" aria-describedby="frequency_note">
					<small id="frequency_note" class="form-text text-muted">Send sequence invitation emails every [x] days</small>
				</div>
				<div class="form-group">
					<label for="duration">Duration (number of days)</label>
					<input type="text" class="form-control w-25" id="duration" aria-describedby="duration_note">
					<small id="duration_note" class="form-text text-muted">for [y] days</small>
				</div>
				<div class="form-group">
					<label for="delay">Delay (number of days)</label>
					<input type="text" class="form-control w-25" id="delay" aria-describedby="delay_note">
					<small id="delay_note" class="form-text text-muted">starting after [z] days</small>
				</div>
				<div class="form-group">
					<label for="time_of_day">Time of day to send invitations</label>
					<input type="time" class="form-control w-50" id="time_of_day" aria-describedby="time_of_day_note">
					<small id="time_of_day_note" class="form-text text-muted">For example, send invitations at 10:30 AM</small>
				</div>
				<button id="scheduleInterval" type="button" class="btn btn-primary">Add to Schedule</button>
			</form>
		</div>
		<div class='col-6'>
			<h5 class='mb-3'>Schedule (Single)</h5>
			<form>
				<div class="form-group">
					<label for="offset">Offset (number of days)</label>
					<input type="text" class="form-control w-25" id="offset" aria-describedby="offset_note">
					<small id="offset_note" class="form-text text-muted">Wait [x] days to send invitation after participant enrollment</small>
				</div>
				<div class="form-group">
					<label for="time_of_day_b">Time of day to send invitations</label>
					<input type="time" class="form-control w-50" id="time_of_day_b" aria-describedby="time_of_day_note">
					<small id="time_of_day_note_b" class="form-text text-muted">For example, send invitations at 10:30 AM</small>
				</div>
				<button id="scheduleSingle" type="button" class="btn btn-primary">Add to Schedule</button>
			</form>
		</div>
	</div>
</div>

<div class="card card-body w-75 mt-3">
	<h3>Scheduled Sequences</h3>
	<table id='seq_schedule'>
		<thead>
			<th>Select</th>
			<th>Sequence</th>
			<th>Offset</th>
			<th>Time of Day</th>
		</thead>
		<tbody>
		</tbody>
	</table>
	<button id="deleteScheduledSequence" disabled='true' type="button" class="btn btn-primary col-2">Delete</button>
</div>

<div class="card card-body w-50 mt-3">
	<h3>Reminder Emails</h3>
	<div class="form-group form-check">
		<input type="checkbox" class="form-check-input reminder_setting" id="reminders_cbox">
		<label class="form-check-label" style="font-size: 0.9rem" for="reminders_cbox">Send reminder emails to patients who have not completed their scheduled sequences</label>
	</div>
	<div class="form-group">
		<label for="reminder_frequency">Reminder frequency (number of days)</label>
		<input type="text" class="form-control reminder_setting w-25" id="reminder_frequency" aria-describedby="reminder_frequency_note">
		<small id="reminder_frequency_note" class="form-text text-muted">Send reminder emails every [x] days</small>
	</div>
	<div class="form-group">
		<label for="reminder_duration">Duration (number of days)</label>
		<input type="text" class="form-control reminder_setting w-25" id="reminder_duration" aria-describedby="reminder_duration_note">
		<small id="reminder_duration_note" class="form-text text-muted">for [y] days</small>
	</div>
	<div class="form-group">
		<label for="reminder_delay">Delay (number of days)</label>
		<input type="text" class="form-control reminder_setting w-25" id="reminder_delay" aria-describedby="reminder_delay_note">
		<small id="reminder_delay_note" class="form-text text-muted">starting after [z] days</small>
	</div>
	<div class="alert alert-light" style="border: none !important;" role="alert">
		REDCap will send the reminder emails at the time of day specified for the associated scheduled sequence
	</div>
	<div class="alert alert-light" style="border: none !important;" role="alert">
		Note: Email reminders will also be disabled if the frequency or duration settings above are absent.
	</div>
</div>



<?php
$js_url = $module->getUrl('js/scheduling.js');
$sched_ajax_url = $module->getUrl('ajax/scheduling_ajax.php');
$dash_ajax_url = $module->getUrl('ajax/dashboard_ajax.php');

$scheduled = json_encode($module->getScheduledSequences());
$reminderSettings = json_encode((object) $module->getReminderSettings());

echo "<script type='text/javascript' src='$js_url'></script>";
echo "<script type='text/javascript'>
	CATMH.scheduling_ajax_url = '$sched_ajax_url'
	CATMH.dashboard_ajax_url = '$dash_ajax_url'
	CATMH.debug = false;
	CATMH.scheduledSequences = JSON.parse(JSON.stringify($scheduled))
	CATMH.reminderSettings = JSON.parse(JSON.stringify($reminderSettings))
</script>";
require_once APP_PATH_DOCROOT . 'ProjectGeneral/footer.php';
?>