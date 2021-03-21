<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2013 S.D.O.C. LTD. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Billing MT insert invoice from 
 * 
 * @package  Billing
 * @since    0.5
 */
class Generator_BillrunToBill extends Billrun_Generator {
	
	use Billrun_Traits_Api_OperationsLock;
	use Billrun_Traits_ConditionsCheck;
	use Billrun_Traits_ForeignFields;

	protected $minimum_absolute_amount_for_bill= 0.005;
	protected $invoices;
	protected $billrunColl;
	protected $logo = null;
	protected $confirmDate;
	protected $sendEmail = true;
	protected $filtration = null;
	protected $invoicing_days = [];

	public function __construct($options) {
		$options['auto_create_dir']=false;
		if (!empty($options['invoices'])) {
			$this->invoices = Billrun_Util::verify_array($options['invoices'], 'int');
		}
		if (isset($options['send_email'])) {
			$this->sendEmail = $options['send_email'];
		}
		if (Billrun_Factory::config()->isMultiDayCycle()) {
			$this->invoicing_days = !empty($options['invoicing_days']) ? [$options['invoicing_days']] : null;
		}
		parent::__construct($options);
		$this->minimum_absolute_amount_for_bill = Billrun_Util::getFieldVal($options['generator']['minimum_absolute_amount'],0.005);
		$this->confirmDate = time();
	}

	public function load() {
		$this->billrunColl = Billrun_Factory::db()->billrunCollection();
		$invoiceQuery = !empty($this->invoices) ? array('$in' => $this->invoices) : array('$exists' => 1);
		$query = array(
			'billrun_key' => (string) $this->stamp,
			'billed' => array('$ne' => 1),
			'invoice_id' => $invoiceQuery,
			'allow_bill' => ['$ne' => 0],
		);
		if (!empty($this->invoicing_days)) {
			$query['invoicing_day'] = array('$in' => $this->invoicing_days);
		}
		$invoices = $this->billrunColl->query($query)->cursor()->setReadPreference(Billrun_Factory::config()->getConfigValue('read_only_db_pref'))->timeout(10800000);

		Billrun_Factory::log()->log('generator entities loaded: ' . $invoices->count(true), Zend_Log::INFO);

		Billrun_Factory::dispatcher()->trigger('afterGeneratorLoadData', array('generator' => $this));

		$this->data = $invoices;
	}

	public function generate() {
		$invoicesIds = array();
		$result = array('alreadyRunning' => false, 'releasingProblem'=> false);//help in case it's a onetimeinvoice generate
		$invoices = array();
		foreach ($this->data as $invoice) {
			$this->filtration = $invoice['aid'];
			if (!$this->lock()) {
				Billrun_Factory::log("Generator for aid " . $invoice['aid'] . " is already running", Zend_Log::NOTICE);
				$result['alreadyRunning'] = true;
				continue;
			}
			$this->createBillFromInvoice($invoice->getRawData(), array($this,'updateBillrunONBilled'));
			$invoicesIds[] = $invoice['invoice_id'];
			$invoices[] = $invoice->getRawData();
			if (!$this->release()) {
				Billrun_Factory::log("Problem in releasing operation for aid " . $invoice['aid'], Zend_Log::ALERT);
				$result['releasingProblem'] = true;
		}
		}
		Billrun_Factory::dispatcher()->trigger('afterInvoicesConfirmation', array($invoices, (string) $this->stamp));
		$this->handleSendInvoicesByMail($invoicesIds);
		if(empty($this->invoices)) {
			Billrun_Factory::dispatcher()->trigger('afterExportCycleReports', array($this->data ,&$this));
		}
		return $result;
	}
	
	/**
	 * Create  a bill object  from an invoice object save it to DB and updated the invoice object.
	 * @param type $invoice
	 */
	public function createBillFromInvoice($invoice, $callback = FALSE) {
		$bill =array(
				'type' => 'inv',
				'invoice_id' => $invoice['invoice_id'],
				'aid' => $invoice['aid'],
				'bill_unit' => Billrun_Util::getFieldVal($invoice['attributes']['bill_unit_id'], NULL),
				'due_date' => $this->updateDueDate($invoice),
				'charge' => ['not_before' => $this->updateChargeDate($invoice)],
				'due' => $invoice['totals']['after_vat_rounded'],
				'due_before_vat' => $invoice['totals']['before_vat'],
				'customer_status' => 'open',//$invoice['attributes']['account_status'],
				'payer_name' => $invoice['attributes']['lastname'] .' ' . $invoice['attributes']['firstname'],
				'billrun_key' => $invoice['billrun_key'],
				'amount' => abs($invoice['totals']['after_vat_rounded']),
				'lastname' => $invoice['attributes']['lastname'],
				'firstname' => $invoice['attributes']['firstname'],
				'country_code' => Billrun_Util::getFieldVal($invoice['attributes']['country_code'], NULL),
				'method'=> Billrun_Util::getFieldVal($invoice['attributes']['payment_method'], Billrun_Factory::config()->getConfigValue('PaymentGateways.payment_method')),
				'bank_name' => Billrun_Util::getFieldVal($invoice['attributes']['payment_info']['bank_name'],null),
				'BIC' => Billrun_Util::getFieldVal($invoice['attributes']['payment_info']['bic'],null),
				'IBAN' => Billrun_Util::getFieldVal($invoice['attributes']['payment_info']['iban'],null),
				'RUM' => Billrun_Util::getFieldVal($invoice['attributes']['payment_info']['rum'],null),
				'urt' => new MongoDate(),
				'invoice_date' => $invoice['invoice_date'],
				'invoice_file' => isset($invoice['invoice_file']) ? $invoice['invoice_file'] : null,
                                'invoice_type' => isset($invoice['attributes']['invoice_type']) ? $invoice['attributes']['invoice_type'] : 'regular',
			);
		if (!empty($invoice['invoicing_day'])) {
			$bill['invoicing_day'] = $invoice['invoicing_day'];
		}
		if ($bill['due'] < 0) {
			$bill['left'] = $bill['amount'];
		}
		else {
			$bill['total_paid'] = 0;
			$bill['left_to_pay'] = $bill['due'];
			$bill['vatable_left_to_pay'] = $invoice['totals']['before_vat'];
			$bill['paid'] = '0';
		}
		if(!empty($invoice['attributes']['suspend_debit'])) {
			$bill['suspend_debit'] = $invoice['attributes']['suspend_debit'];
		}
		
		$account = Billrun_Factory::account();
		$foreignData = $this->getForeignFields(array('account' => $account->loadAccountForQuery(['aid' => $invoice['aid']])));
		$bill = array_merge_recursive($bill, $foreignData);
		Billrun_Factory::log('Creating Bill for '.$invoice['aid']. ' on billrun : '.$invoice['billrun_key'] . ' With invoice id : '. $invoice['invoice_id'],Zend_Log::DEBUG);
		Billrun_Factory::dispatcher()->trigger('beforeInvoiceConfirmed', array(&$bill, $invoice));
		$this->safeInsert(Billrun_Factory::db()->billsCollection(), array('invoice_id', 'billrun_key', 'aid', 'type'), $bill, $callback);
		Billrun_Bill::payUnpaidBillsByOverPayingBills($invoice['aid']);
		Billrun_Factory::dispatcher()->trigger('afterInvoiceConfirmed', array($bill));
 	}
	
	/**
	 * update the billrun once the bill object was created and mark it  as billed.
	 * @param type $data
	 */
	protected function updateBillrunONBilled($data) {
		Billrun_Factory::db()->billrunCollection()->update(array('invoice_id'=> $data['invoice_id'],'billrun_key'=>$data['billrun_key'],'aid'=>$data['aid']),array('$set'=>array('billed'=>1)));
	}
	
	/**
	 * update the billrun once the bill object was created and mark it as not to bill.
	 * @param type $data
	 */
	public function updateBillrunNotForBill($data) {
		$query = [
			'invoice_id' => $data['invoice_id'],
			'billrun_key' => $data['billrun_key'],
			'aid' => $data['aid'],
		];
		
		$update = [
			'$set' => [
				'billed' => 2,
			],
		];
		
		if (isset($data['allow_bill'])) {
			$update['$set']['allow_bill'] = $data['allow_bill'];
		}
		Billrun_Factory::db()->billrunCollection()->update($query, $update);
	}
	
	/**
	 * 
	 * @param type $uniqueKeys
	 * @param type $data
	 * @param type $afterSaveCallback
	 */
	protected function safeInsert($collection, $uniqueKeys, $data, $afterSaveCallback = FALSE) {
		$uniqueQuery = array_intersect_key( $data, array_flip($uniqueKeys) );
		$transactionStamp = Billrun_Util::generateArrayStamp($uniqueQuery);
		$uniqueQuery['tx'] = array('$exists'=>FALSE);
		$data['tx'] = $transactionStamp;
		if(!$this->findAndModifyInsert($collection,$uniqueQuery,  $data)) {
			return false;
		}
		
		if($afterSaveCallback) { 
			call_user_func($afterSaveCallback, $data);
		}
		
		$uniqueQuery['tx'] = $transactionStamp;
		$collection->findAndModify(	$uniqueQuery, array('$unset' => array('tx' => 1 )),array(),array('new' => true), true) ;
		return true;
	}
	
	
	/**
	 * Save with  findAndModifiy  to safely handle  concurrent db access.
	 * @param type $query the query to find the item to save.
	 * @param type $updateData the  data  to update the item with if  it exists in the DB
	 * @param type $newData the  data to create the  item in the  db  with
	 * @return boolean true  if the  save was successful  false otherwise.
	 */
	protected function findAndModifyInsert($collection, $query,  $newData) {
		$ret = $collection->findAndModify(	$query, array('$setOnInsert' => $newData), 
													array(), 
													array('upsert' => true, 'new' => true)) ;
		if ( !$ret || $ret->isEmpty()  || $ret['ok'] === 0) {
				Billrun_Factory::log('Failed when trying to save : ' . print_r($newData, 1));
				return false;
		}		
		return true;
	}
		
	protected function getConflictingQuery() {	
                return array('filtration' => $this->filtration);
	}
	
	protected function getInsertData() {
		return array(
			'action' => 'confirm_cycle',
			'filtration' => $this->filtration,
		);
	}
	
	protected function getReleaseQuery() {
		return array(
			'action' => 'confirm_cycle',
			'filtration' => $this->filtration,
			'end_time' => array('$exists' => false)
		);
	}
	
	
	public function handleSendInvoicesByMail($invoices) {
		if (!$this->sendEmail) {
			return;
		}
		
		$options = array(
			'email_type' => 'invoiceReady',
			'billrun_key' => (string) $this->stamp,
			'invoices' => $invoices,
		);
		Billrun_Factory::emailSenderManager($options)->notify();
	}
	
	protected function updateDueDate($invoice) {
		$options = Billrun_Factory::config()->getConfigValue('billrun.due_date', []);
		foreach ($options as $option) {
			if ($option['anchor_field'] == 'confirm_date' && $this->isConditionsMeet($invoice, $option['conditions'])) {
				return new MongoDate(Billrun_Util::calcRelativeTime($option['relative_time'], $this->confirmDate));
			}
		}
		return $invoice['due_date'];
	}
	
	protected function updateChargeDate($invoice) {
		$options = Billrun_Factory::config()->getConfigValue('charge.not_before', []);
		$invoiceType = @$invoice['attributes']['invoice_type'];
		
		// go through all config options and try to match the relevant
		foreach ($options as $option) {
			if ($option['anchor_field'] == 'confirm_date' && in_array($invoiceType, $option['invoice_type'])) {				
				return new MongoDate(Billrun_Util::calcRelativeTime($option['relative_time'], $this->confirmDate));
			}
			if (in_array($invoiceType, $option['invoice_type']) && !empty($invoice[$option['anchor_field']])) {	
				return new MongoDate(Billrun_Util::calcRelativeTime($option['relative_time'], $invoice[$option['anchor_field']]->sec));
			}
		}
		
		// if no config option was matched this could be an on-confirmation invoice - use invoice 'due_date' field
		if (!empty($invoice['due_date'])) {
			return $invoice['due_date'];
		}
		
		// else - get config default value or temporerily use 'invoice_date' with offset
		Billrun_Factory::log()->log('Failed to match charge date for invoice:' . $invoice['invoice_id'] . ', using default configuration', Zend_Log::NOTICE);
		return new MongoDate(strtotime(Billrun_Factory::config()->getConfigValue('billrun.charge_not_before', '+0 seconds'), $this->confirmDate));
	}
	
	protected function getForeignFieldsEntity () {
		return 'bills';
	}
	
}
