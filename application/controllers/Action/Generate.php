<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Generate action controller class
 *
 * @package     Controllers
 * @subpackage  Action
 * @since       1.0
 */
class GenerateAction extends Action_Base {

	/**
	 * method to execute the generate process
	 * it's called automatically by the cli main controller
	 */
	public function execute() {

		$possibleOptions = array(
			'type' => false,
			'stamp' => true,
			'page' => true,
			'size' => true,
		);

		if (($options = $this->getController()->getInstanceOptions($possibleOptions)) === FALSE) {
			return;
		}

		$this->getController()->addOutput("Loading generator");

		$extraParams = $this->getController()->getParameters();
		if (!empty($extraParams)) {
			$options = array_merge($extraParams, $options);
		}
		try {
			$generator = Billrun_Generator::getInstance($options);
		} catch (Exception $ex) {
			Billrun_Factory::log()->log($ex->getMessage(), Zend_Log::ERR);
			Billrun_Factory::log()->log('Something went wrong while building the generator. No generate was made.', Zend_Log::ALERT);
			return;
		}

		if (!$generator) {
			$this->getController()->addOutput("Generator cannot be loaded");
			return;
		}

		$this->getController()->addOutput("Generator loaded");
		$this->getController()->addOutput("Loading data to Generate...");
		try {
			$generator->load();
		} catch (Exception $ex) {
			Billrun_Factory::log()->log($ex->getMessage(), Zend_Log::ERR);
			Billrun_Factory::log()->log('Something went wrong while loading. No generate was made.', Zend_Log::ALERT);
			return;
		}
		$this->getController()->addOutput("Starting to Generate. This action can take a while...");
		try {
			$generator->generate();
		} catch (Exception $ex) {
			Billrun_Factory::log()->log($ex->getMessage(), Zend_Log::ERR);
			Billrun_Factory::log()->log('Something went wrong while generating. Please pay attention.', Zend_Log::ERR);
		}
		$this->getController()->addOutput("Finished generating.");
		if ($generator->shouldFileBeMoved()) {
			$this->getController()->addOutput("Exporting the file");
			$generator->move();
			$this->getController()->addOutput("Finished exporting");
		}
	}

}
