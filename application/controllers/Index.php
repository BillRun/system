<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012 S.D.O.C. LTD. All rights reserved.
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */

/**
 * Billing index controller class
 *
 * @package  Controller
 * @since    1.0
 */
class IndexController extends Yaf_Controller_Abstract {

	protected $eol = PHP_EOL;

	public function indexAction() {
		$this->getView()->content = "Open Source Last Forever!";
	}

	public function cliAction() {
		$this->outputAdd("Running Billrun from CLI!");
		try {
			$input = array(
				'p|P|process' => 'Process files into database',
				'c|C|calc|calculate' => 'Calculate lines in database',
				'a|A|aggregate' => 'Aggregate lines for billrun',
				'g|G|generate' => 'Generate xml and csv files of specific billrun',
				'g|G|generate' => 'Generate xml and csv files of specific billrun',
				'h|H|help' => 'Displays usage information.',
				'ild-s' => 'Process: Ild to use',
				'path-s' => 'Process: Path of the process file',
				'parser-s' => 'Process: Parser type (default fixed)',
			);

			$opts = new Zend_Console_Getopt($input);
			$opts->parse();
		} catch (Zend_Console_Getopt_Exception $e) {
			$this->outputAdd($e->getMessage() . "\n\n" . $e->getUsageMessage());
			return;
		}

		/**
		 * Action : receive
		 */
		if (isset($opts->receive)) {
			$this->outputAdd("Receiveing...");
			$this->receive($opts);
			return;
		}

		/**
		 * Action : process
		 */
		if (isset($opts->process)) {
			$this->outputAdd("Processing...");
			$this->process($opts);
			return;
		}

		/**
		 * Action : calculate
		 */
		if (isset($opts->calculate)) {
			// do something
			$this->outputAdd("calculate");
			$this->calculate($opts);
			return;
		}

		/**
		 * Action : aggregate
		 */
		if (isset($opts->aggregate)) {
			// do something
			$this->outputAdd("aggregate");
			$this->aggregate($opts);
			return;
		}

		/**
		 * Action : generate
		 */
		if (isset($opts->generate)) {
			// do something
			$this->outputAdd("generate");
			$this->generate($opts);
			return;
		}

		$this->outputAdd($opts->getUsageMessage());
	}

	protected function outputAdd($content) {
		Billrun_Log::getInstance()->log($content . PHP_EOL, Zend_Log::INFO);
		$this->getView()->output .= $content . $this->eol;
	}

	protected function receive($opts) {
		
	}

	protected function process($opts) {
		$ild = $opts->getOption('ild');
		if (empty($ild)) {
			$this->outputAdd("Error: No ild selected");
			return;
		}

		$path = $opts->getOption('path');
		$parser = $opts->getOption('parser');
		if (empty($parser)) {
			$parser = 'fixed';
		}

		$this->outputAdd("Parser selected: " . $parser);

		$options = array(
			'type' => $ild,
			'file_path' => $path,
			'parser' => Billrun_Parser::getInstance(array('type' => $parser)),
		);

		$this->outputAdd("Loading processor");
		$processor = Billrun_Processor::getInstance($options);
		$this->outputAdd("Processor loaded");

		if ($processor) {
			$this->outputAdd("Start to process. This action can take awhile...");
			
			// buffer all action output
			ob_start();
			$processor->process();
			// write the buffer into log and output
			$this->outputAdd(ob_get_contents());
			ob_end_clean();
		} else {
			$this->outputAdd("Processor cannot be loaded");
		}
	}

	protected function calculate($opts) {
		
	}

	protected function aggregate($opts) {
		
	}

	protected function generate($opts) {
		
	}

}
