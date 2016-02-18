<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 S.D.O.C. LTD. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Billing Importer For The PP Threshold of a customer Plan.
 * Imports from Csv to mongo collection plans
 *
 * @package  Billrun
 * @since    4.0
 * @author Tom Feigin
 */
class Billrun_Importer_PPThreshold extends Billrun_Importer_Csv {

	protected $fieldsColumns = null;
	
	protected $thresholdTable = array();
	protected $cos;
	protected $ppID;
	
	public function __construct($options) {
		parent::__construct($options);
		$this->fields = Billrun_Factory::config()->getConfigValue('importer.PPThreshold.fields', array());
	}
	
	protected function getCollectionName() {
		return 'plans';
	}

	
	protected function getCOS($rowData) {
		$field = $this->fields['COS']['i'];
		$this->cos = $rowData[$field];
		return null;
	}
	
	protected function getPPId($rowData) {
		$this->ppID =  $rowData[$this->fields['PP_Id']['i']];
		return null;
	}
	
	protected function getMax($rowData) {
		$this->thresholdTable[$this->cos][$this->ppID] = $rowData[$this->fields['Max']['i']];
		return null;
	}
	
	public function save() {
		try {
			$error = '';
			$updateOptions = array(
				'multi' => true,
				'upsert' => false
			);
			$collectionName = $this->getCollectionName();
			$collection = Billrun_Factory::db()->getCollection($collectionName);
			
			$updateQuery = array();
			$findQuery = array("type" => "customer");
			
			$count = 0;
			$success = true;
			// GO through the list of COS values.
			foreach ($this->thresholdTable as $COS => $PP) {
				$findQuery['name'] = $COS;
				$updateQuery = array('$set' => array("pp_threshold" => $PP));
				
				$res = $collection->update($findQuery, $updateQuery,$updateOptions);
				$success = $res['ok'];
				if(!$success) {
					break;
				}
				Billrun_Factory::log("Inserted: " . $COS . " => " . print_r($PP,1));
				$count += $res['n'];
			}
			
			Billrun_Factory::log($count . " entries was added to " . $this->collectionName . " collection", Zend_Log::INFO);
		} catch (\Exception $e) {
			$error = 'Failed storing in the DB got error : ' . $e->getCode() . ' : ' . $e->getMessage();
			Billrun_Factory::log($error, Zend_Log::ALERT);
			$success = false;
		}
		
		if (!$success) {
			Billrun_Factory::log('Entities:' . print_r($this->thresholdTable, 1), Zend_Log::ALERT);
			return false;
		}
		
		return true;
	}
}
