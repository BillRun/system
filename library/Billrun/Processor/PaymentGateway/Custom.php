<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2019 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Input processor for payment gateways files
 */
class Billrun_Processor_PaymentGateway_Custom extends Billrun_Processor_Updater {
	
	protected $configByType;
	protected $bills;
	protected $fileType;
	protected $receiverSource;
	protected $gatewayName;
	protected $headerRows;
	protected $trailerRows;
	protected $correlatedValue;
	
	public function __construct($options) {
		$this->configByType = !empty($options[$options['type']]) ? $options[$options['type']] : array();
		$this->gatewayName = str_replace('_', '', ucwords($options['name'], '_'));
		$this->receiverSource = $this->gatewayName . str_replace('_', '', ucwords($options['type'], '_'));
		$this->bills = Billrun_Factory::db()->billsCollection();
		$this->log = Billrun_Factory::db()->logCollection();
	}

/**
	 * @see Billrun_Plugin_Interface_IProcessor::processData
	 */
	protected function processLines() {
		$currentProcessor = current(array_filter($this->configByType, function($settingsByType) {
			return $settingsByType['file_type'] === $this->fileType;
		}));
		if (isset($currentProcessor['parser']) && $currentProcessor['parser'] != 'none') {
			$this->setParser($currentProcessor['parser']);
		} else {
			throw new Exception("Parser definition missing");
		}
		if (!$this->mapProcessorFields($currentProcessor)) { // if missing mapping fields in conf
			return false;
		}
		$headerStructure = isset($currentProcessor['parser']['header_structure']) ? $currentProcessor['parser']['header_structure'] : array();
		$dataStructure = isset($currentProcessor['parser']['data_structure']) ? $currentProcessor['parser']['data_structure'] : array();
		$parser = $this->getParser();
		$parser->setHeaderStructure($headerStructure);
		$parser->setDataStructure($dataStructure);
		$parser->parse($this->fileHandler);
		$this->headerRows = $parser->getHeaderRows();
		$this->trailerRows = $parser->getTrailerRows();
		$parsedData = $parser->getDataRows();
		$rowCount = 0;

		foreach ($parsedData as $line) {
			$row = $this->getBillRunLine($line);
			if (!$row){
				return false;
			}
			$row['row_number'] = ++$rowCount;
			$this->addDataRow($row);
		}
		$this->data['header'] = array('header' => TRUE); //TODO
               $this->data['trailer'] = array('trailer' => TRUE); //TODO

		return true;
	}

	protected function getBillRunLine($rawLine) {
		$row = $rawLine;
		$row['stamp'] = md5(serialize($row));
		return $row;
	}

	protected function updateData() {
		$data = $this->getData();
		$currentProcessor = current(array_filter($this->configByType, function($settingsByType) {
			return $settingsByType['file_type'] === $this->fileType;
		}));
		
		$fileStatus = isset($currentProcessor['file_status']) ? $currentProcessor['file_status'] : null;
		$fileConfCount = isset($currentProcessor['response_files_count']) ? $currentProcessor['response_files_count'] : null;
		$fileCorrelationObj = isset($currentProcessor['correlation']) ? $currentProcessor['correlation'] : null;
		if (!empty($fileStatus) && in_array($fileStatus, array('only_rejections', 'only_acceptance'))) {
			if (empty($fileConfCount) || empty($fileCorrelationObj)) {
				throw new Exception('Missing file response definitions');
			}
			$this->updateLogCollection($fileCorrelationObj);
		}
                if ($currentProcessor['file_status'] == 'only_rejections' || $currentProcessor['file_status'] == 'only_acceptance') {
                    $currentFileCount = $this->getCurrentFileCount();
                    if (($currentFileCount + 1) > $fileConfCount){
                        Billrun_Factory::log('Too many files were received for correlatedValue: ' . $this->correlatedValue . '. Only the first ' . $fileConfCount . ' files were updated in the Data Base.' , Zend_Log::ALERT);
                        return False;
                    }
                }
		$this->updatePaymentsByRows($data, $currentProcessor);
	}

	protected function getRowDateTime($dateStr) {
		$datetime = new DateTime();
		$date = $datetime->createFromFormat('ymdHis', $dateStr);
		return $date;
	}
	
	public function skipQueueCalculators() {
		return true;
	}

	protected function setPgFileType($fileType) {
		$this->fileType = $fileType;
	}
	
	protected function getCurrentFileCount() {
		if (empty($this->correlatedValue)) {
			throw new Exception("Missing correlated value");
		}
		$query = array(
			'related_request_file' => $this->correlatedValue,
			'process_time' => array('$exists' => true),
		);
		
		return $this->log->query($query)->cursor()->count();
	}
	
	protected function updateLeftPaymentsByFileStatus() {
		$currentProcessor = current(array_filter($this->configByType, function($settingsByType) {
			return $settingsByType['file_type'] === $this->fileType;
		}));
		if ($currentProcessor['file_status'] == 'only_rejections' || $currentProcessor['file_status'] == 'only_acceptance') {
		$currentFileCount = $this->getCurrentFileCount();
		$fileStatus = isset($currentProcessor['file_status']) ? $currentProcessor['file_status'] : null;
		$fileConfCount = isset($currentProcessor['response_files_count']) ? $currentProcessor['response_files_count'] : null;
		$fileCorrelationObj = isset($currentProcessor['correlation']) ? $currentProcessor['correlation'] : null;
		if (!empty($fileStatus) && in_array($fileStatus, array('only_rejections', 'only_acceptance'))) {
			if (empty($fileConfCount) || empty($fileCorrelationObj)) {
				throw new Exception('Missing file response definitions');
			}
		}
		$correlatedField =  $fileCorrelationObj['file_field'];
		if (!empty($fileConfCount) && !empty($currentFileCount) && $currentFileCount != $fileConfCount) {
			return;
		}
		$origFileStamp = $this->getOriginalFileStamp($correlatedField);
		$relevantBills = $this->getOrigFileBills($origFileStamp);
		foreach ($relevantBills as $bill) {
			if (!($bill instanceof Billrun_Bill)) {
				$bill = Billrun_Bill::getInstanceByData($bill);
			} 
			if ($fileStatus == 'only_rejections') {
				$bill->markApproved('Completed');
				$bill->setPending(false);
				$bill->updateConfirmation();
				$bill->save();
				$billData = $bill->getRawData();
				if (isset($billData['left_to_pay']) && $billData['due']  > (0 + Billrun_Bill::precision)) {
					Billrun_Factory::dispatcher()->trigger('afterRefundSuccess', array($billData));
				}
				if (isset($billData['left']) && $billData['due'] < (0 - Billrun_Bill::precision)) {
					Billrun_Factory::dispatcher()->trigger('afterChargeSuccess', array($billData));
				}
			} else if ($fileStatus == 'only_acceptance') {
				$billData['method'] = isset($billData['payment_method']) ? $billData['payment_method'] : (isset($billData['method']) ? $billData['method'] : 'automatic');
				$billToReject = Billrun_Bill_Payment::getInstanceByData($billData);
				Billrun_Factory::log('Rejecting transaction  ' . $billToReject->getId(), Zend_Log::INFO);
				$rejection = $billToReject->getRejectionPayment(array('status' => 'acceptance_file'));
				$rejection->setConfirmationStatus(false);
				$rejection->save();
				$billToReject->markRejected();
			}
		}
	}
	}
	
        protected function getOriginalFileStamp($correlatedField) {
		$query = array(
			$correlatedField => $this->correlatedValue,
		);
		$fileLog = $this->log->query($query)->cursor()->current();
		$logData = $fileLog->getRawData();
		return $logData['stamp'];
	}
	
	protected function updatePaymentsByRows($data, $currentProcessor) {
                $no_txid_counter = 0;
		foreach ($data['data'] as $row) {
                    if($row[$this->tranIdentifierField] !== ""){
			$bill = (static::$type != 'payments') ?  Billrun_Bill_Payment::getInstanceByid($row[$this->tranIdentifierField]) : null;
			if (is_null($bill) && static::$type != 'payments') {
				Billrun_Factory::log('Unknown transaction ' . $row[$this->tranIdentifierField] . ' in file ' . $this->filePath, Zend_Log::ALERT);
				continue;
			}
			$this->updatePayments($row, $bill, $currentProcessor);
                    }else{
                        $no_txid_counter++;
                    }
		}
                if($no_txid_counter > 0){
                    Billrun_Factory::log()->log('In ' .$no_txid_counter . ' lines, ' . $this->tranIdentifierField . ' field is empty. No update was made for these lines.', Zend_Log::ALERT);
                }
	}
	
	protected function updateLogCollection($fileCorrelation) {
		$source = isset($fileCorrelation['source']) ? $fileCorrelation['source'] : null;
		$correlationField = isset($fileCorrelation['field']) ? $fileCorrelation['field'] : null;
		$logField = isset($fileCorrelation['file_field']) ? $fileCorrelation['file_field'] : null;
		if (empty($source) || empty($correlationField) || empty($logField)) {
			throw new Exception('Missing correlaction definitions');
		}
		$relevantRow = ($source == 'header') ? current($this->headerRows) : current($this->trailerRows); // TODO: support in more than one header/trailer
		$query = array(
			'stamp' => $this->getFileStamp()
		);
		
		$update = array (
			'$set' => array(
				'related_request_file' => $relevantRow[$correlationField],
				'response_file' => true,
			)
		);
		$this->log->update($query, $update);
		$this->correlatedValue = $relevantRow[$correlationField];
	}
	
	protected function getOrigFileBills($fileStamp) {
		$nonRejectedOrCanceled = Billrun_Bill::getNotRejectedOrCancelledQuery();
		$query = array(
			'generated_pg_file_log' => $fileStamp,
		);
		$query = array_merge($query, $nonRejectedOrCanceled);
		return $this->bills->query($query)->cursor();
	}

}