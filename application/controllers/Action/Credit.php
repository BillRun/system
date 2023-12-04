<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */
require_once APPLICATION_PATH . '/application/controllers/Action/Api.php';

/**
 * Credit action class
 *
 * @package  Action
 * @since    0.5
 */
class CreditAction extends ApiAction {
	use Billrun_Traits_Api_UserPermissions;
	
	const INSTALLMENTS_PRECISION = 2;
	
	protected $request = null;
	protected $events = [];
	protected $status = 1;
	protected $desc = 'success';
	
	/**
	 * method to execute the refund
	 * it's called automatically by the api main controller
	 */
	public function execute() {
		$this->allowed();
		$request = $this->getRequest();
		try {
			switch ($request->get('action')) {
				case 'prepone' :
					$response = $this->preponeCreditInstallments($request);
					break;
				default :
					Billrun_Factory::log("Execute credit", Zend_Log::INFO);
					$this->request = $this->getRequest()->getRequest(); // supports GET / POST requests;
					$this->setEventsData();
					$this->process();
					return $this->response();
			}
			if ($response !== FALSE) {
				$this->getController()->setOutput(array(array(
						'status' => 1,
						'desc' => 'success',
						'input' => $request->getPost(),
						'details' => $response,
				)));
			}
		} catch (Exception $ex) {
			$this->setError($ex->getMessage(), $request->getPost());
			return;
		}
	}
	
	protected function preponeCreditInstallments($request){
		$sid = $request->get('sid');
		$aid = $request->get('aid');
		if(!is_numeric($sid) || !is_numeric($aid)){
			$this->setError('Illegal sid/aid', $request->getPost());
			return FALSE;
		}		
		$accountArray = [intval($aid) => [intval($sid)]];
		Billrun_Aggregator_Customer::preponeInstallments($accountArray);
	}
	
	protected function setEventsData() {
		$requests = [];
                $reportResult = [];
                $this->originRequest = $this->request;
                if(isset($this->request['suggestion_stamp'])){
                    $requests = Billrun_Compute_Suggestions::getCreditRequests($this->request['suggestion_stamp']);          
                }
                if(empty($requests)){
                    $requests[] = $this->request;
                    $singleEvent = true; 
                }
                foreach ($requests as $request){
                    try {
                        $this->request = $request;
                        $basicEvent = $this->setEventData();
                        $this->events[] = $basicEvent;

                        if ($this->hasInstallments()) {
                                $this->setInstallmentsData();
                        }
                        $reportResult[] = "Succeeded credit request : " .  json_encode($this->request);
                    } catch (Exception $ex) {
                        if($singleEvent){// single event
                            throw $ex;
                        }else{// multiple events
                            $this->status = 2;
                            $message = "Failed credit request: " . json_encode($this->request) .", " . $ex->getMessage();
                            $reportResult[] = $message;
                            Billrun_Factory::log($message . ", origin request: " . json_encode($this->originRequest), Zend_Log::NOTICE);
                        }
                    }                   
                }
                if(!$singleEvent){
                    $this->desc = $reportResult;
                }
	}
	
	protected function setEventData() {
		$event = $this->parse($this->request);
		$event['source'] = 'credit';
		$event['rand'] = rand(1, 1000000);
		if ($this->hasInstallments()) {
			$event['installment_no'] = 1;
			$event['invoice_label'] = $event['label'] ?: '';
		}
		$event['stamp'] = Billrun_Util::generateArrayStamp($event);
		return $event;
	}
	
	protected function hasInstallments() {
		return isset($this->request['installments']) && is_numeric($this->request['installments']);
	}
	
	protected function setInstallmentsData() {
		$firstInstallment = &$this->events[0];
		
		if (!isset($firstInstallment['aprice'])) {
			$this->setError('Installments can only be applied on credit by price');
		}
		
		if (!isset($firstInstallment['installments'])) {
			$this->setError('Missing field: number of installments');
		}
		
		$numOfInstallments = $firstInstallment['installments'];
		if ($numOfInstallments <= 0) {
			$this->setError('Number of installments must be a positive number');
		}
		
		if ($numOfInstallments == 1) {
			return;
		}
		
		
		$totalPrice = $firstInstallment['aprice'];
		$installmentPrice = $totalPrice / $numOfInstallments;
		$billrunKey = Billrun_Billingcycle::getBillrunKeyByTimestamp($firstInstallment['credit_time']);
		
		// handle first installment
		$firstInstallment['aprice'] = $installmentPrice;
		unset($firstInstallment['stamp']);
		$firstInstallment['stamp'] = Billrun_Util::generateArrayStamp($firstInstallment); // update stamp because data was changed
		$firstInstallment['first_installment'] = $firstInstallment['stamp'];
		// handle other installments
		for ($i = 2; $i <= $numOfInstallments; $i++) {
			$billrunKey = Billrun_Billingcycle::getFollowingBillrunKey($billrunKey);
			$installmentData = $firstInstallment;
			$installmentData['installment_no'] = $i;
			$installmentData['aprice'] = $installmentPrice;
			$installmentData['first_installment'] = $firstInstallment['stamp'];
			$installmentData['credit_time'] = Billrun_Billingcycle::getStartTime($billrunKey) + 1;
			$installmentData['rand'] = rand(1, 1000000);
			unset($installmentData['stamp']);
			$installmentData['stamp'] = Billrun_Util::generateArrayStamp($installmentData);
			
			$this->events[] = $installmentData;
		}
	}
	
	/**
	 * Runs Billrun process
	 * 
	 * @return type Data generated by process
	 */
	protected function process() {
		Billrun_Factory::log("Process of credit starting", Zend_Log::INFO);
		$options = array(
			'type' => 'Credit',
			'parser' => 'none',
		);
		$processor = Billrun_Processor::getInstance($options);
		
		foreach ($this->events as $event) {
			$processor->addDataRow($event);
		}
		
		if ($processor->process() === false) {
			$this->status = 0;
			$this->desc = 'Processor error';
			$this->handleProcessError();
		}
		Billrun_Factory::log("Process of credit ended", Zend_Log::INFO);
//		return current($processor->getAllLines());
	}
	
	protected function parse($credit_row) {
		$ret = $this->validateFields($credit_row);
		$ret['skip_calc'] = $this->getSkipCalcs($ret);
		$ret['process_time'] = new Mongodloid_Date();
		$ret['usaget'] = $this->getCreditUsaget($ret);
		$rate = Billrun_Rates_Util::getRateByName($credit_row['rate']);
		if ($rate->isEmpty()) {
			throw new Exception("Rate doesn't exist");
		}
		$ret['credit'] = array(
			'usagev' => $ret['usagev'],
			'credit_by' => 'rate',
			'rate' => $ret['rate'],
			'usaget' => $this->getUsageTypeFromRate($rate)
		);
		if ($this->isCreditByPrice($ret)) {
			$this->parseCreditByPrice($ret);
		} else {
			$this->parseCreditByUsagev($ret);
		}
                if(isset($credit_row['recalculation_type'])){
                    $grouping_keys = Billrun_Compute_Suggestions::getGroupingFieldsByRecalculationType($credit_row['recalculation_type']);
                    foreach ($grouping_keys as $grouping_key){
                        $value = Billrun_util::getIn($credit_row, $grouping_key);
                        if(isset($value)){
                            Billrun_Util::setIn($ret, $grouping_key, $value);
                        }
                    }                   
                }
                
		return $ret;
	}
	
	protected function parseCreditByPrice(&$row) {
		$row['credit']['aprice'] = $row['aprice'];
		if (!isset($row['multiply_charge_by_volume']) || boolval($row['multiply_charge_by_volume'])) {
			$row['aprice'] = $row['aprice'] * $row['usagev'];
		}
		$row['prepriced'] = true;
	}
	
	protected function parseCreditByUsagev(&$row) {
		$row['usagev'] = 1;
		$row['prepriced'] = false;
	}
	
	protected function isCreditByPrice($row) {
		return isset($row['aprice']);
	}
	
	protected function getCreditUsaget($row) {
		if (!isset($row['aprice'])) {
			return (isset($row['credit_type']) && in_array($row['credit_type'], ['charge' , 'refund'])) ? $row['credit_type'] : 'refund';
		}
		return ($row['aprice'] >= 0 ? 'charge' : 'refund');
	}
	
	protected function getSkipCalcs($row) {
		$skipArray = array('unify');
		return $skipArray;
	}
	
	protected function validateFields($credit_row) {
		$fields = Billrun_Factory::config()->getConfigValue('credit.fields', array());
		$ret = array();
		
		foreach ($fields as $fieldName => $field) {
			if (isset($field['mandatory']) && $field['mandatory']) {
				if (isset($credit_row[$fieldName])) {
					$ret[$fieldName] = $credit_row[$fieldName];
				} else if (isset($field['alternative_fields']) && is_array($field['alternative_fields'])) {
					$found = false;
					foreach ($field['alternative_fields'] as $alternativeFieldName) {
						if (isset($credit_row[$alternativeFieldName])) {
							$ret[$fieldName] = $credit_row[$alternativeFieldName];
							$found = true;
							break;
						}
					}
					
					if (!$found) {
						$this->setError('Following field/s are missing: one of: (' . implode(', ', array_merge(array($fieldName), $field['alternative_fields']))) . ')';
					}
				} else {
					$this->setError('Following field/s are missing: ' . $fieldName);
				}
			} else if (isset($credit_row[$fieldName])) { // not mandatory field
				$ret[$fieldName] = $credit_row[$fieldName];
			} else {
				continue;
			}
			
			if (!empty($field['validator'])) {
				$validator = Billrun_TypeValidator_Manager::getValidator($field['validator']);
				if (!$validator) {
					Billrun_Factory::log('Cannot get validator for field ' .  $fieldName . '. Details: ' . print_r($field, 1));
					$this->setError('General error');
				}
				$params = isset($field['validator_params']) ? $field['validator_params'] : array();
				if (!$validator->validate($ret[$fieldName], $params)) {
					$this->setError('Field ' . $fieldName . ' should be of type ' . ucfirst($field['validator']));
				}
			}
			
			if (!empty($field['conversionMethod'])) {
				$ret[$fieldName] = call_user_func($field['conversionMethod'], $ret[$fieldName]);
			}
		}
		
		// credit custom fields
		if (isset($credit_row['uf'])) {
			if (!isset($ret['uf'])) {
				$ret['uf'] = array();
			}
			$entry = json_decode($credit_row['uf'], JSON_OBJECT_AS_ARRAY);
			$ufFields = Billrun_Factory::config()->getConfigValue('lines.credit.fields', array());
			foreach ($ufFields as $field) {
				$key = $field['field_name'];
				if (!empty($field['mandatory']) && !isset($entry[$key])) {
					$this->setError('Following field is missing: uf.' . $key);
				} else if (isset($entry[$key])) {
					$ret['uf'][$key] = $entry[$key];
				}
			}
		}

		return $ret;
	}
	
	/**
	 * Handles the case of an error while processing credit lines
	 */
	protected function handleProcessError() {
		if (!$this->hasInstallments()) {
			return;
		}
		
		// delete all processed lines
		$stamps = array_column($this->events, 'stamp');
		Billrun_Lines_Util::removeLinesByStamps($stamps, ['type' => 'credit']);
		$this->setError('Process error');
	}
	
	protected function proccess() {
		
	}
	
	protected function response() {
		$ret = [
			'status' => $this->status,
			'desc' => $this->desc,
			'input' => $this->originRequest,
		];
		
		$stamps = array_column($this->events, 'stamp');
		$ret['stamp'] = Billrun_Util::getIn($stamps, 0, ''); // in case of installments this will be the first installments' stamp
		
		if ($this->hasInstallments()) {
			$ret['stamps'] = $stamps;
		}
		
		$this->getController()->setOutput([$ret]);
		Billrun_Factory::log("done credit line/s: " . implode(',', $stamps), Zend_Log::INFO);
		return true;
	}

	protected function getPermissionLevel() {
		return Billrun_Traits_Api_IUserPermissions::PERMISSION_WRITE;
	}

	protected function getUsageTypeFromRate($rate) {
		return key($rate['rates']);
	}

}
