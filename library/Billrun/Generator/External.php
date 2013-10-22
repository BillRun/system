<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2013 S.D.O.C. LTD. All rights reserved.
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */

/**
 * Billing call generator class
 * Make and  receive call  base on several  parameters
 *
 * @package  Billing
 * @since    0.5
 */
class Billrun_Generator_External extends Billrun_Generator {
	
	/**
	 * Call the hook for the external plugin to generate the calls as defined in the configuration.
	 */
	public function generate() {
		return Billrun_Factory::chain()->trigger('GeneratorGenerate',array($this->getType(), &$this));	
	}

	/**
	 * Call the hook for the external plugin to  load the script
	 */
	public function load() {
			return Billrun_Factory::chain()->trigger('GeneratorLoad',array($this->getType(), &$this));	
	}

}
