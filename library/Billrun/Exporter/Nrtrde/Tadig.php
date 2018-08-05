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
	
	const CALL_EVENT_MOC = 'MOC';
	const CALL_EVENT_MTC = 'MTC';
	const CALL_EVENT_GPRS = 'GPRS';
	
	const SEQUENCE_NUM_INIT = 1;
	
	protected $vpmnTadig = '';
	protected $stamps = '';
	protected $lastLogSequenceNum = null;
	protected $sequenceNum = null;
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
	 * see parent::getHeader()
	 */
	protected function getHeader() {
		$headersMapping = $this->getConfig('header_mapping', array());
		return $this->mapFields($headersMapping);
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
		$logData['sequence_num'] = $this->getNextLogSequenceNumber();
		
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
	 * gets current sequence number for the file
	 * 
	 * @return string - number in the range of 00001-99999
	 */
	protected function getSequenceNumber() {
		if (is_null($this->sequenceNum)) {
			$nextSequenceNum = $this->getNextLogSequenceNumber();
			$this->sequenceNum = sprintf('%05d', $nextSequenceNum % 100000);
		}
		return $this->sequenceNum;
	}
	
	/**
	 * gets the next sequence number of the VPMN from log collection
	 */
	protected function getNextLogSequenceNumber() {
		if (is_null($this->lastLogSequenceNum)) {
			$query = array(
				'source' => 'export',
				'type' => static::$type,
				'tadig' => $this->getVpmnTadig(),
			);
			$sort = array(
				'export_time' => -1,
			);
			$lastSeq = $this->logCollection->query($query)->cursor()->sort($sort)->limit(1)->current()->get('sequence_num');
			if (is_null($lastSeq)) {
				$this->lastLogSequenceNum = self::SEQUENCE_NUM_INIT;
			} else {
				$this->lastLogSequenceNum = $lastSeq + 1;
			}
		}
		return $this->lastLogSequenceNum;
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
		$hpmnTadig = $this->getHpmnTadig();
		$vpmnTadig = $this->getVpmnTadig();
		$sequenceNum = $this->getSequenceNumber();
		return $pref . $hpmnTadig . $vpmnTadig . $sequenceNum . '.csv';
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
		switch ($row['type']) {
			case 'nsn':
			default:
				return self::CALL_EVENT_MOC;
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

}

