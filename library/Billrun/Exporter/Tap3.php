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
	protected $startTimeStamp = '';
	protected $numOfDecPlaces;
	
	public function __construct($options = array()) {
		parent::__construct($options);
		$this->startTime = time();
		$this->timeZoneOffset = date($this->getConfig('datetime_offset_format', 'O'), $this->startTime);
		$this->startTimeStamp = date($this->getConfig('datetime_format', 'YmdHis'), $this->startTime);
		$this->numOfDecPlaces = intval($this->getConfig('header.num_of_decimal_places'));
	}

	protected function getFileName() {
		return '/home/yonatan/Downloads/TDINDATISRGT00003_4';
	}

	protected function getQuery() {
		return array();
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
							'UtcTimeOffsetCode' => 0,
							'UtcTimeOffset' => $this->timeZoneOffset,
						),
					),
				),
				'RecEntityInfoList' => array(
					array(
						'RecEntityInformation' => array(
							'RecEntityCode' => 0,
							'RecEntityType' => 4,
							'RecEntityId' => '223.224.40.4',
						),
					),
					array(
						'RecEntityInformation' => array(
							'RecEntityCode' => 1,
							'RecEntityType' => 3,
							'RecEntityId' => '37.26.145.17',
						),
					),
					array(
						'RecEntityInformation' => array(
							'RecEntityCode' => 2,
							'RecEntityType' => 3,
							'RecEntityId' => '37.26.144.17',
						),
					),
					array(
						'RecEntityInformation' => array(
							'RecEntityCode' => 3,
							'RecEntityType' => 3,
							'RecEntityId' => '37.26.145.18',
						),
					),
				),
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
		foreach ($this->rawRows as $row) {
			$totalCharge += isset($row['aprice']) ? floatval($row['aprice']) * pow(10, $this->numOfDecPlaces) : 0;
			$totalTax += isset($row['tax']) ? floatval($row['tax']) * pow(10, $this->numOfDecPlaces) : 0;
		}
		return array(
			'AuditControlInfo' => array(
				'EarliestCallTimeStamp' => array(
					'LocalTimeStamp' => '20160916170645',
					'UtcTimeOffset' => $this->timeZoneOffset,
				),
				'LatestCallTimeStamp' => array(
					'LocalTimeStamp' => '20160916180052',
					'UtcTimeOffset' => $this->timeZoneOffset,
				),
				'TotalCharge' => $totalCharge,
				'TotalTaxValue' => $totalTax,
				'TotalDiscountValue' => $totalDiscount,
				'CallEventDetailsCount' => count($this->rowsToExport),
			),
		);
	}
	
	protected function loadRows() {// TODO: REMOVE!
		$json = '{"GprsCall" : {
	"GprsBasicCallInformation" : {
		"GprsChargeableSubscriber" : {
			"ChargeableSubscriber" : {
				"SimChargeableSubscriber" : {
					"Imsi" : "BP"
				}
			},
			"PdpAddress" : "10.138.11.82"
		},
		"GprsDestination" : {
			"AccessPointNameNI" : "internet.golantelecom.net.il"
		},
		"CallEventStartTimeStamp" : {
			"LocalTimeStamp" : "20160916175521",
			"UtcTimeOffsetCode" : "00"
		},
		"TotalCallEventDuration" : 93,
		"ChargingId" : 1274051057
	},
	"GprsLocationInformation" : {
		"GprsNetworkLocation" : {
			"RecEntityCodeList" : [
				{"RecEntityCode" : "00"},
				{"RecEntityCode" : "01"}
			],
			"LocationArea" : 113,
			"CellId" : 10657
		}
	},
	"GprsServiceUsed" : {
		"DataVolumeIncoming" : 1004471,
		"DataVolumeOutgoing" : 137101,
		"ChargeInformationList" : [{
			"ChargeInformation" : {
				"ChargedItem" : "X",
				"ExchangeRateCode" : "00",
				"CallTypeGroup" : {
					"CallTypeLevel1" : 10,
					"CallTypeLevel2" : 0,
					"CallTypeLevel3" : 0
				},
				"ChargeDetailList" : [{
					"ChargeDetail" : {
						"ChargeType" : "00",
						"Charge" : 23740,
						"ChargeableUnits" : 1141572,
						"ChargedUnits" : 1146880,
						"ChargeDetailTimeStamp" : {
							"LocalTimeStamp" : "20160916175521",
							"UtcTimeOffsetCode" : "00"
						}
					}
				}]
			}
		}]
	}
}
}
';

		$line = json_decode($json, JSON_OBJECT_AS_ARRAY);
		$line2 = $line;
		$this->rowsToExport[] = $line;
		$this->rowsToExport[] = $line2;
		
		// from here this is the real function
//		parent::loadRows();
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

}
