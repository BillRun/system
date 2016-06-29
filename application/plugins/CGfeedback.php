<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 S.D.O.C. LTD. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */



/**
 * Receive and process files from CG server
 * @package  Billing
 * @since    5.0
 */
class CGfeedbackPlugin extends Billrun_Plugin_BillrunPluginFraud implements Billrun_Plugin_Interface_IUpdaterProcessor {

	/**
	 * plugin name
	 *
	 * @var string
	 */
	protected $name = 'CGfeedback';
	
	protected $structConfig;
	protected $header_structure;
	protected $data_structure;
	protected $deals_num;
	protected $bills;

	
	/**
	 * @see Billrun_Plugin_Interface_IProcessor::processData
	 */
	public function processData($type, $fileHandle, \Billrun_Processor &$processor) {	
		$this->loadConfig(Billrun_Factory::config()->getConfigValue($this->getName() . '.config_path'));
		$processor->getParser()->setFileHandler($fileHandle);	
		$processor->getParser()->setStructure($this->header_structure);
		$line = $processor->fgetsIncrementLine($fileHandle);
		$processor->getParser()->setLine($line);
		$headerContents = $processor->getParser()->parse();

		$processor->getParser()->setStructure($this->data_structure);
		while ($line = $processor->fgetsIncrementLine($fileHandle)) {
			$processor->getParser()->setLine($line);
			$dataContents[] = $processor->getParser()->parse();
		}
		$fileContents = array('header' => $headerContents, 'data' => $dataContents);
		if ($fileContents === FALSE) {
			return FALSE;
		}
		$processedData = &$processor->getData();
		$processedData['header'] = $headerContents;
		foreach ($dataContents as $line) {
			$row = $processor->buildDataRow();
			$row['amount'] = $line['amount'];
			$row['transaction_id'] = $line['deal_id'];
			$row['stamp'] = $row['transaction_id'];
			$row['ret_code'] = $line['deal_status'];
			$processor->addDataRow($row);
		}

		return true;
	}

	protected function addAlertData(&$event) {
		
	}

	public function getFilenameData($type, $filename, &$processor) {
		
	}

	public function handlerCollect($options) {
		
	}

	public function isProcessingFinished($type, $fileHandle, \Billrun_Processor &$processor) {
		
	}

	public function updateData($type, $fileHandle, \Billrun_Processor_Updater &$processor) {
		$this->bills = Billrun_Factory::db()->billsCollection();
		$data = $processor->getData();
 		$emailsToSend = array();
		$rejections = Billrun_Bill_Payment::getRejections();
		foreach ($data['data'] as $row) {
			if ($this->isValidTransaction($row)) { 
				continue;
			}else{
				$bills = $this->findBill($row);
				if (count($bills) == 0) {
					Billrun_Factory::log('Unknown transaction ' . $row['transaction_id'], Zend_Log::ALERT);
				} else {
					$bill = Billrun_Bill_Payment::getInstanceByid($bills->current()['txid']);
					if (!$bill->isRejected()) {
						if (!Billrun_Util::isEqual(Billrun_Util::getChargableAmount($bill->getAmount()), Billrun_Util::getChargableAmount($row['amount']), Billrun_Bill::precision)) {
							Billrun_Factory::log('Charge not matching for transaction id ' . $row['transaction_id'] . '. Skipping.', Zend_Log::ALERT);
							continue;
						}
						Billrun_Factory::log('Rejecting transaction  ' . $row['transaction_id'], Zend_Log::DEBUG);
						$rejection = $bill->getRejectionPayment($row['ret_code']);
						$rejection->save();
						$bill->markRejected();

						$processor->incrementGoodLinesCounter();
						if (Billrun_Factory::config()->getConfigValue('CGfeedback.send_email')) {
							$emailsToSend = $this->defineEmailToSend();
						}
					} else {
						Billrun_Factory::log('Transaction ' . $row['transaction_id'] . ' already rejected', Zend_Log::NOTICE);
					}
				}
			}
		}
		$this->sendEmail($emailsToSend);
	}

	/**
	 * the structure configuration
	 * @param type $path
	 */
	protected function loadConfig($path) {
		$this->structConfig = (new Yaf_Config_Ini($path))->toArray();

		$this->header_structure = $this->structConfig['header'];
		$this->data_structure = $this->structConfig['data'];
		$this->trailer_structure = $this->structConfig['trailer'];
	}
	
	
	protected function isValidTransaction($row){
		if ($row['ret_code'] == '000') { // 000 - Good Deal
			return true;
		} else{
			return false;
		}
	}
	
	protected function defineEmailToSend() {
		return array(
			'aid' => $bill->getAccountNo(),
			'amount' => $bill->getAmount(),
			'date' => $row['process_time'],
			'reason' => $rejections[$row['ret_code']],
		);
	}
	
	protected function sendEmail($emailsToSend) {
		if ($emailsToSend) {
			$subscriber = Billrun_Factory::subscriber();
			$data = array(
				'operation' => 'CGfeedback',
				'entities' => $emailsToSend,
			);
			$emailsResult = $subscriber->sendBillingOperationsNotifications($data);
			if (isset($emailsResult['status']) && $emailsResult['status'] == 1) {
				Billrun_Factory::log()->log('CG Deal rejection: ' . $emailsResult['emails_sent'] . ' emails queued for sending.', Zend_Log::INFO);
			} else {
				Billrun_Factory::log()->log('CRM returned with error when trying to send emails (CG Deal rejection)', Zend_Log::ALERT);
			}
		}
	}
	
	protected function findBill($row){
		return $this->bills->query('txid', $row['transaction_id'])->cursor();
	}

}

?>
