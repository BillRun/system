<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Billing Importer Csv importer.
 * Imports from Csv to mongo collection
 *
 * @package  Billrun
 * @since    4.0
 */
abstract class Billrun_Importer_Csv extends Billrun_Importer_Abstract {

	protected $collectionName = null;
	protected $fieldToImport = null;
	protected $dataToImport = null;
	protected $importerName = null;
	protected $fields = null;
	protected $handle = null;
	protected $delimiter = null;
	protected $limit = null;

	abstract protected function getCollectionName();

	public function __construct($options) {
		parent::__construct($options);
		Billrun_Factory::config()->addConfig(APPLICATION_PATH . "/conf/importers/conf.ini");
	}

	public function import() {
		Billrun_Factory::log("Starting to import CSV", Zend_Log::INFO);
		$path = $this->getPath();
		$this->importerName = $this->getImporterName();

		if (!file_exists($path) || is_dir($path)) {
			Billrun_Factory::log("File does not exist or path is a directory", Zend_Log::ALERT);
			return FALSE;
		}

		if (($this->handle = fopen($path, "r")) !== FALSE) {
			$this->delimiter = $this->getDelimiter();
			$this->limit = $this->getLimit();
			$rowsIndexesToSkip = $this->getRowsIndexesToSkip();
			$this->dataToImport = array();
			$this->fields = $this->getImporterFields();
			$rowIndex = 0;
			while (($data = fgetcsv($this->handle, $this->limit, $this->delimiter)) !== FALSE) {
				if (in_array($rowIndex, $rowsIndexesToSkip)) {
					Billrun_Factory::log("Row " . $rowIndex . " skipped", Zend_Log::INFO);
					$rowIndex++;
					continue;
				}
				Billrun_Factory::log("Processed row " . $rowIndex, Zend_Log::INFO);
				$this->dataToImport[] = $this->getDataToSave($data);
				$rowIndex++;
			}
		}

		$this->save();

		Billrun_Factory::log("Done importing CSV", Zend_Log::INFO);
	}

	protected function getImporterName() {
		return str_replace('Billrun_Importer_', '', get_called_class());
	}

	public function save() {
		try {
			$bulkOptions = array(
				'ordered' => false,
				'continueOnError' => true,
				'socketTimeoutMS' => 300000,
				'wTimeoutMS' => 300000,
			);
			$collectionName = $this->getCollectionName();
			$collection = Billrun_Factory::db()->getCollection($collectionName);
			$res = $collection->batchInsert($this->dataToImport, $bulkOptions);
			$count = isset($res['nInserted']) ? $res['nInserted'] : 0;
		} catch (\Exception $e) {
			if (!is_a($e, 'MongoWriteConcernException')) {
				Billrun_Factory::log('Failed storing in the DB got error : ' . $e->getCode() . ' : ' . $e->getMessage(), Zend_Log::ALERT);
				return false;
			}
			$error_doc = $e->getDocument();
			$errors = array_column($error_doc['writeErrors'], 'errmsg');
			array_map(array('Billrun_Factory', 'log'), $errors, array_fill(0, count($errors), Zend_Log::NOTICE));
			if (isset($error_doc['nInserted'])) {
				$count = $error_doc['nInserted'];
			} else {
				$count = 0;
			}
		}

		Billrun_Factory::log($count . " entries was added to " . $this->collectionName . " collection", Zend_Log::INFO);

		return true;
	}

	public function getDelimiter() {
		$delimiter = Billrun_Factory::config()->getConfigValue('importer.' . $this->importerName . '.delimiter', false);
		if ($delimiter === false) {
			$delimiter = Billrun_Factory::config()->getConfigValue('importer.basic.delimiter', ',');
		}
		return $delimiter;
	}

	public function getLimit() {
		$limit = Billrun_Factory::config()->getConfigValue('importer.' . $this->importerName . '.limit', false);
		if ($limit === false) {
			$limit = Billrun_Factory::config()->getConfigValue('importer.basic.limit', 0);
		}
		return intval($limit);
	}

	public function getRowsIndexesToSkip() {
		return array(0); //TODO: move to config
	}

	/**
	 * Gets the fields to save in the document
	 * 
	 * @return type
	 */
	protected function getImporterFields() {
		if (empty($this->fieldToImport)) {
			$this->fieldToImport = array_merge(
					Billrun_Factory::config()->getConfigValue('importer.basic.fields', array()), Billrun_Factory::config()->getConfigValue('importer.' . $this->importerName . '.fields', array()));
		}

		return $this->fieldToImport;
	}

	/**
	 * Gets the data to save
	 * 
	 * @return array
	 */
	protected function getDataToSave($rowData) {
		$ret = array();
		foreach ($this->fields as $field => $rowFieldIndex) {
			if (is_array($rowFieldIndex)) {  // The value needs to be calculated from an inner function
				$ret[$field] = (isset($rowFieldIndex['classMethod']) ? call_user_method($rowFieldIndex['classMethod'], $this, $rowData) : '');
			} else if (is_numeric($rowFieldIndex)) { // This is an index in the row's data
				$ret[$field] = (isset($rowData[$rowFieldIndex]) ? $rowData[$rowFieldIndex] : '');
			} else { // Hard-coded value
				$ret[$field] = $rowFieldIndex;
			}

			if (is_null($ret[$field])) {
				unset($ret[$field]);
			}
		}
		return $ret;
	}

}
