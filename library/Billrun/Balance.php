<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Billing abstract subscriber class
 *
 * @package  Billing
 * @since    0.5
 */
abstract class Billrun_Balance extends Mongodloid_Entity {

	/**
	 * Type of object
	 *
	 * @var string
	 */
	static protected $type = 'balance';
	static protected $instance = array();

	/**
	 * the row that load the balance
	 * @var array
	 */
	protected $row;

	/**
	 * constant of calculator db field
	 */
	const DEF_CALC_DB_FIELD = 'aprice';

	/**
	 * the pricing field
	 * @var string
	 * @todo take from customer pricing
	 */
	public $pricingField = self::DEF_CALC_DB_FIELD;

	/**
	 * constructor of balance entity
	 * 
	 * @param array $values options to load the balance
	 * 
	 * @return void
	 * 
	 */
	public function __construct($values = null, $collection = null) {
		// Balance require to be used only with primary preferred (specially on real-time)
		$this->collection(Billrun_Factory::db()->balancesCollection()->setReadPreference('RP_PRIMARY'));

		$this->row = $values;

		if (!isset($this->row['sid']) || !isset($this->row['aid'])) {
			Billrun_Factory::log('Error creating balance, no aid or sid', Zend_Log::ALERT);
			return;
		}

		$this->init($values); // this for override behaviour by the inheritance classes

		$balance_values = $this->load();

		$this->setRawData($balance_values);
	}

	/**
	 * abstract class to extend constructor before load the balance from DB after set the row
	 */
	abstract protected function init();

	/**
	 * method to get the instance of the class (singleton)
	 * 
	 * @param type $params
	 * 
	 * @return Billrun_Balance
	 */
	static public function getInstance($params = null) {
		$stamp = Billrun_Util::generateArrayStamp($params);
		if (empty(self::$instance[$stamp])) {
			if (empty($params)) {
				$params = Yaf_Application::app()->getConfig();
			}
			if (isset($params['charging_type'])) {
				$class = 'Billrun_Balance_' . $params['charging_type'];
			} else { // fallback to default postpaid balance
				$class = 'Billrun_Balance_Postpaid';
			}
			self::$instance[$stamp] = new $class($params);
		} else {
			if (isset($params['balance_db_refresh']) && $params['balance_db_refresh']) {
				self::$instance[$stamp]->load();
			}
		}

		return self::$instance[$stamp];
	}

	/**
	 * get balance collection
	 * 
	 * @return collection object
	 * 
	 * @deprecated since version 5.3
	 */
	public static function getCollection() {
		Billrun_Factory::log("Use deprecated method: " . __FUNCTION__, Zend_Log::DEBUG);
		// Balance require to be used only with primary preferred (specially on real-time)
		return Billrun_Factory::db()->balancesCollection()->setReadPreference('RP_PRIMARY');
	}

	/**
	 * Loads the balance for subscriber
	 * @return array subscriber's balance
	 */
	protected function load() {
		Billrun_Factory::log("Trying to load balance for subscriber " . $this->row['sid'] . ". urt: " . $this->row['urt']->sec . ". charging_type: " . $this->charging_type, Zend_Log::DEBUG);
		$query = $this->getBalanceLoadQuery();
		return $this->collection()->query($query)->cursor()->sort($this->loadQuerySort())->setReadPreference('RP_PRIMARY')->limit(1)->current();
	}

	protected function loadQuerySort() {
		return array();
	}

	/**
	 * Gets a query to get the correct balance of the subscriber.
	 * 
	 * @param type $subscriberId
	 * @param type $timeNow - The time now.
	 * @param type $chargingType
	 * @param type $usageType
	 * @return array
	 */
	protected function getBalanceLoadQuery(array $query = array()) {
		$query['sid'] = $this->row['sid'];
		$query['sid'] = array('$lte' => $this->row['urt']);
		$query['to'] = array('$gte' => $this->row['urt']);

		Billrun_Factory::dispatcher()->trigger('getBalanceLoadQuery', array(&$query, $this->row, $this));

		return $query;
	}

	/**
	 * method to check if the loaded balance is valid
	 */
	public function isValid() {
		return count($this->getRawData()) > 0;
	}

	/**
	 * method to update subscriber balance to db
	 * 
	 * @param Mongodloid_Entity $row the input line
	 * @param mixed $rate The rate of associated with the usage.
	 * @param Billrun_Plan $plan the customer plan
	 * @param string $usage_type The type  of the usage (call/sms/data)
	 * @param int $volume The usage volume (seconds of call, count of SMS, bytes  of data)
	 * 
	 * @return mixed on success update return pricing data array, else false
	 * 
	 * @todo move to balance object
	 */
	public function updateBalanceByRow($row, $rate, $plan, $usage_type, $volume) {
		$tx = $this->get('tx');
		if (is_array($tx) && empty($tx)) {
			$this->set('tx', new stdClass());
			$this->save();
		}
		if (!empty($tx) && array_key_exists($row['stamp'], $tx)) { // we're after a crash
			$pricingData = $tx[$row['stamp']]; // restore the pricingData before the crash
			return $pricingData;
		}
		$pricingData = $this->getLinePricingData($volume, $usage_type, $rate, $plan, $row);
		if (isset($row['billrun_pretend']) && $row['billrun_pretend']) {
			Billrun_Factory::dispatcher()->trigger('afterUpdateSubscriberBalance', array(array_merge($row->getRawData(), $pricingData), $this, &$pricingData, $this));
			return $pricingData;
		}

		$balance_id = $this->getId();
		Billrun_Factory::log("Updating balance " . $balance_id . " of subscriber " . $row['sid'], Zend_Log::DEBUG);
		list($query, $update) = $this->BuildBalanceUpdateQuery($pricingData, $row, $volume);

		Billrun_Factory::dispatcher()->trigger('beforeCommitSubscriberBalance', array(&$row, &$pricingData, &$query, &$update, $rate, $this));
		$ret = $this->collection()->update($query, $update);
		if (!($ret['ok'] && $ret['updatedExisting'])) {
			Billrun_Factory::log('Update subscriber balance failed on updated existing document. Update status: ' . print_r($ret, true), Zend_Log::INFO);
			return false;
		}
		Billrun_Factory::log("Line with stamp " . $row['stamp'] . " was written to balance " . $balance_id . " for subscriber " . $row['sid'], Zend_Log::DEBUG);
		$row['tx_saved'] = true; // indication for transaction existence in balances. Won't & shouldn't be saved to the db.
		return $pricingData;
	}

	/**
	 * method to build update query of the balance
	 * 
	 * @param array $pricingData pricing data array
	 * @param Mongodloid_Entity $row the input line
	 * @param int $volume The usage volume (seconds of call, count of SMS, bytes  of data)
	 * 
	 * @return array update query array (mongo style)
	 * 
	 * @todo move to balance object
	 */
	protected function BuildBalanceUpdateQuery(&$pricingData, $row, $volume) {
		$update = array();
		$update['$set']['tx.' . $row['stamp']] = $pricingData;
		$balance_totals_key = $this->getBalanceTotalsKey($pricingData);
		$balance_key = 'balance.totals.' . $balance_totals_key . '.usagev';
		$query = array(
			'_id' => $this->getId()->getMongoID(),
			'$or' => array(
				array($balance_key => $this->getCurrentUsage($balance_totals_key)),
				array($balance_key => array('$exists' => 0))
			)
		);

		return array($query, $update);
	}

	/**
	 * get current usage which will the old (after update)
	 * @return int
	 */
	protected function getCurrentUsage($balance_totals_key) {
		if (!isset($this->get('balance')['totals'][$balance_totals_key]['usagev'])) {
			return 0;
		}
		return $this->get('balance')['totals'][$balance_totals_key]['usagev'];
	}

	/**
	 * method to get balance totals key
	 * 
	 * @param array $row
	 * @param array $pricingData rate handle
	 * 
	 * @return string
	 */
	abstract protected function getBalanceTotalsKey($pricingData);

	/**
	 * Get pricing data for a given rate / subscriber.
	 * 
	 * @param int $volume The usage volume (seconds of call, count of SMS, bytes  of data)
	 * @param string $usageType The type  of the usage (call/sms/data)
	 * @param mixed $rate The rate of associated with the usage.
	 * @param Billrun_Plan $plan the subscriber's current plan
	 * @param array $row the row handle
	 * @return array pricing data details of the specific volume
	 * 
	 * @todo refactoring the if-else-if-else-if-else to methods
	 * @todo remove (in/over/out)_plan support (group used instead)
	 */
	public function getLinePricingData($volume, $usageType, $rate, $plan, $row = null) {
		$ret = array();
		if ($plan->isRateInEntityGroup($rate, $usageType)) {
			$groupVolumeLeft = $plan->usageLeftInEntityGroup($this, $rate, $usageType);
			$volumeToCharge = $volume - $groupVolumeLeft;
			if ($volumeToCharge < 0) {
				$volumeToCharge = 0;
				$ret['in_group'] = $ret['in_plan'] = $volume;
				$ret['arategroups'][] = array(
					'name' => $plan->getEntityGroup(),
					'usagev' => $volume,
					'left' => $groupVolumeLeft - $volume,
					'total' => $plan->getGroupVolume($usageType),
				);
			} else if ($volumeToCharge > 0) {
				$ret['in_group'] = $ret['in_plan'] = $groupVolumeLeft;
				if ($plan->getEntityGroup() !== FALSE && isset($ret['in_group']) && $ret['in_group'] > 0) { // verify that after all calculations we are in group
					$ret['over_group'] = $ret['over_plan'] = $volumeToCharge;
					$ret['arategroups'][] = array(
						'name' => $plan->getEntityGroup(),
						'usagev' => $ret['in_group'],
						'left' => 0,
						'total' => $plan->getGroupVolume($usageType),
					);
				} else if ($volumeToCharge > 0) {
					$ret['out_group'] = $ret['out_plan'] = $volumeToCharge;
				}
				$services = $this->loadSubscriberServices((isset($row['services']) ? $row['services'] : array()), $row['urt']->sec);
				if ($volumeToCharge > 0 && $this->isRateInServicesGroups($rate, $usageType, $services)) {
					$ret['over_group'] = $ret['over_plan'] = $groupVolumeLeft = $this->usageLeftInServicesGroups($rate, $usageType, $services, $volumeToCharge, $ret['arategroups']);
					$ret['in_plan'] = $ret['in_group'] += $volumeToCharge - $groupVolumeLeft;
					$volumeToCharge = $groupVolumeLeft;
					unset($ret['out_group'], $ret['out_plan']);
				}
			}
		} else {
			$services = $this->loadSubscriberServices((isset($row['services']) ? $row['services'] : array()), $row['urt']->sec);
			if ($this->isRateInServicesGroups($rate, $usageType, $services)) {
				$ret['arategroups'] = array();
				$ret['over_group'] = $ret['over_plan'] = $groupVolumeLeft = $this->usageLeftInServicesGroups($rate, $usageType, $services, $volume, $ret['arategroups']);
				$ret['in_plan'] = $ret['in_group'] = $volume - $groupVolumeLeft;
				$volumeToCharge = $groupVolumeLeft;
			} else { // @todo: else if (dispatcher->isRateInPlugin {dispatcher->trigger->calc}
				$ret['out_plan'] = $ret['out_group'] = $volumeToCharge = $volume;
			}
		}

		$charges = Billrun_Rates_Util::getCharges($rate, $usageType, $volumeToCharge, $plan->getName(), 0); // TODO: handle call offset (set 0 for now)
		Billrun_Factory::dispatcher()->trigger('afterChargesCalculation', array(&$row, &$charges, $this));

		$ret[$this->pricingField] = $charges['total'];
		return $ret;
	}

	/**
	 * load subscribers services objects by their name
	 * 
	 * @param array $services services names
	 * @param int $time unix timestamp of effective datetime
	 * 
	 * @return array of services objects
	 */
	protected function loadSubscriberServices($services, $time) {
		$ret = array();
		foreach ($services as $service) {
			$serviceSettings = array(
				'name' => $service,
				'time' => $time
			);
			$ret[] = new Billrun_Service($serviceSettings);
		}

		return $ret; // array of service objects
	}

	/**
	 * check if rate is includes in customer services groups
	 * 
	 * @param object $rate
	 * @param string $usageType
	 * @param array $services
	 * 
	 * @return boolean true if rate in services groups else false
	 * 
	 * @todo check also if there is available includes in the group (require subscriber balance object)
	 */
	protected function isRateInServicesGroups($rate, $usageType, $services) {
		foreach ($services as $service) {
			if ($service->isRateInEntityGroup($rate, $usageType)) {
				return true;
			}
		}
		return false;
	}

	/**
	 * method to check subset of services if there are groups includes available to use
	 * 
	 * @param array $rate the rate
	 * @param string $usageType usage type
	 * @param array $services array of Billrun_Service objects
	 * @param int $volumeRequired the volume required to charge
	 * @param array $arategroups the group services to return to (reference - will be added to this array)
	 * 
	 * @return int volume left to charge after used by all services groups
	 */
	protected function usageLeftInServicesGroups($rate, $usageType, $services, $volumeRequired, &$arategroups) {
		foreach ($services as $service) {
			if ($volumeRequired <= 0) {
				break;
			}
			$serviceGroups = $service->getRateGroups($rate, $usageType);
			foreach ($serviceGroups as $serviceGroup) {
				$groupVolume = $service->usageLeftInEntityGroup($this, $rate, $usageType, $serviceGroup);
				if ($groupVolume === FALSE || $groupVolume <= 0) {
					continue;
				}
				if ($volumeRequired <= $groupVolume) {
					$arategroups[] = array(
						'name' => $serviceGroup,
						'usagev' => $volumeRequired,
						'left' => $groupVolume - $volumeRequired,
						'total' => $service->getGroupVolume($usageType, $serviceGroup),
					);
					return 0;
				}
				$arategroups[] = array(
					'name' => $serviceGroup,
					'usagev' => $groupVolume,
					'left' => 0,
					'total' => $service->getGroupVolume($usageType, $serviceGroup),
				);
				$volumeRequired -= $groupVolume;
			}
		}
		return $volumeRequired; // volume left to charge
	}

	public function getBalanceChargingTotalsKey($usaget) {
		return $this->chargingTotalsKey = $usaget;
	}

	/**
	 * method to get free row pricing data
	 * 
	 * @return array
	 */
	public function getFreeRowPricingData() {
		return array(
			'in_plan' => 0,
			'over_plan' => 0,
			'out_plan' => 0,
			'in_group' => 0,
			'over_group' => 0,
			$this->pricingField => 0,
		);
	}

}
