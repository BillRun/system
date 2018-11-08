<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2018 S.D.O.C. LTD. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Billing MABAL exporter for single TADIG (VPMN - Visited Public Mobile Network)
 *
 * @package  Billing
 * @since    2.8
 */
class Billrun_Exporter_Mabal_Tadig extends Billrun_Exporter_Csv {
	
	static protected $type = 'mabal';
	
	const CALL_EVENT_MOC = 'MOC';
	const CALL_EVENT_MTC = 'MTC';
	
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
		return array($this->mapFields($headersMapping));
	}

	/**
	 * see parent::getFooter()
	 */
	protected function getFooter() {
		$footerMapping = $this->getConfig('footer_mapping', array());
		return array($this->mapFields($footerMapping));
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
	 * gets total number of records in the file (including header and footer)
	 * 
	 * @return int
	 */
	protected function getTotalRecNo() {
		return count($this->rawRows) + 2;
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
		$suffix = $this->getConfig('file_name.suffix', 'csv');
		$fileType = $this->getConfig('file_name.file_type', '');
		$hpmnTadig = $this->getHpmnTadig();
		$vpmnTadig = $this->getVpmnTadig();
		$sequenceNum = $this->getSequenceNumber();
		$timestamp = $this->getFileAvailableTimeStamp();
		return "{$pref}_{$hpmnTadig}_{$vpmnTadig}_{$fileType}_{$sequenceNum}_{$timestamp}.$suffix";
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
	 * @return one of: MOC, MTC
	 * @todo currently, only supports MOC
	 */
	protected function getCallEvent($row) {
		switch ($row['type']) {
			case 'call':
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
	
	/**
	 * gets call type
	 * 
	 * @param array $row
	 * @param array $fieldMapping
	 * @return 1 - regular call, 11 - prepaid call
	 */
	protected function getCallType($row, $fieldMapping) {
		if (isset($row['connection_type']) && $row['connection_type'] == 'prepaid') {
			return $this->getConfig('call_type.prepaid', '');
		}
		return $this->getConfig('call_type.postpaid', '');
	}
	
	/**
	 * gets call start time
	 * 
	 * @param array $row
	 * @param array $fieldMapping
	 * @return string
	 */
	protected function getCallStartDt($row, $fieldMapping) {
		$callStart = str_replace('+', ' ', Billrun_Util::getIn($row, 'uf.egress_call_start', ''));
		return $this->formatDate($callStart);
	}
	
	/**
	 * gets call end time
	 * 
	 * @param array $row
	 * @param array $fieldMapping
	 * @return string
	 */
	protected function getCallEndDt($row, $fieldMapping) {
		$duration = $row['usagev'];
		$callEnd = strtotime("+{$duration} seconds", strtotime(str_replace('+', ' ', Billrun_Util::getIn($row, 'uf.egress_call_start', ''))));
		return $this->formatDate($callEnd);
	}
	
	/**
	 * gets collection indication
	 * 
	 * @param array $row
	 * @param array $fieldMapping
	 * @return C - by the supplier, O - by the operator
	 */
	protected function getCollectionInd($row, $fieldMapping) {
		return $this->getConfig('collection_ind.operator', '');
	}

}

