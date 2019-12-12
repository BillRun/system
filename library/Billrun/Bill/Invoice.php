<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * BillRun Invoice class
 *
 * @package  Billrun
 * @since    5.0
 */
class Billrun_Bill_Invoice extends Billrun_Bill {

	/**
	 *
	 * @var string
	 */
	protected $type = 'inv';

	/**
	 * Optional fields to be saved to the payment. For some payment methods they are mandatory.
	 * @var array
	 */
	protected $optionalFields = array();

	/**
	 * 
	 * @param type $options
	 */
	public function __construct($options) {
		$this->billsColl = Billrun_Factory::db()->billsCollection();
		if (isset($options['aid'], $options['amount'], $options['invoice_id'], $options['due'], $options['due_date'], $options['due_before_vat'])) {
			if (!is_numeric($options['amount']) || !is_numeric($options['aid']) || ($options['amount'] != abs($options['due']))) {
				throw new Exception('Billrun_Bill_Invoice: Wrong input. Was: Customer: ' . $options['aid'] . ', amount: ' . $options['amount'] . ', due:' . $options['due'] . '.');
			}
			$this->data = new Mongodloid_Entity(NULL, $this->billsColl);
			$this->data['aid'] = intval($options['aid']);
			$this->data['type'] = $this->type;
			$this->data['amount'] = floatval($options['amount']);
			$this->data['due'] = floatval($options['due']);
			$this->data['due_before_vat'] = floatval($options['due_before_vat']);
			$this->data['urt'] = new MongoDate();
			$this->data['invoice_id'] = intval($options['invoice_id']);

			foreach ($this->optionalFields as $optionalField) {
				if (isset($options[$optionalField])) {
					$this->data[$optionalField] = $options[$optionalField];
				}
			}
		} else {
			throw new Exception('Billrun_Invoice: Insufficient options supplied.');
		}
		parent::__construct($options);
	}

	public static function getUnpaidInvoices($query = array(), $sort = array()) {
		$mandatoryQuery = static::getUnpaidQuery();
		$query = array_merge($query, $mandatoryQuery);
		return static::getInvoices($query, $sort);
	}

	public static function getInvoices($query = array(), $sort = array()) {
		$billsColl = Billrun_Factory::db()->billsCollection();
		$mandatoryQuery = array(
			'type' => 'inv',
		);
		$query = array_merge($query, $mandatoryQuery);
		if (!$sort) {
			$sort = array('due_date' => 1);
		}
		return static::getBills($query, $sort);
	}

	public function getId() {
		return $this->data['invoice_id'];
	}

	public function getBillrunKey() {
		return isset($this->data['billrun_key']) ? $this->data['billrun_key'] : NULL;
	}

	public function getPayerFirstName() {
		return isset($this->data['firstname']) ? $this->data['firstname'] : '';
	}

	public function getPayerLastName() {
		return isset($this->data['lastname']) ? $this->data['lastname'] : '';
	}

	public function getBillUnit() {
		return $this->data['bill_unit'];
	}

	public function getDueDate() {
		return $this->data['due_date'];
	}

	/**
	 * 
	 * @param int $id
	 * @return Billrun_Bill_Invoice
	 */
	public static function getInstanceByid($id) {
		$data = Billrun_Factory::db()->billsCollection()->query('type', 'inv')->query('invoice_id', $id)->cursor()->current();
		if ($data->isEmpty()) {
			return NULL;
		}
		return static::getInstanceByData($data);
	}

	/**
	 * 
	 * @param Mongodloid_Entity|array $data
	 * @return Billrun_Bill_Invoice
	 */
	public static function getInstanceByData($data) {
		$rawData = is_array($data) ? $data : $data->getRawData();
		$instance = new self($rawData);
		$instance->setRawData($rawData);
		return $instance;
	}
	
	public static function isInvoiceConfirmed($aid, $key) {
		$billrunColl = Billrun_Factory::db()->billrunCollection();
		$query = array(
			'aid' => (int) $aid,
			'billrun_key' => $key,
			'billed' => 1
		);
		
		$confirmed = $billrunColl->query($query)->count();
		if ($confirmed) {
			return true;
		}
		return false; 
	}
        /**
         * Function gets start + end time, as Unix Timestamp,and aid.
         * @param string $startTime
         * @param string $endTime
         * @param integer $aid
         * @return array of Billrun_Bill_Invoice objects, of the wanted account's immediate invoices, in the time rang.
         */
        public static function getImmediateInvoicesByRange($aid, $startTime, $endTime, $returnAsArray = false) {
            $convertedStartTime = date('YmdHis', $startTime);
            $convertedEndTime = date('YmdHis', $endTime);
            $query = array(
                        'aid' => $aid,
                        'invoice_type' => array('$eq' => 'immediate'),
                        'billrun_key' => array('$gte' => $convertedStartTime, '$lt' => $convertedEndTime)

		);
            $sort = array(
			'billrun_key' => -1,
		);
            $fields = array(
			'billrun_key' => 1,
                );  
            $bills = Billrun_Factory::db()->billsCollection()->query($query)->cursor()->sort($sort);
            $billsArray = iterator_to_array($bills, true);
            $invoicesArray = [];
            if(!$returnAsArray){
                foreach($billsArray as $id => $entity){
                    $invoicesArray[] = Billrun_Bill_Invoice::getInstanceByData($entity);
                }
            }else{
                foreach($billsArray as $id => $entity){
                    $invoicesArray[] = $entity->getRawData();
                }
            }
            return $invoicesArray;
        }

}
