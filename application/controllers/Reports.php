<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012 S.D.O.C. LTD. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Description of Reports
 *
 * @author eran
 */
class ReportsController extends Yaf_Controller_Abstract {

	/**
	 * api call output. the output will be converted to json on view
	 * 
	 * @var mixed
	 */
	protected $output;

	/**
	 * initialize method for yaf controller (instead of constructor)
	 */
	public function init() {
		// all output will be store at class output class
		$this->output = new stdClass();
		$this->getView()->output = $this->output;
		// set the actions autoloader
		$loader = Yaf_Loader::getInstance(APPLICATION_PATH . '/application/helpers');
		$loader->registerLocalNamespace("Action");
		$this->setActions();
		$this->setOutputMethod();
	}

	public function indexAction() {

		$this->getView()->title = "BillRun | Reporting so you don't have to";
		$this->getView()->content = "reporting for duty";
	}

	/**
	 * method to add output to the stream and log
	 * 
	 * @param string $content the content to add
	 */
	public function addOutput($content) {
		$this->output .= $content;
	}

	/**
	 * method to set the available actions of the api from config declaration
	 */
	protected function setActions() {
		$this->actions = Billrun_Factory::config()->getConfigValue('reports.actions', array('wholesale' => 'controllers/Action/Wholesale.php'));
	}

	/**
	 * method to set how the api output method
	 */
	protected function setOutputMethod() {
		$this->getView()->outputMethod = Billrun_Factory::config()->getConfigValue('reports.outputMethod');
	}

}

?>
