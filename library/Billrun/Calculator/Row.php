<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Billing calculator update row for calc in row level
 *
 * @package     calculator
 * @subpackage  row
 * @since       5.3
 */
abstract class Billrun_Calculator_Row {

	/**
	 * the instances that the class handles
	 * 
	 * @var array of Billrun_Calculator_Updaterow
	 */
	static protected $instances = array();
	
	protected $calculator;
	
	/**
	 * the row that handle
	 * @var array
	 */
	protected $row = null;

	public function __construct($row, $callerClass) {
		$this->setRow($row);
		$this->calculator = $callerClass;
		$this->init();
	}

	/**
	 * main method to make the row update
	 * 
	 * @return array update data
	 */
	abstract public function update();
	
	/**
	 * initialization of the class
	 * 
	 * @return void
	 */
	abstract protected function init();

	public function getRow() {
		return $this->row;
	}

	public function setRow($row) {
		$this->row = $row;
		return $this;
	}

	public function __get($name) {
		if (isset($this->row[$name])) {
			return $this->row[$name];
		}
	}

	public function __set($name, $value) {
		$this->row[$name] = $value;
	}

	static public function getInstance($calc, ArrayAccess $row, $callerClass, $subcalc = null) {
		$stamp = Billrun_Util::generateArrayStamp($row);
		if (!isset(self::$instances[$stamp])) {
			$class = get_called_class() . '_' . ucfirst($calc);
			if (!is_null($subcalc)) {
				$class .= '_' . ucfirst($subcalc);
			} else { // default is postpaid sub-calc
				$class .= '_Postpaid';
			}
			self::$instances[$stamp] = new $class($row, $callerClass);
		}
		return self::$instances[$stamp];
	}

}
