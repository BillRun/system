<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2013 S.D.O.C. LTD. All rights reserved.
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */

/**
 * Table Date model class to pull data from database for collections with from/to fields
 *
 * @package  Models
 * @subpackage Table
 * @since    0.5
 */
class TabledateModel extends TableModel {

	/**
	 * the date we filter the table
	 * 
	 * @var Zend_Date
	 */
	protected $date;
	
	/**
	 * constructor
	 * 
	 * @param array $params of parameters to preset the object
	 */
	public function __construct(array $params = array()) {
		parent::__construct($params);
		if (isset($params['date'])) {
			$this->date = $params['date'];
		}
	}
	
	/**
	 * Get the data resource
	 * 
	 * @return Mongo Cursor
	 */
	public function getData() {
		$dateInput = new MongoDate($this->date->getTimestamp());;
		$resource = $this->collection->query()
			->lessEq('from', $dateInput)
			->greaterEq('to', $dateInput)
			->cursor()->sort($this->sort)->skip($this->offset())->limit($this->size);
		return $resource;
	}


}
