<?php

require_once APP_PATH_DOCROOT . 'ProjectGeneral/header.php';

?>
<script type='text/javascript' src="//cdn.datatables.net/1.10.20/js/jquery.dataTables.min.js"></script>
<link rel='stylesheet' href='//cdn.datatables.net/1.10.20/css/jquery.dataTables.min.css'>
<div class="card card-body w-75">
	<h3>Schedule a Sequence</h3>
	<div class="dropdown">
		<label class="pr-3">Choose a Sequence:</label>
		<button class="btn btn-primary dropdown-toggle" type="button" id="dropdownMenuButton" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
			Sequences
		</button>
		<div class="dropdown-menu" aria-labelledby="dropdownMenuButton">
			<?php
			$seq_names = $module->getProjectSetting('sequence');
			foreach ($seq_names as $i => $name) {
				echo "<a class=\"dropdown-item\" href=\"#\">$name</a>";
			}
			?>
		</div>
	</div>
	<div class='row mt-3'>
		<div class='col-6'>
			<h5>Schedule By Interval</h5>
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
				<button id="scheduleByInterval" type="button" class="btn btn-primary">Add to Schedule</button>
			</form>
		</div>
		<div class='col-6'>
			<h5 class='mb-3'>Schedule By Calendar</h5>
			<div id="calendar" class='dt-picker'></div>
			<button id="scheduleByCalendar" type="button" class="btn btn-primary mt-4">Add to Schedule</button>
		</div>
	</div>
</div>

<div class="card card-body w-75 mt-3">
	<h3>Scheduled Sequences</h3>
	<table id='seq_schedule'>
		<thead>
			<th>Select</th>
			<th>Date</th>
			<th>Sequence</th>
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
$sched_ajax_url = $module->getUrl('scheduling_ajax.php');

$scheduled = json_encode($module->getScheduledSequences());
$reminderSettings = json_encode((object) $module->getReminderSettings());

echo "<script type='text/javascript' src='$js_url'></script>";
echo "<script type='text/javascript'>
	CATMH.scheduling_ajax_url = '$sched_ajax_url'
	CATMH.debug = false;
	CATMH.scheduledSequences = JSON.parse('$scheduled')
	CATMH.reminderSettings = JSON.parse('$reminderSettings')
</script>";
require_once APP_PATH_DOCROOT . 'ProjectGeneral/footer.php';
?>