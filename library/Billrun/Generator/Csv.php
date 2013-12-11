<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2013 S.D.O.C. LTD. All rights reserved.
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */

/**
 * Billing abstract generator Csv class
 *
 * @package  Billing
 * @since    0.5
 */
abstract class Billrun_Generator_Csv extends Billrun_Generator {
    
	/**
	 * the type of the object
	 *
	 * @var string
	 */
	static protected $type = 'csv';

        public function __construct($options) {
            parent::__construct($options);
	}
        
        /**
	 * @see Billrun_Calculator::load
	 */
	public function load() {
            
        }

	/**
	 * execute the generate action
	 */
	public function generate() {
            
            $str = '';
            foreach ($this->data as $row) {
               $str .= $this->createRow($row);
            }
               
            if(empty($str)) {
                return FALSE;
            }
               
            $file = $this->createTreatedFile($str);
            if ($file) {

                $this->setGeneratedStamp();
                Billrun_Factory::log()->log("file: ". $file ." was generated and file response created", Zend_Log::INFO);
                return TRUE;
            }

            return FALSE;
        }
        
        /**
	 * execute the createTreatedFile action
         * 
         * @param $content the file content.
         * 
         * return file name on success or false on failure
	 */
        abstract public function createTreatedFile($content);
        
        /**
	 * execute the createRow action
         * 
         * @param $row the CDR line.
         * 
         * return formatted data structure row
	 */
	abstract public function createRow($row);
        
        /*
         * set stamp on the generated file on log collecton
         */
        abstract public function setGeneratedStamp();
}
