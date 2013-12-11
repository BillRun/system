<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012 S.D.O.C. LTD. All rights reserved.
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */

/**
 * Billing generator Csv_Fixed class
 *
 * @package  Billing
 * @since    1.0
 */
class Billrun_Generator_Csv_Fixed extends Billrun_Generator_Csv {

	/**
	 * the type of the object
	 *
	 * @var string
	 */
	static protected $type = 'fixed';
        
        /* data structure
	 * @var array
	 */
        static protected $data_structure = array();

	public function __construct($options) {
            parent::__construct($options);
            $this->data_structure = $this->dataStructure();
	}
        
        /**
	 * @see Billrun_Generator_Csv::createRow
	 */
        public function createRow($row) {
            
            $str = '';
            foreach ($this->data_structure as $column => $width) {
                $str .= str_pad($row[$column],$width," ",STR_PAD_LEFT);
            }
            
            $str .= PHP_EOL;     
            return $str;
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
