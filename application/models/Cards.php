<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2015 S.D.O.C. LTD. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */


/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * This class is to hold the logic for the Cards module.
 *
 * @package  Models
 * @subpackage Table
 * @since    4.0
 */
class CardsModel extends TabledateModel{
	
	protected $cards_coll;
	
	/**
	 * constructor
	 * 
	 * @param array $params of parameters to preset the object
	 */
	public function __construct(array $params = array()) {
		$params['collection'] = Billrun_Factory::db()->cards;
		parent::__construct($params);
		$this->cards_coll = Billrun_Factory::db()->cardsCollection();
		$this->search_key = "secret";
	}
	
	public function getProtectedKeys($entity, $type) {
		$parentKeys = parent::getProtectedKeys($entity, $type);
		return array_merge(	array("_id"),
							$parentKeys,
							array(
								"secret",
								"batch_number",
								"serial_number",
								"charging_plan_external_id",
								"service_provider",
								"to",
								"status",
								"additional_information"
							)
						);
	}
}
