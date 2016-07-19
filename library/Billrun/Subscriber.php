<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 S.D.O.C. LTD. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Billing abstract subscriber class
 *
 * @package  Billing
 * @since    0.5
 */
abstract class Billrun_Subscriber extends Billrun_Base {

	/**
	 * Type of object
	 *
	 * @var string
	 */
	static protected $type = 'subscriber';

	/**
	 * Data container for subscriber details
	 * 
	 * @var array
	 */
	protected $data = array();

	/**
	 * the fields that are accessible to public
	 * 
	 * @var array
	 */
	protected $availableFields = array();

	/**
	 * extra fields for billrun
	 * @var array
	 */
	protected $billrunExtraFields = array();

	/**
	 * extra fields for the customer
	 * @var array
	 */
	protected $customerExtraData = array();
	protected $time;

	/**
	 * Plans the subscriber had this month
	 * @var array
	 */
	protected $plans = array();

	/**
	 * The active plan at the start of the next billing cycle
	 * @var Billrun_Plan
	 */
	protected $nextPlan = null;

	/**
	 * If the subscriber has a next plan, this is its first activation date
	 * @var string
	 */
	protected $nextPlanActivation = null;

	public function __construct($options = array()) {
		parent::__construct($options);
		if (isset($options['availableFields'])) {
			$this->availableFields = $options['availableFields'];
		}
		if (isset($options['extra_data'])) {
			$this->customerExtraData = $options['extra_data'];
		}
		if (isset($options['data'])) {
			$this->data = $options['data'];
		}
		$dataOptions = Billrun_Util::getFieldVal($options['data'], array());
		$plans = array();
		if (!empty($dataOptions['plans'])) {
			foreach ($dataOptions['plans'] as &$planArr) {
				foreach ($planArr['active_dates'] as $activeRange) {
					$plans[] = array_merge($activeRange, array('plan' => new Billrun_Plan(array('name' => $planArr['name'], 'time' => strtotime($activeRange['from'])))));
				}
			}
			$this->plans = $plans;
		}
		if (isset($options['time'])) {
			$this->time = $options['time'];
		}
		if (isset($dataOptions['next_plan'])) {
			$params = array(
				'name' => $dataOptions['next_plan'],
				'time' => Billrun_Util::getStartTime(Billrun_Util::getFollowingBillrunKey(Billrun_Util::getBillrunKey($this->time))),
			);
			$this->nextPlan = new Billrun_Plan($params);
			$this->nextPlanActivation = $dataOptions['next_plan_activation'];
		}
	}

	/**
	 * method to load subsbscriber details
	 */
	public function __set($name, $value) {
		if (array_key_exists($name, $this->availableFields) && array_key_exists($name, $this->data)) {
			$this->data[$name] = $value;
		}
		return null;
	}

	/**
	 * method to receive public properties of the subscriber
	 * 
	 * @return array the available fields for the subscriber
	 */
	public function getAvailableFields() {
		return $this->availableFields;
	}

	/**
	 * method to get public field from the data container
	 * 
	 * @param string $name name of the field
	 * @return mixed if data field  accessible return data field, else null
	 */
	public function __get($name) {
		if ((array_key_exists($name, $this->availableFields) || in_array($name, $this->billrunExtraFields)) && array_key_exists($name, $this->data)) {
			return $this->data[$name];
		} else if (array_key_exists($name, $this->customerExtraData) && isset($this->data['extra_data'][$name])) {
			return $this->data['extra_data'][$name];
		}
		return null;
	}

	/**
	 * Return true if the subscriber has no data.
	 */
	public function isEmpty() {
		return empty($this->data);
	}

	/**
	 * method to load subsbscriber details
	 * 
	 * @param array $params load by those params 
	 */
	abstract public function load($params);

	/**
	 * method to save subsbscriber details
	 */
	abstract public function save();

	/**
	 * method to delete subsbscriber entity
	 */
	abstract public function delete();

	/**
	 * method to check if the subscriber is valid
	 */
	abstract public function isValid();

	/**
	 * Get subscriber balance information for the current month.
	 * @param type $subscriberId (optional)
	 * @param type $billrunKey (optional)
	 * @return boolean
	 */
	public function getBalance() {
		// TODO: Create a getPlan function.
		return Billrun_Factory::balance()->load($this->data['sid'], Billrun_Util::getNextChargeKey(time()));
	}

	/**
	 * get the (paged) current account(s) plans by time
	 */
	abstract public function getList($startTime, $endTime, $page, $size, $aid = null);

	/**
	 * get the list of active subscribers from a json file. Parse subscribers plans at the given time (unix timestamp)
	 */
	abstract public function getListFromFile($file_path, $time);

	abstract public function getSubscribersByParams($params, $availableFields);

	abstract public function getCredits($billrun_key, $retEntity = false);

	abstract public function getServices($billrun_key, $retEntity = false);

	/**
	 * Returns field names to be saved when creating billrun
	 * @return array
	 */
	public function getExtraFieldsForBillrun() {
		return $this->billrunExtraFields;
	}

	/**
	 * Returns extra fields for the customer
	 * @return array
	 */
	public function getCustomerExtraData() {
		return $this->customerExtraData;
	}

	public function getId() {
		return $this->sid;
	}

	public function getNextPlanName() {
		return $this->nextPlan;
	}

	public function getNextPlanActivationDate() {
		return $this->nextPlanActivation;
	}

	public function getCurrentPlans() {
		return $this->plans;
	}

	/**
	 * 
	 * @param string $billrunKey
	 * @return array
	 */
	public function getFlatEntries($billrunKey) {
		foreach ($this->getCurrentPlans() as $planArr) {
			$charge = 0;
			$plan = $planArr['plan'];
			/* @var $plan Billrun_Plan */
			if (!$plan->isUpfrontPayment()) {
				if ($plan->getPeriodicity() == 'month') {
					$charge = $plan->getPrice($planArr['plan_activation'], $planArr['from'], $planArr['to']);
				}
			} else {
				if ($plan->getPeriodicity() == 'month') {
					
				} else if ($plan->getPeriodicity() == 'year') {
					
				}
			}
			$flatEntries[] = $this->getFlatEntry($billrunKey, $plan, $planArr['from'], $charge);
		}
		$nextPlan = $this->getNextPlan();
		if ($nextPlan && $nextPlan->isUpfrontPayment()) {
			$charge = $plan->getPrice($this->getNextPlanActivationDate(), date(Billrun_Base::base_dateformat, $this->time), Billrun_Billrun::getEndTime($billrunKey));
		}
		return $flatEntries;
	}

	protected function getFlatEntry($billrunKey, $plan, $start, $charge) {
		$startTimestamp = strtotime($start);
		$flatEntry = new Mongodloid_Entity(array(
			'aid' => $this->aid,
			'sid' => $this->sid,
			'source' => 'billrun',
			'billrun' => $billrunKey,
			'type' => 'flat',
			'usaget' => 'flat',
			'urt' => new MongoDate($startTimestamp),
			'aprice' => $charge,
			'plan' => $plan->getName(),
			'plan_ref' => $plan->createRef(),
			'process_time' => new MongoDate(),
		));
		$stamp = md5($this->aid . '_' . $this->sid . $plan->getName() . '_' . $start . $billrunKey);
		$flatEntry['stamp'] = $stamp;
		return $flatEntry;
	}

	/**
	 * 
	 * @return Billrun_Plan
	 */
	public function getNextPlan() {
		return $this->nextPlan;
	}

}
