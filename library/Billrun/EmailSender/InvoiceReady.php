<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * This is email sender for invoice ready.
 *
 */
class Billrun_EmailSender_InvoiceReady extends Billrun_EmailSender_Base {
	
	/*
	 * see Billrun_EmailSender_Base::shouldNotify
	 */
	public function shouldNotify() {
		return Billrun_Factory::config()->getConfigValue('billrun.email_after_confirmation', false);
	}
	
	/**
	 * see Billrun_EmailSender_Base::getEmailAddress
	 */
	protected function getEmailAddress($data) {
		return $data['attributes']['email'];
	}

	/**
	 * see Billrun_EmailSender_Base::getEmailBody
	 */
	protected function getEmailBody($data) {
		return Billrun_Factory::config()->getConfigValue('email_templates.invoice_ready.content', '');
	}

	/**
	 * see Billrun_EmailSender_Base::getEmailSubject
	 */
	protected function getEmailSubject($data) {
		return Billrun_Factory::config()->getConfigValue('email_templates.invoice_ready.subject', '');
	}
	
	/**
	 * see Billrun_EmailSender_Base::translateMessage
	 */
	public function translateMessage($msg, $data = array()) {
		$replaces = array(
			'[[date]]' => date(Billrun_Base::base_dateformat),
			'[[invoice_id]]' => $data['invoice_id'],
			'[[invoice_total]]' => $data['totals']['after_vat_rounded'],
			'[[invoice_due_date]]' => date(Billrun_Base::base_dateformat, $data['due_date']->sec),
			'[[cycle_range]]' => date(Billrun_Base::base_dateformat, $data['start_date']->sec) . ' - ' . date(Billrun_Base::base_dateformat, $data['end_date']->sec),
			'[[company_email]]' => Billrun_Factory::config()->getConfigValue('tenant.email', ''),
			'[[company_name]]' => Billrun_Factory::config()->getConfigValue('tenant.name', ''),
		);

		Billrun_Factory::dispatcher()->trigger('alterMessageTranslations',[&$replaces, $data, $this]);
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
			$replaces[$placeHolder] = $data['attributes'][$subscriberField];
		}
		
		return str_replace(array_keys($replaces), array_values($replaces), $msg);
	}

	/**
	 * see Billrun_EmailSender_Base::getData
	 */
	public function getData() {
		$billrunColl = Billrun_Factory::db()->billrunCollection();
		$invoiceIds = !empty($this->params['invoices']) ? Billrun_Util::verify_array($this->params['invoices'], 'int') : array();
		$invoiceQuery = !empty($invoiceIds) ? array('$in' => $invoiceIds) : array('$exists' => 1);
		$query = array(
			'billed' => 1,
			'invoice_id' => $invoiceQuery,
		);
		if (!empty($this->params['billrun_key'])) {
			$query['billrun_key'] = (string) $this->params['billrun_key'];
		}
		return $billrunColl->query($query)->cursor()->setReadPreference(Billrun_Factory::config()->getConfigValue('read_only_db_pref'))->timeout(10800000);
	}

	/**
	 * see Billrun_EmailSender_Base::validateData
	 */
	public function validateData($data) {
		if (empty($data['invoice_file']) || empty($data['attributes']['email'])) {
			Billrun_Factory::log('sendInvoiceReadyMail - missing invoice file or email. Invoice data: ' . print_R($data, 1), Billrun_Log::NOTICE);
			return false;
		}
		if (!$this->isShippingMethodMatch($data)) {
			Billrun_Factory::log('sendInvoiceReadyMail - invoice method does not match. invoice ID: ' . $data['invoice_id'], Billrun_Log::NOTICE);
			return false;
		}
		if ($this->isAlreadySent($data) && !$this->isForceSend()) {
			Billrun_Factory::log('sendInvoiceReadyMail - invoice already sent. invoice ID: ' . $data['invoice_id'], Billrun_Log::NOTICE);
			return false;
		}
		return parent::validateData($data);
	}
	
	
	protected function isShippingMethodMatch($data) {
		$shippingMethod = Billrun_Util::getIn($data, array('attributes', 'invoice_shipping_method'), 'email');
		return $shippingMethod == 'email';
	}
	
	protected function isAlreadySent($data) {
		$query = $this->getRelatedBillrunQuery($data);
		$query['email_sent'] = array('$exists' => 1);
		return !Billrun_Factory::db()->billrunCollection()->query($query)->cursor()->limit(1)->current()->isEmpty();
	}
	
	protected function isForceSend() {
		return isset($this->params['force_send']) && $this->params['force_send'];
	}
	
	/**
	 * see Billrun_EmailSender_Base::getAttachments
	 */
	public function getAttachment($data) {
		$dataFile = $this->getInvoicePDF($data);
		if (!$dataFile) {
			return FALSE;
		};
		$attachment = new Zend_Mime_Part($dataFile);
		$attachment->type = 'application/pdf';
		$attachment->disposition = Zend_Mime::DISPOSITION_ATTACHMENT;
		$attachment->encoding = Zend_Mime::ENCODING_BASE64;
		$attachment->filename = $data['billrun_key'] . '_' . $data['aid'] . '_' . $data['invoice_id'] . ".pdf";
		Billrun_Factory::dispatcher()->trigger('afterInvoiceReadyGetAttachment',[&$attachment, $data ,$this]);
		return $attachment;
	}
	
	protected function getInvoicePDF($data) {
		$pdf = $data['invoice_file'];
		if(!file_exists($pdf)) {
			$aid = $data['aid'];
			$billrunKey = $data['billrun_key'];
			$dataId = $data['invoice_id'];	
			$filesPath = Billrun_Util::getBillRunSharedFolderPath(Billrun_Factory::config()->getConfigValue('invoice_export.export','files/invoices/'));
			$fileName = $billrunKey . '_' . $aid . '_' . $dataId . ".pdf";
			$pdf = $filesPath . $billrunKey . '/pdf/' . $fileName;
		}
		$cont = file_get_contents($pdf);
		return $cont;
	}
	
	protected function afterSend($data, $callback = false) {
		$this->updateBillrunOnEmailSent($data);
		parent::afterSend($data, $callback);
	}
	
	protected function getRelatedBillrunQuery($data) {
		return array(
			'invoice_id' => $data['invoice_id'],
			'billrun_key' => $data['billrun_key'],
			'aid' => $data['aid']
		);
	}


	/**
	 * update the billrun once the email was sent (if necessary).
	 * @param type $data
	 */
	protected function updateBillrunOnEmailSent($data) {
		$query = $this->getRelatedBillrunQuery($data);
		$update = array(
			'$set' => array(
				'email_sent' => new Mongodloid_Date(),
			),
		);
		Billrun_Factory::db()->billrunCollection()->update($query, $update);
	}

}
