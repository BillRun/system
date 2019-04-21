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
	static protected $LINE_TYPE_INCOMING_CALL = 'incoming_call';
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
	protected $recEntities = array();
	protected $currencyCodes = array();
	
	public function __construct($options = array()) {
		$this->vpmnTadig = $options['tadig'];
		$this->stamps = isset($options['stamps']) ? $options['stamps'] : array();
		$this->startTime = time();
		
		parent::__construct($options);
		$this->timeZoneOffset = gmdate($this->getConfig('datetime_offset_format', 'O'), $this->startTime);
		$this->timeZoneOffsetCode = intval($this->getConfig('datetime_offset_code', 0));
		$this->startTimeStamp = gmdate($this->getConfig('datetime_format', 'YmdHis'), $this->startTime);
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
	 * gets data to log after export is done
	 * 
	 * @return array
	 */
	protected function getLogData() {
		$logData = parent::getLogData();
		$logData['tadig'] = $this->getVpmnTadig();
		
		return $logData;
	}
	
	/**
	 * see parent::getNextLogSequenceNumberQuery()
	 */
	protected function getNextLogSequenceNumberQuery() {
		$query = parent::getNextLogSequenceNumberQuery();
		$query['tadig'] = $this->getVpmnTadig();
		
		return $query;
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
			case self::$LINE_TYPE_INCOMING_SMS:
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
		if (Billrun_Factory::config()->isProd()) {
			$pref = $this->getConfig('file_name.prefix.prod', '');
		} else {
			$pref = $this->getConfig('file_name.prefix.test', '');
		}
		$suffix = $this->getConfig('file_name.suffix', '');
		$hpmnTadig = $this->getHpmnTadig();
		$vpmnTadig = $this->getVpmnTadig();
		$sequenceNum = $this->getSequenceNumber();
		return $pref . $hpmnTadig . $vpmnTadig . $sequenceNum . $suffix;
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
				'Sender' => $this->getHpmnTadig(),
				'Recipient' => $this->getVpmnTadig(),
				'FileSequenceNumber' => $this->getSequenceNumber(),
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
				'CurrencyConversionList' => $this->getCurrencyConversionList(),
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
	
	protected function getCurrencyConversionList() {
		$currencyConversionList = array();
		$numberOfDecimalPlaces = intval($this->getConfig('header.currency_conversion.num_of_decimal_places'));
		foreach ($this->rawRows as $row) {
			$currencyCode = intval($this->getCurrencyCode($row));
			$found = false;
			foreach ($currencyConversionList as $currencyConversion) {
				if ($currencyConversion['CurrencyConversion']['ExchangeRateCode'] == $currencyCode) {
					$found = true;
					break;
				}
			}
			if (!$found) {
				$currencyConversionList[] = array(
					'CurrencyConversion' => array(
						'ExchangeRateCode' => $currencyCode,
						'NumberOfDecimalPlaces' => $numberOfDecimalPlaces,
						'ExchangeRate' => floor($this->getExchangeRate($row) * pow(10, $numberOfDecimalPlaces)),
					),
				);
			}
		}
		
		return $currencyConversionList;
	}
	
	protected function getCurrencyParams($row) {
		$currency = $this->getCurrency($row);
		if (!isset($this->currencyCodes[$currency])) {
			$fromCurrency = 'SDR';
			$urt = $row['urt']->sec;
			$exchangeRate = Billrun_Utils_Currency_Converter::convertCurrency(1, $fromCurrency, $currency, $urt);
			$this->currencyCodes[$currency] = array(
				'code' => count($this->currencyCodes),
				'exchange_rate' => $exchangeRate,
			);
		}
		return $this->currencyCodes[$currency];
	}
	
	protected function getExchangeRate($row) {
		return Billrun_Util::getIn($this->getCurrencyParams($row), 'exchange_rate', 1);
	}
	
	protected function getCurrencyCode($row) {
		return Billrun_Util::getIn($this->getCurrencyParams($row), 'code', '');
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
			$totalCharge += isset($row['apr']) ? floatval($row['apr']) * pow(10, $this->numOfDecPlaces) : 0;
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
					'LocalTimeStamp' => gmdate($dateFormat, $earliestUrt),
					'UtcTimeOffset' => $this->timeZoneOffset,
				),
				'LatestCallTimeStamp' => array(
					'LocalTimeStamp' => gmdate($dateFormat, $latestUrt),
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
				$recEntityType = $this->getConfig('rec_entity_type.GGSN');
				$recEntityId = Billrun_Util::getIn($row, 'ggsn_address', '');
				$recEntityCode = $this->getRecEntityCodeByRecEntityId($recEntityId);
				break;
			
			case self::$LINE_TYPE_CALL:
			case self::$LINE_TYPE_INCOMING_CALL:
			case self::$LINE_TYPE_SMS:
			case self::$LINE_TYPE_INCOMING_SMS:
				$recEntityType = $this->getConfig('rec_entity_type.MSC');
				$recEntityId = Billrun_Util::getIn($row, 'exchange_id', '');
				$recEntityCode = $this->getRecEntityCodeByRecEntityId($recEntityId);
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
	
	protected function getRecEntityCodeByRecEntityId($recEntityId) {
		if (!isset($this->recEntities[$recEntityId])) {
			$this->recEntities[$recEntityId] = count($this->recEntities);
		}
		return $this->recEntities[$recEntityId];
	}


	protected function getRecEntityCode($row) {
		return $this->getRecEntityInformation($row)['RecEntityCode'];
	}
	
	protected function getTeleServiceCode($row) {
		switch ($this->getLineType($row)) {
				
			case self::$LINE_TYPE_CALL:
			case self::$LINE_TYPE_INCOMING_CALL:
				return $this->getConfig('tele_service_codes.telephony', '');
				
			case self::$LINE_TYPE_SMS:
				return $this->getConfig('tele_service_codes.short_message_MO_PP', '');

			case self::$LINE_TYPE_INCOMING_SMS:
				return $this->getConfig('tele_service_codes.short_message_MT_PP', '');
			
			case self::$LINE_TYPE_DATA:	
			default:
				return '';
		}
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
			case self::$LINE_TYPE_INCOMING_SMS:
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
			case self::$LINE_TYPE_INCOMING_SMS:
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
					'ExchangeRateCode' => $this->getCurrencyCode($row),
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
									'LocalTimeStamp' => $this->getCallEventStartTimeStamp($row),
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
		$decimalPlaces = intval($this->getConfig('header.currency_conversion.num_of_decimal_places'));
		$sdrPrice = $price / $this->getExchangeRate($row);
		return $sdrPrice * pow(10, $decimalPlaces);
	}
	
	protected function getTotalCallEventDuration($row) {
		switch ($this->getLineType($row)) {
				
			case self::$LINE_TYPE_CALL:
			case self::$LINE_TYPE_INCOMING_CALL:
				return Billrun_Util::getIn($row, 'duration', 0);
				
			case self::$LINE_TYPE_SMS:
			case self::$LINE_TYPE_INCOMING_SMS:
				return 0;
			
			case self::$LINE_TYPE_DATA:	
			default:
				return Billrun_Util::getIn($row, 'duration', 0);
		}
	}
	
	protected function getCurrency($row) {
		$defaultCurrency = 'EUR';
		$currentDate = new MongoDate();
		if (!isset($row['arate'])) {
			return $defaultCurrency;
		}
		
		$rate = Billrun_DBRef::getEntity($row['arate']);
		if (!$rate || $rate->isEmpty()) {
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
			case 'sgsn':
				return self::$LINE_TYPE_DATA;
			case 'nsn':
				if ($row['usaget'] == 'incoming_call') {
					return self::$LINE_TYPE_INCOMING_CALL;
				}
				if ($row['usaget'] == 'sms') {
					return Billrun_Util::getIn($row, 'record_type', '08') == '08'
						? self::$LINE_TYPE_SMS
						: self::$LINE_TYPE_INCOMING_SMS;
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
		return gmdate($dateFormat, $datetime);
	}

}
