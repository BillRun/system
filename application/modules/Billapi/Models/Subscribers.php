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
			$this->update['creation_time'] = new MongoDate();
		} else if (isset($this->before['plan_activation']) && isset($this->update['plan']) && isset($this->before['plan']) && $this->before['plan'] !== $this->update['plan']) { // plan was changed
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
		if (empty($this->update)) {
			return FALSE;
		}
		if (!empty($this->update['services'])) {
			foreach ($this->update['services'] as &$service) {
				if (gettype($service) == 'string') {
					$service = array('name' => $service);
				}
				if (empty($this->before)) { // this is new subscriber
					$service['from'] = isset($service['from']) && $service['from'] >= $this->update['from'] ? $service['from'] : $this->update['from'];
				}
				//to can't be more then the updated 'to' of the subscription
				$service['to'] = !empty($service['to']) && $service['to'] <= $this->update['to'] ? $service['to'] : $this->update['to'];
			}
		}
	}

	/**
	 * move from date of entity including change the previous entity to field
	 * 
	 * @return boolean true on success else false
	 */
	protected function moveEntry($edge = 'from') {
		if ($edge == 'from') {
			$otherEdge = 'to';
		} else { // $current == 'to'
			$otherEdge = 'from';
		}
		if (!isset($this->update[$edge])) {
			$this->update = array(
				$edge => new MongoDate()
			);
		}

		if (($edge == 'from' && $this->update[$edge]->sec > $this->before[$otherEdge]->sec) || ($edge == 'to' && $this->update[$edge]->sec < $this->before[$otherEdge]->sec)) {
			throw new Billrun_Exceptions_Api(0, array(), 'Requested start date greater than end date');
		}

		$this->checkMinimumDate($this->update, $edge);

		$keyField = $this->getKeyField();

		if ($edge == 'from') {
			$query = array(
				$keyField => $this->before[$keyField],
				$otherEdge => array(
					'$lte' => $this->before[$edge],
				)
			);
			$sort = -1;
			$rangeError = 'Requested start date is less than previous end date';
		} else {
			$query = array(
				$keyField => $this->before[$keyField],
				$otherEdge => array(
					'$gte' => $this->before[$edge],
				)
			);
			$sort = 1;
			$rangeError = 'Requested end date is greater than next start date';
		}

		// previous entry on move from, next entry on move to
		$followingEntry = $this->collection->query($query)->cursor()
			->sort(array($otherEdge => $sort))
			->current();

		if (!empty($followingEntry) && !$followingEntry->isEmpty() && (
			($edge == 'from' && $followingEntry[$edge]->sec > $this->update[$edge]->sec) ||
			($edge == 'to' && $followingEntry[$edge]->sec < $this->update[$edge]->sec)
			)
		) {
			throw new Billrun_Exceptions_Api(0, array(), $rangeError);
		}


		if ($edge == 'from' && $this->before['plan_activation']->sec == $this->before['from']->sec) {
			$this->update['plan_activation'] = $this->update[$edge];
		}

		if ($edge == 'to' && isset($this->before['deactivation_date']->sec) && $this->before['deactivation_date']->sec == $this->before[$edge]->sec) {
			$this->update['deactivation_date'] = $this->update[$edge];
		}

		foreach ($this->before['services'] as $key => $service) {
			if ($service[$edge]->sec == $this->before[$edge]->sec) {
				$this->update['services'][$key][$edge] = $this->update[$edge];
			}
		}

		$status = $this->dbUpdate($this->query, $this->update);
		if (!isset($status['nModified']) || !$status['nModified']) {
			return false;
		}
		$this->trackChanges($this->query['_id']);

		if (!empty($followingEntry) && !$followingEntry->isEmpty()) {
			$update = array($otherEdge => new MongoDate($this->update[$edge]->sec));
			if ($edge == 'to' && isset($followingEntry['plan_activation']->sec) && $followingEntry['plan_activation']->sec == $this->before[$edge]->sec) {
				$update['plan_activation'] = $update[$otherEdge];
			}

			// currently hypothetical case
			if ($edge == 'from' && isset($followingEntry['deactivation_date']->sec) && $followingEntry['deactivation_date']->sec == $this->before[$edge]->sec) {
				$update['deactivation_date'] = $update[$otherEdge];
			}

			foreach ($followingEntry['services'] as $key => $service) {
				if ($service[$otherEdge]->sec == $followingEntry[$otherEdge]->sec) {
					$update['services'][$key][$otherEdge] = $update[$otherEdge];
				}
			}
			$this->setQuery(array('_id' => $followingEntry['_id']->getMongoID()));
			$this->setUpdate($update);
			$this->setBefore($followingEntry);
			return $this->update();
		}
		return true;
	}

	public function close() {
		if (empty($this->update)) {
			$this->update = array();
		}
		if (isset($this->update['to'])) {
			$this->update['deactivation_date'] = $this->update['to'];
		} else {
			$this->update['to'] = $this->update['deactivation_date'] = new MongoDate();
		}
		return parent::close();
	}

	/**
	 * future entity was removed - need to update the to of the previous change
	 */
	protected function reopenPreviousEntry() {
		$key = $this->getKeyField();
		$previousEntryQuery = array(
			$key => $this->before[$key],
		);
		$previousEntrySort = array(
			'_id' => -1
		);
		$previousEntry = $this->collection->query($previousEntryQuery)->cursor()
				->sort($previousEntrySort)->limit(1)->current();
		if (!$previousEntry->isEmpty()) {
			$this->setQuery(array('_id' => $previousEntry['_id']->getMongoID()));
			$update = array(
				'to' => $this->before['to'],
			);
			if (isset($this->before['deactivation_date']) && $this->before['deactivation_date']->sec == $this->before['to']->sec) {
				$update['deactivation_date'] = $this->before['to'];
			}
			$this->setUpdate($update);
			$this->setBefore($previousEntry);
			return $this->update();
		}
		return TRUE;
	}

	/**
	 * method to get the db command that run on close and new operation
	 * 
	 * @return array db update command
	 */
	protected function getCloseAndNewPreUpdateCommand() {
		$ret = parent::getCloseAndNewPreUpdateCommand();
		if (isset($this->before['deactivation_date'])) {
			$ret['$unset'] = array('deactivation_date' => 1);
		}
		return $ret;
	}
	
	public function create() {
		if (empty($this->update['to'])) {
			$this->update['to'] = new MongoDate(strtotime('+149 years'));
		}
		if (empty($this->update['deactivation_date'])) {
			$this->update['deactivation_date'] = $this->update['to'];
		}
		parent::create();
	}

}
