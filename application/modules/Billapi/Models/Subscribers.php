<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Billapi subscribers model for subscribers entity
 *
 * @package  Billapi
 * @since    5.3
 */
class Models_Subscribers extends Models_Entity {

	protected function init($params) {
		parent::init($params);
		$this->update['type'] = 'subscriber';

		// TODO: move to translators?
		if (empty($this->before)) { // this is new subscriber
			$this->update['plan_activation'] = isset($this->update['from']) ? $this->update['from'] : new MongoDate();
		} else if (isset($this->before['plan_activation']) && isset($this->update['plan']) 
			&& isset($this->before['plan']) && $this->before['plan'] !== $this->update['plan']) { // plan was changed
			$this->update['plan_activation'] = isset($this->update['from']) ? $this->update['from'] : new MongoDate();
		} else { // plan was not changed
			$this->update['plan_activation'] = $this->before['plan_activation'];
		}
		
		//transalte to and from fields
		Billrun_Utils_Mongo::convertQueryMongoDates($this->update);
				
		$this->verifyServices();
		
	}

	public function get() {
		$this->query['type'] = 'subscriber';
		return parent::get();
	}

	/**
	 * method to add entity custom fields values from request
	 * 
	 * @param array $fields array of field settings
	 */
	protected function getCustomFields() {
		$customFields = parent::getCustomFields();
		$accountFields = Billrun_Factory::config()->getConfigValue($this->collectionName . ".subscriber.fields", array());
		return array_merge($accountFields, $customFields);
	}
	
	/**
	 * Verfiy services are corrrect before update is applied tothe subscrition.
	 */
	protected function verifyServices() {
		if(empty($this->update)) {
			return FALSE;
		}
		foreach($this->update['services'] as  &$service) {
			if(gettype($service) =='string') {
				$service = array('name' => $service);
			}
			if (empty($this->before)) { // this is new subscriber
				$service['from'] = isset($service['from']) && $service['from'] >= $this->update['from'] ? $service['from'] : $this->update['from'];				
			} 
			//to  can't be more then the updated 'to' of the subscription
			$service['to'] = isset($service['to']) && $service['to'] <= $this->update['to'] ? $service['to'] : $this->update['to'];
		}
	}
	
	/**
	 * move from date of entity including change the previous entity to field
	 * 
	 * @return boolean true on success else false
	 */
	public function move() {
		$this->action = 'move';
		if (!$this->query || empty($this->query) || !isset($this->query['_id'])) { // currently must have some query
			return;
		}

		if (!isset($this->update['from'])) {
			$this->update = array(
				'from' => new MongoDate()
			);
		}

		if ($this->update['from']->sec > $this->before['to']->sec) {
			throw new Billrun_Exceptions_Api(0, array(), 'Requested start date greater than end date');
		}

		$this->checkMinimumDate($this->update, 'from');

		$keyField = $this->getKeyField();
		$query = array(
			$keyField => $this->before[$keyField],
			'to' => array(
				'$lte' => $this->before['from'],
			)
		);
		$previousEntry = $this->collection->query($query)->cursor()->sort(array('to' => -1))->current();

		if (!empty($previousEntry) && !$previousEntry->isEmpty() && $previousEntry['from']->sec > $this->update['from']->sec) {
			throw new Billrun_Exceptions_Api(0, array(), 'Requested start date is less than previous end date');
		}
		
		if ($this->before['plan_activation']->sec == $this->before['from']->sec) {
			$this->update['plan_activation'] = $this->update['from'];
		}
		
		foreach($this->before['services'] as $key => $service) {
			if ($service['from']->sec == $this->before['from']->sec) {
				$this->update['services'][$key]['from'] = $this->update['from'];
			}
		}

		$status = $this->dbUpdate($this->query, $this->update);
		if (!isset($status['nModified']) || !$status['nModified']) {
			return false;
		}
		$this->trackChanges($this->query['_id']);

		if (!empty($previousEntry) && !$previousEntry->isEmpty()) {
			$update = array('to' => new MongoDate($this->update['from']->sec - 1));
			foreach($previousEntry['services'] as $key => $service) {
				if ($service['to']->sec == $update['to']) {
					$this->update['services'][$key]['to'] = $update['to'];
				}
			}
			$this->setQuery(array('_id' => $previousEntry['_id']->getMongoID()));
			$this->setUpdate($update);
			$this->setBefore($previousEntry);
			return $this->update();
		}
		return true;
	}

}
