<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2013 S.D.O.C. LTD. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Billing command line controller class
 *
 * @package  Controller
 * @since    0.5
 */
class CliController extends Yaf_Controller_Abstract {

	/**
	 * the input options from the command line
	 * @var Zend_Console_Getopt 
	 */
	protected $options;

	public function init() {
		$forceUser = Billrun_Factory::config()->getConfigValue('cliForceUser', '');
		$systemExecuterUser = trim(shell_exec('whoami'));
		if (!empty($forceUser) && $systemExecuterUser != $forceUser && $systemExecuterUser != 'apache') {
			Billrun_Log::getInstance()->addWriter(new Zend_Log_Writer_Stream('php://stdout'));
			$this->addOutput('Cannot run cli command with the system user ' . $systemExecuterUser . '. Please use ' . $forceUser . ' for CLI operations');
			exit();
		}
		$this->setActions();
		$this->setOptions();
		// this will verify db config will load into main config
		Billrun_Factory::db();
	}

	/**
	 * method to set the options from the command line
	 * 
	 * @return boolean true on success, else false
	 */
	protected function setOptions() {
		try {
			$input = array(
				'r|R|receive' => 'Receive files and process them into database',
				'p|P|process' => 'Process files into database',
				'c|C|calc|calculate' => 'Calculate lines in database',
				'a|A|aggregate' => 'Aggregate lines for billrun',
				'g|G|generate' => 'Generate xml and csv files of specific billrun',
				'e|E|respond' => 'Respond to files that were processed',
				'l|L|alert' => 'Process and detect alerts',
				'h|H|help' => 'Displays usage information.',
				'cycle' => 'aggregate lines in billing_cycle',
				'export' => 'Export data',
				'type-s' => 'Process: Ild type to use',
				'stamp-s' => 'Process: Stamp to use for this run',
				'path-s' => 'Process: Path of the process file',
				'export-path-s' => 'Respond: The path To export files',
				'workspace-s' => 'The path to the workspace directory', 
				'parser-s' => 'Process: Parser type (default fixed)',
				'backup' => 'Process: Backup path after the file processed (default ./backup)',
				'page-s' => 'the  page to aggregate',
				'size-s' => 'the size of the page to aggregate',
				'environment-s' => 'Environment of the running command',
				'env-s' => 'Environment of the running command',
				'fetchonly' => 'Only fetch data from remote or db instead of doing complete action',
			);

			$this->options = new Zend_Console_Getopt($input);
			$this->options->parse();
		} catch (Zend_Console_Getopt_Exception $e) {
			$this->addOutput($e->getMessage() . "\n\n" . $e->getUsageMessage());
			return false;
		}

		return true;
	}

	/**
	 * method to get command-line options
	 * 
	 * @return Zend_Console_Getopt the Zend object for manipulate the cli options
	 */
	public function getOptions() {
		return $this->options;
	}

	/**
	 * method to set the available actions of the api from config declaration
	 */
	protected function setActions() {
		$this->actions = Billrun_Factory::config()->getConfigValue('cli.actions', array());
	}

	/**
	 * method which run when the app running from command line.
	 * @return void
	 * @since 1.0
	 */
	public function indexAction() {
		// add log to stdout when we are on cli
		Billrun_Log::getInstance()->addWriter(new Zend_Log_Writer_Stream('php://stdout'));
		$this->addOutput("Running Billrun from CLI!");
		$this->addOutput("Running under : '" . Billrun_Factory::config()->getEnv() . "' configuration.");


		//Go through all actions and run the first one that was selected
			foreach (array_keys($this->actions) as $val) {
				if (isset($this->options->{$val})) {
					$this->addOutput(ucfirst($val) . "...");
					$this->forward($val);
				}
			}
		}

	/**
	 * method to add output to the stream and log
	 * 
	 * @param string $content the content to add
	 */
	public function addOutput($content) {
		Billrun_Log::getInstance()->log($content, Zend_Log::INFO);
	}

	/**
	 * method to sync the options received from cli with the possible options
	 * if options is mandatory and not received return false
	 * 
	 * @param type $possibleOptions
	 * 
	 * @return mixed array of options if all mandatory options received, else false
	 */
	public function getInstanceOptions($possibleOptions = null) {
		if (is_null($possibleOptions)) {
			$possibleOptions = array(
				'type' => false,
				'stamp' => false,
				'path' => "./",
				'parser' => 'fixed');
		}

		$options = array();

		//Retrive  the command line  properties
//		foreach($this->options->getRemainingArgs() as  $cmdLineArg) {
//			$seperatedCmdStr = !strpos('=',$cmdLineArg) ? split("=", $cmdLineArg) : split(" ", $cmdLineArg);
//			$inLineOpt = isset($seperatedCmdStr[1]) ?  $seperatedCmdStr[1] : true;
//			foreach (array_reverse(split("\.", $seperatedCmdStr[0])) as $field) {				
//				$inLineOpt = array( $field => $inLineOpt);
//			}
//			$options['cmd_opts'] = array_merge_recursive( (isset($options['cmd_opts']) ? $options['cmd_opts'] : array() ), $inLineOpt );
//		}

		foreach ($possibleOptions as $key => $defVal) {
			$options[$key] = $this->options->getOption($key);
			if (is_null($options[$key])) {
				if (!$defVal) {
					$this->addOutput("Error: No $key selected");
					return false;
				} else if (true !== $defVal) {
					$options[$key] = $defVal;
				} else {
					unset($options[$key]);
				}
			}
		}

		return $options;
	}

}
