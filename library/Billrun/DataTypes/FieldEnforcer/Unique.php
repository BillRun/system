<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */
	
/**
 * This class represents a field enforcer rule which enforces the uniquness of input data.
 *
 * @package  DataTypes
 * @subpackage FieldEnforcer
 * @since    5.3
 */
class Billrun_DataTypes_FieldEnforcer_Unique extends Billrun_DataTypes_FieldEnforcer_Rule {

	const COLLECTION_INDEX = 'collection';
	const BASEQUERY_INDEX = 'base_query';

	/**
	 * The collection to check.
	 * @var Mongodloid_Collection
	 */
	protected $collection;
	
	protected $baseQuery;
	
	/**
	 * Create a new instance of the field enforcer class.
	 * @param array $config - Array of configurations.
	 */
	public function __construct(array $config) {
		parent::__construct($config);
		$this->collection = Billrun_Util::getFieldVal($config[self::COLLECTION_INDEX], null);
		$this->baseQuery = Billrun_Util::getFieldVal($config[self::BASEQUERY_INDEX], array());
	}
	
	/**
	 * Enforce the rule.
	 * Return true if enforce is successful or invalid field if invalid.
	 * @param array $data - The data to enforce the rule on.
	 * @return \Billrun_DataTypes_InvalidField
	 */
	public function enforce(array $data) {
		$parentResult = parent::enforce($data);
			
		if($parentResult !== true) {
			return $parentResult;
		}
		
		// Check if exists.
		if(!isset($data[$this->fieldName])) {
			// This is not an error!
			return true;
		}
		
		if(!$this->collection) {
			return new Billrun_DataTypes_InvalidField($this->fieldName,4);
		}
		
		// Query the collection.
		// TODO: Should this check be date bound?
		$query = $this->baseQuery;
		$query[$this->fieldName] = $data[$this->fieldName];
		$count = $this->collection->query($query)->cursor()->count();
		if($count > 0) {
			return new Billrun_DataTypes_InvalidField($this->fieldName,5);			
		}
		
		return true;
	}
}
