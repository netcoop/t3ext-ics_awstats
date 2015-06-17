<?php

class tx_icsawstats_updatetask extends tx_scheduler_Task {

	public function execute() {

		$awstats = t3lib_div::makeInstance('tx_icsawstats_awstats');

		// do the job
		$retval = true;
		$logconfigs = $awstats->get_logconfigs();
		foreach ( $logconfigs as $lfile => $logconfig ) {
			if (! $logconfig['cron_update']) {
				continue;
			}
			$output = $awstats->call_awstats_cli_update($lfile);
			if (!is_numeric($output)) {
				foreach (explode("\n", $output) as $line) {
					if (strpos($line, 'Error') !== FALSE) {
						$GLOBALS['BE_USER']->simplelog($line, 'ics_awstats', 1);
					}
				}
			} else {
				switch ($output) {
					case tx_icsawstats_awstats::$ERR_LOGFILE_NOT_CONFIGURED:
						$GLOBALS['BE_USER']->simplelog('Error: Logfile not configured ('.$lfile.')', 'ics_awstats', 1);
					break;
					case tx_icsawstats_awstats::$ERR_AWSTATS_CALL_FAILED:
						$GLOBALS['BE_USER']->simplelog('Error: AWStats call failed ('.$lfile.')', 'ics_awstats', 1);
					break;
					case tx_icsawstats_awstats::$ERR_UPDATE_IS_LOCKED:
						$GLOBALS['BE_USER']->simplelog('Error: Update is locked ('.$lfile.')', 'ics_awstats', 1);
					break;
					default:
						$GLOBALS['BE_USER']->simplelog('Error: Unknown error ('.$lfile.')', 'ics_awstats', 1);
				}
				$retval = false;
			}
		}

		return $retval;

	} // End of Method: execute()

} // End of class: tx_icsawstats_UpdateTask

if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/ics_awstats/tasks/class.tx_icsawstats_UpdateTask.php']) {
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/ics_awstats/task/class.tx_icsawstats_UpdateTask.php']);
}

?>