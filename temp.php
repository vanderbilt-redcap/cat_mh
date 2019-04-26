<?php
echo("<pre>");
$tpk = \Records::getTablePK($module->getProjectId());
$ret = \Records::deleteRecord(1, $tpk, null, null, null, null, null, "CAT-MH module removed record for consent==0", true);
echo("</pre>");
