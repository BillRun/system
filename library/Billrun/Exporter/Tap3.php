<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2018 S.D.O.C. LTD. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Billing TAP3 exporter
 *
 * @package  Billing
 * @since    2.8
 */
class Billrun_Exporter_Tap3 extends Billrun_Exporter_Asn1 {

	static protected $type = 'tap3';
	
	protected $startTime = null;
	protected $timeZoneOffset = '';
	protected $timeZoneOffsetCode = '';
	protected $startTimeStamp = '';
	protected $numOfDecPlaces;
	
	public function __construct($options = array()) {
		parent::__construct($options);
		$this->startTime = time();
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
	 * gets call event details to get correct mapping according to usage type
	 * 
	 * @param array $row
	 * @return string one of: MobileOriginatedCall/MobileTerminatedCall/SupplServiceEvent/ServiceCentreUsage/GprsCall/ContentTransaction/LocationService/MessagingEvent/MobileSession
	 * @todo implement for all types
	 */
	protected function getCallEventDetail($row) {
		switch ($row['type']) {
			case 'ggsn':
				return 'GprsCall';
			default:
				return '';
		}
	}

	protected function getFileName() { // TODO: implement
		return '/home/yonatan/Downloads/TDINDATISRGT00003_4';
	}

	protected function getQuery() { // TODO: fix query
		return array(
			'type' => 'ggsn',
			'imsi' => ['$exists' => 1],
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
			$recEntityCode = 0; //TODO: get correct code
			$recEntityType = 4; //TODO: get correct type
			$recEntityId = '223.224.40.4'; //TODO: get correct ID
			$found = !empty(array_filter($ret, function($ele) use($recEntityCode, $recEntityType, $recEntityId) {
				return $ele['RecEntityInformation']['RecEntityCode'] === $recEntityCode &&
					$ele['RecEntityInformation']['RecEntityType'] === $recEntityType && 
					$ele['RecEntityInformation']['RecEntityId'] === $recEntityId;
			}));
			if (!$found) {
				$ret[] = array(
					'RecEntityInformation' => array(
							'RecEntityCode' => $recEntityCode,
							'RecEntityType' => $recEntityType,
							'RecEntityId' => $recEntityId,
						),
				);
			}
		}
		return $ret;
	}
	
	protected function getUtcTimeOffsetCode($row, $fieldMapping) {
		return $this->timeZoneOffsetCode;
	}
	
	protected function getRecEntityCodeList($row, $fieldMapping) {
		return array(
			array(
				'RecEntityCode' => 0, // TODO: get correct value from row
			),
			array(
				'RecEntityCode' => 1, // TODO: get correct value from row
			),
		);
	}
	
	protected function getChargeInformationList($row, $fieldMapping) {
		return array(
			array(
				'ChargeInformation' => array(
					'ChargedItem' => 'X', // TODO: get correct value from row
					'ExchangeRateCode' => 0, // TODO: get correct value from row
					'CallTypeGroup' => array(
						'CallTypeLevel1' => 10, // TODO: get correct value from row
						'CallTypeLevel2' => 0, // TODO: get correct value from row
						'CallTypeLevel3' => 0, // TODO: get correct value from row
					),
					'ChargeDetailList' => array(
						array(
							'ChargeDetail' => array(
								'ChargeType' => '00', // TODO: get correct value from row
								'Charge' => 23740, // TODO: get correct value from row
								'ChargeableUnits' => 1141572, // TODO: get correct value from row
								'ChargedUnits' => 1146880, // TODO: get correct value from row
								'ChargeDetailTimeStamp' => array(
									'LocalTimeStamp' => date($this->getConfig('datetime_format', 'YmdHis'), $row['urt']->sec), // TODO: get correct value from row
									'UtcTimeOffsetCode' => $this->timeZoneOffsetCode,
								),
							),
						),
					),
				),
			),
		);
	}

}
