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
			$this->update['plan_activation'] = isset($this->update['from']) ? $this->update['from'] : new Mongodloid_Date();
		} else if (isset($this->before['plan_activation']) && isset($this->update['plan']) && isset($this->before['plan']) && $this->before['plan'] !== $this->update['plan']) { // plan was changed
			$this->update['plan_activation'] = isset($this->update['from']) ? $this->update['from'] : new Mongodloid_Date();
		} else { // plan was not changed
			$this->update['plan_activation'] = $this->before['plan_activation'];
		}

		//transalte to and from fields
		Billrun_Utils_Mongo::convertQueryMongodloidDates($this->update);
		if ($this->action == 'create' && !isset($this->update['to'])) {
			$this->update['to'] = new Mongodloid_Date(strtotime('+149 years'));
		}
		
		$this->verifyServices();
		$this->validatePlan();
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
	protected function getCustomFields($update = array()) {
		$customFields = parent::getCustomFields();
		$subscriberFields = Billrun_Factory::config()->getConfigValue($this->collectionName . ".subscriber.fields", array());
		$defaultPlay = Billrun_Utils_Plays::getDefaultPlay();
		$defaultPlayName = isset($defaultPlay['name'])? $defaultPlay['name'] : '';
		$subscriberPlay = Billrun_Util::getIn($update, 'play', Billrun_Util::getIn($this->before, 'play', $defaultPlayName));
		$subscriberFields = Billrun_Utils_Plays::filterCustomFields($subscriberFields, $subscriberPlay);
		return array_merge($subscriberFields, $customFields);
	}
	
	public function getCustomFieldsPath() {
		return $this->collectionName . ".subscriber.fields";
	}

	/**
	 * Verify services are correct before update is applied to the subscription
	 * and makes sure it matches his play
	 */
	protected function verifyServices() {
		$services_sources = array();
		if (!empty($this->update['services'])) {
			$services_sources[] = &$this->update['services'];
		}
		if (!empty($this->queryOptions['$push']['services']['$each'])){
			$services_sources[] = &$this->queryOptions['$push']['services']['$each'];
		}
		
		if (empty($services_sources)) {
			return FALSE;
		}
		foreach ($services_sources as &$services_source) {	
			foreach ($services_source as $key => &$service) {
				if (gettype($service) == 'string') {
					$service = array('name' => $service);
				}
				if (gettype($service['from']) == 'string') {
					$service['from'] = new Mongodloid_Date(strtotime($service['from']));
				}
				if (empty($this->before)) { // this is new subscriber
					$service['from'] = isset($service['from']) && $service['from'] >= $this->update['from'] ? $service['from'] : $this->update['from'];
				}
				if (!empty($service['to']) && gettype($service['to']) == 'string') {
					$service['to'] = new Mongodloid_Date(strtotime($service['to']));
				}
				// handle custom period service or limited cycles service
				$serviceTime = $service['to']->sec ?? time();
				$serviceRate = new Billrun_Service(array('name' => $service['name'], 'time' => $serviceTime));
				// if service not found, throw exception
				if (empty($serviceRate) || empty($serviceRate->get('_id'))) {
					throw new Billrun_Exceptions_Api(66601, array(), "Service was not found");
				}
				if (!empty($servicePeriod = @$serviceRate->get('balance_period')) && $servicePeriod !== "default") {
					$service['to'] = new Mongodloid_Date(strtotime($servicePeriod, $service['from']->sec));
				} else {
					// Handle limited cycle services
					$serviceAvailableCycles = $serviceRate->getServiceCyclesCount();
					if ($serviceAvailableCycles !== Billrun_Service::UNLIMITED_VALUE) {
						$vDate = date(Billrun_Base::base_datetimeformat, $service['from']->sec);
						$to = strtotime('+' . $serviceAvailableCycles . ' months', Billrun_Billingcycle::getBillrunStartTimeByDate($vDate));
						$service['to'] = new Mongodloid_Date($to);
					}
				}
				if (empty($service['to'])) {
					$service['to'] =  new Mongodloid_Date(strtotime('+149 years'));
				}
				if (!isset($service['service_id'])) {
					$service['service_id'] = hexdec(uniqid());
				}

				if (!isset($service['creation_time'])) {
					$service['creation_time'] = new Mongodloid_Date();
				}
				
				$this->validateServicePlay($service['name']);

			}
		}
	}
	
	/**
	 * validates that the plan added to the subscriber matches his play
	 */
	protected function validatePlan() {
		$plan = isset($this->update['plan']) ? $this->update['plan'] : '';
		return $this->validateServicePlay($plan, 'plan');
	}
	
	/**
	 * validates that the plan added to the subscriber matches his play
	 */
	protected function validateServicePlay($serviceName, $type ='service') {
		if (empty($serviceName) || !Billrun_Utils_Plays::isPlaysInUse()) {
			return true;
		}
		if ($type == 'plan') {
			$service = new Billrun_Plan(array('name'=> $serviceName, 'time'=> time())); 
		} else {
			$service = new Billrun_Service(array('name'=> $serviceName, 'time'=> time())); 
		}
		
		if (!$service) {
			return false;
		}
		$servicePlays = $service->getPlays();
		if (empty($servicePlays)) {
			return true;
		}
		$subscriberPlay = Billrun_Util::getIn($this->update, 'play', Billrun_Util::getIn($this->before, 'play', ''));
		if (!in_array($subscriberPlay, $servicePlays)) {
			throw new Billrun_Exceptions_Api(0, array(), "\"{$service->get('description')}\" does not match subscriber's play");
		}
		return true;
	}
	
		
	/**
	 * Return the key field
	 * 
	 * @return String
	 */
	protected function getKeyField() {
		return 'sid';
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
				$edge => new Mongodloid_Date()
			);
		}

		if (($edge == 'from' && $this->update[$edge]->sec >= $this->before[$otherEdge]->sec) || ($edge == 'to' && $this->update[$edge]->sec <= $this->before[$otherEdge]->sec)) {
			throw new Billrun_Exceptions_Api(0, array(), 'Requested start date greater than or equal to end date');
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
				$this->update["services.$key.$edge"] = $this->update[$edge];
			}
		}

		$status = $this->dbUpdate($this->query, $this->update);
		if ($edge == 'from' && $followingEntry->isEmpty()) {
			$update = array_merge($this->update, array('aid'=>$this->before['aid']));
			$this->afterSubscriberAction($status, $update);
		}
		if (!isset($status['nModified']) || !$status['nModified']) {
			return false;
		}
		$this->trackChanges($this->query['_id']);

		if (!empty($followingEntry) && !$followingEntry->isEmpty()) {
			$update = array($otherEdge => new Mongodloid_Date($this->update[$edge]->sec));
			if ($edge == 'to' && isset($followingEntry['plan_activation']->sec) && $followingEntry['plan_activation']->sec == $this->before[$edge]->sec) {
				$update['plan_activation'] = $update[$otherEdge];
			}

			// currently hypothetical case
			if ($edge == 'from' && isset($followingEntry['deactivation_date']->sec) && $followingEntry['deactivation_date']->sec == $this->before[$edge]->sec) {
				$update['deactivation_date'] = $update[$otherEdge];
			}

			foreach ($followingEntry['services'] as $key => $service) {
				if ($service[$otherEdge]->sec == $followingEntry[$otherEdge]->sec) {
					$update["services.$key.$otherEdge"] = $update[$otherEdge];
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
			$this->update['to'] = $this->update['deactivation_date'] = new Mongodloid_Date();
		}
		return parent::close();
	}

	/**
	 * future entity was removed - need to update the to of the previous change
	 */
	protected function reopenPreviousEntry() {
		if (!$this->previousEntry->isEmpty()) {
			$this->setQuery(array('_id' => $this->previousEntry['_id']->getMongoID()));
			$update = array(
				'to' => $this->before['to'],
			);
			if (isset($this->before['deactivation_date']) && $this->before['deactivation_date']->sec == $this->before['to']->sec) {
				$update['deactivation_date'] = $this->before['to'];
			}
			$this->setUpdate($update);
			$this->setBefore($this->previousEntry);
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
	
	/**
	 * Deals with changes need to be done after subscriber create/closeAndNew/move in specific cases.
	 * 
	 * @param array $status - Insert Status.
	 * 
	 */
	protected function afterSubscriberAction($status, $update) {
		if (isset($status['ok']) && $status['ok']) {
			$query['type'] = 'account';
			$query['aid'] = $update['aid'];
			$account = $this->collection->query($query)->cursor()->sort(array('from' => 1))->limit(1)->current();
			if ($account->isEmpty()) {
				Billrun_Factory::log("There isn't an account matching the subscriber.", Zend_Log::ERR);
			}
			if (isset($update['from']) && isset($account['from']) && $update['from'] < $account['from']) {
				$account['from'] = $update['from'];
				$accountDetails = $account->getRawData();
				$query['_id'] = $accountDetails['_id'];
				$this->dbUpdate($query, $accountDetails);
			}
		}
		return;
	}

	protected function insert(&$data) {
		$status = parent::insert($data);
		$this->afterSubscriberAction($status, $data);
	}

	public function create() {
		if (empty($this->update['to'])) {
			$this->update['to'] = new Mongodloid_Date(strtotime('+149 years'));
		}
		if (empty($this->update['deactivation_date'])) {
			$this->update['deactivation_date'] = $this->update['to'];
		}
		if (Billrun_Utils_Plays::isPlaysInUse() && empty($this->update['play'])) {
			if ($defaultPlay = Billrun_Utils_Plays::getDefaultPlay()) {
				$this->update['play'] = $defaultPlay['name'];
			}
			else {
				throw new Billrun_Exceptions_Api(0, array(), 'Mandatory update parameter play missing');
			}
		}
		
		parent::create();
	}
	
	/**
	 * method to keep maintenance of subscriber fields.
	 * 
	 * @param array $revisionsQuery query to get the correct revisions
	 */
	protected function fixSubscriberFields($revisionsQuery) {
		$needUpdate = array();
		$previousRevision = array();
		$indicator = 0; 
		$plansDeactivation = array();
		$previousPlan = '';
		$revisionsFrom = $this->collection->query($revisionsQuery)->cursor()->sort(array('from' => 1));
		$subscriberDeactivation = $this->collection->query($revisionsQuery)->cursor()->sort(array('to' => -1))->current()['to'];
//		$subscriberActivation = $revisionsFrom->current()['from']; // MongoDB cursor should avoid double fetching on the same cursor; the next foreach cause reset the cursor
		$first = true;
		foreach ($revisionsFrom as $revision) {
			if ($first) {
				$first = false;
				$subscriberActivation = $revision['from'];
			}
			$revisionsArray[] = $revision->getRawData();
		}
		foreach ($revisionsArray as &$revision) {
			$revisionId = $revision['_id']->{'$id'};
			if (empty($revision['deactivation_date']) || $subscriberDeactivation != $revision['deactivation_date']) {
				$needUpdate[$revisionId]['deactivation_date'] = $subscriberDeactivation;
			}
			if (empty($revision['activation_date']) || $subscriberActivation != $revision['activation_date']) {
				$needUpdate[$revisionId]['activation_date'] = $subscriberActivation;
			}
			$currentPlan = $revision['plan'];
			if ($currentPlan != $previousPlan && (empty($previousRevision) || $previousRevision['to'] == $revision['from']) || 
				(isset($previousRevision['to']) && $previousRevision['to'] != $revision['from'])) {
				if (!empty($previousPlan) && !(isset($previousRevision['to']) && $previousRevision['to'] != $revision['from'])) {
					$formerPlan = $previousPlan;
				}
				$previousPlan = $currentPlan;
				$planActivation = $revision['from'];
				$planDeactivation = $revision['to'];
				$indicator += 1;
			}		
			if (empty($revision['plan_activation']) || $planActivation != $revision['plan_activation']) {
				$needUpdate[$revisionId]['plan_activation'] = $planActivation;
			}
			if (!empty($formerPlan) && ($formerPlan != $currentPlan)) {
				$needUpdate[$revisionId]['former_plan'] = $formerPlan;
			} else if (!empty($revision['former_plan'])) {
				$needUpdate[$revisionId]['former_plan'] = 'unset';
			}
			$futureDeactivation = $revision['to'];
			if ($planDeactivation < $futureDeactivation) {
				$planDeactivation = $futureDeactivation;
			}
			$revision['indicator'] = $indicator;	
			$plansDeactivation[$indicator] = $planDeactivation;
			$previousRevision = $revision;
		}
	
		foreach($plansDeactivation as $index => $deactivationDate) {
			foreach($revisionsArray as $revision2) {
				$revisionId = $revision2['_id']->{'$id'};
				if ($revision2['indicator'] == $index && (!isset($revision2['plan_deactivation']) || $revision2['plan_deactivation'] != $deactivationDate)) {
					$needUpdate[$revisionId]['plan_deactivation'] = $deactivationDate;
				}
			}
		}

		foreach ($needUpdate as $revisionId => $updateValue) {
			$update = array();
			$query = array('_id' => new Mongodloid_Id($revisionId));
			foreach ($updateValue as $field => $value) {
				if ($field == 'former_plan' && $value == 'unset') {
					$update['$unset'][$field] = true;
					continue;
				}
				$update['$set'][$field] = $value;
			}
			$this->collection->update($query, $update);
		}
	}
	
	/**
	 * get all revisions of a subscriber.
	 * 
	 * @param int $entity subscriber revision.
	 * @param int $aid - account id 
	 */
	protected function getSubscriberRevisionsQuery($entity, $aid) {
		$query = array();
		foreach (Billrun_Util::getFieldVal($this->config['duplicate_check'], []) as $fieldName) {
			$query[$fieldName] = $entity[$fieldName];
		}
		$query['aid'] = $aid;
		return $query;
	}
	
	protected function fixEntityFields($entity) {
		if (is_null($entity)) { // create action
			$update['$set']['plan_activation'] = $update['$set']['activation_date'] = $this->update['from'];
			$update['$set']['plan_deactivation'] = $update['$set']['deactivation_date'] = $this->update['to'];
			$this->collection->update(array('_id' => $this->update['_id']), $update);
			return;
		}
		$revisionsQuery = $this->getSubscriberRevisionsQuery($entity, $entity['aid']);
		$this->fixSubscriberFields($revisionsQuery);
		if (isset($this->update['aid']) && $entity['aid'] != $this->update['aid']) {
			$revisionsQuery = $this->getSubscriberRevisionsQuery($entity, $this->update['aid']);
			$this->fixSubscriberFields($revisionsQuery);
		}
	}
	
	public function permanentChange() {
		unset($this->update['plan_activation']);
		unset($this->update['type']);
		return parent::permanentChange();
	}
}
