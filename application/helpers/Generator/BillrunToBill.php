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

	protected $minimum_absolute_amount_for_bill= 0.005;
	protected $invoices;
	protected $billrunColl;
	protected $logo = null;

	public function __construct($options) {
		$options['auto_create_dir']=false;
		if (!empty($options['invoices'])) {
			$this->invoices = Billrun_Util::verify_array($options['invoices'], 'int');
		}
		parent::__construct($options);
		$this->minimum_absolute_amount_for_bill = Billrun_Util::getFieldVal($options['generator']['minimum_absolute_amount'],0.005);
	}

	public function load() {
		$this->billrunColl = Billrun_Factory::db()->billrunCollection();
		$invoiceQuery = !empty($this->invoices) ? array('$in' => $this->invoices) : array('$exists' => 1);
		$query = array(
			'billrun_key' => (string) $this->stamp,
			'billed' => array('$ne' => 1),
			'invoice_id' => $invoiceQuery,
		);
		$invoices = $this->billrunColl->query($query)->cursor()->setReadPreference(Billrun_Factory::config()->getConfigValue('read_only_db_pref'))->timeout(10800000);

		Billrun_Factory::log()->log('generator entities loaded: ' . $invoices->count(true), Zend_Log::INFO);

		Billrun_Factory::dispatcher()->trigger('afterGeneratorLoadData', array('generator' => $this));

		$this->data = $invoices;
	}

	public function generate() {
		$invoices = array();
		foreach ($this->data as $invoice) {
			$this->createBillFromInvoice($invoice->getRawData(), array($this,'updateBillrunONBilled'));
			$invoices[] = $invoice;
		}
		$this->handleSendInvoicesByMail($invoices);
		if(empty($this->invoices)) {
			Billrun_Factory::dispatcher()->trigger('afterExportCycleReports', array($this->data ,&$this));
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
				'payer_name' => $invoice['attributes']['lastname'] .' ' . $invoice['attributes']['firstname'],
				'billrun_key' => $invoice['billrun_key'],
				'amount' => abs($invoice['totals']['after_vat_rounded']),
				'lastname' => $invoice['attributes']['lastname'],
				'firstname' => $invoice['attributes']['firstname'],
				'country_code' => Billrun_Util::getFieldVal($invoice['attributes']['country_code'], NULL),
				'payment_method'=> Billrun_Util::getFieldVal($invoice['attributes']['payment_method'], Billrun_Factory::config()->getConfigValue('PaymentGateways.payment_method')),
				'bank_name' => Billrun_Util::getFieldVal($invoice['attributes']['payment_info']['bank_name'],null),
				'BIC' => Billrun_Util::getFieldVal($invoice['attributes']['payment_info']['bic'],null),
				'IBAN' => Billrun_Util::getFieldVal($invoice['attributes']['payment_info']['iban'],null),
				'RUM' => Billrun_Util::getFieldVal($invoice['attributes']['payment_info']['rum'],null),
				'urt' => new MongoDate(),
				'invoice_date' => $invoice['invoice_date'],
				'invoice_file' => $invoice['invoice_file'],
			);
		if ($bill['due'] < 0) {
			$bill['left'] = $bill['amount'];
		}
		else {
			$bill['total_paid'] = 0;
			$bill['left_to_pay'] = $bill['due'];
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
	 * update the billrun once the email was sent (if necessary).
	 * @param type $data
	 */
	protected function updateBillrunOnEmailSent($data) {
		$query = array(
			'invoice_id' => $data['invoice_id'],
			'billrun_key' => $data['billrun_key'],
			'aid' => $data['aid']
		);
		$update = array(
			'$set' => array(
				'email_sent' => new MongoDate(),
			),
		);
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
		if (!empty($this->invoices)){
			return array(
				'$or' => array(
					array('filtration' => 'all'),
					array('filtration' => array('$in' => $this->invoices)),
				),
			);
		}
		
		return array();	
	}
	
	protected function getInsertData() {
		return array(
			'action' => 'confirm_cycle',
			'filtration' => (empty($this->invoices) ? 'all' : $this->invoices),
		);
	}
	
	protected function getReleaseQuery() {
		return array(
			'action' => 'confirm_cycle',
			'filtration' => (empty($this->invoices) ? 'all' : $this->invoices),
			'end_time' => array('$exists' => false)
		);
	}
	
	protected function translateMessage($msg, $invoice) {
		$replaces = array(
			'[[date]]' => date(Billrun_Base::base_dateformat),
			'[[invoice_id]]' => $invoice['invoice_id'],
			'[[invoice_total]]' => $invoice['totals']['after_vat'],
			'[[invoice_due_date]]' => date(Billrun_Base::base_dateformat, $invoice['due_date']->sec),
			'[[cycle_range]]' => date(Billrun_Base::base_dateformat, $invoice['start_date']->sec) . ' - ' . date(Billrun_Base::base_dateformat, $invoice['end_date']->sec),
			'[[company_email]]' => Billrun_Factory::config()->getConfigValue('tenant.email', ''),
			'[[company_name]]' => Billrun_Factory::config()->getConfigValue('tenant.name', ''),
		);

//		This is currently disabled because email with embedded base64 images is not supported, but we might want it in the future
//		// handle company logo
//		if (is_null($this->logo)) {
//			$logoContent = Billrun_Util::getCompanyLogo();
//			$this->logo = "<img src='data:image/png;base64, " . $logoContent . "' alt='' style='width:100px;object-fit:contain;'>";
//		}
//		$replaces['[[company_logo]]'] = $this->logo;
		
		// handle subscriber fields
		$subscriberFields = array();
		preg_match_all('/\[\[customer_(.*?)\]\]/s', $msg, $subscriberFields);
		foreach ($subscriberFields[0] as $index => $placeHolder) {
			$subscriberField = $subscriberFields[1][$index];
			$replaces[$placeHolder] = $invoice['attributes'][$subscriberField];
		}
		
		return str_replace(array_keys($replaces), array_values($replaces), $msg);
	}
	
	protected function buildEmailBody($invoice) {
		$msg = Billrun_Factory::config()->getConfigValue('email_templates.invoice_ready.content', '');
		return $this->translateMessage($msg, $invoice);
	}
	
	protected function buildEmailSubject($invoice) {
		$subject = Billrun_Factory::config()->getConfigValue('email_templates.invoice_ready.subject', '');
		return $this->translateMessage($subject, $invoice);
	}
	
	
	protected function handleSendInvoicesByMail($invoices) {
		$sendCycleNotification = Billrun_Factory::config()->getConfigValue('billrun.email_after_confirmation', false);
		if (!$sendCycleNotification) {
			return;
		}
		
		foreach ($invoices as $invoice) {
			$shippingMethod = Billrun_Util::getIn($invoice, array('attributes', 'invoice_shipping_method'), 'email');
			if ($shippingMethod == 'email') {
				$this->sendInvoiceByMail($invoice);
			}
		}
	}
	
	protected function sendInvoiceByMail($invoice) {
		if (empty($invoice['invoice_file']) || empty($invoice['attributes']['email'])) {
			Billrun_Factory::log('sendInvoiceByMail - missing invoice file or email. Invoice data: ' . print_R($invoice->getRawData(), 1), Billrun_Log::NOTICE);
			return;
		}
		$attachments = array();
		$email = $invoice['attributes']['email'];
		$msg = $this->buildEmailBody($invoice);
		$subject = $this->buildEmailSubject($invoice);
		$invoiceData = $invoice->getRawData();
		$invoiceFile = $this->getInvoicePDF($invoiceData);
		if ($invoiceFile) {
			$attachment = new Zend_Mime_Part($invoiceFile);
			$attachment->type = 'application/pdf';
			$attachment->disposition = Zend_Mime::DISPOSITION_ATTACHMENT;
			$attachment->encoding = Zend_Mime::ENCODING_BASE64;
			$attachment->filename = $invoiceData['billrun_key'] . '_' . $invoiceData['aid'] . '_' . $invoiceData['invoice_id'] . ".pdf";
			array_push($attachments, $attachment);
		}
		try {
			Billrun_Util::sendMail($subject, $msg, array($email), $attachments, true);
		} catch (Exception $ex) {
			Billrun_Factory::log('sendInvoiceByMail - error sending email. Error: "' . $ex->getMessage() . '". Invoice data: ' . print_R($invoiceData, 1), Billrun_Log::ERR);
			return;
		}
		$this->updateBillrunOnEmailSent($invoiceData);
	}
	
	protected function getInvoicePDF($invoiceData) {
		$aid = $invoiceData['aid'];
		$billrunKey = $invoiceData['billrun_key'];
		$invoiceId = $invoiceData['invoice_id'];	
		$filesPath = Billrun_Util::getBillRunSharedFolderPath(Billrun_Factory::config()->getConfigValue('invoice_export.export','files/invoices/'));
		$fileName = $billrunKey . '_' . $aid . '_' . $invoiceId . ".pdf";
		$pdf = $filesPath . $billrunKey . '/pdf/' . $fileName;
		header('Content-disposition: inline; filename="'.$fileName.'"');
		header('Cache-Control: public, must-revalidate, max-age=0');
		header('Pragma: public');
		header('Last-Modified: '.gmdate('D, d M Y H:i:s').' GMT');
		header('Content-Type: application/pdf');
		$cont = file_get_contents($pdf);
		return $cont;
	}
	
}
