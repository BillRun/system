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
				'Sender' => 'INDAT',
				'Recipient' => 'ISRGT',
				'FileSequenceNumber' => '00003',
				'FileCreationTimeStamp' => array(
					'LocalTimeStamp' => '20160917193749',
					'UtcTimeOffset' => '+0530',
				),
				'TransferCutOffTimeStamp' => array(
					'LocalTimeStamp' => '20160917193749',
					'UtcTimeOffset' => '+0530',
				),
				'FileAvailableTimeStamp' => array(
					'LocalTimeStamp' => '20160917193749',
					'UtcTimeOffset' => '+0530',
				),
				'SpecificationVersionNumber' => 3,
				'ReleaseVersionNumber' => 12,
				'FileTypeIndicator' => 'T',
			),
			'AccountingInfo' => array(
				'LocalCurrency' => 'INR',
				'TapCurrency' => 'SDR',
				'CurrencyConversionList' => array(
					array(
						'CurrencyConversion' => array(
							'ExchangeRateCode' => 0,
							'NumberOfDecimalPlaces' => 4,
							'ExchangeRate' => 943574,
						),
					),
				),
				'TapDecimalPlaces' => 5,
			),
			'NetworkInfo' => array(
				'UtcTimeOffsetInfoList' => array(
					array(
						'UtcTimeOffsetInfo' => array(
							'UtcTimeOffsetCode' => 0,
							'UtcTimeOffset' => '+0530',
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
		return array(
			'AuditControlInfo' => array(
				'EarliestCallTimeStamp' => array(
					'LocalTimeStamp' => '20160916170645',
					'UtcTimeOffset' => '+0530',
				),
				'LatestCallTimeStamp' => array(
					'LocalTimeStamp' => '20160916180052',
					'UtcTimeOffset' => '+0530',
				),
				'TotalCharge' => 244603,
				'TotalTaxValue' => 0,
				'TotalDiscountValue' => 0,
				'CallEventDetailsCount' => 7,
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
