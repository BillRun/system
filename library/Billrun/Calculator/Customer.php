<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Billing calculator class for records
 *
 * @package  calculator
 * @since    5.0
 */
class Billrun_Calculator_Customer extends Billrun_Calculator {

	/**
	 * the type of the object
	 *
	 * @var string
	 */
	static protected $type = 'customer';

	/**
	 * Array for translating CDR line values to  customer identifing values (finding out thier MSISDN/IMSI numbers)
	 * @var array
	 */
	protected $translateCustomerIdentToAPI = array();

	/**
	 *
	 * @var Billrun_Subscriber 
	 */
	protected $subscriber;

	/**
	 * array of Billrun_Subscriber
	 * @var array
	 */
	protected $subscribers;
        
        	/**
	 * array of Billrun_Accounts
	 * @var array
	 */
	protected $accounts;

	/**
	 * Whether or not to use the subscriber bulk API method
	 * @var boolean
	 */
	protected $bulk = false;

        /**
	 * Whether or not to use the account bulk API method
	 * @var boolean
	 */
	protected $accountBulk = false;
	/**
	 * Extra customer fields to be saved by line type
	 * @var array
	 */
	protected $extraData = array();

	/**
	 * Should the mandatory customer fields be overriden if they exist
	 * @var boolean
	 */
	protected $overrideMandatoryFields = TRUE;
	
	/**
	 * These mapping are required raw field that must be filled by the customer calculator.
	 */
	const REQUIRED_ROW_ENRICHMENT_MAPPING = array(array('sid'=>'sid'), array('aid'=>'aid'), array('plan'=> 'plan'));
		
	public function __construct($options = array()) {
		parent::__construct($options);

		if (isset($options['calculator']['customer_identification_translation'])) {
			$this->translateCustomerIdentToAPI = $this->getCustomerIdentificationTranslation();
		}
		if (isset($options['calculator']['bulk'])) {
			$this->bulk = $options['calculator']['bulk'];
		}
                if (isset($options['calculator']['account_bulk'])) {
			$this->accountBulk = $options['calculator']['account_bulk'];
		}
		if (isset($options['calculator']['extra_data'])) {
			$this->extraData = $options['calculator']['extra_data'];
		}
		if (isset($options['realtime'])) {
			$this->overrideMandatoryFields = !boolval($options['realtime']);
		}
		if (isset($options['calculator']['override_mandatory_fields'])) {
			$this->overrideMandatoryFields = boolval($options['calculator']['override_mandatory_fields']);
		}

		$this->subscriber = Billrun_Factory::subscriber();
		$this->plans = Billrun_Factory::db()->plansCollection();
		$this->lines_coll = Billrun_Factory::db()->linesCollection();
	}

	/**
	 * method to get calculator lines
	 */
	protected function getLines() {
		return $this->getQueuedLines(array());
	}

	protected function subscribersByStamp() {
		if (!isset($this->subscribers_by_stamp) || !$this->subscribers_by_stamp) {
			$subs_by_stamp = array();
			foreach ($this->subscribers as $key => $sub) {
				$subData = $sub->getData();
				$key = !empty($subData['id']) ? $subData['id'] :
						(!empty($subData['stamp']) ? $subData['stamp'] : $key );
				$subs_by_stamp[$key] = $sub;
			}
			$this->subscribers = $subs_by_stamp;
			$this->subscribers_by_stamp = true;
		}
	}

	
	
	public function prepareData($lines) {
		if ($this->isBulk() && empty($this->subscriber)) {
			$this->subscribers = $this->loadSubscribers($lines);
		}
	}

	/**
	 * make the  calculation
	 */
	public function updateRow($row) {
		Billrun_Factory::dispatcher()->trigger('beforeCalculatorUpdateRow', array(&$row, $this));
		$row->collection($this->lines_coll);
		
		if ($this->isAccountLevelLine($row)) {
			$row = $this->enrichWithSubscriberInformation($row);
			Billrun_Factory::dispatcher()->trigger('afterCalculatorUpdateRow', array(&$row, $this));
			return $row;
		}
		
		if ($this->isBulk()) {
			$this->subscribersByStamp();
			$subscriber = isset($this->subscribers[$row['stamp']]) ? $this->subscribers[$row['stamp']] : FALSE;
		} else {
			if(!$this->loadSubscriberForLine($row)) {
				Billrun_Factory::log('Error loading subscriber for row ' . $row->get('stamp'), Zend_Log::NOTICE);
				return false;
			}
			$subscriber = $this->subscriber;
		}
		if (!$subscriber || !$subscriber->isValid()) {
			if ($this->isOutgoingCall($row)) {
				Billrun_Factory::log('Missing subscriber info for line with stamp : ' . $row->get('stamp'), Zend_Log::NOTICE);
				return false;
			} else {
				Billrun_Factory::log('Missing subscriber info for line with stamp : ' . $row->get('stamp'), Zend_Log::DEBUG);
				return $row;
			}
		}
		
		$row = $this->enrichWithSubscriberInformation($row,$subscriber);

//		foreach (array_keys($subscriber->getAvailableFields()) as $key) {
//			if (is_numeric($subscriber->{$key})) {
//				$subscriber->{$key} = intval($subscriber->{$key}); // remove this conversion when the CRM output contains integers
//			}
//				$subscriber_field = $subscriber->{$key};
//			if (is_array($row[$key]) && (is_array($subscriber_field) || is_null($subscriber_field))) {
//				$row[$key] = array_merge($row[$key], is_null($subscriber_field) ? array() : $subscriber_field);
//			} else {
//				$row[$key] = $subscriber_field;
//			}
//		}
//		
//		foreach (array_keys($subscriber->getCustomerExtraData())as $key) {
//			if ($this->isExtraDataRelevant($row, $key)) {
//					$subscriber_field = $subscriber->{$key};
//				if (is_array($row[$key]) && (is_array($subscriber_field) || is_null($subscriber_field))) { // if existing value is array and in input value is array let's do merge
//					$row[$key] = array_merge($row[$key], is_null($subscriber_field) ? array() : $subscriber_field);
//				} else {
//					$row[$key] = $subscriber_field;
//				}
//			}
//		}

		$plan = Billrun_Factory::plan(array('name' => $row['plan'], 'time' => $row['urt']->sec,'disableCache' => true));
		$plan_ref = $plan->createRef();
		if (is_null($plan_ref)) {
			Billrun_Factory::log('No plan found for subscriber ' . $row['sid'] . ', line ' . $row['stamp'], Zend_Log::ALERT);
			$row['usagev'] = 0;
			$row['apr'] = 0;
			return false;
		}
		
		$connection_type = $plan->get('connection_type') ? $plan->get('connection_type') : 'postpaid';
		if ($row['type'] === 'credit' && $connection_type !== 'postpaid') {
			Billrun_Factory::log('Credit can only be applied on postpaid customers ' . $row->get('stamp'), Zend_Log::ERR);
			return false;
		}
		
		foreach ($plan->getFieldsForLine() as $lineKey => $planKey) {
			if (!empty($planField = $plan->get($planKey))) {
				$row[$lineKey] = $planField;
			}
		}
		$row['plan_ref'] = $plan_ref;

		Billrun_Factory::dispatcher()->trigger('afterCalculatorUpdateRow', array(&$row, $this));
		return $row;
	}

	/**
	 * Returns whether to save the extra data field to the line or not
	 * @param Mongodloid_Entity $line
	 * @param string $field
	 * @return boolean
	 */
	public function isExtraDataRelevant(&$line, $field) {
		if (empty($this->extraData[$line['type']]) || !in_array($field, $this->extraData[$line['type']])) {
			return false;
		}
		
		return true;
	}

	/**
	 * Override parent calculator to save changes with update (not save)
	 */
	public function writeLine($line, $dataKey) {
		Billrun_Factory::dispatcher()->trigger('beforeCalculatorWriteLine', array('data' => $line, 'calculator' => $this));

		$save = array(
			'$set' => array(),
		);
		$saveProperties = $this->getPossiblyUpdatedFields();
		foreach ($saveProperties as $p) {
			if (!is_null($val = $line->get($p, true))) {
				$save['$set'][$p] = $val;
			}
		}

		if (count($save['$set'])) {
			$where = array('stamp' => $line['stamp']);
			Billrun_Factory::db()->linesCollection()->update($where, $save);
			Billrun_Factory::db()->queueCollection()->update($where, $save);
		}

		Billrun_Factory::dispatcher()->trigger('afterCalculatorWriteLine', array('data' => $line, 'calculator' => $this));
	}
	
	/**
	 * in some realtime cases a line usagev should be zero
	 * 
	 * @param type $line
	 * @return boolean
	 * @todo add logic
	 */
	protected function shouldUsagevBeZero($line) {
		return $line['realtime'] && $line['request_type'] == intval(Billrun_Factory::config()->getConfigValue('realtimeevent.requestType.FINAL_REQUEST'));
	}

	public function getPossiblyUpdatedFields() {
		return array_merge(parent::getPossiblyUpdatedFields(), $this->getCustomerPossiblyUpdatedFields(), array('granted_return_code', 'usagev'));
	}

	public function  getCustomerPossiblyUpdatedFields() {
		$subscriber = Billrun_Factory::subscriber();
		$configFields = Billrun_Factory::config()->getConfigValue('customer.calculator.row_enrichment', array());
		$availableFileds = array_keys($subscriber->getAvailableFields());
		$customerExtraData = array_keys($subscriber->getCustomerExtraData());
		return array_merge($availableFileds, $customerExtraData, array('subscriber_lang', 'plan_ref'), array_keys(call_user_func_array('array_merge',$configFields)));
	}

	/**
	 * 
	 * @param type $queueLines
	 * @return type
	 * @todo consider moving isLineLegitimate to here if possible
	 */
	protected function pullLines($queueLines) {
		$lines = parent::pullLines($queueLines);
		if ($this->bulk) { // load all the subscribers in one call
			$this->subscribers = $this->loadSubscribers($lines);
		}
		return $lines;
	}

	public function isBulk() {
		return $this->bulk;
	}

	public function loadSubscribers($rows) {
		$this->subscribers_by_stamp = false;
		$subscriber_extra_data = array_keys($this->subscriber->getCustomerExtraData());
		
		// build customer mapping priorities
		$priorities = $this->buildPriorities($rows, $subscriber_extra_data);
		$subsData = [];
		$queriesToMatchSubs = [];
		foreach ($priorities as $priorityQueries) {
			if (empty($priorityQueries)) {
				continue;
			}
			$queriesToMatchSubs[] = $priorityQueries;
		}
			// load one subscriber for each query
			$results = $this->subscriber->loadSubscriberForQueries($queriesToMatchSubs, $this->subscriber->getAvailableFields());
			if (!$results) {
				Billrun_Factory::log('Failed to load subscribers data for params: ' . print_r($priorityQueries, 1), Zend_Log::NOTICE);
				return false;
			}


		return array_map(function($data) {
			$type = array('type' => Billrun_Factory::config()->getConfigValue('subscribers.subscriber.type', 'db'));
			$options = array('data' => $data->getRawData());
			$subscriber = Billrun_Subscriber::getInstance(array_merge($data->getRawData(), $options, $type));
			return $subscriber;
		}, $results);
	}

	/**
	 * Checks if the current line supposed to be on account's level, means no subscriber should be loaded
	 * @param array $row
	 * @return boolean
	 */
	protected function isAccountLevelLine($row) {
		return Billrun_Util::getIn($row, 'account_level', false);
	}

	/**
	 * Load a subscriber for a given CDR line.
	 * @param type $row
	 * @return type
	 */
	protected function loadSubscriberForLine($row) {
		$priorities = $this->buildPriorities([$row]);
		foreach ($priorities as $priority) {
			if ( $subData = $this->subscriber->loadSubscriberForQuery($priority) ) {
				$type = array('type' => Billrun_Factory::config()->getConfigValue('subscribers.subscriber.type', 'db'));
				$options = array('data' => $subData->getRawData());
				$subscriber = Billrun_Subscriber::getInstance(array_merge($subData->getRawData(), $options, $type));
				return $subscriber;
			}
		}
		return false;
	}
	
	// method for building priorities to perform customer calculation by
	protected function buildPriorities($rows, $subscriber_extra_data = []) {
		$priorities = [];
		foreach ($rows as $row) {
			if ($this->isLineLegitimate($row)) {
				$line_params = $this->getIdentityParams($row);
				if (count($line_params) == 0) {
					Billrun_Factory::log('Couldn\'t identify caller for line of stamp ' . $row['stamp'], Zend_Log::ALERT);
					return;
				} else {
					foreach ($line_params as $key => $currParams) {
						$currParams['time'] = date(Billrun_Base::base_datetimeformat, $row['urt']->sec);
						$currParams['id'] = $row['stamp'];
						$currParams['EXTRAS'] = 0;
						foreach ($subscriber_extra_data as $key) {
							if ($this->isExtraDataRelevant($row, $key)) {
								$currParams['EXTRAS'] = 1;
								break;
							}
						}
						$priorities[] = $currParams;
					}
				}
			}
		}
		return $priorities;
	}
	
	protected function getIdentityParams($row) {
		$params = array();
		$customer_identification_translation = Billrun_Util::getIn($this->translateCustomerIdentToAPI, array($row['type'], $row['usaget']), array());
		foreach ($customer_identification_translation as $translationRules) {
				if (!empty($translationRules['conditions'])) {
				foreach ($translationRules['conditions'] as $condition) {
					if (!preg_match($condition['regex'], $row[$condition['field']])) {
						continue 2;
					}
				}
			}
			$key = $translationRules['src_key'];
			if (isset($row['uf.' .$key])) {
				if (isset($translationRules['clear_regex'])) {
					$val = preg_replace($translationRules['clear_regex'], '', $row['uf.' .$key]);
				} else {
					if ($translationRules['target_key'] === 'msisdn') {
						$val = Billrun_Util::msisdn($row['uf.' .$key]);
					} else {
						$val = $row['uf.' .$key];
					}
				}
				$fieldName = $translationRules['target_key'];
				$fieldType = Billrun_Factory::config()->getCustomFieldType('subscribers.subscriber', $fieldName);
				if ($fieldType == 'ranges') {
					$params[] = Api_Translator_RangesModel::getRangesFieldQuery($fieldName, $val);
				} else {
					$params[] = array($fieldName => $val);
				}
				Billrun_Factory::log("found identification for row: {$row['stamp']} from {$key} to " . $translationRules['target_key'] . ' with value: ' . print_R(end($params)[$translationRules['target_key']], 1), Zend_Log::DEBUG);
			}
			else {
				Billrun_Factory::log('Customer calculator missing field ' . $key . ' for line with stamp ' . $row['stamp'], Zend_Log::ALERT);
			}
		}
		if (empty($params) && $row['type'] === 'credit' && isset($row['sid'])) {
			$params = array(array(
				'sid' => $row['sid'],
				'aid' => $row['aid'],
			));
		}
		return $params;
	}

	/**
	 * @see Billrun_Calculator::getCalculatorQueueType
	 */
	public function getCalculatorQueueType() {
		return self::$type;
	}

	/**
	 * 
	 * @param type $query
	 * @param type $update
	 */
	protected function setCalculatorTag($query = array(), $update = array()) {
		$queue = Billrun_Factory::db()->queueCollection();
		$calculator_tag = $this->getCalculatorQueueType();
		$advance_stamps = array();
		foreach ($this->lines as $stamp => $item) {
			if (!isset($this->data[$stamp]['aid'])) {
				$advance_stamps[] = $stamp;
			} else {
				$query = array('stamp' => $stamp);
				$update = array('$set' => array('calc_name' => $calculator_tag, 'calc_time' => false, 'aid' => $this->data[$stamp]['aid'], 'sid' => $this->data[$stamp]['sid']));
				$queue->update($query, $update);
			}
		}

		if (!empty($advance_stamps)) {
			$query = array('stamp' => array('$in' => $advance_stamps));
			$update = array('$set' => array('calc_name' => $calculator_tag, 'calc_time' => false));
			$queue->update($query, $update, array('multiple' => true));
		}
	}

	/**
	 * @see Billrun_Calculator::isLineLegitimate
	 */
	public function isLineLegitimate($line) {
		return empty($line['skip_calc']) || !in_array(static::$type, $line['skip_calc']);
	}

	protected function isCustomerable($line) {		
		return true;
	}

	/**
	 * It is assumed that the line is customerable
	 * @param type $line
	 * @return boolean
	 */
	protected function isOutgoingCall($line) {
		$outgoing = true;
		if ($line['type'] == 'nsn') {
			$outgoing = in_array($line['record_type'], array('01', '11'));
		}
		if (in_array($line['usaget'], Billrun_Factory::config()->getConfigValue('realtimeevent.incomingCallUsageTypes', array()))) {
			return false;
		}
		return $outgoing;
	}
	
	protected function getCustomerIdentificationTranslation() {
		$customerIdentificationTranslation = array();
		foreach (Billrun_Factory::config()->getConfigValue('file_types', array()) as $fileSettings) {
			if (Billrun_Config::isFileTypeConfigEnabled($fileSettings) && !empty($fileSettings['customer_identification_fields'])) {
				$customerIdentificationTranslation[$fileSettings['file_type']] = $fileSettings['customer_identification_fields'];
			}
		}
		return $customerIdentificationTranslation;
	}

	protected function enrichWithSubscriberInformation($row, $subscriber = null) {
		$enrichedData = array();
		$rowData = $row instanceof Mongodloid_Entity  ? $row->getRawData() : $row;
		if (!is_null($subscriber)) {
			$enrinchmentMapping = array_merge( Billrun_Factory::config()->getConfigValue(static::$type.'.calculator.row_enrichment', array()) , static::REQUIRED_ROW_ENRICHMENT_MAPPING );
			foreach($enrinchmentMapping as $mapping ) {
				$enrichedData = array_merge($enrichedData,Billrun_Util::translateFields($subscriber->getSubscriberData(), $mapping, $this, $rowData));
			}
		}
		$foreignEntitiesToAutoload = Billrun_Factory::config()->getConfigValue(static::$type.'.calculator.foreign_entities_autoload', array('account', 'account_subscribers'));
		$foreignData =  $this->getForeignFields(array('subscriber' => $subscriber ), $enrichedData, $foreignEntitiesToAutoload, $rowData);
		if((!is_null($subscriber) || !empty($enrichedData)) ||
				is_null($subscriber) || !empty($foreignData)) {
			if($row instanceof Mongodloid_Entity) {
				if (!is_null($subscriber)) {
					$rowData['subscriber'] = $enrichedData;
				}
				$row->setRawData(array_merge($rowData, $foreignData, $enrichedData));
			} else {
				if (!is_null($subscriber)) {
					$row['subscriber'] = $enrichedData;
				}
				$row = array_merge($row,$foreignData, $enrichedData);
			}
			
			if (Billrun_Utils_Plays::isPlaysInUse() && !isset($row['subscriber']['play'])) {
				$newRowSubscriber = $row['subscriber'];
				$newRowSubscriber['play'] = Billrun_Utils_Plays::getDefaultPlay()['name'];
				$row['subscriber'] = $newRowSubscriber;
			}
		}
		return $row;
	}
	
	/**
	 * Gets the services which includes for any customer having this plan.
	 * 
	 * @param string $planName
	 * @param date time
	 * @param boolean $addServiceData
	 * @return array - services names array if $addServiceData is false, services names and data otherwise
	 */
	protected function getPlanIncludedServices($planName, $time, $addServiceData, $subscriberData ) {
		if ($time instanceof MongoDate) {
			$time = $time->sec;
		}

//		$plansQuery = Billrun_Utils_Mongo::getDateBoundQuery($time);
//		$plansQuery['name'] = $planName;
//		$plan = Billrun_Factory::db()->plansCollection()->query($plansQuery)->cursor()->current();
		
		$planParams = array(
			'name' => $planName,
			'time' => $time,
			'disableCache' => true
		);
		
		$planObject = Billrun_Factory::plan($planParams);
		if (empty($planObject)) {
			return array();
		}
		
		$plan = $planObject->getData();
		
		if($plan->isEmpty() || empty($plan->get('include')) || !isset($plan->get('include')['services']) || empty($services = $plan->get('include')['services'])) {
			return array();
		}
		
		if (!$addServiceData) {
			return $services;
		}
		
		$retServices = array();
		foreach ($services as $service) {
			$retServices[] = array(
				'name' => $service,
				'from' => $subscriberData['plan_activation'],
				'to' => $subscriberData['plan_deactivation'],
				'service_id' => 0, // assumption: there is no *custom period* service includes
				'plan_included' => true,
			);
		}
		return $retServices;
	}
	
	public function getServicesFromRow($services, $translationRules,$subscriber,$row) {
		$retServices = array();
		foreach (Billrun_Util::getFieldVal($services, array()) as $service) {
			if ($service['from'] <= $row['urt'] && $row['urt'] < $service['to']) {
				$retServices[] = $service['name'];
			}
		}
		$planIncludedServices = $this->getPlanIncludedServices($subscriber['plan'], $row['urt'], false, $subscriber);
		return array_merge($planIncludedServices, $retServices);
	}
	
	/**
	 * Used for enriching lines data with services from subscriber document.
	 * includes services names and the dates from which they are valid for the subscriber
	 * 
	 * @param array $services
	 * @param array $translationRules
	 * @param array $subscriber
	 * @param array $row
	 * @return services array
	 */
	public function getServicesDataFromRow($services, $translationRules,$subscriber,$row) {
		$retServices = array();
		foreach(Billrun_Util::getFieldVal($services, array()) as $service) {
			if($service['from'] <= $row['urt'] && $row['urt'] < $service['to']) {
				$retServices[] = array(
					'name' => $service['name'],
					'from' => $service['from'],
					'to' => $service['to'],
					'service_id' => isset($service['service_id']) ? $service['service_id'] : 0,
					'quantity' => isset($service['quantity']) ? $service['quantity'] : 1,
					'plan_included' => false,
				);
			}
		}
		$planIncludedServices = $this->getPlanIncludedServices($subscriber['plan'], $row['urt'], true, $subscriber);
		return array_merge($planIncludedServices, $retServices);
	}
	
	/**
	 * Used for enriching lines data with subscriber's play
	 * 
	 * @param array $services
	 * @param array $translationRules
	 * @param array $subscriber
	 * @param array $row
	 * @return services array
	 */
	public function getPlayFromRow($play, $translationRules, $subscriber, $row) {
		if (!Billrun_Utils_Plays::isPlaysInUse()) {
			return null;
		}
		return $play;
	}
	
}
