<?php
require_once APP_PATH_DOCROOT . 'ProjectGeneral/header.php';

session_start();

// support 'show_future_seqs' checkbox
if (!isset($_SESSION['show_future_seqs'])) {
	$_SESSION['show_future_seqs'] = false;
}
if ($_GET['show_future_seqs'] === 'true') {
	$_SESSION['show_future_seqs'] = true;
	$module->llog('show_future_seqs setting to true');
}
if ($_GET['show_future_seqs'] === 'false') {
	$_SESSION['show_future_seqs'] = false;
	$module->llog('show_future_seqs setting to false');
}

?>

<link rel="stylesheet" type="text/css" href="<?php echo $module->getUrl('css/dashboard.css'); ?>">
<link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.10.21/css/jquery.dataTables.css">
<script type="text/javascript" charset="utf8" src="https://cdn.datatables.net/1.10.21/js/jquery.dataTables.js"></script>
<script type="text/javascript" charset="utf8">
	CATMH = {
		dashboard_ajax_url: <?php echo '"' . $module->getUrl("ajax/dashboard_ajax.php") . '"'; ?>,
		icon_urls: <?php echo json_encode((object) $module->interviewStatusIconURLs); ?>,
		acknowledge_ajax_url: <?php echo '"' . $module->getUrl("ajax/acknowledge_ajax.php") . '"'; ?>
	}
</script>
<script type="text/javascript" charset="utf8" src="<?php echo $module->getUrl('js/dashboard.js'); ?>"></script>
<?php
	if (empty($module->getProjectSetting('enrollment_field'))) {
		echo '
<div class="alert alert-warning w-50" role="alert">
	<h5>The CAT-MH module does not have an Enrollment Field configured!</h5>
	
	<p>No participant interview data will be tabulated until an Enrollment Field has been chosen via the External Modules page\'s Configure modal.</p>
</div>';
	}
		if ($module->local_env) {
		echo '
<div class="alert alert-info w-50" role="alert">
	<h5>Local environment variable detected.</h5>
</div>';
	}
?>

<input type='checkbox' id='show_future_seqs'<?php if ($_SESSION['show_future_seqs']) {echo ' checked';} ?>>
<label for='show_future_seqs'> Show Future Sequences</label><br>

<table id="records" class="display compact nowrap">
    <thead>
        <tr>
			<?php
			$cols = $module->dashboardColumns;
			foreach($cols as $i => $name) {
				echo "<th>$name</th>\n";
			}
			?>
        </tr>
    </thead>
    <tbody>
    </tbody>
</table>

<?php
require_once APP_PATH_DOCROOT . 'ProjectGeneral/footer.php';
?>