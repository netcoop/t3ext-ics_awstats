<?php

use \TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use \TYPO3\CMS\Core\Utility\GeneralUtility;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2006 ICSurselva AG (info@icsurselva.ch)
 *  All rights reserved
 *
 *  This script is part of the TYPO3 project. The TYPO3 project is
 *  free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
 *  (at your option) any later version.
 *
 *  The GNU General Public License can be found at
 *  http://www.gnu.org/copyleft/gpl.html.
 *  A copy is found in the textfile GPL.txt and important notices to the license
 *  from the author is found in LICENSE.txt distributed with these scripts.
 *
 *
 *  This script is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  This copyright notice MUST APPEAR in all copies of the script!
 ***************************************************************/
/**
 * This class is a awstats wrapper for TYPO3
 *
 * @author	Valentin Schmid <valli@icsurselva.ch>
 */

class tx_icsawstats_awstats {

	// Configuration set internally (see constructor method for required keys and their meaning)
	var $conf = array();
	var $ext_conf = array();

	// error constants
	public static $ERR_LOGFILE_NOT_CONFIGURED = 1;
	public static $ERR_AWSTATS_CALL_FAILED = 2;
	public static $ERR_UPDATE_IS_LOCKED = 3;
	// logfile type constants
	public static $LOGF_EXCLUDE = 1;
	public static $LOGF_REGISTERED = 2;
	public static $LOGF_UNREGISTERED = 3;
	public static $LOGF_CHECKED = 4;

	// constructor
	function tx_icsawstats_awstats() {
		global $TYPO3_CONF_VARS;

		$this->ext_conf = unserialize($GLOBALS['TYPO3_CONF_VARS']['EXT']['extConf']['ics_awstats']);
		$this->conf['awstatsFullDir'] = ExtensionManagementUtility::extPath('ics_awstats').'awstats/';
		// we need the absolute URL path to awstats (without host)
		// relative URL wouldn't work with ics_web_awstats or ics_beuser_awstats
		$siteUrl = GeneralUtility::getIndpEnv('TYPO3_SITE_URL');
		$reqHost = GeneralUtility::getIndpEnv('TYPO3_REQUEST_HOST');
		$siteUrlPath = substr($siteUrl, strlen($reqHost));
		$this->conf['awstatsFullUrl'] = $siteUrlPath . ExtensionManagementUtility::siteRelPath('ics_awstats').'awstats/';
		$this->conf['awstatsScript']  = 'awstats.pl';

		// check if logfile path is relative or not
		if (substr($TYPO3_CONF_VARS['FE']['logfile_dir'],0,1) == '/') {
			$this->conf['logfile_dir'] = $TYPO3_CONF_VARS['FE']['logfile_dir'];
		} else {
			$this->conf['logfile_dir'] = str_replace ('//', '/', PATH_site.	$TYPO3_CONF_VARS['FE']['logfile_dir']);
		}
		$this->conf['awstats_data_dir'] = $this->conf['logfile_dir'] .'.awstats-data/';
		$this->conf['awstats_conf'] = $this->conf['awstats_data_dir'].'awstats-module.conf';
	}

	function get_perlbin() {
		$perlbin = $this->ext_conf['perlbin'];
		if (! $perlbin) $perlbin = '/usr/bin/perl';
		// test if perl is running and do some basic version checks
		$pbcmd = $perlbin.' '.escapeshellarg(ExtensionManagementUtility::extPath('ics_awstats').'mod1/get_pv.pl');
		exec($pbcmd, $testoutput, $retval);
		if ($retval || (! preg_match('/^<pv>([\d\.]+)<\/pv><enc>([01])<\/enc><esc>([01])<\/esc><geo>([01])<\/geo><geopp>([01])<\/geopp>$/', $testoutput[0], $matches)) ) {
			echo("Perl was not running as expected. Please check the following points:<br />\n");
			echo(" - php-'safe_mode' must not be enabled (Current configuration: safe_mode=".(ini_get('safe_mode')?1:0).")<br />\n");
			echo(" - the ics_awstats extension misconfiguration (Current configuration: perlbin=".$perlbin.")<br />\n");
			echo(" - check the permissions of '".$perlbin."'<br />\n");
			die();
		} elseif ($matches[1] == 5.008) {
			echo("You have perl version 5.008 (5.8.0) installed.<br />\n");
			echo("ics_awstats does not work with this perl version.<br />\n");
			echo("Please upgrade your perl installation.<br />\n");
			die();
		} elseif ($matches[1] < 5.007003) {
			echo("You have perl version ".$matches[1]." installed.<br />\n");
			echo("AWStats needs at least perl version 5.007003 (5.7.3) or higher.<br />\n");
			echo("(or downgrade ics_awstats to version <= 0.2.7)<br />\n");
			echo("Please upgrade your perl installation.<br />\n");
			die();
		}
		// test if the required modules are present
		$enc = $matches[2];
		$esc = $matches[3];
		$geo = $matches[4];
		$geopp = $matches[5];
		if (!$enc) {
			echo("You need to install the perl module Encode.<br />\n");
			echo("(or downgrade ics_awstats to version <= 0.2.7)<br />\n");
			die();
		} elseif ($this->ext_conf['enableDecodeUTFKeys'] && !($enc && $esc)) {
			echo("enableDecodeUTFKeys is enabled!<br />\n");
			echo("This requires the perl modules Encode and URI::Escape.<br />\n");
			die();
		} elseif ($this->ext_conf['enableGeoIP'] && !($geo || $geopp)) {
			echo("enableGeoIP is enabled!<br />\n");
			echo("This requires either the perl module Geo::IP or Geo::IP::PurePerl.<br />\n");
			die();
		}
		return $perlbin;
	}


	// the update lockfile methods
	function get_update_lockfile_name($t3log) {
		return $this->conf['awstats_data_dir'].$t3log.'.upd.lock';
	}

	function set_update_lockfile($t3log) {
		$updlfile = $this->get_update_lockfile_name($t3log);
		touch($updlfile);
	}

	function unlink_update_lockfile($t3log) {
		$updlfile = $this->get_update_lockfile_name($t3log);
		if (@is_file($updlfile)) {
			unlink($updlfile);
		}
	}

	function is_set_update_lockfile($t3log) {
		$updlfile = $this->get_update_lockfile_name($t3log);
		return @is_file($updlfile);
	}


	// the logconfig methods
	function get_logf_type($domain) {
		if ($domain == '-') {
			return self::$LOGF_EXCLUDE;
		} elseif ($domain == '') {
			return self::$LOGF_UNREGISTERED;
		}
		return self::$LOGF_REGISTERED;
	}

	function get_single_logconfig($t3log) {
		// we could do this more efficient
		// (that's the only reason for this function)
		$logconfigs = $this->get_logconfigs();
		if ($logconfigs[$t3log]) {
			return $logconfigs[$t3log];
		}
		return array();
	}

	function get_logconfigs() {
		$logconfigs = array();

		if (@is_file($this->conf['awstats_conf'])) {
			$fh = fopen($this->conf['awstats_conf'], 'r');
			while (list($lfile, $domains, $browser_update, $cron_update, $reverse_dnslookup, $after_analyzing_action) = fscanf($fh, "%s\t%s\t%s\t%s\t%s\t%s\n")) {
				$logconfigs[$lfile]['type'] = $this->get_logf_type($domains);
				$logconfigs[$lfile]['domains'] = explode(',', $domains);
				if (($browser_update == '1') || ($browser_update == '')) {
					$logconfigs[$lfile]['browser_update'] = 1;
				} else {
					$logconfigs[$lfile]['browser_update'] = 0;
				}
				if ($cron_update == '1') {
					$logconfigs[$lfile]['cron_update'] = 1;
				} else {
					$logconfigs[$lfile]['cron_update'] = 0;
				}
				if ($reverse_dnslookup == '1') {
					$logconfigs[$lfile]['reverse_dnslookup'] = 1;
				} else {
					$logconfigs[$lfile]['reverse_dnslookup'] = 0;
				}
				switch ($after_analyzing_action) {
					case 'p':	// purge logfile
						$logconfigs[$lfile]['after_analyzing_action'] = 'p';
						break;
					case 'a':	// archive logrecords
						$logconfigs[$lfile]['after_analyzing_action'] = 'a';
						break;
					case 'n':	// do nothing
					default:
						$logconfigs[$lfile]['after_analyzing_action'] = 'n';
						break;
				}
			}
			fclose($fh);
		}
		ksort($logconfigs, SORT_STRING);
		return $logconfigs;
	}

	function set_logconfigs($logconfigs) {
		GeneralUtility::fixPermissions($awstats->conf['awstats_conf']);
		$fh = fopen($this->conf['awstats_conf'], 'w');
		foreach ( $logconfigs as $lfile => $logconfig ) {
			if ($logconfig['type'] != self::$LOGF_UNREGISTERED) {
				fputs($fh, $lfile."\t");
				if ($logconfig['type'] == self::$LOGF_EXCLUDE) {
					fputs($fh, "-\t");
				} else {
					fputs($fh, implode(',', $logconfig['domains'])."\t");
				}
				fputs($fh, $logconfig['browser_update']."\t");
				fputs($fh, $logconfig['cron_update']."\t");
				fputs($fh, $logconfig['reverse_dnslookup']."\t");
				fputs($fh, $logconfig['after_analyzing_action']."\n");
			}
		}
		fclose($fh);
		GeneralUtility::fixPermissions($awstats->conf['awstats_conf']);
	}

	function clear_cache($t3log) {
		// do not clear cache files if disableClearCache is set
		if ($this->ext_conf['disableClearCache']) {
			return false;
		}
		$aws_cache_dir = $this->conf['awstats_data_dir'].$t3log.'/';
		$cache_files = GeneralUtility::getFilesInDir($aws_cache_dir, 'txt', 1);
		foreach ( $cache_files as $key => $file ) {
			// do not delete the DNSStaticCacheFile
			if (basename($file) == 'dnscache.txt') continue;
			unlink($file);
		}
		return true;
	}

	/**
	 *
	 * Enter description here ...
	 * @param string $t3log
	 * @param string $aws_wrapper
	 * @param boolean $dbg
	 */
	function call_awstats($t3log, $aws_wrapper, $dbg=false) {
		global $LANG, $TBE_TEMPLATE;

		// Set some environment values for awstats.conf:
		// this magic prevents calling the awstats script directly
		putenv ('TYPO3_MAGIC=1');
		putenv ('AWS_LANG='.$LANG->lang);
		$logconfig = $this->get_single_logconfig($t3log);
		if (! $logconfig['domains']) {
			return self::$ERR_LOGFILE_NOT_CONFIGURED;
		}

		// Set some usefull vars
		$aws_domain = $logconfig['domains'][0];
		$aws_cache_dir = $this->conf['awstats_data_dir'].$t3log.'/';

		// check for the existance of the awstats cache dir
		if (!@is_dir($aws_cache_dir)) {
			GeneralUtility::mkdir($aws_cache_dir);
		}

		putenv ('AWS_DOMAIN='. $aws_domain);
		putenv ('AWS_DOMAINS='. implode(' ', $logconfig['domains']));
		putenv ('AWS_LOGFILE='. $this->conf['logfile_dir'].$t3log);
		putenv ('GATEWAY_INTERFACE=');
		putenv ('AWS_LANG_DIR='. $this->conf['awstatsFullDir'].'lang/');
		putenv ('AWS_ICON_DIR='. $this->conf['awstatsFullUrl'].'icon/');
		putenv ('AWS_ALLOW_UPDATE='. (($logconfig['browser_update'] && (! $this->is_set_update_lockfile($t3log)))?'1':'0'));
		putenv ('AWS_DNSLOOKUP='. ($logconfig['reverse_dnslookup']?'1':'0'));
		putenv ('AWS_PURGELOGFILE='. ((($logconfig['after_analyzing_action'] == 'p') || ($logconfig['after_analyzing_action'] == 'a'))?'1':'0'));
		putenv ('AWS_ARCHIVELOGRECORDS='. (($logconfig['after_analyzing_action'] == 'a')?'%MM%YYYY':'0'));
		putenv ('AWS_CACHE_DIR='. $aws_cache_dir);
		putenv ('AWS_WRAPPER='. $aws_wrapper);
		putenv ('AWS_BGCOLOR='. $TBE_TEMPLATE->bgColor);
		putenv ('AWS_TBT_BGCOLOR='. $TBE_TEMPLATE->bgColor5);
		putenv ('AWS_TB_BGCOLOR='. $TBE_TEMPLATE->bgColor4);
		putenv ('AWS_TB_COLOR='. GeneralUtility::modifyHTMLColor($TBE_TEMPLATE->bgColor4,-10,-10,-10));
		putenv ('AWS_TBR_BGCOLOR='. GeneralUtility::modifyHTMLColor($TBE_TEMPLATE->bgColor4,+15,+15,+15));
		putenv ('AWS_INCL_DECODEUTFKEYS='. $this->conf['awstatsFullDir'].'dummy.inc.conf');
		putenv ('AWS_INCL_GEOIP='. $this->conf['awstatsFullDir'].'dummy.inc.conf');
		putenv ('AWS_PATH_TO_GEOIP='.$this->ext_conf['pathToGeoIPDat']);
		if ($this->ext_conf['enableDecodeUTFKeys']) {
			putenv ('AWS_INCL_DECODEUTFKEYS='. $this->conf['awstatsFullDir'].'decodeutfkeys.inc.conf');
		}
		if ($this->ext_conf['enableGeoIP']) {
			putenv ('AWS_INCL_GEOIP='. $this->conf['awstatsFullDir'].'geoip.inc.conf');
		}

		// building the command line parameters for awstats.pl
		$parameter = ' -config='.escapeshellarg($aws_domain);
		$parameter.= (GeneralUtility::_GP('output')) ? ' -output='.escapeshellarg(GeneralUtility::_GP('output')) : ' -output';
		$parameter.= (GeneralUtility::_GP('year')) ? ' -year='.escapeshellarg(GeneralUtility::_GP('year')) : '';
		$parameter.= (GeneralUtility::_GP('month')) ? ' -month='.escapeshellarg(GeneralUtility::_GP('month')) : '';
		$parameter.= (GeneralUtility::_GP('lang')) ? ' -lang='.escapeshellarg(GeneralUtility::_GP('lang')) : '';
		$parameter.= (GeneralUtility::_GP('hostfilter')) ? ' -hostfilter='.escapeshellarg(GeneralUtility::_GP('hostfilter')) : '';
		$parameter.= (GeneralUtility::_GP('hostfilterex')) ? ' -hostfilterex='.escapeshellarg(GeneralUtility::_GP('hostfilterex')) : '';
		$parameter.= (GeneralUtility::_GP('urlfilter')) ? ' -urlfilter='.escapeshellarg(GeneralUtility::_GP('urlfilter')) : '';
		$parameter.= (GeneralUtility::_GP('urlfilterex')) ? ' -urlfilterex='.escapeshellarg(GeneralUtility::_GP('urlfilterex')) : '';
		$parameter.= (GeneralUtility::_GP('refererpagesfilter')) ? ' -refererpagesfilter='.escapeshellarg(GeneralUtility::_GP('refererpagesfilter')) : '';
		$parameter.= (GeneralUtility::_GP('refererpagesfilterex')) ? ' -refererpagesfilterex='.escapeshellarg(GeneralUtility::_GP('refererpagesfilterex')) : '';
		$parameter.= ($dbg) ? ' -debug=1' : '';
		$update_in_progress = 0;
		if ($logconfig['browser_update'] && GeneralUtility::_GP('update')) {
			$parameter.= ' -update='.escapeshellarg(GeneralUtility::_GP('update'));
			if ($this->is_set_update_lockfile($t3log)) {
				return self::$ERR_UPDATE_IS_LOCKED;
			}
			$this->set_update_lockfile($t3log);
			$update_in_progress = 1;
		}

		// exec script
		$perlbin = $this->get_perlbin();
		$syscmd = $perlbin.' '.escapeshellarg($this->conf['awstatsFullDir'].$this->conf['awstatsScript']).$parameter;
		if (!$dbg) {
			// awstats uses its own charsets, so we remove the charset of typo3 from the http-header
			header('Content-type: text/html; charset=');
			ob_start();
			passthru($syscmd, $retval);
			$content = ob_get_clean();

			if ($update_in_progress) {
				$this->unlink_update_lockfile($t3log);
			}

			if ($retval) {
				return self::$ERR_AWSTATS_CALL_FAILED;
			}
			else {
				return $content;
			}
		} else {
			echo('<h1>DEBUG OUTPUT</h1>');
			\TYPO3\CMS\Core\Utility\DebugUtility::debug($this->conf);
			\TYPO3\CMS\Core\Utility\DebugUtility::debug($logconfig);
			$env = array();
			$env['TYPO3_MAGIC'] = getenv('TYPO3_MAGIC');
			$env['AWS_LANG'] = getenv('AWS_LANG');
			$env['AWS_DOMAIN'] = getenv('AWS_DOMAIN');
			$env['AWS_DOMAINS'] = getenv('AWS_DOMAINS');
			$env['AWS_LOGFILE'] = getenv('AWS_LOGFILE');
			$env['GATEWAY_INTERFACE'] = getenv('GATEWAY_INTERFACE');
			$env['AWS_LANG_DIR'] = getenv('AWS_LANG_DIR');
			$env['AWS_ICON_DIR'] = getenv('AWS_ICON_DIR');
			$env['AWS_ALLOW_UPDATE'] = getenv('AWS_ALLOW_UPDATE');
			$env['AWS_DNSLOOKUP'] = getenv('AWS_DNSLOOKUP');
			$env['AWS_PURGELOGFILE'] = getenv('AWS_PURGELOGFILE');
			$env['AWS_ARCHIVELOGRECORDS'] = getenv('AWS_ARCHIVELOGRECORDS');
			$env['AWS_CACHE_DIR'] = getenv('AWS_CACHE_DIR');
			$env['AWS_WRAPPER'] = getenv('AWS_WRAPPER');
			$env['AWS_BGCOLOR'] = getenv('AWS_BGCOLOR');
			$env['AWS_TBT_BGCOLOR'] = getenv('AWS_TBT_BGCOLOR');
			$env['AWS_TB_BGCOLOR'] = getenv('AWS_TB_BGCOLOR');
			$env['AWS_TB_COLOR'] = getenv('AWS_TB_COLOR');
			$env['AWS_TBR_BGCOLOR'] = getenv('AWS_TBR_BGCOLOR');
			$env['AWS_INCL_DECODEUTFKEYS'] = getenv('AWS_INCL_DECODEUTFKEYS');
			$env['AWS_INCL_GEOIP'] = getenv('AWS_INCL_GEOIP');
			$env['AWS_PATH_TO_GEOIP'] = getenv('AWS_PATH_TO_GEOIP');

			\TYPO3\CMS\Core\Utility\DebugUtility::debug($env);
			\TYPO3\CMS\Core\Utility\DebugUtility::debug(array('syscmd' => $syscmd));
			passthru($syscmd);
			phpinfo();
		}
		// we may die at this point
		if ($update_in_progress) {
			$this->unlink_update_lockfile($t3log);
		}
		die();
	}

	function call_awstats_cli_update($t3log) {
		// Set some environment values for awstats.conf:
		// we do only set the necessary vars for update
		putenv ('TYPO3_MAGIC=1');
		$logconfig = $this->get_single_logconfig($t3log);
		if (! $logconfig['domains']) {
			return self::$ERR_LOGFILE_NOT_CONFIGURED;
		}

		// Set some usefull vars
		$aws_domain = $logconfig['domains'][0];
		$aws_cache_dir = $this->conf['awstats_data_dir'].$t3log.'/';

		// check for the existance of the awstats cache dir
		if (!@is_dir($aws_cache_dir)) {
			GeneralUtility::mkdir($aws_cache_dir);
		}

		putenv ('AWS_DOMAIN='. $aws_domain);
		putenv ('AWS_DOMAINS='. implode(' ', $logconfig['domains']));
		putenv ('AWS_LOGFILE='. $this->conf['logfile_dir'].$t3log);
		putenv ('GATEWAY_INTERFACE=');
		putenv ('AWS_DNSLOOKUP='. ($logconfig['reverse_dnslookup']?'1':'0'));
		putenv ('AWS_PURGELOGFILE='. ((($logconfig['after_analyzing_action'] == 'p') || ($logconfig['after_analyzing_action'] == 'a'))?'1':'0'));
		putenv ('AWS_ARCHIVELOGRECORDS='. (($logconfig['after_analyzing_action'] == 'a')?'%MM%YYYY':'0'));
		putenv ('AWS_CACHE_DIR='. $aws_cache_dir);
		putenv ('AWS_INCL_DECODEUTFKEYS='. $this->conf['awstatsFullDir'].'dummy.inc.conf');
		putenv ('AWS_INCL_GEOIP='. $this->conf['awstatsFullDir'].'dummy.inc.conf');
		putenv ('AWS_PATH_TO_GEOIP='.$this->ext_conf['pathToGeoIPDat']);
		if ($this->ext_conf['enableDecodeUTFKeys']) {
			putenv ('AWS_INCL_DECODEUTFKEYS='. $this->conf['awstatsFullDir'].'decodeutfkeys.inc.conf');
		}
		if ($this->ext_conf['enableGeoIP']) {
			putenv ('AWS_INCL_GEOIP='. $this->conf['awstatsFullDir'].'geoip.inc.conf');
		}

		// building the command line parameters for awstats.pl
		$parameter = ' -config='.escapeshellarg($aws_domain);
		$parameter.= ' -update';
		if ($this->is_set_update_lockfile($t3log)) {
			return self::$ERR_UPDATE_IS_LOCKED;
		}
		$this->set_update_lockfile($t3log);

		// exec script
		$perlbin = $this->get_perlbin();
		$syscmd = $perlbin.' '.escapeshellarg($this->conf['awstatsFullDir'].$this->conf['awstatsScript']).$parameter;

		ob_start();
		passthru($syscmd, $retval);
		$content = ob_get_clean();
		$this->unlink_update_lockfile($t3log);
		if ($retval) {
			return tx_icsawstats_awstats::$ERR_AWSTATS_CALL_FAILED;
		}
		else {
			return $output;
		}
	} // End of method: call_awstats_cli_update()

} // End of class: tx_icsawstats_awstats

if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/ics_awstats/lib/class.tx_icsawstats_awstats.php'])	{
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/ics_awstats/lib/class.tx_icsawstats_awstats.php']);
}
?>
