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
class Billrun_Processor_CGfeedback extends Billrun_Processor_Updater{

	/**
	 * processor name
	 *
	 * @var string
	 */
	static protected $type = 'CGfeedback';
	
	protected $structConfig;
	protected $header_structure;
	protected $data_structure;
	protected $bills;

	
	protected function processLines() {
		$this->loadConfig(Billrun_Factory::config()->getConfigValue($this->getType() . '.config_path'));
		$parser = $this->getParser();
		$parser->parse($this->fileHandler);
		$processedData = &$this->getData();
		$processedData['header'] = array('header' => TRUE); //TODO
		$processedData['trailer'] = array('trailer' => TRUE); //TODO
		$parsedData = $parser->getDataRows();
		$rowCount = 0;
		foreach ($parsedData as $parsedRow) {
			$row = $this->buildDataRow();
			$row['amount'] = $parsedRow['amount'];
			$row['transaction_id'] = $parsedRow['deal_id'];
			$row['stamp'] = $row['transaction_id'];
			$row['ret_code'] = $parsedRow['deal_status'];

			$this->addDataRow($row);
		}

		return true;
	}
	
	
	/**
	 * This function should be used to build a Data row
	 * @param $data the raw row data
	 * @return Array that conatins all the parsed and processed data.
	 */
	public function buildDataRow() {
		$row['source'] = self::$type;
		$row['file'] = basename($this->filePath);
		$row['log_stamp'] = $this->getFileStamp();
		$row['process_time'] = date(self::base_dateformat);
		return $row;
	}
	

	protected function addAlertData(&$event) {
		
	}

	public function getFilenameData($type, $filename, &$processor) {
		
	}

	public function handlerCollect($options) {
		
	}

	public function isProcessingFinished($type, $fileHandle, \Billrun_Processor &$processor) {
		
	}

	
	public function updateData() { 
		$this->bills = Billrun_Factory::db()->billsCollection();
		$data = $this->getData();
 		$emailsToSend = array();
		$rejections = Billrun_Bill_Payment::getRejections();
		foreach ($data['data'] as $row) {
			$bills = $this->findBill($row);
			$bill = Billrun_Bill_Payment::getInstanceByid($bills->current()['txid']);
			$bill->updateConfirmation();
			if ($this->isValidTransaction($row)) { 
				continue;
			}else{
				if (count($bills) == 0) {
					Billrun_Factory::log('Unknown transaction ' . $row['transaction_id'], Zend_Log::ALERT);
				} else {
					if (!$bill->isRejected()) {
						if (!Billrun_Util::isEqual(Billrun_Util::getChargableAmount($bill->getAmount()), Billrun_Util::getChargableAmount($row['amount']), Billrun_Bill::precision)) {
							Billrun_Factory::log('Charge not matching for transaction id ' . $row['transaction_id'] . '. Skipping.', Zend_Log::ALERT);
							continue;
						}
						Billrun_Factory::log('Rejecting transaction  ' . $row['transaction_id'], Zend_Log::DEBUG);
						$rejection = $bill->getRejectionPayment($row['ret_code']);
						$rejection->save();
						$bill->markRejected();

						$this->incrementGoodLinesCounter();
						if (Billrun_Factory::config()->getConfigValue('CGfeedback.send_email')) {
							$emailsToSend = $this->defineEmailToSend($bill, $row, $rejections);
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
	
	protected function defineEmailToSend($bill, $row, $rejections) {
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
	
	protected function getLineVolume($row) {
		
	}
	protected function getLineUsageType($row) {
		
	}
	

}

