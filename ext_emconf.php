<?php

/***************************************************************
 * Extension Manager/Repository config file for ext "ics_awstats".
 *
 * Auto generated 17-06-2015 17:26
 *
 * Manual updates:
 * Only the data in the array - everything else is removed by next
 * writing. "version" and "dependencies" must not be touched!
 ***************************************************************/

$EM_CONF[$_EXTKEY] = array (
	'title' => 'ICS AWStats',
	'description' => 'Includes the AWStats logfile analyzer as a backend module. This is a modified version of cc_awstats to support cron, reverse DNS lookups, ics_web_awstats and ics_beuser_awstats.',
	'category' => 'module',
	'version' => '0.6.2',
	'state' => 'stable',
	'uploadfolder' => 0,
	'createDirs' => '',
	'clearcacheonload' => 0,
	'author' => 'Valentin Schmid',
	'author_email' => 'valentin.schmid@newmedia.ch',
	'author_company' => 'Suedostschweiz Newmedia AG',
	'constraints' => 
	array (
		'depends' => 
		array (
			'typo3' => '4.5.0-4.7.99',
			'lang' => '',
		),
		'conflicts' => 
		array (
		),
		'suggests' => 
		array (
		),
	),
);

