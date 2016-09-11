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

	protected $minimum_absolute_amount_for_bill= 0.005;

	public function __construct($options) {
		$options['auto_create_dir']=false;
		parent::__construct($options);
		$this->minimum_absolute_amount_for_bill = Billrun_Util::getFieldVal($options['generator']['minimum_absolute_amount'],0.005);
	}

	public function load() {
		$billrunColl = Billrun_Factory::db()->billrunCollection();		

		$invoices = $billrunColl->query( array(
											'billrun_key'=> (string) $this->stamp,
											'billed'=>array('$ne'=>1),
											'$or' => array(
												array(
													'totals.after_vat_rounded' => array('$gte' =>  $this->minimum_absolute_amount_for_bill),
												),
												array(
													'totals.after_vat_rounded' => array('$lte' => -$this->minimum_absolute_amount_for_bill),
												),
											),
											'invoice_id'=> array('$exists'=>1)
						) )->cursor()->setReadPreference(Billrun_Factory::config()->getConfigValue('read_only_db_pref'))->timeout(-1);

		Billrun_Factory::log()->log('generator entities loaded: ' . $invoices->count(true), Zend_Log::INFO);

		Billrun_Factory::dispatcher()->trigger('afterGeneratorLoadData', array('generator' => $this));
		
		$this->data = $invoices;
		
	}

	public function generate() {
		foreach ($this->data as $invoice) {
			$this->createBillFromInvoice($invoice->getRawData(), array($this,'updateBillrunONBilled'));
		}
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
				'due_date' => $invoice['due_date'],
				'due' => $invoice['totals']['after_vat_rounded'],
				'due_before_vat' => $invoice['totals']['before_vat'],
				'customer_status' => 'open',//$invoice['attributes']['account_status'],
				'payer_name' => $invoice['attributes']['last_name'] .' ' . $invoice['attributes']['first_name'],
				'billrun_key' => $invoice['billrun_key'],
				'amount' => abs($invoice['totals']['after_vat_rounded']),
				'lastname' => $invoice['attributes']['last_name'],
				'firstname' => $invoice['attributes']['first_name'],
				'country_code' => Billrun_Util::getFieldVal($invoice['attributes']['country_code'], NULL),
				'payment_method'=> Billrun_Util::getFieldVal($invoice['attributes']['payment_method'], NULL),
				'bank_name' => Billrun_Util::getFieldVal($invoice['attributes']['payment_info']['bank_name'],null),
				'BIC' => Billrun_Util::getFieldVal($invoice['attributes']['payment_info']['bic'],null),
				'IBAN' => Billrun_Util::getFieldVal($invoice['attributes']['payment_info']['iban'],null),
				'RUM' => Billrun_Util::getFieldVal($invoice['attributes']['payment_info']['rum'],null),
				'urt' => new MongoDate(),
				'invoice_date' => $invoice['invoice_date'],
			);
		if ($bill['due'] < 0) {
			$bill['left'] = $bill['amount'];
		}
		else {
			$bill['total_paid'] = 0;
			$bill['vatable_left_to_pay'] = $invoice['totals']['before_vat'];
		}
		if(!empty($invoice['attributes']['suspend_debit'])) {
			$bill['suspend_debit'] = $invoice['attributes']['suspend_debit'];
		}
		
		Billrun_Factory::log('Creating Bill for '.$invoice['aid']. ' on billrun : '.$invoice['billrun_key'] . ' With invoice id : '. $invoice['invoice_id'],Zend_Log::DEBUG);
		$this->safeInsert(Billrun_Factory::db()->billsCollection(), array('invoice_id', 'billrun_key', 'aid', 'type'), $bill, $callback);
		Billrun_Bill::payUnpaidBillsByOverPayingBills($invoice['aid']);
 	}
	
	/**
	 * update the billrun once the bill object was created and mark it  as billed.
	 * @param type $data
	 */
	protected function updateBillrunONBilled($data) {
		Billrun_Factory::db()->billrunCollection()->update(array('invoice_id'=> $data['invoice_id'],'billrun_key'=>$data['billrun_key'],'aid'=>$data['aid']),array('$set'=>array('billed'=>1)));
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
}
