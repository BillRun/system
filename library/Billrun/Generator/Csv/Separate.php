<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012 S.D.O.C. LTD. All rights reserved.
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */

/**
 * Billing generator Separate class
 *
 * @package  Billing
 * @since    1.0
 */
class Billrun_Generator_Csv_Separate extends Billrun_Generator_Csv {

	/**
	 * the separator for formatting row
	 *
	 * @var string
	 */
	static protected $separator = " ";
        
        /**
	 * @see Billrun_Generator_Csv::createRow
	 */
        public function createRow($row) {
            
        }
        
        /**
	 * @see Billrun_Generator_Csv::createTreatedFile
	 */
        public function createTreatedFile($xmlContent) {
            
        }
        
        /**
	 * @see Billrun_Generator_Csv::setGeneratedStamp
	 */
        public function setGeneratedStamp() {
            
        }

}
