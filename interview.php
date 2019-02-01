<!doctype html>
<html lang="en">
	<head>
		<!-- Required meta tags -->
		<meta charset="utf-8">
		<meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
		
		<link rel="stylesheet" href="<?php echo $module->getUrl("css/base.css");?>">
		<title>CAT-MH Interview</title>
	</head>
	<body>
		<?php
		# fetch first and last name of the participant
		$params = [
			"project_id" => $_GET["pid"],
			"return_format" => 'array',
			"records" => $_GET["record"],
			"fields" => ["firstname", "lastname"],
			"events" => $_GET["eid"] or 1
		];
		$record = \REDCap::getData($params);
		$firstname = ucfirst(current(current($record))['firstname']);
		$lastname = ucfirst(current(current($record))['lastname']);
		echo "<div id='userInfo' style='display:none'>
			{
				\"firstname\" : \"$firstname\",
				\"lastname\" : \"$lastname\"
			}
		</div>"
		?>
		<script src="https://code.jquery.com/jquery-3.3.1.min.js"></script>
		<script src="<?php echo $module->getUrl("js/base.js");?>"></script>
	</body>
</html>


