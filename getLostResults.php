<?php
// search logs for interviews that were ended but that we have no results for
// then try to get results for themg
$result = $module->queryLogs("status=2 and results is NULL");
while($interview = db_fetch_assoc($result)) {
	$fetch = $module->getResults($interview);\
}