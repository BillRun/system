<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2013 S.D.O.C. LTD. All rights reserved.
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */

/**
 * Billing api controller class
 *
 * @package  Action
 * @since    0.5
 */
abstract class Action_Base extends Yaf_Action_Abstract {

	/**
	 * override the render method to use always the index tpl for all api calls
	 * 
	 * @param string $tpl the template
	 * @param array $parameters
	 * 
	 * @return string the output of the api
	 */
	public function render($tpl, array $parameters = null) {
		$tpl = 'index';
		return parent::render($tpl, $parameters);
	}
	
	/**
	 * 
	 * @param type $object
	 * @param type $filename
	 * @return string
	 */
	protected function getTemplate($tempName = false) {
		$name = $tempName ?  $tempName  : 'index.phtml'  ;
		if($name[0] == '/') {
			$template = $name;
		} else {
			$template =  strtolower(preg_replace("/Controller$/","", get_class($this->_controller))). DIRECTORY_SEPARATOR.
						 strtolower(preg_replace("/Action$/","", get_class($this))). DIRECTORY_SEPARATOR.
						 $name;
			if(!file_exists($template) && !file_exists(APPLICATION_PATH."/application/views/".$template)) {
				$template = strtolower(preg_replace("/Controller$/","", get_class($this->_controller))). DIRECTORY_SEPARATOR.
						 $name;
			}
		}
		return $template;
	}
	
}