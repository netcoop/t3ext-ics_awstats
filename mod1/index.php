<?php
/***************************************************************
 *  Copyright notice
 *
 *  (c) 2006 ICSurselva AG (info@icsurselva.ch)
 *  All rights reserved
 *
 *  This script is part of the Typo3 project. The Typo3 project is
 *  free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
 *  (at your option) any later version.
 *
 *  The GNU General Public License can be found at
 *  http://www.gnu.org/copyleft/gpl.html.
 *
 *  This script is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  This copyright notice MUST APPEAR in all copies of the script!
 ***************************************************************/
/**
 * Module 'ICS AWStats' for the 'ics_awstats' extension.
 *
 * Redirect and configuration script for the third party module, AWstats
 *
 * @author	Valentin Schmid <valli@icsurselva.ch>
 */

$LANG->includeLLFile('EXT:ics_awstats/mod1/locallang.xml');
$BE_USER->modAccess($MCONF,1);	// This checks permissions and exits if the users has no permission for entry.

class tx_icsawstats_module1 extends t3lib_SCbase {

	var $pageinfo;

	public function __construct() {
		parent::init();
		
		// Initialize document
		$this->doc = t3lib_div::makeInstance('template');
		$this->doc->setModuleTemplate(
			t3lib_extMgm::extPath('ics_awstats') . 'mod1/mod_template.html'
		);
		$this->doc->backPath = $GLOBALS['BACK_PATH'];
		$this->doc->addStyleSheet(
			'tx_icswebawstats',
			'../' . t3lib_extMgm::siteRelPath('ics_awstats') . 'mod1/mod_styles.css'
		);
	}	
	
	/**
	 * Main function of the module. Write the content to $this->content
	 */
	function main()	{
		global $BE_USER,$LANG,$BACK_PATH,$TCA_DESCR,$TCA,$HTTP_GET_VARS,$HTTP_POST_VARS,$CLIENT,$TYPO3_CONF_VARS;

		$this->pageinfo = t3lib_BEfunc::readPageAccess(0, $this->perms_clause);
		
		$this->doc->form='<form action="" method="post">';

		$this->content.= $this->doc->startPage($LANG->getLL('title'));
		$this->content.= $this->doc->header($LANG->getLL('title'));
		$this->content.= $this->doc->spacer(5);
			
		// Render content:
		$this->moduleContent();
		$this->doc->form='</form>';
		$markers = array();
		$markers['CONTENT'] = $this->content;
		
		// $buttons = $this->getButtons();
		
		// Build the <body> for the module
		$this->content = $this->doc->moduleBody($this->pageinfo, $buttons, $markers);
		// Renders the module page
		$this->content = $this->doc->render(
			$GLOBALS['LANG']->getLL('title'),
			$this->content
		);
	}

	function printContent()	{

		$this->content.= $this->doc->endPage();
		echo $this->content;
	}


	function moduleContent()	{

		global $LANG, $TBE_TEMPLATE;

		$awstats = t3lib_div::makeInstance('tx_icsawstats_awstats');

		// if the user has selected one logfile we run awstats
		if (t3lib_div::_GP('t3log'))	{
			$dbg = t3lib_div::_GP('dbg');
			$t3log = t3lib_div::_GP('t3log');
			$aws_wrapper = $SERVER['PHP_SELF'].'?M=tools_txicsawstatsM1&t3log='.$t3log;
			$aws_wrapper.= ($dbg) ? '&dbg=1' : '';
			$result = $awstats->call_awstats($t3log, $aws_wrapper, $dbg);
			if (!is_numeric($result)) {
				$this->content.=$result;
			}
			else {
				// the following will only be executed if an error occured in call_awstats
				// otherwise the script will die in call_awstats
				switch ($result)	{
					case tx_icsawstats_awstats::$ERR_LOGFILE_NOT_CONFIGURED:
						$content .= $this->getErrorString($LANG->getLL('errNoLogfileConfig'));
						break;
					case tx_icsawstats_awstats::$ERR_AWSTATS_CALL_FAILED:
						$content .= $this->getErrorString($LANG->getLL('errAwsCallFailed'));
						break;
					case tx_icsawstats_awstats::$ERR_UPDATE_IS_LOCKED:
						$content .= $this->getErrorString($LANG->getLL('errUpdateLocked'));
						break;
					default:
						$content .= $this->getWarningString($LANG->getLL('errUnknown'));
				}
			}
		} else {

			// check for the existance of the logfile folder
			if (!@is_dir($awstats->conf['logfile_dir'])) {
				$this->content .= $this->doc->section('',sprintf($LANG->getLL('errLogfileDir'), $awstats->conf['logfile_dir']));
				return;
			}

			// check for the existance of the awstats data dir
			if (!@is_dir($awstats->conf['awstats_data_dir'])) {
				if (! t3lib_div::mkdir($awstats->conf['awstats_data_dir'])) {
					$this->content .= $this->doc->section('',sprintf($LANG->getLL('errCreateAwsDataDir'), $awstats->conf['awstats_data_dir']));
					return;
				}
			}


			$logfiles = array();
			$data = t3lib_div::_GP('data');

			// collect submitted form data
			if (!empty($data['logfiles'])) {
				reset($logfiles);
				//t3lib_utility_Debug::debug($data);
				while (list ($lfile, $attrs) = each($data['logfiles'])) {
					$domains = trim($attrs['domains']);
					$logfiles[$lfile]['type'] = $awstats->get_logf_type($domains);
					if ($logfiles[$lfile]['type'] ==  tx_icsawstats_awstats::$LOGF_REGISTERED) {
						$logfiles[$lfile]['domains'] = explode(',', str_replace (' ', '', $domains));
						$logfiles[$lfile]['browser_update'] = ($attrs['browser_update']) ? 1 : 0;
						$logfiles[$lfile]['cron_update'] = ($attrs['cron_update']) ? 1 : 0;
						$logfiles[$lfile]['reverse_dnslookup'] = ($attrs['reverse_dnslookup']) ? 1 : 0;
						$logfiles[$lfile]['after_analyzing_action'] = $attrs['after_analyzing_action'];
					}
				}
			}

			// get logfile config from config file and merge with submitted data
			$logconfigs = $awstats->get_logconfigs();
			//t3lib_utility_Debug::debug($logconfigs);
			foreach ( $logconfigs as $lfile => $logconfig ) {
				// we don't want to overwrite just submitted data
				if (empty($logfiles[$lfile])) {
					if ($logconfig['type'] == tx_icsawstats_awstats::$LOGF_UNREGISTERED) {
						unset($logfiles[$lfile]);
					} else {
						$logfiles[$lfile] = $logconfig;
					}
				}
			}

			// write the merged data if there's some submitted data
			if (t3lib_div::_GP('logf_save_conf')) {
				$awstats->set_logconfigs($logfiles);
			}

			// get logfiles
			$d = dir($awstats->conf['logfile_dir']);
			while ($entry=$d->read()) {
				if (@is_file($awstats->conf['logfile_dir'].$entry) && (preg_match("/.*log.*\.txt$/i", $entry) || preg_match("/.*\.log$/i", $entry))) {
					if (empty($logfiles[$entry])) {
						$logfiles[$entry]['type'] = tx_icsawstats_awstats::$LOGF_UNREGISTERED;
					} elseif ($logfiles[$entry]['type'] == tx_icsawstats_awstats::$LOGF_REGISTERED) {
						$logfiles[$entry]['type'] = tx_icsawstats_awstats::$LOGF_CHECKED;
					}
				}
			}
			$d->close();

			//t3lib_utility_Debug::debug($logfiles);
			
			// no logfiles found
			if (!count($logfiles)) {
				$this->content.= $this->doc->section('',sprintf($LANG->getLL('noLogfilesFound'),$awstats->conf['logfile_dir']));
				return;
			}

			// delete update lock files
			if (t3lib_div::_GP('rmlock'))	{
				$rmlock = t3lib_div::_GP('rmlock');
				if ($logfiles[$rmlock]['browser_update']) {
					$awstats->unlink_update_lockfile($rmlock);
				}
			}

			// collect logfiles for display
			$theCodeChecked='';
			$theCodeUnreg='';
			$theCodeEdit='';
			reset($logfiles);
			while (list ($lfile, $logconfig) = each($logfiles)) {
				if ($logfiles[$lfile]['type'] == tx_icsawstats_awstats::$LOGF_CHECKED) {
					if (t3lib_div::_GP('logf_clear_cache') && ($logfiles[$lfile]['after_analyzing_action'] == 'n')) {
						$awstats->clear_cache($lfile);
					}
					$theCodeChecked.= $this->get_log_link_elements($awstats, $lfile, $logfiles[$lfile]);
				}
				if ($logfiles[$lfile]['type'] == tx_icsawstats_awstats::$LOGF_UNREGISTERED) {
					$theCodeUnreg.= $this->get_logconfig_form_elements($lfile);
				} else {
					$theCodeEdit.= $this->get_logconfig_form_elements($lfile, $logfiles[$lfile]);
				}
			}

			// output logfiles for selection
			if ($theCodeChecked) {
				$theCode = '<table border="0" cellspacing="0" cellpadding="1">';
				$theCode.= $theCodeChecked;
				$theCode.= '</table>';
				if (!t3lib_div::_GP('logf_edit_conf')) {
					$theCode.= '<br /><input type="submit" name="logf_edit_conf" value="'.$LANG->getLL('btnEditConf').'" /><br />';
				}
				$content.= $this->doc->section($LANG->getLL('hdrSelectLogfile'),'<br />'.$theCode.'<br />',0,1);
			}


			// output logfiles for configuration
			if ($theCodeUnreg) {
				$theCode=$LANG->getLL('msg1ConfLogfile').'<br /><br />';
				$theCode.= '<table border="0" cellspacing="0" cellpadding="1">';
				$theCode.= $this->get_logconfig_form_header();
				$theCode.= $theCodeUnreg;
				$theCode.= $this->get_logconfig_form_footer();
				$theCode.= '</table>';
				$theCode.= '<br /><br />'.$LANG->getLL('msg2ConfLogfile');
				$content.= $this->doc->section($LANG->getLL('hdrConfLogfile'),'<br />'.$theCode.'<br />',0,1);
			}

			// edit logfiles conf
			if (t3lib_div::_GP('logf_edit_conf') && $theCodeEdit) {
				$theCode='<table border="0" cellspacing="0" cellpadding="1">';
				$theCode.= $this->get_logconfig_form_header();
				$theCode.= $theCodeEdit;
				$theCode.= $this->get_logconfig_form_footer();
				$theCode.= '</table>';
				if ($theCodeUnreg=='') {
					$theCode.= '<br /><br />'.$LANG->getLL('msg2ConfLogfile');
				}
				$content.= $this->doc->section($LANG->getLL('hdrEditLogfile'),'<br />'.$theCode.'<br />',0,1);
			}


			// button to delete cache files
			if (($theCodeChecked) && (! $awstats->ext_conf['disableClearCache'])) {
				$content.= $this->doc->spacer(15);
				if (t3lib_div::_GP('logf_clear_cache')) {
					$theCode=''.$LANG->getLL('cacheCleared').'';
				} else {
					$theCode='<input type="submit" name="logf_clear_cache" value="'.$LANG->getLL('btnClearCache').'" /><br /><br />';
					$theCode.= ''.$LANG->getLL('descrClearCache').'<br />';
					$theCode.= ''.$LANG->getLL('descrClearCacheDisabled').'';
				}
				$content.= $this->doc->section($LANG->getLL('hdrClearCache'),'<br />'.$theCode.'<br />',0,1);
			}
			
		}

		// Help text:
		if (!t3lib_div::_GP('t3log') && $GLOBALS['BE_USER']->uc['helpText']) {
			$content.= $this->doc->divider(10);
			$content.= $this->doc->section('','<img src="'.$GLOBALS['BACK_PATH'].'gfx/helpbubble.gif" width="14" height="14" hspace="2" align="top" alt="" />'.$LANG->getLL('helpText'));
		}

		$this->content.= $content;
	}


	function get_logconfig_form_header() {
		global $LANG;
		$content = '<tr>';
		$content.= '<td colspan="2"><strong>'.$LANG->getLL('labelLogfile').'</strong></td>';
		$content.= '<td><img src="clear.gif" width="4" height="1" alt="" /></td>';
		$content.= '<td><strong>'.$LANG->getLL('labelDomains').'</strong></td>';
		$content.= '<td><img src="clear.gif" width="4" height="1" alt="" /></td>';
		$content.= '<td><strong>'.$LANG->getLL('labelBrowserUpdate').'</strong></td>';
		$content.= '<td><img src="clear.gif" width="4" height="1" alt="" /></td>';
		$content.= '<td><strong>'.$LANG->getLL('labelCronUpdate').'</strong></td>';
		$content.= '<td><img src="clear.gif" width="4" height="1" alt="" /></td>';
		$content.= '<td><strong>'.$LANG->getLL('labelReverseDNSLookup').'</strong></td>';
		$content.= '<td><img src="clear.gif" width="4" height="1" alt="" /></td>';
		$content.= '<td><strong>'.$LANG->getLL('labelAfterAnalyzingAction').'</strong></td>';
		$content.= '</tr>'."\n";
		return $content;
	}

	function get_logconfig_form_elements($lfile, $logconfig=array('browser_update' => 1)) {
		global $LANG;
		$baseformname = 'data[logfiles]['.htmlspecialchars($lfile).']';
		$domains_val = ($logconfig['domains']) ? implode(',',$logconfig['domains']) : '';
		$browser_update_val = ($logconfig['browser_update']) ? 'checked="checked" ' : '';
		$cron_update_val = ($logconfig['cron_update']) ? 'checked="checked" ' : '';
		$reverse_dnslookup_val = ($logconfig['reverse_dnslookup']) ? 'checked="checked" ' : '';

		$relPath = t3lib_extMgm::siteRelPath('ics_awstats');
		
		$content = '<tr>';
		$content.= '<td width="18"><img src="../'.$relPath.'/mod1/logfile.gif" width="18" height="16"  title="'.$lfile.'" alt="Logfile: " /></td>';
		$content.= '<td>'.$lfile.'</td>';
		$content.= '<td><img src="clear.gif" width="4" height="1" alt="" /></td>';
		$content.= '<td><input type="text" value="'.$domains_val.'" name="'.$baseformname.'[domains]" /></td>';
		$content.= '<td><img src="clear.gif" width="4" height="1" alt="" /></td>';
		$content.= '<td><input type="checkbox" '.$browser_update_val.'name="'.$baseformname.'[browser_update]" /></td>';
		$content.= '<td><img src="clear.gif" width="4" height="1" alt="" /></td>';
		$content.= '<td><input type="checkbox" '.$cron_update_val.'name="'.$baseformname.'[cron_update]" /></td>';
		$content.= '<td><img src="clear.gif" width="4" height="1" alt="" /></td>';
		$content.= '<td><input type="checkbox" '.$reverse_dnslookup_val.'name="'.$baseformname.'[reverse_dnslookup]" /></td>';
		$content.= '<td><img src="clear.gif" width="4" height="1" alt="" /></td>';
		$content.= '<td><select name="'.$baseformname.'[after_analyzing_action]">';
		$content.= '<option value="n"'.(($logconfig['after_analyzing_action'] == 'n')?' selected="selected"':'').'>'.$LANG->getLL('labelNone').'</option>';
		$content.= '<option value="p"'.(($logconfig['after_analyzing_action'] == 'p')?' selected="selected"':'').'>'.$LANG->getLL('labelPurgeLogFile').'</option>';
		$content.= '<option value="a"'.(($logconfig['after_analyzing_action'] == 'a')?' selected="selected"':'').'>'.$LANG->getLL('labelArchiveLogrecords').'</option>';
		$content.= '</select></td>';
		$content.= '</tr>'."\n";
		return $content;
	}

	function get_logconfig_form_footer() {
		global $LANG;
		$content = '<tr><td colspan="10"><br />';
		$content.= '<input type="submit" name="logf_save_conf" value="'.$LANG->getLL('btnSaveConf').'" />';
		$content.= '</td></tr>'."\n";
		return $content;
	}

	function get_log_link_elements(tx_icsawstats_awstats &$awstats_obj, $lfile, $logconfig) {
		global $LANG;
		
		$url = t3lib_BEfunc::getModuleUrl('tools_txicsawstatsM1', array(
			't3log' => $lfile,
			'dbg' => t3lib_div::_GP('dbg') ? 1 : 0,
		));
		
		$relPath = t3lib_extMgm::siteRelPath('ics_awstats');
		
		$content = '<tr>';
		$content.= '<td width="18"><img src="../'.$relPath.'mod1/logfile.gif" width="18" height="16" title="'.$lfile.'" alt="Logfile: "/></td>';
		$content.= '<td><a href="'.htmlspecialchars($url).'">'.$lfile .'</a></td>';
		$content.= '<td><img src="clear.gif" width="20" height="1" alt="" /></td><td>';
		if ($awstats_obj->is_set_update_lockfile($lfile)) {
			$content.= $LANG->getLL('updateInProgress');
			if ($logconfig['browser_update']) {
				$rmlockurl = t3lib_BEfunc::getModuleUrl('tools_txicsawstatsM1', array(
					'rmlock' => $lfile,
					'dbg' => t3lib_div::_GP('dbg') ? 1 : 0,
				));
				$content.= ' (<a href="'.htmlspecialchars($rmlockurl).'">'.$LANG->getLL('deleteUpdateLockfile').'</a>)';
			}
		} else {
			$content.= '&nbsp;';
		}
		$content.= '</td></tr>'."\n";
		return $content;
	}

	function getErrorString($msg) {
		global $BACK_PATH;
		return '<img src="'.$BACK_PATH.'gfx/icon_fatalerror.gif" width="18" height="16" border="0" align="top" alt="" /><strong>'.$msg.'</strong>';
	}

}


if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/ics_awstats/mod1/index.php'])	{
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/ics_awstats/mod1/index.php']);
}




// Make instance:
$SOBE = t3lib_div::makeInstance('tx_icsawstats_module1');
$SOBE->init();

// Include files?
foreach($SOBE->include_once as $INC_FILE)	include_once($INC_FILE);

$SOBE->main();
$SOBE->printContent();

?>
