<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2018 S.D.O.C. LTD. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Billing TAP3 exporter
 * According to Specification Version Number 3, Release Version Number 12 (3.12)
 *
 * @package  Billing
 * @since    2.8
 */
class Billrun_Exporter_Tap3_Tadig extends Billrun_Exporter_Asn1 {

	static protected $type = 'tap3';
	
	static protected $LINE_TYPE_DATA = 'data';
	static protected $LINE_TYPE_CALL = 'call';
	static protected $LINE_TYPE_INCOMING_CALL = 'incoiming_call';
	static protected $LINE_TYPE_SMS = 'sms';
	static protected $LINE_TYPE_INCOMING_SMS = 'incoming_sms';


	protected $vpmnTadig = '';
	protected $stamps = '';
	protected $startTime = null;
	protected $timeZoneOffset = '';
	protected $timeZoneOffsetCode = '';
	protected $startTimeStamp = '';
	protected $numOfDecPlaces;
	protected $logStamp = null;
	
	public function __construct($options = array()) {
		$this->vpmnTadig = $options['tadig'];
		$this->stamps = isset($options['stamps']) ? $options['stamps'] : array();
		$this->startTime = time();
		
		parent::__construct($options);
		$this->timeZoneOffset = date($this->getConfig('datetime_offset_format', 'O'), $this->startTime);
		$this->timeZoneOffsetCode = intval($this->getConfig('datetime_offset_code', 0));
		$this->startTimeStamp = date($this->getConfig('datetime_format', 'YmdHis'), $this->startTime);
		$this->numOfDecPlaces = intval($this->getConfig('header.num_of_decimal_places'));
	}
	
	/**
	 * see parent::getFieldsMapping
	 */
	protected function getFieldsMapping($row) {
		$callEventDetail = $this->getCallEventDetail($row);
		return $this->getConfig(array('fields_mapping', $callEventDetail), array());
	}
	
	/**
	 * see parent::beforeExport
	 * TAP3 should handle locking
	 */
	function beforeExport() {
		$this->createLogDB($this->getLogStamp());
	}
	
	/**
	 * see parent::afterExport
	 * TAP3 should handle locking
	 */
	function afterExport() {
		$this->logDB($this->getLogStamp(), $this->getLogData());
	}
	
	/**
	 * gets stamp in use for the log
	 * 
	 * @return type
	 */
	protected function getLogStamp() {
		if (empty($this->logStamp)) {
			$stampArr = array(
				'export_stamp' => $this->exportStamp,
				'vpmn' => $this->getVpmnTadig(),
			);
			$this->logStamp = Billrun_Util::generateArrayStamp($stampArr);
		}
		return $this->logStamp;
	}
	
	/**
	 * gets call event details to get correct mapping according to usage type
	 * 
	 * @param array $row
	 * @return string one of: MobileOriginatedCall/MobileTerminatedCall/SupplServiceEvent/ServiceCentreUsage/GprsCall/ContentTransaction/LocationService/MessagingEvent/MobileSession
	 * @todo implement for all types
	 */
	protected function getCallEventDetail($row) {
		switch ($this->getLineType($row)) {
			case self::$LINE_TYPE_DATA:
				return 'GprsCall';
			case self::$LINE_TYPE_CALL:
			case self::$LINE_TYPE_SMS:
				return 'MobileOriginatedCall';
			case self::$LINE_TYPE_INCOMING_CALL:
				return 'MobileTerminatedCall';
			default:
				return '';
		}
	}
		
	/**
	 * gets the current receiver (VPMN) TADIG
	 * 
	 * @return string
	 */
	protected function getVpmnTadig() {
		return $this->vpmnTadig;
	}
	
	/**
	 * gets the sender (HPMN) TADIG
	 * 
	 * @return string
	 */
	protected function getHpmnTadig() {
		return $this->getConfig('hmpn_tadig', '');
	}
	
	/**
	 * see parent::getFileName
	 */
	protected function getFileName() {
		$pref = $this->getConfig('file_name.prefix', '');
		$hpmnTadig = $this->getHpmnTadig();
		$vpmnTadig = $this->getVpmnTadig();
		return (empty($pref) ? '' : "{$pref}_") . "{$hpmnTadig}_{$vpmnTadig}.tap3";
	}

	/**
	 * see parent::getQuery
	 */
	protected function getQuery() {
		return array(
			'stamp' => array(
				'$in' => $this->stamps,
			),
		);
	}
	
	/**
	 * see parent::getHeader
	 */
	protected function getHeader() {
		return array(
			'BatchControlInfo' => array(
				'Sender' => $this->getConfig('header.sender'),
				'Recipient' => 'ISRGT',
				'FileSequenceNumber' => '00003',
				'FileCreationTimeStamp' => array(
					'LocalTimeStamp' => $this->startTimeStamp,
					'UtcTimeOffset' => $this->timeZoneOffset,
				),
				'TransferCutOffTimeStamp' => array(
					'LocalTimeStamp' => $this->startTimeStamp,
					'UtcTimeOffset' => $this->timeZoneOffset,
				),
				'FileAvailableTimeStamp' => array(
					'LocalTimeStamp' => $this->startTimeStamp,
					'UtcTimeOffset' => $this->timeZoneOffset,
				),
				'SpecificationVersionNumber' => intval($this->getConfig('header.version_number')),
				'ReleaseVersionNumber' => intval($this->getConfig('header.release_version_number')),
				'FileTypeIndicator' => $this->getConfig('header.file_type_indicator'),
			),
			'AccountingInfo' => array(
				'LocalCurrency' => $this->getConfig('header.local_currency'),
				'TapCurrency' => $this->getConfig('header.tap_currency'),
				'CurrencyConversionList' => array(
					array(
						'CurrencyConversion' => array(
							'ExchangeRateCode' => intval($this->getConfig('header.currency_conversion.exchange_rate_code')),
							'NumberOfDecimalPlaces' => intval($this->getConfig('header.currency_conversion.num_of_decimal_places')),
							'ExchangeRate' => intval($this->getConfig('header.currency_conversion.exchange_rate')),
						),
					),
				),
				'TapDecimalPlaces' => $this->numOfDecPlaces,
			),
			'NetworkInfo' => array(
				'UtcTimeOffsetInfoList' => array(
					array(
						'UtcTimeOffsetInfo' => array(
							'UtcTimeOffsetCode' => $this->timeZoneOffsetCode,
							'UtcTimeOffset' => $this->timeZoneOffset,
						),
					),
				),
				'RecEntityInfoList' => $this->getRecEntityInfoList(),
			),
		);
	}

	/**
	 * see parent::getFooter
	 */
	protected function getFooter() {
		$totalCharge = 0;
		$totalTax = 0;
		$totalDiscount = 0;
		$earliestUrt = null;
		$latestUrt = null;
		$dateFormat = $this->getConfig('datetime_format', 'YmdHis');
		foreach ($this->rawRows as $row) {
			$totalCharge += isset($row['aprice']) ? floatval($row['aprice']) * pow(10, $this->numOfDecPlaces) : 0;
			$totalTax += isset($row['tax']) ? floatval($row['tax']) * pow(10, $this->numOfDecPlaces) : 0;
			$urt = $row['urt']->sec;
			if (is_null($earliestUrt) || $urt < $earliestUrt) {
				$earliestUrt = $urt;
			}
			if (is_null($latestUrt) || $urt > $latestUrt) {
				$latestUrt = $urt;
			}
		}
		return array(
			'AuditControlInfo' => array(
				'EarliestCallTimeStamp' => array(
					'LocalTimeStamp' => date($dateFormat, $earliestUrt),
					'UtcTimeOffset' => $this->timeZoneOffset,
				),
				'LatestCallTimeStamp' => array(
					'LocalTimeStamp' => date($dateFormat, $latestUrt),
					'UtcTimeOffset' => $this->timeZoneOffset,
				),
				'TotalCharge' => $totalCharge,
				'TotalTaxValue' => $totalTax,
				'TotalDiscountValue' => $totalDiscount,
				'CallEventDetailsCount' => count($this->rawRows),
			),
		);
	}
	
	protected function loadRows() {
		parent::loadRows();
		$this->rowsToExport = array(
			'CallEventDetailList' => $this->rowsToExport,
		);
	}
	
	/**
	 * see parent::getDataToExport
	 */
	protected function getDataToExport() {
		$dataToExport = parent::getDataToExport();
		return array(
			'TransferBatch' => $dataToExport,
		);
	}
	
	protected function getRecEntityInfoList() {
		$ret = array();
		foreach ($this->rawRows as $row) {
			$recEntityInfo = $this->getRecEntityInformation($row);
			$found = !empty(array_filter($ret, function($ele) use($recEntityInfo) {
				foreach ($recEntityInfo as $key => $val) {
					if ($ele['RecEntityInformation'][$key] != $val) {
						return false;
					}
				}
				return true;
			}));
			if (!$found) {
				$ret[] = array(
					'RecEntityInformation' => $recEntityInfo,
				);
			}
		}
		return $ret;
	}
	
	protected function getRecEntityInformation($row) {
		switch ($this->getLineType($row)) {
			case self::$LINE_TYPE_DATA:
				$recEntityCode = 0; // TODO: get correct value
				$recEntityType = $this->getConfig('rec_entity_type.GGSN');
				$recEntityId = $row['ggsn_address'];
				break;
			case self::$LINE_TYPE_CALL:
			case self::$LINE_TYPE_INCOMING_CALL:
			case self::$LINE_TYPE_SMS:
				$recEntityCode = 0; // TODO: get correct value
				$recEntityType = $this->getConfig('rec_entity_type.MSC');
				$recEntityId = Billrun_Util::getIn($row, 'msisdn', '');
				break;
			default:
				$recEntityCode = $recEntityType = 0;
				$recEntityId = '';
		}

		return array(
			'RecEntityCode' => intval($recEntityCode),
			'RecEntityType' => intval($recEntityType),
			'RecEntityId' => $recEntityId,
		);
	}
	
	protected function getUtcTimeOffsetCode($row, $fieldMapping) {
		return $this->timeZoneOffsetCode;
	}
	
	protected function getRecEntityCodeList($row, $fieldMapping) {
		switch ($this->getLineType($row)) {
			case self::$LINE_TYPE_DATA:
				$recEntityCode = 0; // TODO: get correct value
				break;
			case self::$LINE_TYPE_CALL:
			case self::$LINE_TYPE_INCOMING_CALL:
			case self::$LINE_TYPE_SMS:
			default:
				$recEntityCode = 0;
		}

		return array(
			array(
				'RecEntityCode' => $recEntityCode,
			),
		);
	}
	
	protected function getChargeInformationList($row, $fieldMapping) {
		$chargeType = $this->getConfig('charge_type.total_charge');
		$charge = $this->getSdrPrice($row);
		$chargeableUnits = isset($row['usagev']) ? $row['usagev'] : 0;
		$callTypeLevel2 = $this->getConfig('call_type_level_2.unknown');
		$callTypeLevel3 = $this->getConfig('call_type_level_3.unknown');
				
		switch ($this->getLineType($row)) {
			case self::$LINE_TYPE_DATA:
				$callTypeLevel1 = $this->getConfig('call_type_level_1.GGSN');
				$chargedItem = $this->getConfig('charged_item.volume_total_based_charge');
				$chargedUnits = ceil($chargeableUnits / 1024) * 1024; // TODO: currentlty, no "rounded" volume field
				break;
			case self::$LINE_TYPE_CALL:
			case self::$LINE_TYPE_INCOMING_CALL:
				$callTypeLevel1 = $this->getConfig('call_type_level_1.international');
				$chargedItem = $this->getConfig('charged_item.duration_based_charge');
				$chargedUnits = ceil($chargeableUnits / 60) * 60; // TODO: currentlty, no "rounded" volume field
				break;
			case self::$LINE_TYPE_SMS:
				$callTypeLevel1 = $this->getConfig('call_type_level_1.international');
				$chargedItem = $this->getConfig('charged_item.event_based_charge');
				$chargedUnits = $chargeableUnits; // TODO: currentlty, no "rounded" volume field
				break;
			default:
				$callTypeLevel1 = $this->getConfig('call_type_level_1.unknown');
				$chargedItem = $this->getConfig('charged_item.volume_total_based_charge');
				$chargedUnits = $chargeableUnits;
		}

		return array(
			array(
				'ChargeInformation' => array(
					'ChargedItem' => $chargedItem,
					'ExchangeRateCode' => 0, // TODO: get correct value from row
					'CallTypeGroup' => array(
						'CallTypeLevel1' => intval($callTypeLevel1),
						'CallTypeLevel2' => intval($callTypeLevel2),
						'CallTypeLevel3' => intval($callTypeLevel3),
					),
					'ChargeDetailList' => array(
						array(
							'ChargeDetail' => array(
								'ChargeType' => $chargeType,
								'Charge' => $charge,
								'ChargeableUnits' => $chargeableUnits,
								'ChargedUnits' => $chargedUnits,
								'ChargeDetailTimeStamp' => array(
									'LocalTimeStamp' => date($this->getConfig('datetime_format', 'YmdHis'), $row['urt']->sec),
									'UtcTimeOffsetCode' => $this->timeZoneOffsetCode,
								),
							),
						),
					),
				),
			),
		);
	}
	
	protected function getSdrPrice($row) {
		if (!isset($row['apr'])) {
			return 0;
		}
		$price = $row['apr'];
		$fromCurrency = $this->getCurrency($row);
		$toCurrency = 'SDR';
		$decimalPlaces = intval($this->getConfig('header.currency_conversion.num_of_decimal_places'));
		$urt = $row['urt']->sec;
		$sdrPrice = Billrun_Utils_Currency_Converter::convertCurrency($price, $fromCurrency, $toCurrency, $urt);
		return $sdrPrice * pow(10, $decimalPlaces);
	}
	
	protected function getCurrency($row) {
		$defaultCurrency = 'NIS';
		$currentDate = new MongoDate();
		if (!isset($row['arate'])) {
			return $defaultCurrency;
		}
		
		$rate = Billrun_DBRef::getEntity($row['arate']);
		if ($rate->isEmpty()) {
			return $defaultCurrency;
		}

		return isset($rate['rates'][$row['usaget']]['currency'])
			? $rate['rates'][$row['usaget']]['currency']
			: $defaultCurrency;
	}
	
	protected function getOperatorSpecInfoList($row, $fieldMapping) { // TODO: implement
		return array(
			['OperatorSpecInformation' => "SVR: Record modified by tfri"],
			['OperatorSpecInformation' => "tfri: RPID DEFLT"],
			['OperatorSpecInformation' => "tfri: CI D"],
			['OperatorSpecInformation' => "tfri: CBR 2.101565"],
			['OperatorSpecInformation' => "tfri: CUBR 145"],
		);
	}
	
	protected function getLineType($row) {
		switch ($row['type']) {
			case 'ggsn':
				return self::$LINE_TYPE_DATA;
			case 'nsn':
				if ($row['usaget'] == 'incoming_call') {
					return self::$LINE_TYPE_INCOMING_CALL;
				}
				if ($row['usaget'] == 'sms') {
					return self::$LINE_TYPE_SMS;
				}
				return self::$LINE_TYPE_CALL;
			default:
				return false;
		}
	}
	
	/**
	 * gets urt
	 * 
	 * @return string
	 */
	protected function getCallEventStartTimeStamp($row) {
		return $this->formatDate($row['urt']);
	}
	
	/**
	 * format date to file format
	 * 
	 * @param mixed $datetime
	 * @return string
	 */
	protected function formatDate($datetime) {
		if ($datetime instanceof MongoDate) {
			$datetime = $datetime->sec;
		} else if (is_string($datetime)) {
			$datetime = strtotime($datetime);
		}
		$dateFormat = $this->getConfig('date_format', 'YmdHis');
		return date($dateFormat, $datetime);
	}

}
