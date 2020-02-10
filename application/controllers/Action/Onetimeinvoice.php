<?php

/**
* @package         Billing
* @copyright       Copyright (C) 2012-2018 BillRun Technologies Ltd. All rights reserved.
* @license         GNU Affero General Public License Version 3; see LICENSE.txt
*/

require_once APPLICATION_PATH . '/application/controllers/Action/Api.php';


/**
* Description of Onetimeinvoice
*
* @author eran
*/
class OnetimeinvoiceAction extends ApiAction {
	use Billrun_Traits_Api_UserPermissions;
	use Billrun_Traits_Api_OperationsLock;
	
	const STEP_PDF_ONLY = 0;
	const STEP_PDF_AND_BILL = 1;
	const STEP_FULL = 2;

	protected $aid;
	
    public function execute($arg = null) {
        $this->allowed();
        
        //validate input		
        $request = $this->getRequest()->getRequest();
        if(!$this->validateInputs($request) ) {
            return FALSE;
        }
        
        $oneTimeStamp = date('YmdHis', Billrun_Util::getFieldVal($request['invoice_unixtime'],time()));
		$inputCdrs = json_decode($request['cdrs'],JSON_OBJECT_AS_ARRAY);
		$step = isset($request['step']) ? intval($request['step']) : self::STEP_FULL;
		$sendEmail = isset($request['send_email']) ? intval($request['send_email']) : true;
		$allowBill = isset($request['allow_bill']) ? intval($request['allow_bill']) : 1;
        $cdrs = [];
        $this->aid = intval($request['aid']);
        $affectedSids = [];
        
		Billrun_Factory::log('One time invoice action running for account ' . $this->aid, Zend_Log::INFO);
        //Verify the cdrs data
        foreach($inputCdrs as &$cdr) {
            if($this->aid != $cdr['aid']) {
                $this->setError("One of the CDRs AID doesn't match the account AID");
                return;
            }
            $affectedSids[] = $cdr['sid'] ?: 0;
            $cdr['billrun'] = $oneTimeStamp;
			$cdr = $this->parseCDR($cdr);
			$cdr['onettime_invoice'] = $oneTimeStamp;
			if(!$this->processCDR($cdr) ) {
                return FALSE;
			}
        }

        // run aggregate on cdrs generate invoice
        $aggregator = Billrun_Aggregator::getInstance([ 'type' => 'customeronetime',  'stamp' => $oneTimeStamp , 'force_accounts' => [$this->aid], 'invoice_subtype' => Billrun_Util::getFieldVal($request['type'], 'regular'),'affected_sids' => $affectedSids ]);
        $aggregator->aggregate();


        $this->invoice = Billrun_Factory::billrun(['aid' => $this->aid, 'billrun_key' => $oneTimeStamp , 'autoload'=>true]);
        $pdfPath = $this->invoice->getInvoicePath();
		
		Billrun_Factory::log('One time invoice action confirming invoice ' . $this->invoice->getInvoiceID() . ' for account ' . $this->aid, Zend_Log::INFO);
		$billrunToBill = Billrun_Generator::getInstance(['type'=> 'BillrunToBill','stamp' => $oneTimeStamp,'invoices'=> [$this->invoice->getInvoiceID()], 'send_email' => $sendEmail]);
		
		if ($step >= self::STEP_PDF_AND_BILL) {
			if (!$billrunToBill->lock()) {
				Billrun_Factory::log("BillrunToBill is already running", Zend_Log::NOTICE);
				return;
			}
			$billrunToBill->load();
			$billrunToBill->generate();
			if (!$billrunToBill->release()) {
				Billrun_Factory::log("Problem in releasing operation", Zend_Log::ALERT);
				return;
			}
		} else {
			$invoiceData = $this->invoice->getRawData();
			$invoiceData['allow_bill'] = $allowBill;
			$billrunToBill->updateBillrunNotForBill($invoiceData);
			$billrunToBill->handleSendInvoicesByMail([$invoiceData['invoice_id']]);
		}

		if ($step >= self::STEP_FULL) {
			if (!$this->lock()) {
				Billrun_Factory::log("makePayment is already running", Zend_Log::NOTICE);
				return;
			}

			Billrun_Factory::log('One time invoice action paying invoice ' . $this->invoice->getInvoiceID() . ' for account ' . $this->aid, Zend_Log::INFO);
			Billrun_Bill_Payment::makePayment([ 'aids' => [$this->aid], 'invoices' => [$this->invoice->getInvoiceID()] ]);
			if (!$this->release()) {
				Billrun_Factory::log("Problem in releasing operation", Zend_Log::ALERT);
				return;
			}
		}
		
		if(empty($request['send_back_invoices'])) {
			$this->getController()->setOutput(array(array(
					'status' => 1,
					'desc' => 'success',
					'details' => [ 'invoice_path' => $pdfPath ],
					'input' => $request
			)));
			return TRUE;
		} // else 
        return  $this->sendBackInvoice($pdfPath);
    }
	
	protected function getInsertData($filtration = null) {
	return array(
			'action' => 'charge_account',
			'filtration' => (empty($this->aid) ? 'all' : $this->aid),
		);
	}
	
	protected function getConflictingQuery($filtration = null) {
		if (!empty($this->aid)) {
			return array(
				'$or' => array(
					array('filtration' => 'all'),
					array('filtration' => array('$in' => array($this->aid))),
				),
			);
		}
	}
	
	protected function getReleaseQuery($filtration = null) {
		return array(
			'action' => 'charge_account',
			'filtration' => (empty($this->aid) ? 'all' : $this->aid),
			'end_time' => array('$exists' => false)
		);
	}

    /**
    * Validate the API inputs 
    */
    protected function validateInputs($request) {
        $requiredParameters = array(
            'cdrs' => 'string',
            'aid' => 'int',
        );
        $msg = '';
        foreach($requiredParameters as $key => $type) {
            if(empty($request[$key]) /*|| !Billrun_Util::verify_array($request[$key], $type)*/ ) {
                $msg  .= "Required input '{$key}' is missing or of incorrect type.\n";
            }
        }
        if(!empty($msg)) {
            $this->setError($msg,$request);
        }
        return empty($msg);
    }
    
    
    /**
    * Adjust th CDRs billrun stamp to be the one time stamp
    */
    protected static function adjustBillrun($cdr, $params) {
        $cdr['billrun'] = $params['stamp'];
        return $cdr;
    }
    
    
    protected function sendBackInvoice($pdfPath ) {
		$cont = file_get_contents($pdfPath);
		if ($cont) {
			header('Content-disposition: inline; filename="'.$file_name.'"');
			header('Cache-Control: public, must-revalidate, max-age=0');
			header('Pragma: public');
			header('Last-Modified: '.gmdate('D, d M Y H:i:s').' GMT');
			header('Content-Type: application/pdf');
			Billrun_Factory::log('Transfering file content from : '.$pdfPath .' to http connection');
			echo $cont;
			die();
		}  else {
			$this->setError("Failed when trying to access the file at  {$pdfPath}");
			return FALSE;
		}
		return TRUE;
	}
	
	protected function parseCDR($cdr) {
        //TODO add further  CDR types support here
        switch($cdr['type']) {
            case "credit" :
                return $this->parseCredit($cdr);
            default:
                $this->setError("Unknown CDR type {$cdr['type']}");
        }
        return FALSE;
	}
	
    protected function parseCredit($credit_row) {
		$credit_row['rand'] = rand(1, 1000000);
		$ret = $this->validateCDRFields($credit_row);
		$ret['source'] = 'credit';
		$ret['stamp'] = Billrun_Util::generateArrayStamp($credit_row);
		$ret['process_time'] = new MongoDate();
		$ret['urt'] = new MongoDate( empty($credit_row['credit_time']) ? time() : strtotime($credit_row['credit_time']));
		$rate = Billrun_Rates_Util::getRateByName($credit_row['rate']);
		$ret['usaget'] = $this->getCreditUsaget($ret,$rate);
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
			$this->parseCreditByUsagev($ret,$rate);
		}
		$ret['skip_calc'] = $this->getSkipCalcs($ret);
		return $ret;
	}
	
	protected function processCDR($cdr) {
		Billrun_Factory::log("Process of credit starting", Zend_Log::INFO);
		$options = array(
			'type' => 'Credit',
			'parser' => 'none',
			'rand' => $cdr['rand']
		);
		$processor = Billrun_Processor::getInstance($options);
		$processor->addDataRow($cdr);
		if ($processor->process() === false) {
			$this->setError('Processing Error for CDR'.json_encode($cdr));
			return FALSE;
		}
		Billrun_Factory::log("Process of credit ended", Zend_Log::INFO);
		return current($processor->getAllLines());
	}
	
	protected function getSkipCalcs($row) {
		$skipArray = array('unify');
		if(empty($row['sid']) && !empty($row['aid'])) {
            $skipArray[] = 'customer';
		}
		if(!empty($row['prepriced'])) {
		$skipArray[] = 'pricing';
		}
		return $skipArray;
	}
	
	protected function parseCreditByPrice(&$row) {
		$row['credit']['aprice'] = $row['aprice'];
		$row['aprice'] = $row['aprice'] * $row['usagev'];
		$row['prepriced'] = true;
	}
	
	protected function parseCreditByUsagev(&$row, $rate) {
		//$row['usagev'] = 1;
		$row['aprice'] = Billrun_Rates_Util::getTotalCharge($rate, $row['usaget'], $row['usagev'], null, array(), 0, $row['urt']->sec);
		$row['prepriced'] = true;
	}
	
	protected function isCreditByPrice($row) {
		return isset($row['aprice']);
	}
	
	protected function getCreditUsaget($row, $rate) {
		if (!isset($row['aprice'])) {
			return key(@$rate['rates']);
		}
		return ($row['aprice'] >= 0 ? 'charge' : 'refund');
	}
	
	protected function getUsageTypeFromRate($rate) {
		return key(@$rate['rates']);
	}
	
	protected function validateCDRFields($credit_row) {
		$fields = Billrun_Factory::config()->getConfigValue('credit.fields', array());
		$ret = $credit_row;
		
		foreach ($fields as $fieldName => $field) {
			if (isset($field['mandatory']) && $field['mandatory']) {
				if (isset($credit_row[$fieldName])) {
					$ret[$fieldName] = $credit_row[$fieldName];
				} else if (isset($field['alternative_fields']) && is_array($field['alternative_fields'])) {
					foreach ($field['alternative_fields'] as $alternativeFieldName) {
						if (isset($credit_row[$alternativeFieldName])) {
							$ret[$fieldName] = $credit_row[$alternativeFieldName];
							break;
						}
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
		
		return $ret;
	}
	
	protected function getPermissionLevel() {
		return Billrun_Traits_Api_IUserPermissions::PERMISSION_WRITE;
	}
}
