<?php

/***************************************************************
 * Extension Manager/Repository config file for ext "ics_awstats".
 *
 * Auto generated 17-06-2015 17:35
 *
 * Manual updates:
 * Only the data in the array - everything else is removed by next
 * writing. "version" and "dependencies" must not be touched!
 ***************************************************************/

$EM_CONF[$_EXTKEY] = array (
	'title' => 'ICS AWStats',
	'description' => 'Includes the AWStats logfile analyzer as a backend module. Requires ext retrostats. Supports cron, reverse DNS lookups and ics_web_awstats.',
	'category' => 'module',
	'version' => '0.7.5',
	'state' => 'stable',
	'uploadfolder' => 0,
	'createDirs' => '',
	'clearcacheonload' => 0,
	'author' => 'Loek Hilgersom',
	'author_email' => 'typo3extensions@netcoop.nl',
	'author_company' => 'Netcoop.nl',
	'constraints' => 
	array (
		'depends' => 
		array (
			'typo3' => '6.2.0-7.9.99',
			'lang' => '',
			'retrostats' => '',
		),
		'conflicts' => 
		array (
		),
		'suggests' => 
		array (
		),
	),
);

