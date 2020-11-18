<?php
require_once APP_PATH_DOCROOT . 'ProjectGeneral/header.php';
?>

<link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.10.21/css/jquery.dataTables.css">
<script type="text/javascript" charset="utf8" src="https://cdn.datatables.net/1.10.21/js/jquery.dataTables.js"></script>
<link rel="stylesheet" type="text/css" href="<?php echo $module->getUrl('css/dashboard.css'); ?>">
<script type="text/javascript" charset="utf8">
	CATMH = {
		ajax_url: <?php echo '"' . $module->getUrl("dashboard_ajax.php") . '"'; ?>
	}
</script>
<script type="text/javascript" charset="utf8" src="<?php echo $module->getUrl('js/dashboard.js'); ?>"></script>

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