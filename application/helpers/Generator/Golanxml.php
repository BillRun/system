<?php

/**
 * @category   Application
 * @package    Helpers
 * @subpackage Generator
 * @copyright  Copyright (C) 2013 S.D.O.C. LTD. All rights reserved.
 * @license    GNU General Public License version 2 or later
 */

/**
 * Golan invoices generator
 *
 * @todo this class should inherit from abstract class Generator_Golan
 * @package    Generator
 * @subpackage Golanxml
 * @since      1.0
 */
class Generator_Golanxml extends Billrun_Generator {

	/**
	 * the type of the object
	 *
	 * @var string
	 */
	protected static $type = 'golanxml';
	protected $offset = 0;
	protected $size = 10000;
	protected $data = array();
	protected $extras_start_date;
	protected $extras_end_date;
	protected $flat_start_date;
	protected $flat_end_date;
	protected $rates;
	protected $plans;
	protected $data_rate;
	protected $lines_coll;
	protected $invoice_version = "1.1";

	/**
	 * Flush XMLWriter every $flush_size billing lines
	 * @var int
	 */
	protected $flush_size = 5000;

	/**
	 *
	 * @var XMLWriter
	 */
	protected $writer = null;

	/**
	 *
	 * @var XMLReader
	 */
	protected $reader = null;

	/**
	 * fields to filter when pulling account lines
	 * @var array 
	 */
	protected $filter_fields;

	/**
	 * flag to buffer output results
	 * 
	 * @var boolean
	 */
	protected $buffer = false;
	
	
	/**
	 * the billing method
	 * @var string prepaid or postpaid
	 */
	protected $billing_method = null;
	
	/**
	 * the plan the customer had for billing
	 * @var string name of the plan
	 */
	protected $plan_to_charge = null;



	public function __construct($options) {
		libxml_use_internal_errors(TRUE);
		parent::__construct($options);
		if (isset($options['page'])) {
			$this->offset = intval($options['page']);
		}
		if (isset($options['size'])) {
			$this->size = intval($options['size']);
		}
		if (isset($options['invoice_version'])) {
			$this->invoice_version = $options['invoice_version'];
		}
		if (isset($options['flush_size']) && is_numeric($options['flush_size'])) {
			$this->flush_size = intval($options['flush_size']);
		}
		if (isset($options['buffer'])) {
			$this->buffer = $options['buffer'];
		}

		$this->lines_coll = Billrun_Factory::db()->linesCollection();
		$this->loadRates();
		$this->loadPlans();
		
		$this->billing_method = Billrun_Factory::config()->getConfigValue('golan.flat_charging', "postpaid");
		$this->filter_fields = array_map("intval", Billrun_Factory::config()->getConfigValue('billrun.filter_fields', array()));
		$this->writer = new XMLWriter(); //create a new xmlwriter object
		$this->reader = new XMLReader(); //create a new xmlwriter object
	}

	public function load() {
		$billrun = Billrun_Factory::db(array('name' => 'billrun'))->billrunCollection();
		Billrun_Factory::log()->log('Loading ' . $this->size . ' billrun documents with offset ' . $this->offset, Zend_Log::INFO);
		$resource = $billrun
			->query('billrun_key', $this->stamp)
			->exists('invoice_id')
			->notExists('invoice_file')
			->cursor()
			->sort(array("aid" => 1))
			->skip($this->offset * $this->size)
			->limit($this->size);

		$resource->timeout(-1);
		// @TODO - there is issue with the timeout; need to be fixed
		//         meanwhile, let's pull the lines right after the query
		foreach ($resource as $row) {
			$this->data[] = $row;
		}
		Billrun_Factory::log()->log("Generator documents loaded: " . count($this->data), Zend_Log::INFO);

		Billrun_Factory::dispatcher()->trigger('afterGeneratorLoadData', array('generator' => $this));
	}

	public function generate() {
		Billrun_Factory::log('Generating invoices...', Zend_log::INFO);
		// use $this->export_directory
		$i = 1;
		foreach ($this->data as $row) {
			Billrun_Factory::log('Current index ' . $i++);
			$this->createXmlInvoice($row);
		}
	}

	/**
	 * create xml invoice
	 * can be called from outside
	 * 
	 * @param Mongodloid_Entity $row billrun collection document
	 * @param array $lines array of lines to get into the xml
	 */
	public function createXmlInvoice($row, $lines = null) {
		$invoice_id = $row->get('invoice_id');
		$invoice_filename = $row['billrun_key'] . '_' . str_pad($row['aid'], 9, '0', STR_PAD_LEFT) . '_' . str_pad($invoice_id, 11, '0', STR_PAD_LEFT) . '.xml';
		$invoice_file_path = $this->export_directory . '/' . $invoice_filename;
		if (!is_writable($this->export_directory)) {
			Billrun_Factory::log('Couldn\'t create invoice file for account ' . $row['aid'] . ' for billrun ' . $row['billrun_key'], Zend_log::ALERT);
			return;
		}
		$this->writer->openURI($invoice_file_path);
		$this->writeXML($row, $lines);
		@chmod($invoice_file_path, 0777); // make the file writable for enable re-creation through admin
		$xml_validation = $this->validateXml($invoice_file_path);
		if ($xml_validation !== TRUE) {
			Billrun_Factory::log('Xml file is not valid: ' . $invoice_file_path . ((is_array($xml_validation)) ? PHP_EOL . "xml errors: " . print_R($xml_validation, 1) : ''), Zend_log::ALERT);
		}
		$this->setFileStamp($row, $invoice_filename);
		Billrun_Factory::log()->log("invoice file " . $invoice_filename . " created for account " . $row->get('aid'), Zend_Log::INFO);
	}

	/**
	 * method to validate xml file content as valid xml standard
	 * 
	 * @param string $file_path xml file path on disk
	 * 
	 * @return mixed true if xml valid, else array if xml errors raised, else false
	 */
	protected function validateXml($file_path) {
		if (!$this->reader->open($file_path)) {
			return FALSE;
		}
		while ($this->reader->read()) {
			
		}
		$xml_errors = libxml_get_errors();
		if (count($xml_errors) === 0) {
			return TRUE;
		} else {
			libxml_clear_errors();
			return $xml_errors;
		}
	}

	/**
	 * receives a billrun document (account invoice)
	 * @param Mongodloid_Entity $row
	 * @return SimpleXMLElement the invoice in xml format
	 */
	protected function writeXML($billrun, $lines = null) {
		$this->writer->startDocument('1.0', 'UTF-8');
		$invoice_total_gift = 0;
		$invoice_total_above_gift = 0;
		$invoice_total_outside_gift_vat = 0;
		$invoice_total_manual_correction = 0;
		$invoice_total_manual_correction_credit = 0;
		$invoice_total_manual_correction_charge = 0;
		$invoice_total_manual_correction_credit_fixed = 0;
		$invoice_total_manual_correction_charge_fixed = 0;
		$invoice_total_manual_correction_refund_fixed = 0;
		$invoice_total_outside_gift_novat = 0;
		$invoice_total_did_premium = 0;
		$billrun_key = $billrun['billrun_key'];
		$aid = $billrun['aid'];
		Billrun_Factory::log()->log("xml account " . $aid, Zend_Log::INFO);
		// @todo refactoring the xml generation to another class

		$this->startInvoice();
		$this->write_basic_header($billrun);

		if (is_null($lines) && (!isset($this->subscribers) || in_array(0, $this->subscribers))) {
			$lines = $this->get_lines($billrun);
		}
		foreach ($billrun['subs'] as $subscriber) {
			$sid = $subscriber['sid'];
			$subscriber_flat_costs = $this->getFlatCosts($subscriber);		
			$this->plan_to_charge = ($this->billing_method == 'postpaid') ? $this->getPlanName($subscriber) : $this->getNextPlanName($subscriber);
			if (is_null($subscriber['current_plan']) && is_null($subscriber['next_plan']) && !isset($subscriber['breakdown'])) {
				continue;
			}
			$current_plan_ref = $subscriber['current_plan'];
			if (MongoDBRef::isRef($current_plan_ref)) {
				$current_plan = $this->getPlanById(strval($current_plan_ref['$id']));
			} else {
				$current_plan = null;
			}

			if (strtoupper($subscriber['subscriber_status']) == 'REBALANCE') {
				$this->writer->startElement('SUBSCRIBER_INF');
				$this->writer->startElement('SUBSCRIBER_DETAILS');
				$this->writer->writeElement('SUBSCRIBER_ID', $subscriber['sid']);
				$this->writer->writeElement('SUBSCRIBER_STATUS', 'REBALANCE');
				$this->writer->endElement();
				$this->writer->endElement();
				continue;
			}

			if ($subscriber['subscriber_status'] == 'open' && (!is_array($subscriber_flat_costs) || empty($subscriber_flat_costs))) {
				Billrun_Factory::log('Missing flat costs for subscriber ' . $sid, Zend_Log::INFO);
			}

			$this->writer->startElement('SUBSCRIBER_INF');
			$this->writer->startElement('SUBSCRIBER_DETAILS');
			$this->writer->writeElement('SUBSCRIBER_ID', $subscriber['sid']);
			$this->writer->writeElement('OFFER_ID_CURR', $subscriber['offer_id_curr']);
			$this->writer->writeElement('OFFER_ID_NEXT', $subscriber['offer_id_next']);
			$this->writer->endElement();

			$this->writeBillingLines($subscriber, $lines);

			$this->writer->startElement('SUBSCRIBER_GIFT_USAGE');
			$this->writer->writeElement('GIFTID_GIFTCLASSNAME', "GC_GOLAN");
			$this->writer->writeElement('GIFTID_GIFTNAME', $this->getPlanName($subscriber));
			$subscriber_gift_usage_TOTAL_FREE_COUNTER_COST = (isset($subscriber_flat_costs['vatable']) ? $subscriber_flat_costs['vatable'] : 0) + (isset($subscriber_flat_costs['vat_free']) ? $subscriber_flat_costs['vat_free'] : 0);
			$this->writer->writeElement('TOTAL_FREE_COUNTER_COST', $subscriber_gift_usage_TOTAL_FREE_COUNTER_COST);
			//$this->writer->writeElement('VOICE_COUNTERVALUEBEFBILL', ???);
			//$this->writer->writeElement('VOICE_FREECOUNTER', ???);
			//$this->writer->writeElement('VOICE_FREECOUNTERCOST', ???);
			$subscriber_gift_usage_VOICE_FREEUSAGE = 0;
			$subscriber_gift_usage_VOICE_ABOVEFREECOST = 0;
			$subscriber_gift_usage_VOICE_ABOVEFREEUSAGE = 0;
			$subscriber_gift_usage_SMS_FREEUSAGE = 0;
			$subscriber_gift_usage_SMS_ABOVEFREECOST = 0;
			$subscriber_gift_usage_SMS_ABOVEFREEUSAGE = 0;
			$subscriber_gift_usage_DATA_FREEUSAGE = 0;
			$subscriber_gift_usage_DATA_ABOVEFREECOST = 0;
			$subscriber_gift_usage_DATA_ABOVEFREEUSAGE = 0;
			$subscriber_gift_usage_MMS_FREEUSAGE = 0;
			$subscriber_gift_usage_MMS_ABOVEFREECOST = 0;
			$subscriber_gift_usage_MMS_ABOVEFREEUSAGE = 0;
			if (isset($subscriber['breakdown']['over_plan']) && is_array($subscriber['breakdown']['over_plan'])) {
				foreach ($subscriber['breakdown']['over_plan'] as $category_key => $category) {
					if (!in_array($category_key, array('intl', 'roaming'))) { // Sefi's request from 2014-03-06 + do not count VF over_plan
						foreach ($category as $zone) {
							$subscriber_gift_usage_VOICE_ABOVEFREECOST+=$this->getZoneTotalsFieldByUsage($zone, 'cost', 'call');
							$subscriber_gift_usage_SMS_ABOVEFREECOST+=$this->getZoneTotalsFieldByUsage($zone, 'cost', 'sms');
							$subscriber_gift_usage_DATA_ABOVEFREECOST+=$this->getZoneTotalsFieldByUsage($zone, 'cost', 'data');
							$subscriber_gift_usage_MMS_ABOVEFREECOST+=$this->getZoneTotalsFieldByUsage($zone, 'cost', 'mms');
							$subscriber_gift_usage_VOICE_ABOVEFREEUSAGE+= $this->getZoneTotalsFieldByUsage($zone, 'usagev', 'call');
							$subscriber_gift_usage_SMS_ABOVEFREEUSAGE+= $this->getZoneTotalsFieldByUsage($zone, 'usagev', 'sms');
							$subscriber_gift_usage_DATA_ABOVEFREEUSAGE+=$this->bytesToKB($this->getZoneTotalsFieldByUsage($zone, 'usagev', 'data'));
							$subscriber_gift_usage_MMS_ABOVEFREEUSAGE+= $this->getZoneTotalsFieldByUsage($zone, 'usagev', 'mms');
						}
					}
				}
			}
			if (isset($subscriber['breakdown']['in_plan']) && is_array($subscriber['breakdown']['in_plan'])) {
				foreach ($subscriber['breakdown']['in_plan'] as $category_key => $category) {
					if ($category_key != 'roaming') { // Do not count VF in_plan
						foreach ($category as $zone) {
							$subscriber_gift_usage_VOICE_FREEUSAGE+=$this->getZoneTotalsFieldByUsage($zone, 'usagev', 'call');
							$subscriber_gift_usage_SMS_FREEUSAGE+=$this->getZoneTotalsFieldByUsage($zone, 'usagev', 'sms');
							$subscriber_gift_usage_DATA_FREEUSAGE+=$this->bytesToKB($this->getZoneTotalsFieldByUsage($zone, 'usagev', 'data'));
							$subscriber_gift_usage_MMS_FREEUSAGE+=$this->getZoneTotalsFieldByUsage($zone, 'usagev', 'mms');
						}
					}
				}
			}

			$subscriber_sumup_TOTAL_MANUAL_CORRECTION_CREDIT_PROMOTION = 0;
			if (isset($subscriber['breakdown']['credit']['refund_vatable']) && is_array($subscriber['breakdown']['credit']['refund_vatable'])) {
				foreach ($subscriber['breakdown']['credit']['refund_vatable'] as $key => $credit) {
					if (strpos($key, 'CRM-REFUND_PROMOTION') !== FALSE) {
						$subscriber_sumup_TOTAL_MANUAL_CORRECTION_CREDIT_PROMOTION += floatval($credit);
					}
				}
			}

			$subscriber_sumup_TOTAL_MANUAL_CORRECTION_CREDIT_FIXED = 0;
			$subscriber_sumup_TOTAL_MANUAL_CORRECTION_CHARGE_FIXED = 0;
			$subscriber_sumup_TOTAL_MANUAL_CORRECTION_REFUND_FIXED = 0;
			if (isset($subscriber['credits']) && is_array($subscriber['credits'])) {
				foreach ($subscriber['credits'] as $credit) {
					$amount_without_vat = floatval($credit['amount_without_vat']);
					if (isset($credit['fixed']) && $credit['fixed']) {
						$subscriber_sumup_TOTAL_MANUAL_CORRECTION_CREDIT_FIXED += $amount_without_vat;
						if (isset($credit['credit_type']) && $credit['credit_type'] == 'charge') {
							$subscriber_sumup_TOTAL_MANUAL_CORRECTION_CHARGE_FIXED += $amount_without_vat;
						} else if (isset($credit['credit_type']) && $credit['credit_type'] == 'refund') {
							$subscriber_sumup_TOTAL_MANUAL_CORRECTION_REFUND_FIXED += $amount_without_vat;
						}
					}
				}
			}

			$this->writer->writeElement('VOICE_FREEUSAGE', $subscriber_gift_usage_VOICE_FREEUSAGE);
			$this->writer->writeElement('VOICE_ABOVEFREECOST', $subscriber_gift_usage_VOICE_ABOVEFREECOST);
			$this->writer->writeElement('VOICE_ABOVEFREEUSAGE', $subscriber_gift_usage_VOICE_ABOVEFREEUSAGE);
			$this->writer->writeElement('SMS_FREEUSAGE', $subscriber_gift_usage_SMS_FREEUSAGE);
			$this->writer->writeElement('SMS_ABOVEFREECOST', $subscriber_gift_usage_SMS_ABOVEFREECOST);
			$this->writer->writeElement('SMS_ABOVEFREEUSAGE', $subscriber_gift_usage_SMS_ABOVEFREEUSAGE);
			$this->writer->writeElement('DATA_FREEUSAGE', $subscriber_gift_usage_DATA_FREEUSAGE);
			$this->writer->writeElement('DATA_ABOVEFREECOST', $subscriber_gift_usage_DATA_ABOVEFREECOST);
			$this->writer->writeElement('DATA_ABOVEFREEUSAGE', $subscriber_gift_usage_DATA_ABOVEFREEUSAGE);
			$this->writer->writeElement('MMS_FREEUSAGE', $subscriber_gift_usage_MMS_FREEUSAGE);
			$this->writer->writeElement('MMS_ABOVEFREECOST', $subscriber_gift_usage_MMS_ABOVEFREECOST);
			$this->writer->writeElement('MMS_ABOVEFREEUSAGE', $subscriber_gift_usage_MMS_ABOVEFREEUSAGE);
			$this->writer->endElement(); // end SUBSCRIBER_GIFT_USAGE

			if (isset($current_plan['include']['groups'])) {
				foreach ($current_plan['include']['groups'] as $group_name => $group) {
					$this->writer->startElement('SUBSCRIBER_GROUP_USAGE');
					$this->writer->writeElement('GROUP_NAME', $group_name);
					$subscriber_group_usage_VOICE_FREEUSAGE = 0;
					$subscriber_group_usage_VOICE_ABOVEFREECOST = 0;
					$subscriber_group_usage_VOICE_ABOVEFREEUSAGE = 0;
					$subscriber_group_usage_SMS_FREEUSAGE = 0;
					$subscriber_group_usage_SMS_ABOVEFREECOST = 0;
					$subscriber_group_usage_SMS_ABOVEFREEUSAGE = 0;
					$subscriber_group_usage_DATA_FREEUSAGE = 0;
					$subscriber_group_usage_DATA_ABOVEFREECOST = 0;
					$subscriber_group_usage_DATA_ABOVEFREEUSAGE = 0;
					$subscriber_group_usage_MMS_FREEUSAGE = 0;
					$subscriber_group_usage_MMS_ABOVEFREECOST = 0;
					$subscriber_group_usage_MMS_ABOVEFREEUSAGE = 0;
					if (isset($subscriber['groups'][$group_name])) {
						foreach ($subscriber['groups'][$group_name] as $plan => $zone) {
							if ($plan == 'over_plan') {
								$subscriber_group_usage_VOICE_ABOVEFREECOST+=$this->getZoneTotalsFieldByUsage($zone, 'cost', 'call');
								$subscriber_group_usage_SMS_ABOVEFREECOST+=$this->getZoneTotalsFieldByUsage($zone, 'cost', 'sms');
								$subscriber_group_usage_DATA_ABOVEFREECOST+=$this->getZoneTotalsFieldByUsage($zone, 'cost', 'data');
								$subscriber_group_usage_MMS_ABOVEFREECOST+=$this->getZoneTotalsFieldByUsage($zone, 'cost', 'mms');
								$subscriber_group_usage_VOICE_ABOVEFREEUSAGE+= $this->getZoneTotalsFieldByUsage($zone, 'usagev', 'call');
								$subscriber_group_usage_SMS_ABOVEFREEUSAGE+= $this->getZoneTotalsFieldByUsage($zone, 'usagev', 'sms');
								$subscriber_group_usage_DATA_ABOVEFREEUSAGE+=$this->bytesToKB($this->getZoneTotalsFieldByUsage($zone, 'usagev', 'data'));
								$subscriber_group_usage_MMS_ABOVEFREEUSAGE+= $this->getZoneTotalsFieldByUsage($zone, 'usagev', 'mms');
							} else if ($plan == 'in_plan') {
								$subscriber_group_usage_VOICE_FREEUSAGE+=$this->getZoneTotalsFieldByUsage($zone, 'usagev', 'call');
								$subscriber_group_usage_SMS_FREEUSAGE+=$this->getZoneTotalsFieldByUsage($zone, 'usagev', 'sms');
								$subscriber_group_usage_DATA_FREEUSAGE+=$this->bytesToKB($this->getZoneTotalsFieldByUsage($zone, 'usagev', 'data'));
								$subscriber_group_usage_MMS_FREEUSAGE+=$this->getZoneTotalsFieldByUsage($zone, 'usagev', 'mms');
							}
						}
					}
					if (isset($group['call'])) {
						$this->writer->writeElement('VOICE_FREEUSAGE', $subscriber_group_usage_VOICE_FREEUSAGE);
						$this->writer->writeElement('VOICE_ABOVEFREECOST', $subscriber_group_usage_VOICE_ABOVEFREECOST);
						$this->writer->writeElement('VOICE_ABOVEFREEUSAGE', $subscriber_group_usage_VOICE_ABOVEFREEUSAGE);
						$this->writer->writeElement('VOICE_CAPACITY', $group['call']);
					}
					if (isset($group['sms'])) {
						$this->writer->writeElement('SMS_FREEUSAGE', $subscriber_group_usage_SMS_FREEUSAGE);
						$this->writer->writeElement('SMS_ABOVEFREECOST', $subscriber_group_usage_SMS_ABOVEFREECOST);
						$this->writer->writeElement('SMS_ABOVEFREEUSAGE', $subscriber_group_usage_SMS_ABOVEFREEUSAGE);
						$this->writer->writeElement('SMS_CAPACITY', $group['sms']);
					}
					if (isset($group['data'])) {
						$this->writer->writeElement('DATA_FREEUSAGE', $subscriber_group_usage_DATA_FREEUSAGE);
						$this->writer->writeElement('DATA_ABOVEFREECOST', $subscriber_group_usage_DATA_ABOVEFREECOST);
						$this->writer->writeElement('DATA_ABOVEFREEUSAGE', $subscriber_group_usage_DATA_ABOVEFREEUSAGE);
						$this->writer->writeElement('DATA_CAPACITY', $group['data']);
					}
					if (isset($group['mms'])) {
						$this->writer->writeElement('MMS_FREEUSAGE', $subscriber_group_usage_MMS_FREEUSAGE);
						$this->writer->writeElement('MMS_ABOVEFREECOST', $subscriber_group_usage_MMS_ABOVEFREECOST);
						$this->writer->writeElement('MMS_ABOVEFREEUSAGE', $subscriber_group_usage_MMS_ABOVEFREEUSAGE);
						$this->writer->writeElement('MMS_CAPACITY', $group['mms']);
					}
					$this->writer->endElement(); // end SUBSCRIBER_GROUP_USAGE
				}
			}

			$this->writer->startElement('SUBSCRIBER_SUMUP');
			$this->writer->writeElement('TOTAL_GIFT', $subscriber_gift_usage_TOTAL_FREE_COUNTER_COST);
//			$subscriber_sumup->TOTAL_ABOVE_GIFT = floatval((isset($subscriber['costs']['over_plan']['vatable']) ? $subscriber['costs']['over_plan']['vatable'] : 0) + (isset($subscriber['costs']['out_plan']['vatable']) ? $subscriber['costs']['out_plan']['vatable'] : 0)); // vatable over/out plan cost
			$subscriber_sumup_TOTAL_ABOVE_GIFT = floatval((isset($subscriber['costs']['over_plan']['vatable']) ? $subscriber['costs']['over_plan']['vatable'] : 0));
			$this->writer->writeElement('TOTAL_ABOVE_GIFT', $subscriber_sumup_TOTAL_ABOVE_GIFT); // vatable overplan cost
			$subscriber_sumup_TOTAL_OUTSIDE_GIFT_VAT = floatval(isset($subscriber['costs']['out_plan']['vatable']) ? $subscriber['costs']['out_plan']['vatable'] : 0);
			$this->writer->writeElement('TOTAL_OUTSIDE_GIFT_VAT', $subscriber_sumup_TOTAL_OUTSIDE_GIFT_VAT);
			$subscriber_sumup_TOTAL_MANUAL_CORRECTION_CHARGE = floatval(isset($subscriber['costs']['credit']['charge']['vatable']) ? $subscriber['costs']['credit']['charge']['vatable'] : 0) + floatval(isset($subscriber['costs']['credit']['charge']['vat_free']) ? $subscriber['costs']['credit']['charge']['vat_free'] : 0);
			$this->writer->writeElement('TOTAL_MANUAL_CORRECTION_CHARGE', $subscriber_sumup_TOTAL_MANUAL_CORRECTION_CHARGE);
			$subscriber_sumup_TOTAL_MANUAL_CORRECTION_CREDIT = floatval(isset($subscriber['costs']['credit']['refund']['vatable']) ? $subscriber['costs']['credit']['refund']['vatable'] : 0) + floatval(isset($subscriber['costs']['credit']['refund']['vat_free']) ? $subscriber['costs']['credit']['refund']['vat_free'] : 0);
			$this->writer->writeElement('TOTAL_MANUAL_CORRECTION_CREDIT', $subscriber_sumup_TOTAL_MANUAL_CORRECTION_CREDIT);
			$this->writer->writeElement('TOTAL_MANUAL_CORRECTION_CREDIT_PROMOTION', $subscriber_sumup_TOTAL_MANUAL_CORRECTION_CREDIT_PROMOTION);
			$subscriber_sumup_TOTAL_MANUAL_CORRECTION = floatval($subscriber_sumup_TOTAL_MANUAL_CORRECTION_CHARGE) + floatval($subscriber_sumup_TOTAL_MANUAL_CORRECTION_CREDIT);
			$this->writer->writeElement('TOTAL_MANUAL_CORRECTION', $subscriber_sumup_TOTAL_MANUAL_CORRECTION);
			$this->writer->writeElement('TOTAL_MANUAL_CORRECTION_CREDIT_FIXED', $subscriber_sumup_TOTAL_MANUAL_CORRECTION_CREDIT_FIXED);
			$this->writer->writeElement('TOTAL_MANUAL_CORRECTION_CHARGE_FIXED', $subscriber_sumup_TOTAL_MANUAL_CORRECTION_CHARGE_FIXED);
			$this->writer->writeElement('TOTAL_MANUAL_CORRECTION_REFUND_FIXED', $subscriber_sumup_TOTAL_MANUAL_CORRECTION_REFUND_FIXED);
			$subscriber_sumup_TOTAL_OUTSIDE_GIFT_NOVAT = floatval((isset($subscriber['costs']['out_plan']['vat_free']) ? $subscriber['costs']['out_plan']['vat_free'] : 0)) + floatval((isset($subscriber['costs']['over_plan']['vat_free']) ? $subscriber['costs']['over_plan']['vat_free'] : 0));
			$this->writer->writeElement('TOTAL_OUTSIDE_GIFT_NOVAT', $subscriber_sumup_TOTAL_OUTSIDE_GIFT_NOVAT);
			$subscriber_sumup_TOTAL_DID_PREMIUM = floatval((isset($subscriber['costs']['service']['vat_free']) ? $subscriber['costs']['service']['vat_free'] : 0)) + floatval((isset($subscriber['costs']['service']['vatable']) ? $subscriber['costs']['service']['vatable'] : 0));
			$this->writer->writeElement('TOTAL_DID_PREMIUM', $subscriber_sumup_TOTAL_DID_PREMIUM);
			$subscriber_before_vat = $this->getSubscriberTotalBeforeVat($subscriber);
			$subscriber_after_vat = $this->getSubscriberTotalAfterVat($subscriber);
			$this->writer->writeElement('TOTAL_VAT', $subscriber_after_vat - $subscriber_before_vat);
			$this->writer->writeElement('TOTAL_CHARGE_NO_VAT', $subscriber_before_vat);
			$this->writer->writeElement('TOTAL_CHARGE', $subscriber_after_vat);
			
			$subscriber_sumup_ACTIVATION_DATE = isset($subscriber['activation_start']) ? $subscriber['activation_start'] : 0;
			if ($subscriber_sumup_ACTIVATION_DATE){
				$this->writer->writeElement('ACTIVATION_DATE', $subscriber_sumup_ACTIVATION_DATE);
			}	
			$subscriber_sumup_DEACTIVATION_DATE = isset($subscriber['activation_end']) ? $subscriber['activation_end'] : 0;
			if ($subscriber_sumup_DEACTIVATION_DATE) {
				$this->writer->writeElement('DEACTIVATION_DATE', $subscriber_sumup_DEACTIVATION_DATE);
			}
			$subscriber_sumup_FRACTION_OF_MONTH = floatval((isset($subscriber['fraction']) ? $subscriber['fraction'] : 0));
			$this->writer->writeElement('FRACTION_OF_MONTH', $subscriber_sumup_FRACTION_OF_MONTH);

			$subscriber_sumup_FREEZE_AMOUNT = floatval((isset($subscriber['freeze_amount']) ? $subscriber['freeze_amount'] : 0));
			$this->writer->writeElement('FREEZE_AMOUNT', $subscriber_sumup_FREEZE_AMOUNT);

			$invoice_total_gift+= $subscriber_gift_usage_TOTAL_FREE_COUNTER_COST;
			$invoice_total_above_gift+= $subscriber_sumup_TOTAL_ABOVE_GIFT;
			$invoice_total_outside_gift_vat+= $subscriber_sumup_TOTAL_OUTSIDE_GIFT_VAT;
			$invoice_total_manual_correction += $subscriber_sumup_TOTAL_MANUAL_CORRECTION;
			$invoice_total_manual_correction_credit += $subscriber_sumup_TOTAL_MANUAL_CORRECTION_CREDIT;
			$invoice_total_manual_correction_charge += $subscriber_sumup_TOTAL_MANUAL_CORRECTION_CHARGE;
			$invoice_total_manual_correction_credit_fixed += $subscriber_sumup_TOTAL_MANUAL_CORRECTION_CREDIT_FIXED;
			$invoice_total_manual_correction_charge_fixed += $subscriber_sumup_TOTAL_MANUAL_CORRECTION_CHARGE_FIXED;
			$invoice_total_manual_correction_refund_fixed += $subscriber_sumup_TOTAL_MANUAL_CORRECTION_REFUND_FIXED;
			$invoice_total_outside_gift_novat +=$subscriber_sumup_TOTAL_OUTSIDE_GIFT_NOVAT;
			$invoice_total_did_premium += $subscriber_sumup_TOTAL_DID_PREMIUM;
			$this->writer->endElement(); // end SUBSCRIBER_SUMUP
						
			$this->writer->startElement('SUBSCRIBER_BREAKDOWN');
			$this->writer->startElement('BREAKDOWN_TOPIC');
			$this->writer->writeAttribute('name', 'GIFT_XXX_OUT_OF_USAGE');
			$this->writer->startElement('BREAKDOWN_ENTRY');
			$this->writer->writeElement('TITLE', 'SERVICE-GIFT-GC_GOLAN-' . $this->plan_to_charge);
			$this->writer->writeElement('UNITS', 1);
			$out_of_usage_entry_COST_WITHOUTVAT = isset($subscriber['breakdown']['in_plan']['base']['service']['cost']) ? $subscriber['breakdown']['in_plan']['base']['service']['cost'] : 0;
			$this->writer->writeElement('COST_WITHOUTVAT', $out_of_usage_entry_COST_WITHOUTVAT);
			$out_of_usage_entry_VAT = $this->displayVAT($billrun['vat']);
			$this->writer->writeElement('VAT', $out_of_usage_entry_VAT);
			$out_of_usage_entry_VAT_COST = $out_of_usage_entry_COST_WITHOUTVAT * $out_of_usage_entry_VAT / 100;
			$this->writer->writeElement('VAT_COST', $out_of_usage_entry_VAT_COST);
			$this->writer->writeElement('TOTAL_COST', $out_of_usage_entry_COST_WITHOUTVAT + $out_of_usage_entry_VAT_COST);
			$this->writer->writeElement('TYPE_OF_BILLING', 'GIFT');
			$this->writer->endElement();
			$over_plan_base = isset($subscriber['breakdown']['over_plan']['base']) && is_array($subscriber['breakdown']['over_plan']['base']) ? $subscriber['breakdown']['over_plan']['base'] : array();
			$out_plan_base = isset($subscriber['breakdown']['out_plan']['base']) && is_array($subscriber['breakdown']['out_plan']['base']) ? $subscriber['breakdown']['out_plan']['base'] : array();
			$over_out_plan_base = array_merge_recursive($over_plan_base, $out_plan_base);
			foreach ($over_out_plan_base as $zone_name => $zone) {
				if ($zone_name != 'service') {
//							$out_of_usage_entry->addChild('TITLE', ?);
					foreach (array('call', 'sms', 'data', 'incoming_call', 'mms', 'incoming_sms') as $type) {
						$usagev = $this->getZoneTotalsFieldByUsage($zone, 'usagev', $type);
						if ($usagev > 0) {
							$this->writer->startElement('BREAKDOWN_ENTRY');
							$this->writer->writeElement('TITLE', $this->getBreakdownEntryTitle($this->getTariffKind($type), $zone_name));
							$this->writer->writeElement('UNITS', ($type == "data" ? $this->bytesToKB($usagev) : $usagev));
							$out_of_usage_entry_COST_WITHOUTVAT = $this->getZoneTotalsFieldByUsage($zone, 'cost', $type);
							$this->writer->writeElement('COST_WITHOUTVAT', $out_of_usage_entry_COST_WITHOUTVAT);
							$out_of_usage_entry_VAT = $this->displayVAT($this->getZoneVat($zone));
							$this->writer->writeElement('VAT', $out_of_usage_entry_VAT);
							$out_of_usage_entry_VAT_COST = $out_of_usage_entry_COST_WITHOUTVAT * $out_of_usage_entry_VAT / 100;
							$this->writer->writeElement('VAT_COST', $out_of_usage_entry_VAT_COST);
							$this->writer->writeElement('TOTAL_COST', $out_of_usage_entry_COST_WITHOUTVAT + $out_of_usage_entry_VAT_COST);
							$this->writer->writeElement('TYPE_OF_BILLING', strtoupper($type));
							$this->writer->endElement();
						}
					}
				}
			}
			$this->writer->endElement(); // end BREAKDOWN_TOPIC

			$this->writer->startElement('BREAKDOWN_TOPIC');
			$this->writer->writeAttribute('name', 'INTERNATIONAL');
			$subscriber_intl = array();
			if (isset($subscriber['breakdown']) && is_array($subscriber['breakdown'])) {
				foreach ($subscriber['breakdown'] as $plan) {
					if (isset($plan['intl'])) {
						foreach ($plan['intl'] as $zone_name => $zone) {
							foreach ($zone['totals'] as $usage_type => $usage_totals) {
								if ($usage_totals['cost'] > 0 || $usage_totals['usagev'] > 0) {
									if (isset($subscriber_intl[$zone_name][$usage_type])) {
										$subscriber_intl[$zone_name]['totals'][$usage_type]['usagev']+=$usage_totals['usagev'];
										$subscriber_intl[$zone_name]['totals'][$usage_type]['cost']+=$usage_totals['cost'];
									} else {
										$subscriber_intl[$zone_name]['totals'][$usage_type]['usagev'] = $usage_totals['usagev'];
										$subscriber_intl[$zone_name]['totals'][$usage_type]['cost'] = $usage_totals['cost'];
										$subscriber_intl[$zone_name]['vat'] = $zone['vat'];
									}
								}
							}
						}
					}
				}
			}
			foreach ($subscriber_intl as $zone_name => $zone) {
				foreach ($zone['totals'] as $usage_type => $usage_totals) {
					$this->writer->startElement('BREAKDOWN_ENTRY');
					$this->writer->writeElement('TITLE', $this->getBreakdownEntryTitle($this->getTariffKind($usage_type), $zone_name));
					$this->writer->writeElement('UNITS', $usage_totals['usagev']);
					$international_entry_COST_WITHOUTVAT = $usage_totals['cost'];
					$this->writer->writeElement('COST_WITHOUTVAT', $international_entry_COST_WITHOUTVAT);
					$international_entry_VAT = $this->displayVAT($zone['vat']);
					$this->writer->writeElement('VAT', $international_entry_VAT);
					$international_entry_VAT_COST = $international_entry_COST_WITHOUTVAT * $international_entry_VAT / 100;
					$this->writer->writeElement('VAT_COST', $international_entry_VAT_COST);
					$this->writer->writeElement('TOTAL_COST', $international_entry_COST_WITHOUTVAT + $international_entry_VAT_COST);
					$this->writer->writeElement('TYPE_OF_BILLING', strtoupper($usage_type));
					$this->writer->endElement();
				}
			}
			$this->writer->endElement();

			$this->writer->startElement('BREAKDOWN_TOPIC');
			$this->writer->writeAttribute('name', 'SPECIAL_SERVICES');
			$subscriber_special = array();
			if (isset($subscriber['breakdown']) && is_array($subscriber['breakdown'])) {
				foreach ($subscriber['breakdown'] as $plan) {
					if (isset($plan['special'])) {
						foreach ($plan['special'] as $zone_name => $zone) {
							foreach ($zone['totals'] as $usage_type => $usage_totals) {
								if ($usage_totals['cost'] > 0 || $usage_totals['usagev'] > 0) {
									if (isset($subscriber_special[$zone_name][$usage_type])) {
										$subscriber_special[$zone_name]['totals'][$usage_type]['usagev']+=$usage_totals['usagev'];
										$subscriber_special[$zone_name]['totals'][$usage_type]['cost']+=$usage_totals['cost'];
									} else {
										$subscriber_special[$zone_name]['totals'][$usage_type]['usagev'] = $usage_totals['usagev'];
										$subscriber_special[$zone_name]['totals'][$usage_type]['cost'] = $usage_totals['cost'];
										$subscriber_special[$zone_name]['vat'] = $zone['vat'];
									}
								}
							}
						}
					}
				}
			}
			foreach ($subscriber_special as $zone_name => $zone) {
				foreach ($zone['totals'] as $usage_type => $usage_totals) {
					$this->writer->startElement('BREAKDOWN_ENTRY');
					$this->writer->writeElement('TITLE', $this->getBreakdownEntryTitle($this->getTariffKind($usage_type), $zone_name));
					$this->writer->writeElement('UNITS', $usage_totals['usagev']);
					$special_entry_COST_WITHOUTVAT = $usage_totals['cost'];
					$this->writer->writeElement('COST_WITHOUTVAT', $special_entry_COST_WITHOUTVAT);
					$special_entry_VAT = $this->displayVAT($zone['vat']);
					$this->writer->writeElement('VAT', $special_entry_VAT);
					$special_entry_VAT_COST = $special_entry_COST_WITHOUTVAT * $special_entry_VAT / 100;
					$this->writer->writeElement('VAT_COST', $special_entry_VAT_COST);
					$this->writer->writeElement('TOTAL_COST', $special_entry_COST_WITHOUTVAT + $special_entry_VAT_COST);
					$this->writer->writeElement('TYPE_OF_BILLING', strtoupper($usage_type));
					$this->writer->endElement();
				}
			}
			$this->writer->endElement();

			$this->writer->startElement('BREAKDOWN_TOPIC');
			$this->writer->writeAttribute('name', 'ROAMING');
			$subscriber_roaming = array();
			if (isset($subscriber['breakdown']) && is_array($subscriber['breakdown'])) {
				foreach ($subscriber['breakdown'] as $plan) {
					if (isset($plan['roaming'])) {
						foreach ($plan['roaming'] as $zone_name => $zone) {
							foreach ($zone['totals'] as $usage_type => $usage_totals) {
								if ($usage_totals['cost'] > 0 || $usage_totals['usagev'] > 0) {
									if (isset($subscriber_roaming[$zone_name]['totals'][$usage_type])) {
										$subscriber_roaming[$zone_name]['totals'][$usage_type]['usagev']+=$usage_totals['usagev'];
										$subscriber_roaming[$zone_name]['totals'][$usage_type]['cost']+=$usage_totals['cost'];
									} else {
										$subscriber_roaming[$zone_name]['totals'][$usage_type]['usagev'] = $usage_totals['usagev'];
										$subscriber_roaming[$zone_name]['totals'][$usage_type]['cost'] = $usage_totals['cost'];
										$subscriber_roaming[$zone_name]['vat'] = $zone['vat'];
									}
								}
							}
						}
					}
				}
			}
			foreach ($subscriber_roaming as $zone_key => $zone) {
				$this->writer->startElement('BREAKDOWN_SUBTOPIC');
				$this->writer->writeAttribute('name', '');
				$this->writer->writeAttribute('plmn', $zone_key);
				foreach ($zone['totals'] as $usage_type => $usage_totals) {
					$this->writer->startElement('BREAKDOWN_ENTRY');
					$this->writer->writeElement('TITLE', $this->getBreakdownEntryTitle($usage_type, $this->getNsoftRoamingRate($usage_type)));
					$this->writer->writeElement('UNITS', ($usage_type == "data" ? $this->bytesToKB($usage_totals['usagev']) : $usage_totals['usagev']));
					$roaming_entry_COST_WITHOUTVAT = $usage_totals['cost'];
					$this->writer->writeElement('COST_WITHOUTVAT', $roaming_entry_COST_WITHOUTVAT);
					$roaming_entry_VAT = $this->displayVAT($zone['vat']);
					$this->writer->writeElement('VAT', $roaming_entry_VAT);
					$roaming_entry_VAT_COST = $roaming_entry_COST_WITHOUTVAT * $roaming_entry_VAT / 100;
					$this->writer->writeElement('VAT_COST', $roaming_entry_VAT_COST);
					$this->writer->writeElement('TOTAL_COST', $roaming_entry_COST_WITHOUTVAT + $roaming_entry_VAT_COST);
					$this->writer->writeElement('TYPE_OF_BILLING', strtoupper($usage_type));
					$this->writer->endElement();
				}
				$this->writer->endElement();
			}
			$this->writer->endElement();

			$this->writer->startElement('BREAKDOWN_TOPIC');
			$this->writer->writeAttribute('name', 'CHARGE_PER_CLI');
			if (isset($subscriber['breakdown']['credit']['charge_vatable']) && is_array($subscriber['breakdown']['credit']['charge_vatable'])) {
				foreach ($subscriber['breakdown']['credit']['charge_vatable'] as $reason => $cost) {
					$this->writer->startElement('BREAKDOWN_ENTRY');
					$this->writer->writeElement('TITLE', $this->getBreakdownEntryTitle($this->getTariffKind("credit"), $reason));
					$this->writer->writeElement('UNITS', 1);
					$charge_entry_COST_WITHOUTVAT = $cost;
					$this->writer->writeElement('COST_WITHOUTVAT', $charge_entry_COST_WITHOUTVAT);
					$charge_entry_VAT = $this->displayVAT($billrun['vat']);
					$this->writer->writeElement('VAT', $charge_entry_VAT);
					$charge_entry_VAT_COST = $charge_entry_COST_WITHOUTVAT * $charge_entry_VAT / 100;
					$this->writer->writeElement('VAT_COST', $charge_entry_VAT_COST);
					$this->writer->writeElement('TOTAL_COST', $charge_entry_COST_WITHOUTVAT + $charge_entry_VAT_COST);
					$this->writer->endElement();
				}
			}
			if (isset($subscriber['breakdown']['credit']['charge_vat_free']) && is_array($subscriber['breakdown']['credit']['charge_vat_free'])) {
				foreach ($subscriber['breakdown']['credit']['charge_vat_free'] as $reason => $cost) {
					$this->writer->startElement('BREAKDOWN_ENTRY');
					$this->writer->writeElement('TITLE', $this->getBreakdownEntryTitle($this->getTariffKind("credit"), $reason));
					$this->writer->writeElement('UNITS', 1);
					$charge_entry_COST_WITHOUTVAT = $cost;
					$this->writer->writeElement('COST_WITHOUTVAT', $charge_entry_COST_WITHOUTVAT);
					$charge_entry_VAT = 0;
					$this->writer->writeElement('VAT', $charge_entry_VAT);
					$charge_entry_VAT_COST = $charge_entry_COST_WITHOUTVAT * $charge_entry_VAT / 100;
					$this->writer->writeElement('VAT_COST', $charge_entry_VAT_COST);
					$this->writer->writeElement('TOTAL_COST', $charge_entry_COST_WITHOUTVAT + $charge_entry_VAT_COST);
					$this->writer->endElement();
				}
			}
			$this->writer->endElement();

			$this->writer->startElement('BREAKDOWN_TOPIC');
			$this->writer->writeAttribute('name', 'REFUND_PER_CLI');
			if (isset($subscriber['breakdown']['credit']['refund_vatable']) && is_array($subscriber['breakdown']['credit']['refund_vatable'])) {
				foreach ($subscriber['breakdown']['credit']['refund_vatable'] as $reason => $cost) {
					$this->writer->startElement('BREAKDOWN_ENTRY');
					$this->writer->writeElement('TITLE', $this->getBreakdownEntryTitle($this->getTariffKind("credit"), $reason));
					$this->writer->writeElement('UNITS', 1);
					$refund_entry_COST_WITHOUTVAT = $cost;
					$this->writer->writeElement('COST_WITHOUTVAT', $refund_entry_COST_WITHOUTVAT);
					$refund_entry_VAT = $this->displayVAT($billrun['vat']);
					$this->writer->writeElement('VAT', $refund_entry_VAT);
					$refund_entry_VAT_COST = $refund_entry_COST_WITHOUTVAT * $refund_entry_VAT / 100;
					$this->writer->writeElement('VAT_COST', $refund_entry_VAT_COST);
					$this->writer->writeElement('TOTAL_COST', $refund_entry_COST_WITHOUTVAT + $refund_entry_VAT_COST);
					$this->writer->endElement();
				}
			}
			if (isset($subscriber['breakdown']['credit']['refund_vat_free']) && is_array($subscriber['breakdown']['credit']['refund_vat_free'])) {
				foreach ($subscriber['breakdown']['credit']['refund_vat_free'] as $reason => $cost) {
					$this->writer->startElement('BREAKDOWN_ENTRY');
					$this->writer->writeElement('TITLE', $this->getBreakdownEntryTitle($this->getTariffKind("credit"), $reason));
					$this->writer->writeElement('UNITS', 1);
					$refund_entry_COST_WITHOUTVAT = $cost;
					$this->writer->writeElement('COST_WITHOUTVAT', $refund_entry_COST_WITHOUTVAT);
					$refund_entry_VAT = 0;
					$this->writer->writeElement('VAT', $refund_entry_VAT);
					$refund_entry_VAT_COST = $refund_entry_COST_WITHOUTVAT * $refund_entry_VAT / 100;
					$this->writer->writeElement('VAT_COST', $refund_entry_VAT_COST);
					$this->writer->writeElement('TOTAL_COST', $refund_entry_COST_WITHOUTVAT + $refund_entry_VAT_COST);
					$this->writer->endElement();
				}
			}
			$this->writer->endElement(); // end BREAKDOWN_TOPIC
			$this->writer->endElement(); // end SUBSCRIBER_BREAKDOWN
			$this->writer->endElement(); // end SUBSCRIBER_INF
			$this->flush();
		}

		$this->writer->startElement('INV_INVOICE_TOTAL');
		$this->writer->writeElement('INVOICE_NUMBER', $this->getInvoiceId($billrun));
		$this->writer->writeElement('FIRST_GENERATION_TIME', $this->getFlatStartDate());
		$this->writer->writeElement('FROM_PERIOD', date('Y/m/d', Billrun_Util::getStartTime($billrun_key)));
		$this->writer->writeElement('TO_PERIOD', date('Y/m/d', Billrun_Util::getEndTime($billrun_key)));
		$this->writer->writeElement('SUBSCRIBER_COUNT', count($billrun['subs']));
		$this->writer->writeElement('CUR_MONTH_CADENCE_START', $this->getExtrasStartDate());
		$this->writer->writeElement('CUR_MONTH_CADENCE_END', $this->getExtrasEndDate());
		$this->writer->writeElement('NEXT_MONTH_CADENCE_START', $this->getFlatStartDate());
		$this->writer->writeElement('NEXT_MONTH_CADENCE_END', $this->getFlatEndDate());
		$account_before_vat = $this->getAccTotalBeforeVat($billrun);
		$account_after_vat = $this->getAccTotalAfterVat($billrun);
		$this->writer->writeElement('TOTAL_CHARGE', $account_after_vat);
		$this->writer->writeElement('TOTAL_CREDIT', $invoice_total_manual_correction_credit);
		$this->writer->writeElement('GIFTS');
		$this->writer->startElement('INVOICE_SUMUP');
		$this->writer->writeElement('TOTAL_GIFT', $invoice_total_gift);
		$this->writer->writeElement('TOTAL_ABOVE_GIFT', $invoice_total_above_gift);
		$this->writer->writeElement('TOTAL_OUTSIDE_GIFT_VAT', $invoice_total_outside_gift_vat);
		$this->writer->writeElement('TOTAL_MANUAL_CORRECTION', $invoice_total_manual_correction);
		$this->writer->writeElement('TOTAL_MANUAL_CORRECTION_CREDIT', $invoice_total_manual_correction_credit);
		$this->writer->writeElement('TOTAL_MANUAL_CORRECTION_CHARGE', $invoice_total_manual_correction_charge);
		$this->writer->writeElement('TOTAL_MANUAL_CORRECTION_CREDIT_FIXED', $invoice_total_manual_correction_credit_fixed);
		$this->writer->writeElement('TOTAL_MANUAL_CORRECTION_CHARGE_FIXED', $invoice_total_manual_correction_charge_fixed);
		$this->writer->writeElement('TOTAL_MANUAL_CORRECTION_REFUND_FIXED', $invoice_total_manual_correction_refund_fixed);
		$this->writer->writeElement('TOTAL_DID_PREMIUM', $invoice_total_did_premium);
		$this->writer->writeElement('TOTAL_OUTSIDE_GIFT_NOVAT', $invoice_total_outside_gift_novat);
		$this->writer->writeElement('TOTAL_VAT', $account_after_vat - $account_before_vat);
		$this->writer->writeElement('TOTAL_CHARGE_NO_VAT', $account_before_vat);
		$this->writer->writeElement('TOTAL_CHARGE', $account_after_vat);
		$this->writer->endElement(); // end INVOICE_SUMUP
		$this->writer->endElement(); // end INV_INVOICE_TOTAL

		$this->endInvoice();

		$this->writer->endDocument();
		$this->flush();
	}

	/**
	 * 
	 * @param type $fileName
	 * @param type $xmlContent
	 * @return type
	 * @todo do not override files?
	 */
	protected function createXmlFile($fileName, $xmlContent) {
		Billrun_Factory::log()->log("create xml file " . $fileName, Zend_Log::INFO);
		$path = $this->export_directory . '/' . $fileName;
		$ret = file_put_contents($path, $xmlContent);
		return $ret;
	}

//	/**
//	 * 
//	 * @param array $subscriber subscriber billrun entry
//	 * @return type
//	 */
//	protected function get_subscriber_lines_refs($subscriber) {
//		$refs = array();
//		if (isset($subscriber['lines'])) {
//			foreach ($subscriber['lines'] as $usage_type => $lines_by_usage_type) {
//				if ($usage_type != 'data' && isset($lines_by_usage_type["refs"]) && is_array($lines_by_usage_type["refs"])) {
//					$refs = array_merge($refs, $lines_by_usage_type["refs"]);
//				}
//			}
//		}
//		return $refs;
//	}

	/**
	 * 
	 * @param array $entity account or subscriber document (billrun collection)
	 * @return type
	 */
	protected function get_lines($entity) {
//		$start_time = new MongoDate(0);
		$end_time = new MongoDate(Billrun_Util::getEndTime($this->stamp));
		if (isset($entity['aid'])) {
			$field = 'aid';
		} else if (isset($entity['sid'])) {
			$field = 'sid';
		} else {
			// throw warning
			return false;
		}

		$filter = array(
			$field => $entity[$field],
		);
		$query = array_merge($filter, array(
			'urt' => array(
				'$lte' => $end_time, // to filter out next billrun lines
			),
			'billrun' => array(
				'$in' => array($this->stamp),
			),
			'type' => array(
				'$ne' => 'ggsn',
			),
		));

		$sort = array(
			$field => 1,
			'urt' => 1,
		);

		$lines = $this->lines_coll->query($query)->cursor()->fields($this->filter_fields)->sort($sort)->hint($sort);
		Billrun_Factory::log()->log('Pulling lines of ' . $field . ' ' . $entity[$field], Zend_Log::DEBUG);
		$ret = array();
		foreach ($lines as $line) {
			$ret[$line['stamp']] = $line;
		}
		Billrun_Factory::log()->log('Pulling lines of ' . $field . ' ' . $entity[$field] . ' - finished', Zend_Log::DEBUG);
		return $ret;
	}

	/**
	 * 
	 * @param array $subscriber subscriber billrun entry
	 * @return type
	 */
	protected function get_subscriber_aggregated_data_lines($subscriber) {
		$aggregated_lines = array();
		if (isset($subscriber['lines']['data']['counters'])) {
			foreach ($subscriber['lines']['data']['counters'] as $day => $data_by_day) {
				$aggregated_line = array();
				$aggregated_line['day'] = date_create_from_format("Ymd", $day)->format('Y/m/d 00:00:00');
				$aggregated_line['rate_key'] = $this->data_rate['key'];
				$aggregated_line['usage_volume'] = $this->bytesToKB($data_by_day['usagev']);
				$aggregated_line['aprice'] = $data_by_day['aprice'];
				$aggregated_line['tariff_kind'] = $this->getTariffKind('data');
				$aggregated_line['interval'] = $this->getIntervalByRate($this->data_rate, 'data');
				$aggregated_line['rate_price'] = $this->getPriceByRate($this->data_rate, 'data');
				$aggregated_line['discount_usage'] = $this->getDiscountUsageByPlanFlag($data_by_day['plan_flag']);
				$aggregated_lines[] = $aggregated_line;
			}
		}
		return $aggregated_lines;
	}

	protected function getUsageVolume($line) {
		if (isset($line['usagev']) && isset($line['usaget'])) {
			switch ($line['usaget']) {
				case 'call':
				case 'incoming_call':
				case 'sms':
				case 'mms':
				case 'incoming_sms':
				case 'service':
					return $line['usagev'];
				case 'data':
//					if ($line['type'] == 'tap3') {
//						$arate = $this->getRowRate($line); 
//						return $this->bytesToKB($line['usagev'], $arate['rates']['data']['rate'][0]['interval']);
//					} else {
					return $this->bytesToKB($line['usagev']);
//					}
				default:
					break;
			}
		}
		return 0;
	}

	protected function getCharge($line) {
		if (!($line['type'] == 'credit' && isset($line['credit_type']) && $line['credit_type'] == 'refund')) {
			return abs($line['aprice']);
		}
		return 0;
	}

	protected function getCredit($line) {
		if ($line['type'] == 'credit' && isset($line['credit_type']) && $line['credit_type'] == 'refund') {
			return abs($line['aprice']);
		}
		return 0;
	}

	protected function getAccessPrice($line) {
		$arate = $this->getRowRate($line);
		if (isset($line['usaget']) && isset($arate['rates'][$line['usaget']]['access'])) {
			return $arate['rates'][$line['usaget']]['access'];
		}
		return 0;
	}

	protected function getInterval($line) {
		$arate = $this->getRowRate($line);
		if (isset($line['usaget']) && isset($arate['rates'][$line['usaget']]['rate'][0]['interval'])) {
			return $this->getIntervalByRate($arate, $line['usaget']);
		}
		return 0;
	}

	protected function getIntervalByRate($rate, $usage_type) {
		$interval = $rate['rates'][$usage_type]['rate'][0]['interval'];
		if ($usage_type == 'data' && $rate['rates'][$usage_type]['category'] == 'roaming') {
			$interval = $interval / 1024;
		}
		return $interval;
	}

	protected function getPriceByRate($rate, $usage_type) {
		if (isset($rate['rates'][$usage_type]['rate'][0]['price']) && $usage_type != 'credit') {
			if (in_array($usage_type, array('call', 'data', 'incoming_call')) && isset($rate['rates'][$usage_type]['rate'][0]['interval']) && $rate['rates'][$usage_type]['rate'][0]['interval'] == 1) {
				return $rate['rates'][$usage_type]['rate'][0]['price'] * ($usage_type == 'data' ? 1024 : 60);
			}
			if ($usage_type == 'data' && $rate['rates'][$usage_type]['category'] == 'roaming') {
				return $rate['rates'][$usage_type]['rate'][0]['price'] * 1048576 / $rate['rates'][$usage_type]['rate'][0]['interval'];
			}
			return $rate['rates'][$usage_type]['rate'][0]['price'];
		}
		return 0;
	}

	protected function getRate($line) {
		$arate = $this->getRowRate($line);
		if (isset($line['usaget']) && $arate) {
			return $this->getPriceByRate($arate, $line['usaget']);
		}
		return 0;
	}

	/**
	 * Get a rate from the row
	 * @param Mongodloid_Entity the row to get rate from
	 * @return Mongodloid_Entity the rate of the row
	 */
	protected function getRowRate($row) {
		$rate = false;
		$raw_rate = $row->get('arate', true);
		if ($raw_rate) {
			$id_str = strval($raw_rate['$id']);
			$rate = $this->getRateById($id_str);
		}
		return $rate;
	}

	/**
	 * Get a rate by hexadecimal id
	 * @param string $id hexadecimal id of rate (taken from Mongo ID)
	 * @return Mongodloid_Entity the corresponding rate
	 */
	protected function getRateById($id) {
		if (!isset($this->rates[$id])) {
			$rates = Billrun_Factory::db()->ratesCollection();
			$this->rates[$id] = $rates->findOne($id);
		}
		return $this->rates[$id];
	}

	/**
	 * Get a rate by hexadecimal id
	 * @param string $id hexadecimal id of rate (taken from Mongo ID)
	 * @return Mongodloid_Entity the corresponding rate
	 */
	protected function getPlanById($id) {
		if (!isset($this->plans[$id])) {
			$plans_coll = Billrun_Factory::db()->plansCollection();
			$this->plans[$id] = $plans_coll->findOne($id);
		}
		return $this->plans[$id];
	}

	protected function getIntlFlag($line) {
		$arate = $this->getRowRate($line);
		if (isset($line['usaget']) && isset($arate['rates'][$line['usaget']]['category'])) {
			$category = $arate['rates'][$line['usaget']]['category'];
			if ($category == 'intl' || $category == 'roaming') {
				return 1;
			}
		}
		return 0;
	}

	protected function getTariffKind($usage_type) {
		switch ($usage_type) {
			case 'call':
				return 'Call';
			case 'data':
				return 'Internet Access';
			case 'sms':
				return 'SMS';
			case 'incoming_call':
				return 'Incoming Call';
			case 'mms':
				return 'MMS';
			case 'incoming_sms': // in theory...
				return 'Incoming SMS';
			case 'credit':
			case 'flat':
				return 'Service';
			default:
				return '';
		}
	}

	protected function getTariffItem($line, $subscriber) {
		$tariffItem = '';
		if ($line['type'] == 'flat') {
			$tariffItem = 'GIFT-GC_GOLAN-' . $this->plan_to_charge;
		} else if ($line['type'] == 'credit' && isset($line['service_name'])) {
			$tariffItem = $line['service_name'];
		} else {
			if ($line['type'] == 'tap3') {
				$tariffItem = $this->getNsoftRoamingRate($line['usaget']);
			} else {
				$arate = $this->getRowRate($line);
				if (isset($arate['key'])) {
					$tariffItem = $arate['key'];
				}
			}
		}
		return $tariffItem;
	}

	protected function getNsoftRoamingRate($usage_type) {
		switch ($usage_type) {
			case 'incoming_call':
			case 'incoming_sms':
				$rate = '$DEFAULT';
				break;
			case 'call':
			case 'sms':
			case 'mms': // a guess
				$rate = 'ROAM_ALL_DEST';
				break;
			case 'data':
				$rate = 'INTERNET_BILL_BY_VOLUME';
				break;
			default:
				$rate = '';
				break;
		}
		return $rate;
	}

	protected function getCallerNo($line) {
		$calling_number = '';
		if (isset($line['calling_number'])) {
			$calling_number = $line['calling_number'];
		}
		return $calling_number;
	}

	protected function getCalledNo($line) {
		$called_number = '';
		if ($line['type'] == 'tap3' // on tap3
			|| (isset($line['out_circuit_group']) && (in_array($line['out_circuit_group'], Billrun_Util::getIntlCircuitGroups())))) { // or call to abroad
			if ($line['usaget'] == 'incoming_call') {
				$called_number = $line['calling_number'];
			} else {
				$called_number = $line['called_number'];
			}
		} else if (isset($line['called_number'])) { // mmsc might not have called_number
			$called_number = $this->beautifyPhoneNumber($line['called_number']);
		}
		return $called_number;
	}

	protected function getDiscountUsage($line) {
		if (isset($line['out_plan']) || $line['type'] == 'credit') {
			$plan_flag = 'out';
		} else if (isset($line['over_plan']) && ($line['usagev'] == $line['over_plan'])) {
			$plan_flag = 'over';
		} else if ($line['type'] == 'flat' || (isset($line['over_plan']) && ($line['usagev'] > $line['over_plan']))) {
			$plan_flag = 'partial';
		} else {
			$plan_flag = 'in';
		}
		return $this->getDiscountUsageByPlanFlag($plan_flag);
	}

	protected function getDiscountUsageByPlanFlag($plan_flag) {
		switch ($plan_flag) {
			case 'over':
				return 'DISCOUNT_OUT';
			case 'out':
				return 'DISCOUNT_NONE';
			case 'partial':
				return 'DISCOUNT_PARTIAL';
			case 'in':
			default:
				return 'DISCOUNT_FULL';
		}
	}

	protected function write_basic_header($billrun) {
		$xml = <<<EOI
	<TELECOM_INFORMATION>
		<VAT_VALUE>{$this->displayVAT($billrun['vat'])}</VAT_VALUE>
	</TELECOM_INFORMATION>
	<INV_CUSTOMER_INFORMATION>
		<CUSTOMER_CONTACT>
			<EXTERNALACCOUNTREFERENCE>{$billrun['aid']}</EXTERNALACCOUNTREFERENCE>
		</CUSTOMER_CONTACT>
	</INV_CUSTOMER_INFORMATION>	
EOI;
		return $this->writer->writeRaw($xml);
	}

	protected function writeBillingLines($subscriber, $lines) {
		$sid = $subscriber['sid'];
		$this->writer->startElement('BILLING_LINES');

		if ($this->billingLinesNeeded($sid)) {
			if (is_null($lines)) {
				$subscriber_lines = $this->get_lines($subscriber);
			} else {
				$func = function($line) use ($sid) {
					return $line['sid'] == $sid;
				};
				$subscriber_lines = array_filter($lines, $func);
			}
			$subscriber_united_lines = $this->aggregateLinesByCallReference($subscriber_lines);
			$lines_counter = 0;
			foreach ($subscriber_united_lines as $line) {
				if (!$line->isEmpty() && $line['type'] != 'ggsn') {
					$lines_counter++;
					$line->collection($this->lines_coll);
					$this->writeBillingRecord($this->getDate($line), $this->getTariffItem($line, $subscriber), $this->getCalledNo($line), $this->getCallerNo($line), $this->getUsageVolume($line), $this->getCharge($line), $this->getCredit($line), $this->getTariffKind($line['usaget']), $this->getAccessPrice($line), $this->getInterval($line), $this->getRate($line), $this->getIntlFlag($line), $this->getDiscountUsage($line), $this->getRoaming($line), $this->getServingNetwork($line), $this->getLineTypeOfBillingChar($line));
					if ($lines_counter % $this->flush_size == 0) {
						$this->flush();
					}
				}
			}
			$subscriber_aggregated_data = $this->get_subscriber_aggregated_data_lines($subscriber);
			foreach ($subscriber_aggregated_data as $line) {
				$this->writeBillingRecord($line['day'], $line['rate_key'], '', '', $line['usage_volume'], $line['aprice'], 0, $line['tariff_kind'], 0, $line['interval'], $line['rate_price'], 0, $line['discount_usage'], 0, '', 'D');
			}
		}
		$this->writer->endElement(); // end BILLING_LINES
	}

	protected function flush() {
		if (!$this->buffer) {
			$this->writer->flush(true);
		}
	}

	/**
	 * Aggregate lines by call reference & call reference time (Handover)
	 * @param array $lines lines with stamp as their key, sorted by urt
	 * @return array
	 */
	protected function aggregateLinesByCallReference($lines) {
		$call_references = array();
		foreach ($lines as $stamp => $line) {
			if ($line['type'] == 'nsn' && isset($line['call_reference']) && isset($line['call_reference_time'])) {
				$unique_call_reference = $line['call_reference'] . $line['call_reference_time'];
				$call_references[$unique_call_reference][] = $stamp;
			}
		}
		foreach ($call_references as $stamps) {
			if (count($stamps) > 1) {
				for ($i = 1; $i < count($stamps); $i++) {
					$lines[$stamps[0]]['usagev']+=$lines[$stamps[$i]]['usagev'];
					$lines[$stamps[0]]['aprice']+=$lines[$stamps[$i]]['aprice'];
					$plan_flag = isset($lines[$stamps[$i]]['over_plan']) ? 'over_plan' : (isset($lines[$stamps[$i]]['out_plan']) ? 'out_plan' : false);
					if ($plan_flag) {
						if (isset($lines[$stamps[0]][$plan_flag])) {
							$lines[$stamps[0]][$plan_flag] += $lines[$stamps[$i]][$plan_flag];
						} else {
							$lines[$stamps[0]][$plan_flag] = $lines[$stamps[$i]][$plan_flag];
						}
					}
					unset($lines[$stamps[$i]]);
				}
			}
		}
		return $lines;
	}

	protected function startInvoice() {
		$this->writer->startElement('INVOICE');
		$this->writer->writeAttribute('version', $this->invoice_version);
	}

	protected function endInvoice() {
		$this->writer->endElement();
	}

	protected function setFileStamp($line, $filename) {
		$current = $line->getRawData();
		$added_values = array(
			'invoice_file' => $filename,
		);

		$newData = array_merge($current, $added_values);
		$line->setRawData($newData);
		$line->save(Billrun_Factory::db(array('name' => 'billrun'))->billrunCollection());
		return true;
	}

	/**
	 * 
	 * @param int $bytes
	 * @return int interval to ceil by in bytes
	 */
	protected function bytesToKB($bytes, $interval = null) {
		$bytes_to_price = $bytes;
		if (!is_null($interval)) {
			$bytes_to_price = ceil($bytes / $interval) * $interval;
		}
//		$ret = ceil($bytes_to_price / 1024); // we won't imitate nsoft here
		$ret = $bytes_to_price / 1024;
		return $ret;
	}

	/**
	 * 
	 * @param float $vat vat value
	 * @return mixed
	 */
	protected function displayVAT($vat) {
		return $vat * 100;
	}

	protected function getDate($line) {
		$timsetamp = $line['urt']->sec;
		if (isset($line['tzoffset'])) {
			// TODO change this to regex
			$tzoffset = $line['tzoffset'];
			$sign = substr($tzoffset, 0, 1);
			$hours = substr($tzoffset, 1, 2);
			$minutes = substr($tzoffset, 3, 2);
			$time = $hours . ' hours ' . $minutes . ' minutes';
			if ($sign == "-") {
				$time .= ' ago';
			}
			$timsetamp = strtotime($time, $timsetamp);
			$zend_date = new Zend_Date($timsetamp);
			$zend_date->setTimezone('UTC');
		} else {
			$zend_date = new Zend_Date($timsetamp);
		}
		return $this->getGolanDate($zend_date);
	}

	/**
	 * 
	 * @param Zend_Date $date
	 * @return type
	 */
	protected function getGolanDate($date) {
		return $date->toString('YYYY/MM/dd HH:mm:ss');
	}

	/**
	 * 
	 * @param array $subscriber the subscriber billrun entry
	 */
	protected function getPlanName($subscriber) {
		$current_plan_ref = $subscriber['current_plan'];
		if (MongoDBRef::isRef($current_plan_ref)) {
			$current_plan = $this->getPlanById(strval($current_plan_ref['$id']));
			$current_plan_name = $current_plan['name'];
		} else {
			$current_plan_name = '';
		}
		return $current_plan_name;
	}

	/**
	 * 
	 * @param array $subscriber the subscriber billrun entry
	 */
	protected function getNextPlanName($subscriber) {
		$next_plan_ref = $subscriber['next_plan'];
		if (MongoDBRef::isRef($next_plan_ref)) {
			$next_plan = $this->getPlanById(strval($next_plan_ref['$id']));
			$next_plan_name = $next_plan['name'];
		} else {
			$next_plan_name = '';
		}
		return $next_plan_name;
	}

	/**
	 * 
	 * @param array $subscriber subscriber entry from billrun collection
	 * @return array
	 */
	protected function getFlatCosts($subscriber) {
		$flat_costs = array();
		if (isset($subscriber['costs']['flat'])) {
			$flat_costs = $subscriber['costs']['flat'];
		}
		return $flat_costs;
	}

	protected function billingLinesNeeded($sid) {
		return true;
	}

	protected function getZoneTotalsFieldByUsage($zone, $field, $usage_type) {
		if (isset($zone['totals'][$usage_type][$field])) {
			if (is_array($zone['totals'][$usage_type][$field])) {
				return array_sum($zone['totals'][$usage_type][$field]);
			} else {
				return $zone['totals'][$usage_type][$field];
			}
		} else {
			return 0;
		}
	}

	protected function getZoneVat($zone) {
		if (isset($zone['vat'])) {
			if (is_array($zone['vat'])) {
				return current($zone['vat']);
			} else {
				return $zone['vat'];
			}
		} else {
			return 0;
		}
	}

	/**
	 * 
	 * @param array $subscriber subscriber entry from billrun collection
	 * @return int
	 */
	protected function getSubscriberTotalBeforeVat($subscriber) {
		return isset($subscriber['totals']['before_vat']) ? $subscriber['totals']['before_vat'] : 0;
	}

	/**
	 * 
	 * @param array $subscriber subscriber entry from billrun collection
	 * @return int
	 */
	protected function getSubscriberTotalAfterVat($subscriber) {
		return isset($subscriber['totals']['after_vat']) ? $subscriber['totals']['after_vat'] : 0;
	}

	protected function getAccTotalBeforeVat($row) {
		return isset($row['totals']['before_vat']) ? $row['totals']['before_vat'] : 0;
	}

	protected function getAccTotalAfterVat($row) {
		return isset($row['totals']['after_vat']) ? $row['totals']['after_vat'] : 0;
	}

	protected function getExtrasStartDate() {
		return date('d/m/Y', Billrun_Util::getStartTime($this->stamp));
	}

	protected function getExtrasEndDate() {
		return date('d/m/Y', Billrun_Util::getEndTime($this->stamp));
	}

	protected function getFlatStartDate() {
		return date('d/m/Y', strtotime('+ 1 month', Billrun_Util::getStartTime($this->stamp)));
	}

	protected function getFlatEndDate() {
		return date('d/m/Y', strtotime('+ 1 month', Billrun_Util::getEndTime($this->stamp)));
	}

	protected function getBreakdownEntryTitle($taarif_kind, $rate_key) {
		return str_replace(' ', '_', strtoupper($taarif_kind . '-' . $rate_key));
	}

	protected function writeBillingRecord($golan_date, $tariff_item, $called_number, $caller_number, $volume, $charge, $credit, $tariff_kind, $access_price, $interval, $rate, $intl_flag, $discount_usage, $roaming, $serving_network, $type_of_billing_char) {
		$this->writer->startElement('BILLING_RECORD');
		$this->writer->writeElement('TIMEOFBILLING', $golan_date);
		$this->writer->writeElement('TARIFFITEM', $tariff_item);
		$this->writer->writeElement('CTXT_CALL_OUT_DESTINATIONPNB', $called_number); //@todo maybe save dest_no in all processors and use it here
		$this->writer->writeElement('CTXT_CALL_IN_CLI', $caller_number); //@todo maybe save it in all processors and use it here
		$this->writer->writeElement('CHARGEDURATIONINSEC', $volume);
		$this->writer->writeElement('CHARGE', $charge);
		$this->writer->writeElement('CREDIT', $credit);
		$this->writer->writeElement('TARIFFKIND', $tariff_kind);
		$this->writer->writeElement('TTAR_ACCESSPRICE1', $access_price);
		$this->writer->writeElement('TTAR_SAMPLEDELAYINSEC1', $interval);
		$this->writer->writeElement('TTAR_SAMPPRICE1', $rate);
		$this->writer->writeElement('INTERNATIONAL', $intl_flag);
		$this->writer->writeElement('DISCOUNT_USAGE', $discount_usage);
		$this->writer->writeElement('ROAMING', $roaming);
		$this->writer->writeElement('SERVINGPLMN', $serving_network);
		$this->writer->writeElement('TYPE_OF_BILLING_CHAR', $type_of_billing_char);
		$this->writer->endElement();
	}

	protected function getDataRate() {
		$rates = Billrun_Factory::db()->ratesCollection();
		$query = array(
			'key' => 'INTERNET_BILL_BY_VOLUME',
			'from' => array(
				'$lte' => new MongoDate(Billrun_Util::getStartTime($this->stamp)),
			),
			'to' => array(
				'$gte' => new MongoDate(Billrun_Util::getStartTime($this->stamp)),
			),
		);
		return $rates->query($query)->cursor()->current();
	}

	/**
	 * Load all rates from db into memory
	 */
	protected function loadRates() {
		$rates_coll = Billrun_Factory::db()->ratesCollection();
		$rates = $rates_coll->query()->cursor();
		foreach ($rates as $rate) {
			$rate->collection($rates_coll);
			$this->rates[strval($rate->getId())] = $rate;
		}
		$this->data_rate = $this->getDataRate();
	}

	/**
	 * Load all rates from db into memory
	 */
	protected function loadPlans() {
		$plans_coll = Billrun_Factory::db()->plansCollection();
		$plans = $plans_coll->query()->cursor();
		foreach ($plans as $plan) {
			$plan->collection($plans_coll);
			$this->plans[strval($plan->getId())] = $plan;
		}
	}

	protected function beautifyPhoneNumber($phone_number) {
		$separator = "-";
		$phone_number = intval($phone_number);
		if (substr($phone_number, 0, 3) == "972") {
			$phone_number = intval(substr($phone_number, 3));
		}
		$length = strlen($phone_number);
		if ($length == 8) {
			$phone_number = "0" . substr($phone_number, 0, 1) . $separator . substr($phone_number, 1);
		} else if ($length == 9) {
			$phone_number = "0" . substr($phone_number, 0, 2) . $separator . substr($phone_number, 2);
		}
		return $phone_number;
	}

	protected function getRoaming($line) {
		return $line['type'] == 'tap3' ? 1 : 0;
	}

	protected function getServingNetwork($line) {
		return isset($line['serving_network']) ? $line['serving_network'] : '';
	}

	protected function getLineTypeOfBillingChar($line) {
		$type = $line['type'];
		$usaget = $line['usaget'];
		$char = '';
		if ($usaget == 'call') {
			$char = 'S';
		} else if ($usaget == 'sms') {
			$char = 'T';
		} else if ($type == 'credit') {
			$credit_type = $line['credit_type'];
			if ($credit_type == 'refund') {
				$char = 'R';
			} else if ($credit_type == 'charge') {
				$char = 'C';
			}
		} else if ($type == 'tap3') {
			if ($usaget == 'incoming_call') {
				$char = 'I';
			} else if ($usaget == 'data') {
				$char = 'W';
			}
		} else if ($type == 'flat') {
			$char = 'G';
		} else if ($type == 'mmsc') {
			$char = 'P';
		} else if ($type == 'ggsn') {
			$char = 'D';
		}
		return $char;
	}

	protected function getInvoiceId($row) {
		return $row['invoice_id'];
	}

	public function __destruct() {
		libxml_use_internal_errors(FALSE);
	}

}
