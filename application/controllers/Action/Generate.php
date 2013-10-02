<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2013 S.D.O.C. LTD. All rights reserved.
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
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
			'stamp' => false,
		);

		if (($options = $this->_controller->getInstanceOptions($possibleOptions)) === FALSE) {
			return;
		}

		$this->_controller->addOutput("Loading generator");
		$generator = Billrun_Generator::getInstance($options);
		$this->_controller->addOutput("Generator loaded");

		if ($generator) {
			$this->_controller->addOutput("Loading data to Generate...");
			$generator->load();
			$this->_controller->addOutput("Starting to Generate. This action can take awhile...");
			$results = $generator->generate();
			$this->_controller->addOutput("Generating output files..");
			$this->generateFiles($results, $generator);
			$this->_controller->addOutput("Finish to Generate. This action can take awhile...");
			
		} else {
			$this->_controller->addOutput("Generator cannot be loaded");
		}
	}
	
	protected function getTemplate($object = null,$filename) {
		$name = 'index';
		if($object && method_exists($object,'getTemplate') && $object->getTemplate()) {
			$name = $object->getTemplate();
		}
		if($name[0] == '/') {
			$template = $name;
		} else {
			$template =  strtolower(preg_replace("/Controller$/","", get_class($this->_controller))). DIRECTORY_SEPARATOR.
						 strtolower(preg_replace("/Action$/","", get_class($this))). DIRECTORY_SEPARATOR.
						 $name . ".phtml";
		}
		return $template;
	}
	
	protected function generateFiles($resultFiles,$generator) {
		if($resultFiles) {
				foreach ($resultFiles as $name => $report) {
					$this->_controller->addOutput("Generating file $name");
					$fd = fopen("files/$name","w+");//@TODO change the  output  dir to be configurable.
					fwrite($fd, $this->getView()->render($this->getTemplate($generator, $name),$report));
					fclose($fd);	
				}				
			}
	}
}