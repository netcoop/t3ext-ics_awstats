<?php
if (TYPO3_MODE=='BE')	{
	$TYPO3_CONF_VARS['SC_OPTIONS']['scheduler']['tasks']['tx_icsawstats_updatetask'] = array(
		'extension' => $_EXTKEY, // Selbsterklärend
		'title' => 'LLL:EXT:'.$_EXTKEY.'/locallang.xml:updatetask.meta.name',
		'description' => 'LLL:EXT:'.$_EXTKEY.'/locallang.xml:updatetask.meta.description',
	);
}
?>