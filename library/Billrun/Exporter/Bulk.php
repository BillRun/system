<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2018 S.D.O.C. LTD. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Billing abstract exporter bulk (multiple rows at once)
 *
 * @package  Billing
 * @since    2.8
 */
abstract class Billrun_Exporter_Bulk extends Billrun_Exporter {
	
	static protected $type = 'bulk';
	
	protected $exportStamp = '';
	protected $collection = null;
	protected $query = array();
	protected $rowsToExport = array();
	protected $rawRows = array();
	
	public function __construct($options = array()) {
		parent::__construct($options);
		$this->setExportStamp();
		$collectionName = $this->getCollectionName();
		$this->collection = Billrun_Factory::db()->{"{$collectionName}Collection"}();
		$this->query = $this->getQuery();
	}
	
	/**
	 * set stamp for the current run of the exporter
	 */
	protected function setExportStamp() {
		$this->exportStamp = uniqid();
	}

	/**
	 * gets DB collection name
	 */
	protected function getCollectionName() {
		return $this->getConfig('collection_name');
	}
	
	/**
	 * get query to load data from the DB
	 */
	abstract protected function getQuery();

	/**
	 * get the header data (first line in file)
	 * 
	 * @return array
	 */
	protected function getHeader() {
		return array();
	}

	/**
	 * get the footer data (last line in file)
	 * 
	 * @return array
	 */
	protected function getFooter() {
		return array();
	}
	
	/**
	 * get rows to be exported
	 * 
	 * @return array
	 */
	protected function loadRows() {
		$rows = $this->collection->query($this->query)->cursor();
		foreach ($rows as $row) {
			$rawRow = $row->getRawData();
			$this->rawRows[] = $rawRow;
			$this->rowsToExport[] = $this->getRecordData($rawRow);
		}
	}
	
	/**
	 * mark the lines which are about to be exported
	 */
	function beforeExport() {
		$this->query['export_start'] = array(
			'$exists' => false,
		);
		$this->query['export_stamp'] = array(
			'$exists' => false,
		);
		$update = array(
			'$set' => array(
				'export_start' => new MongoDate(),
				'export_stamp' => $this->exportStamp,
			),
		);
		$options = array(
			'multiple' => true,
		);
		
		$this->collection->update($this->query, $update, $options);
		unset($this->query['export_start']);
		$this->query['export_stamp'] = $this->exportStamp;
	}
	
	/**
	 * mark the lines as exported
	 */
	function afterExport() {
		$stamps = array();
		foreach ($this->rowsToExport as $row) {
			$stamps[] = $row['stamp'];
		}
		$query = array(
			'stamp' => array(
				'$in' => $stamps,
			),
		);
		$update = array(
			'$set' => array(
				'exported' => new MongoDate(),
			),
		);
		$options = array(
			'multiple' => true,
		);
		
		$this->collection->update($query, $update, $options);
	}

	/**
	 * get entire file data to be exported
	 * 
	 * @return array
	 */
	protected function getDataToExport() {
		$this->loadRows();
		$header = $this->getHeader();
		$footer = $this->getFooter();
		$dataToExport = $this->rowsToExport;
		if (!empty($header)) {
			$dataToExport = array_merge([$header], $dataToExport);
		}
		if (!empty($footer)) {
			$dataToExport = array_merge($dataToExport, [$footer]);
		}
		
		return $dataToExport;
	}
	
}

