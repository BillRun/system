<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * BillRun CollectionsSteps class
 *
 * @package  Billrun
 * @since    5.0
 */
class Billrun_CollectionSteps_Db extends Billrun_CollectionSteps {
	
	
	/**
	 * The instance of the DB collection.
	 */
	protected $collection;
	
	
	/**
	 * Construct a new account DB instance.
	 * @param array $options - Array of initialization parameters.
	 */
	public function __construct($options = array()) {
		parent::__construct($options);
		$this->collection = Billrun_Factory::db()->collection_stepsCollection();
	}
	
	public function createCollectionSteps($aid) {
		$steps = Billrun_Factory::config()->getConfigValue('collection',Array());
		foreach ($steps as $step) {
			if($step['active']){
				unset($step['active']);
				$step['aid'] = $aid;
				$step['done'] = false;
				$step['create_date'] =  new MongoDate();
				$step['trigger_date'] =  new MongoDate(strtotime('+' . $step['do_after_days'] . ' days'));
				$newEntity = new Mongodloid_Entity($step);
				$this->collection->insert($newEntity);
			}
		}
	}
	
	public function removeCollectionSteps($aid) {
		$query = array(
			'aid' => $aid,
			'done' => false
		);
		$this->collection->remove($query);
	}


}
