<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2018 S.D.O.C. LTD. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Billing NRTRDE exporter for single TADIG (VPMN - Visited Public Mobile Network)
 *
 * @package  Billing
 * @since    2.8
 */
class Billrun_Exporter_Nrtrde_Tadig extends Billrun_Exporter_Csv {
	
	static protected $type = 'nrtrde';
	
	static protected $LINE_TYPE_DATA = 'data';
	static protected $LINE_TYPE_CALL = 'call';
	static protected $LINE_TYPE_INCOMING_CALL = 'incoming_call';
	static protected $LINE_TYPE_SMS = 'sms';
	static protected $LINE_TYPE_INCOMING_SMS = 'incoming_sms';
	
	const CALL_EVENT_MOC = 'MOC';
	const CALL_EVENT_MTC = 'MTC';
	const CALL_EVENT_GPRS = 'GPRS';
	
	protected $vpmnTadig = '';
	protected $stamps = '';
	protected $time = null;
	protected $logStamp = null;


	public function __construct($options = array()) {
		$this->vpmnTadig = $options['tadig'];
		$this->stamps = isset($options['stamps']) ? $options['stamps'] : array();
		$this->time = isset($options['time']) ? $options['time'] : time();
		
		parent::__construct($options);
	}
	
	/**
	 * see parent::getFieldsMapping
	 */
	protected function getFieldsMapping($row) {
		$callEvent = $this->getCallEvent($row);
		return $this->getConfig(array('fields_mapping', strtolower($callEvent)), array());
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
	 * see parent::getHeader()
	 */
	protected function getHeader() {
		$headersMapping = $this->getConfig('header_mapping', array());
		return [$this->mapFields($headersMapping)];
	}
	
	/**
	 * see parent::beforeExport
	 * NRTRDE should handle locking
	 */
	function beforeExport() {
		$this->createLogDB($this->getLogStamp());
	}
	
	/**
	 * see parent::afterExport
	 * NRTRDE should handle locking
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
				'sequence_num' => $this->getNextLogSequenceNumber(),
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
	 * gets file available timestamp
	 * 
	 * @return string
	 */
	protected function getFileAvailableTimeStamp() {
		return $this->formatDate($this->time);
	}
	
	/**
	 * gets UTC time offset
	 * 
	 * @return string
	 */
	protected function getUtcTimeOffset() {
		return date('O');
	}
	
	/**
	 * get number of call events in file
	 * 
	 * @return int
	 */
	protected function getCallEventsCount() {
		return count($this->rowsToExport);
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
	 * get number of records in the FDR, including header and trailer
	 */
	protected function getNumberOfRecords() {
		return count($this->rowsToExport) + 2;
	}

	/**
	 * see parent::getFileName
	 */
	protected function getFileName() {
		$pref = $this->getConfig('file_name.prefix', '');
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
	 * gets call event of the row
	 * 
	 * @param array $row
	 * @return one of: MOC, MTC, GPRS
	 * @todo currently, only supports MOC
	 */
	protected function getCallEvent($row) {
		switch ($this->getLineType($row)) {
			case self::$LINE_TYPE_DATA:
				return self::CALL_EVENT_GPRS;
			case self::$LINE_TYPE_CALL:
			case self::$LINE_TYPE_SMS:
				return self::CALL_EVENT_MOC;
			case self::$LINE_TYPE_INCOMING_CALL:
			case self::$LINE_TYPE_INCOMING_SMS:
				return self::CALL_EVENT_MTC;
			default:
				return '';
		}
	}
	
	protected function getLineType($row) {
		switch ($row['type']) {
			case 'sgsn':
				return self::$LINE_TYPE_DATA;
			case 'nsn':
				switch ($row['usaget']) {
					case 'incoming_call':
						return self::$LINE_TYPE_INCOMING_CALL;
					case 'incoming_sms':
						return self::$LINE_TYPE_INCOMING_SMS;
					case 'sms':
						return self::$LINE_TYPE_SMS;
					case 'call':
					default:
						return self::$LINE_TYPE_CALL;
				}

			default:
				return false;
		}
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
	
	protected function getCauseForTermination($row) {
		return intval($row['cause_for_termination']);
	}
	
	protected function getCallReference($row) {
		if (empty($row['call_reference'])) {
			return '';
		}
		return hexdec($row['call_reference']);
	}
	
	protected function getCallEventDuration($row) {
		switch ($this->getLineType($row)) {
			case self::$LINE_TYPE_DATA:
				return intval($row['duration']);
			case self::$LINE_TYPE_CALL:
			case self::$LINE_TYPE_INCOMING_CALL:
				return intval($row['usagev']);
			case self::$LINE_TYPE_SMS:
			case self::$LINE_TYPE_INCOMING_SMS:
				return 0;
			default:
				return 0;
		}
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

}

