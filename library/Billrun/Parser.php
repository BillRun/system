<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Billing abstract parser class
 *
 * @package  Billing
 * @since    0.5
 */
abstract class Billrun_Parser extends Billrun_Base {

    const DEFAULT_TARGET_ENCODING = 'UTF-8';
	/**
	 * the type of the object
	 *
	 * @var string
	 */
	static protected $type = "parser";

	/**
	 *
	 * @var string the line to parse 
	 */
	protected $line = '';

	/**
	 *
	 * @var string the return type of the parser (object or array)
	 */
	protected $return = 'array';
	
	protected $headerRows = array();
	protected $dataRows = array();
	protected $trailerRows = array();
    
    protected $encodingSource = null;
    
    protected $encodingTarget = null;

	public function __construct($options) {

		parent::__construct($options);

		if (isset($options['return'])) {
			$this->return = $options['return'];
		}
        
        if (isset($options['encoding_source'])) {
            $this->encodingSource = $options['encoding_source'];
        }
        
        if (isset($options['encoding_target'])) {
            $this->encodingTarget = $options['encoding_target'];
        }
	}

	/**
	 * 
	 * @return string the line that parsed
	 */
	public function getLine($fp) {
		return $this->line;
	}

	/**
	 * method to set the line of the parser
	 * 
	 * @param string $line the line to set to the parser
	 * 
	 * @return mixed the parser itself (for concatening methods)
	 */
	public function setLine($line) {
		$this->line = $line;
		return $this;
	}

	/**
	 * general function to parse
	 */
	abstract public function parse($fp);
	
	public function getHeaderRows() {
		return $this->headerRows;
	}
	
	public function getDataRows() {
		return $this->dataRows;
	}
	
	public function getTrailerRows() {
		return $this->trailerRows;
	}

	public function resetData() {
		$this->headerRows = array();
		$this->dataRows = array();
		$this->trailerRows = array();
	}
}
