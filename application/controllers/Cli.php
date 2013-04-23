<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012 S.D.O.C. LTD. All rights reserved.
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
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
		$this->setActions();
		$this->setOptions();
	}

	/**
	 * method to set the options from the command line
	 * 
	 * @return boolean true on success, else false
	 */
	protected function setOptions() {
		try {
			$input = array(
				'r|R|receive' => 'Recieve files and process them into database',
				'p|P|process' => 'Process files into database',
				'c|C|calc|calculate' => 'Calculate lines in database',
				'a|A|aggregate' => 'Aggregate lines for billrun',
				'g|G|generate' => 'Generate xml and csv files of specific billrun',
				'e|E|respond' => 'Respond to files that were processed',
				'l|L|alert' => 'Process and detect alerts',
				'h|H|help' => 'Displays usage information.',
				'type-s' => 'Process: Ild type to use',
				'stamp-s' => 'Process: Stamp to use for this run',
				'path-s' => 'Process: Path of the process file',
				'export-path-s' => 'Respond: The path To export files',
				'workspace-s' => 'The path to the workspace directory',
				'parser-s' => 'Process: Parser type (default fixed)',
				'backup' => 'Process: Backup path after the file processed (default ./backup)',
				'environment-s' => 'Set the  Environment to dev/test/prod temporarly (for a single run)'
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
		$this->addOutput("Runnning under : '" . Billrun_Factory::config()->getEnv() . "' configuration.");


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

		foreach ($possibleOptions as $key => $defVal) {
			$options[$key] = $this->options->getOption($key);
			if (empty($options[$key])) {
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
