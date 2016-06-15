<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 S.D.O.C. LTD. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

require_once APPLICATION_PATH . '/application/controllers/Action/Pay.php';
require_once APPLICATION_PATH . '/application/controllers/Action/Collect.php';

/**
 * Billing CreditGuard csv generator class
 * 
 * @package  Billing
 * @since    0.5
 */
class Billrun_Generator_CGcsv extends Billrun_Generator_CsvAbstract {

	protected $paymentMethods = array('Debit');

		protected $terminal_id = '0962832';  
		protected $customers;
		protected $subscribers;
		protected $dd_log_file; 

	/**
	 *
	 * @var string
	 */
	protected $extraction_date;

	public function __construct($options) {
		$this->extraction_date = date('YmdHis');
		$this->initLogFile($options['stamp']);
		if (!isset($options['pad_string'])){
			
		}
		if (!isset($options['pad_type'])){
			
		}
		if (!isset($options['pad_length'])){
			
		}
		parent::__construct($options);
	}

	public function load() {
		$today = new MongoDate();
		$paymentParams = array(
			'dd_stamp' => $this->getStamp(),
		);
		if (!Billrun_Bill_Payment::removePayments($paymentParams)) { // removePayments if this is a rerun
			throw new Exception('Error removing payments before rerun');
		}
		$this->customers = iterator_to_array($this->getDDFileCustomers());
		
		Billrun_Factory::log()->log('generator entities loaded: ' . count($this->customers), Zend_Log::INFO);
		Billrun_Factory::dispatcher()->trigger('afterGeneratorLoadData', array('generator' => $this));
		
		$sequence = $this->getSequenceField();
		$involvedAccounts = array();
		$options = array('collect' => FALSE);
		$this->data = array();	
		$this->subscribers = Billrun_Factory::db()->subscribersCollection();
		
		foreach ($this->customers as $customer) {
			$subscriber = $this->subscribers->query(array('aid' => (int)$customer['aid'], 'from' => array('$lte' => $today), 'to' => array('$gte' => $today)))->cursor()->current();
			$involvedAccounts[] = $paymentParams['aid'] = $customer['aid'];
			$paymentParams['billrun_key'] = $customer['billrun_key'];
			$paymentParams['amount'] = $customer['due'];
			$payment = payAction::pay('debit', array($paymentParams), $options)[0];
			$line = array(
				0 => '001',
				1 => $this->terminal_id,
				2 => $paymentParams['amount'],
				3 => 1,
				4 => '1092187729461881', //$subscriber['card_token'] ,
				5 => '0420',             //$subscriber['card_expiration'],
				6 => '01',
				7 => 1,
				8 => '',
				9 => rand(100000, 999999),                 //$subscriber['transaction_id']
				10 => '',
				11 => '',
				12 => 4,
				13 => '',
				14 => '',
				15 => '',
				16 => '',
			);
			$this->data[] = $line;
		}
		$this->buildHeader();
	}

	protected function setFilename() {
		$this->filename = 'c' . $this->extraction_date . '.' . $this->terminal_id;
	}
	
	protected function getSequenceField() {
		return $this->extraction_date . $this->dd_log_file->getSequenceNumber();
	}
	
	protected function writeHeaders() {
		$file_contents = '';
		$counter = 0;
		foreach ($this->headers as $entity) {
			$counter++;
			if (!is_array($entity)) {
				$entity = $entity->getRawData();
			}
			$pad_length = Billrun_Factory::config()->getConfigValue('CGcsv.pad_length_header');
			$file_contents .= $this->getHeaderRowContent($entity,$pad_length);
			$file_contents .= PHP_EOL;
			if ($counter == 50000) {
				$this->writeToFile($file_contents);
				$file_contents = '';
				$counter = 0;
			}
		}
		$this->writeToFile($file_contents);
	}
	
		protected function writeRows() {
		$file_contents = '';
		$counter = 0;
		foreach ($this->data as $index => $entity) {
			$counter++;
			if (!is_array($entity)) {
				$entity = $entity->getRawData();
			}
			$pad_length = Billrun_Factory::config()->getConfigValue('CGcsv.pad_length_data');
			$file_contents .= $this->getRowContent($entity,$pad_length);
			if ($index < count($this->customers)-1){
				$file_contents.= PHP_EOL;
			}
			if ($counter == 50000) {
				$this->writeToFile($file_contents);
				$file_contents = '';
				$counter = 0;
			}
		}
		$this->writeToFile($file_contents);
	}
	

	protected function buildHeader() {

		$line = array(
			0 => '000',
			1 => '',
			2 => date('ymdHis'),
			3 => '',
			4 => '',
			5 => '',
			6 => '',
			7 => '',
			8 => '',
			9 => count($this->customers),
			10 => round(microtime(true) * 1000) . rand(100000, 999999),
			11 => '',
			12 => '',
			13 => '',
		);
		$this->headers[0] = $line;
	}

	public function generate() {
		parent::generate();
		$this->dd_log_file->setProcessTime();
		$this->dd_log_file->save();
	}

	protected function initLogFile($stamp) {
		$this->dd_log_file = new Billrun_LogFile_DD(array('stamp' => $stamp));
		$this->dd_log_file->setSequenceNumber();
		$this->setFilename();
		$this->dd_log_file->setFileName($this->filename);
	}

	protected function getDDFileCustomers() {
		$billsColl = Billrun_Factory::db()->billsCollection();
		$sort = array(
			'$sort' => array(
				'type' => 1,
				'due_date' => -1,
			),
		);
		$group = array(
			'$group' => array(
				'_id' => '$aid',
				'suspend_debit' => array(
					'$first' => '$suspend_debit',
				),
				'type' => array(
					'$first' => '$type',
				),
				'payment_method' => array(
					'$first' => '$payment_method',
				),
				'due' => array(
					'$sum' => '$due',
				),
				'aid' => array(
					'$first' => '$aid',
				),
				'billrun_key' => array(
					'$first' => '$billrun_key',
				),
				'lastname' => array(
					'$first' => '$lastname',
				),
				'firstname' => array(
					'$first' => '$firstname',
				),
				'bill_unit' => array(
					'$first' => '$bill_unit',
				),
				'bank_name' => array(
					'$first' => '$bank_name',
				),
				'due_date' => array(
					'$first' => '$due_date',
				),
			),
		);
		$match = array(
			'$match' => array(
				'due' => array(
					'$gt' => Billrun_Bill::precision,
				),
				'payment_method' => array(
					'$in' => $this->paymentMethods,
				),
				'suspend_debit' => NULL,
			),
		);
		$res = $billsColl->aggregate($sort, $group, $match);
		return $res;
	}

}
