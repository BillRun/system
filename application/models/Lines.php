<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2013 S.D.O.C. LTD. All rights reserved.
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */

/**
 * Lines model class to pull data from database for lines collection
 *
 * @package  Models
 * @subpackage Lines
 * @since    0.5
 */
class LinesModel extends TableModel {

//	/**
//	 * constructor
//	 * 
//	 * @param array $params of parameters to preset the object
//	 */
//	public function __construct(array $params = array()) {
//		parent::__construct($params);
//		if (isset($params['date'])) {
//			$this->date = $params['date'];
//		}
//	}

	/**
	 * Get the data resource
	 * 
	 * @return Mongo Cursor
	 */
	public function getData() {
		$resource = $this->collection->query()->cursor()->sort($this->sort)->skip($this->offset())->limit($this->size);
		return $resource;
	}

}
