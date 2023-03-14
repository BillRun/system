<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2020 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Billing realtime controller class
 * Used for events in real-time
 * 
 * @package  Controller
 * @since    5.14
 */
class OpenapiController extends RealtimeController {

	const LOCATION = '/ratingdata';

	const RECORD_TYPES = [
		'initial' => [
			'record_type' => 'initial_request',
			'request_type' => '1',
		],
		'update' => [
			'record_type' => 'update_request',
			'request_type' => '2',
		],
		'release' => [
			'record_type' => 'final_request',
			'request_type' => '3',
		],
	];

	const RATING_ACTION_RESERVE = 'RESERVE';
	const RATING_ACTION_DEBIT = 'DEBIT';
	const RATING_ACTION_RELEASE = 'RELEASE';

	protected static $VALIDITY_TIME = 86400;

	protected $requestType;
	protected $sessionId;
	protected $initialRequest = null;
	protected $missingData = [];

	public function indexAction() {
		$this->setHttpStatusCode(Billrun_Utils_HttpStatusCodes::HTTP_FORBIDDEN);
		return $this->respond(null);
	}
	
	/**
	 * the entry point for realtime requests arriving from network gateway 
	 *
	 */
	public function ratingdataAction() {
		if (!$this->authorize()) {
			$this->setHttpStatusCode(Billrun_Utils_HttpStatusCodes::HTTP_UNAUTHORIZED);
			return $this->respond(['status' => 0, 'cause' => 'Unauthorized']);
		}

		return $this->execute();
	}
	
	/**
	 * validates OAuth2 authorization with dedicated scope
	 */
	protected function authorize() {
		return Billrun_Utils_Security::validateOauth(false, 'openapi'); 
	}
	
	/**
	 * see parent::allowed
	 * Authorization is done via OAuth2 (client credentials with specific scope - so no need for additional authorization
	 */
	protected function allowed(array $input = []) {
		return true;
	}
	
	/**
	 * see parent::getConfig
	 */
	protected function getConfig() {
		return Billrun_Factory::config()->getFileTypeSettings('REALTIME_RF', true);
	}
	
	/**
	 * see parent::getRequestType
	 */
	protected function getRequestType() {
		return self::RECORD_TYPES[$this->requestType]['request_type'] ?? '';
	}
	
	/**
	 * see parent::getDataRecordType
	 */
	protected function getDataRecordType($data) {
		return self::RECORD_TYPES[$this->requestType]['record_type'] ?? '';
	}
	
	/**
	 * see parent::getSessionId
	 */
	protected function getSessionId() {
		return $this->sessionId;
	}
	
	/**
	 * see parent::setDataFromRequest
	 */
	protected function setDataFromRequest() {
		$this->prepareRequestData();
		return parent::setDataFromRequest();
	}

	protected function prepareRequestData() {
		$params = $this->getRequest()->getParams();
		if (empty($params)) {
			$this->setHttpStatusCode(Billrun_Utils_HttpStatusCodes::HTTP_CREATED);
			$this->requestType = 'initial';
			$this->sessionId = uniqid();
			$this->setLocationHeader();
		} else {
			$this->sessionId = array_keys($params)[0];
			$this->requestType = array_values($params)[0];
		}
	}
	
	/**
	 * method to set header location in case of success reservation
	 */
	protected function setLocationHeader() {
		$location = self::LOCATION . "/{$this->sessionId}";
		$this->getResponse()->setHeader('Location', $location);
	}
	
	/**
	 * see parent::process
	 */
	protected function process() {
		$this->initMissingData();
		$this->splitServiceRatings();
		$lines = parent::process();
		return $this->mergeServiceRatings($lines);
	}
	
	/**
	 * add missing data from initial request
	 */
	protected function initMissingData() {
		// todo: move to configuration
		$fieldsToAdd = [
			'uf.subscriptionId',
			'uf.serviceRating.serviceInformation.sgsnMccMnc.mcc',
			'uf.serviceRating.serviceInformation.sgsnMccMnc.mnc',
		];
		
		if ($this->requestType === 'initial') {
			return true;
		}

		foreach ($fieldsToAdd as $fieldToAdd) {
			$data = Billrun_Util::getIn($this->event, $fieldToAdd, '');
			if (!empty($data)) {
				continue;
			}

			$initialRequest = $this->getInitialRequest();
			$missingData = Billrun_Util::getIn($initialRequest, $fieldToAdd, '');
			$this->missingData[$fieldToAdd] = $missingData;
		}
	}
	
	/**
	 * method to setup missing data for row
	 * 
	 * @param void
	 */
	protected function setMissingDataForRow(&$row) {
		foreach ($this->missingData as $key => $val) {
			Billrun_Util::setIn($row, $key, $val);
		}
	}
	
	/**
	 * get session's initial request
	 *
	 * @return array
	 */
	protected function getInitialRequest() {
		if (is_null($this->initialRequest)) {
			$query = array(
				'session_id' => $this->sessionId,
				'urt' => array(
					'$gt' => new MongoDate(strtotime('-' . self::$VALIDITY_TIME . ' seconds -1 hour')),
				),
				'record_type' => 'initial_request',
				'type' => $this->event['type'],
			);
			$line = $this->getBaseCollection()->query($query)->cursor()
				->sort(array('urt' => -1))
				->limit(1);
			$this->initialRequest = $line->count() ? $line->current() : false;
		}

		return $this->initialRequest;
	}
	
	/**
	 * method to return the collection the initial line exists
	 * in prepaid it would be archive collection, while postpaid it will be lines collection
	 * 
	 * @return Mongodloid_Collection
	 */
	protected function getBaseCollection() {
		$config = $this->getConfig();
		if ($config['realtime']['postpay_charge']) {
			return Billrun_Factory::db()->linesCollection();
		}
		return Billrun_Factory::db()->archiveCollection();
	}
	
	/**
	 * split service ratings to multiple lines (one for each service rating)
	 */
	protected function splitServiceRatings() {
		$requestId = uniqid();
		$origRow = $this->event;
		$this->event = [];
		$serviceRatings = $origRow['uf']['serviceRating'] ?? [];

		foreach ($serviceRatings as $serviceRating) {
			$requestSubType = $serviceRating['requestSubType'] ?? '';
			if (!in_array($requestSubType, [self::RATING_ACTION_DEBIT, self::RATING_ACTION_RELEASE, self::RATING_ACTION_RESERVE])) {
				continue;
			}
			
			$row = $origRow;
			$row['uf']['serviceRating'] = $serviceRating;
			if (isset($row['uf']['serviceRating']['ratingGroup'])) {
				$row['uf']['serviceRating']['ratingGroup'] = (string)$row['uf']['serviceRating']['ratingGroup']; //currently, custom fields are strings
			}
			$row['rebalance_required'] = !$this->config['realtime']['postpay_charge'] && in_array($requestSubType, [self::RATING_ACTION_DEBIT, self::RATING_ACTION_RELEASE]);
			$row['reservation_required'] = $this->config['realtime']['postpay_charge'] || in_array($requestSubType, [self::RATING_ACTION_RESERVE]);
			$row['request_id'] = $requestId;
			$this->setMissingDataForRow($row);
			$this->event[] = $row;
		}

		if (empty($this->event)) {
			$this->event[] = $origRow;
		}
	}
	
	/**
	 * merge data from multiple processes done to 1 unified line
	 * used to merge service ratings there were splitted prior to the process
	 *
	 * @param  array $lines
	 * @return array
	 */
	protected function mergeServiceRatings($lines) {
		$ret = current($lines);
		$ret['service_rating'] = [];
		foreach ($lines as $line) {
			if (empty($serviceRating = $line['uf']['serviceRating'])) {
				continue;
			}
			$serviceRating['usagev'] = $line['usagev'];
			$serviceRating['return_code'] = $line['granted_return_code'];
			$serviceRating['rebalance_required'] = $line['rebalance_required'];
			$serviceRating['reservation_required'] = $line['reservation_required'];
			$serviceRating['blocked_rate'] = $line['blocked_rate'] ?? false;
			$serviceRating['arategroups'] = $line['arategroups'] ?? false;
			$ret['service_rating'][] = $serviceRating;
		}

		return $ret;
	}

}
