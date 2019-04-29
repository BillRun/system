<?php

/**
 * @category   Billrun
 * @package    Processor
 * @subpackage Nrtrde
 * @copyright  Copyright (C) 2012-2013 S.D.O.C. LTD. All rights reserved.
 * @license    GNU General Public License version 2 or later
 */

/**
 * Billing processor for NRTRDE
 * see also:
 * http://www.tapeditor.com/OnlineDemo/NRTRDE-ASCII-format.html
 *
 * @package    Billing
 * @subpackage Processor
 * @since      1.0
 */
class Billrun_Processor_Nrtrde extends Billrun_Processor_Base_Separator {

	protected $standatizeFieldsMapping = array(
		'callingNumber' => 'calling_number',
		'connectedNumber' => 'called_number',
	);
	
	/**
	 * the type of the object
	 *
	 * @var string
	 */
	static protected $type = 'nrtrde';

	public function __construct($options) {
		parent::__construct($options);

		$this->header_structure = array(
			'record_type',
			'specificationVersionNumber',
			'releaseVersionNumber',
			'sender',
			'recipient',
			'sequenceNumber',
			'fileAvailableTimeStamp',
			'utcTimeOffset',
			'callEventsCount',
		);

		$this->moc_structure = array(
			'record_type',
			'imsi',
			'imei',
			'callEventStartTimeStamp',
			'utcTimeOffset',
			'callEventDuration',
			'causeForTermination',
			'teleServiceCode',
			'bearerServiceCode',
			'supplementaryServiceCode',
			'dialledDigits',
			'connectedNumber',
			'thirdPartyNumber',
			'recEntityId',
			'callReference',
			'chargeAmount',
		);

		$this->mtc_structure = array(
			'record_type',
			'imsi',
			'imei',
			'callEventStartTimeStamp',
			'utcTimeOffset',
			'callEventDuration',
			'causeForTermination',
			'teleServiceCode',
			'bearerServiceCode',
			'callingNumber',
			'recEntityId',
			'callReference',
			'chargeAmount',
		);
	}

	/**
	 * method to get available header record type strings
	 * 
	 * @return array all strings available as header
	 */
	protected function getHeaderOptions() {
		return array('10', 'NRTRDE');
	}

	/**
	 * method to get available data record type strings
	 * 
	 * @return array all strings available as header
	 */
	protected function getDataOptions() {
		return array('20', 'MOC', '30', 'MTC');
	}

	/**
	 * method to parse data
	 * 
	 * @param array $line data line
	 * 
	 * @return array the data array
	 */
	protected function parseData($line) {
		if (!isset($this->data['header'])) {
			Billrun_Factory::log()->log("No header found", Zend_Log::ERR);
			return false;
		}

		$data_type = strtolower($this->getLineType($line, $this->parser->getSeparator())); // can be moc or mtc
		$this->parser->setStructure($this->{$data_type . "_structure"}); // for the next iteration
		$this->parser->setLine($line);
		Billrun_Factory::dispatcher()->trigger('beforeDataParsing', array(&$line, $this));
		$row = $this->parser->parse();
		
		foreach ($this->standatizeFieldsMapping as $key => $value) {
			if(isset($row[$key])) {
				$row[$value] = $row[$key];
			}
		}
		//Remove leading zeros from the called/calling numbers
		if(!empty($row['calling_number'])) {
			$row['calling_number'] = preg_replace('/^0+/','',$row['calling_number']);
		}
		if(!empty($row['called_number'])) {
			$row['called_number'] = preg_replace('/^0+/','',$row['called_number']);
		}

		
		$row['source'] = static::$type;
		$row['type'] = self::$type;
		$row['sender'] = $this->data['header']['sender'];
		$row['header_stamp'] = $this->data['header']['stamp'];	
		$row['log_stamp'] = $this->getFileStamp();
		$row['file'] = basename($this->filePath);
		$row['process_time'] = date(self::base_dateformat);
		$row['urt'] = new MongoDate(Billrun_Util::dateTimeConvertShortToIso($row['callEventStartTimeStamp'], $row['utcTimeOffset']));
		$row['usaget'] = $this->getLineUsageType($row);
		settype($row['callEventDuration'], 'integer');
		$row['usagev'] = $this->getLineVolume($row,$row['usaget']);
		
		Billrun_Factory::dispatcher()->trigger('afterDataParsing', array(&$row, $this));
		if ($row['usaget'] == 'call' || $row['usaget'] == 'incoming_call') { // filter usaget sms because that sms transferred from billing.
			$this->data['data'][] = $row;
		}
		return $row;
	}
	
	protected function getLineUsageType($row) {
		if($row['callEventDuration'] > 0) {
			if($row['record_type'] == "MTC" ) {
				return "incoming_call";
			} else if($row['record_type'] == "MOC") {
				return "call";
			}
		}
		else if($row['callEventDuration'] == 0) {
			if($row['record_type'] == "MTC" ) {
				return "incoming_sms";
			} else if($row['record_type'] == "MOC") {
				return "sms";
			}
		}
	}
	
	protected function getLineVolume($row, $usage_type) {
		if($usage_type == 'sms' || $usage_type == 'incoming_sms') {
			return 1;
		} else if($usage_type == 'call' || $usage_type == 'incoming_call') {
			return $row['callEventDuration'];
		}
	}

}
