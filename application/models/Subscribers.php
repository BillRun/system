<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2013 S.D.O.C. LTD. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */


/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * This class is to hold the logic for the subscribers module.
 *
 * @package  Models
 * @subpackage Table
 * @since    4.0
 */
class SubscribersModel extends TabledateModel{
	
	protected $subscribers_coll;
	
	/**
	 * constructor
	 * 
	 * @param array $params of parameters to preset the object
	 */
	public function __construct(array $params = array()) {
		$params['collection'] = Billrun_Factory::db()->subscribers;
		parent::__construct($params);
		$this->subscribers_coll = Billrun_Factory::db()->subscribersCollection();
		$this->search_key = "sid";
	}
	
	public function getProtectedKeys($entity, $type) {
		$parentKeys = parent::getProtectedKeys($entity, $type);
		return array_merge(array("_id"),
						   $parentKeys, 
						   array("imsi", 
							     "msisdn", 
							     "aid",
							     "sid",
							     "plan",
							     "language",
							     "service_provider",
							     "charging_type"));
	}
}
