<?php
require_once APP_PATH_DOCROOT . 'ProjectGeneral/header.php';
?>

<link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.10.21/css/jquery.dataTables.css">
<script type="text/javascript" charset="utf8" src="https://cdn.datatables.net/1.10.21/js/jquery.dataTables.js"></script>
<link rel="stylesheet" type="text/css" href="<?php echo $module->getUrl('css/dashboard.css'); ?>">
<script type="text/javascript" charset="utf8">
	CATMH = {
		dashboard_ajax_url: <?php echo '"' . $module->getUrl("dashboard_ajax.php") . '"'; ?>,
		circle_blue_url: <?php echo '"' . $module->getUrl("images/circle_blue.png") . '"'; ?>,
		review_ajax_url: <?php echo '"' . $module->getUrl("review_ajax.php") . '"'; ?>
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
?>
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