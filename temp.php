<?php
echo("<pre>");
$pid = $module->getProjectId();
print_r($module->getProjectSettings($pid));
echo("</pre>");