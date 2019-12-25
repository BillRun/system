<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012 S.D.O.C. LTD. All rights reserved.
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */

/**
 * Billing generator balance class
 * require to generate xml for specific account for balance api
 *
 * @package  Billing
 * @since    1.0
 */
class Billrun_Generator_Balance extends Billrun_Generator_Ilds {

	/**
	 * The VAT value including the complete; for example 1.17
	 * 
	 * @var float
	 */
	protected $aid;
	protected $subscribers;
	protected $stamp;

	public function __construct($options) {
		parent::__construct($options);
		$this->aid = $options['aid'];
		$this->stamp = $options['stamp'];
		$this->subscribers = $options['subscribers'];
	}

	/**
	 * load the container the need to be generate
	 */
	public function load() {
		$options = array(
			'type' => 'balance',
			'aid' => $this->aid,
			'subscribers' => $this->subscribers,
			'stamp' => $this->stamp,
			'buffer' => true,
			'bug_arg' => 'aggregator', //this to prevent BASE to create the same stamp and thus return the generator instead
		);
		$aggregator = Billrun_Aggregator::getInstance($options);
		$aggregator->load();
		$this->data = $aggregator->aggregate();

	}

	/**
	 * execute the generate action
	 */
	public function generate() {
		// generate xml
		return $this->xml();
	}

	protected function xml() {
		// use $this->export_directory
		$short_format_date = 'd/m/Y';
		$premium_providers = Billrun_Factory::config()->getConfigValue('premium.provider_ids', array());
		$add_header = true;
		foreach ($this->data as $row) {
			Billrun_Factory::log()->log("xml account " . $row->get('account_id'), Zend_Log::INFO);
			// @todo refactoring the xml generation to another class
			$xml = $this->basic_xml();
			$xml->TELECOM_INFORMATION->LASTTIMECDRPROCESSED = date('Y-m-d h:i:s');
			$xml->TELECOM_INFORMATION->VAT_VALUE = (string) (($this->vat * 100) - 100); //'17';
			$xml->TELECOM_INFORMATION->COMPANY_NAME_IN_ENGLISH = 'GOLAN';
			$xml->INV_CUSTOMER_INFORMATION->CUSTOMER_CONTACT->EXTERNALACCOUNTREFERENCE = $row->get('account_id');
			;
			$total_ilds = array();
			$stamps = array();
			foreach ($row->get('subscribers') as $id => $subscriber) {
				$subscriber_inf = $xml->addChild('SUBSCRIBER_INF');
				$subscriber_inf->SUBSCRIBER_DETAILS->SUBSCRIBER_ID = $id;
				$billing_records = $subscriber_inf->addChild('BILLING_LINES');

				$subscriber_lines = $this->get_subscriber_lines($id);
				foreach ($subscriber_lines as $line) {
					$stamps[] = $line['stamp'];
					$billing_record = $billing_records->addChild('BILLING_RECORD');
					if ($line['type'] == 'refund') {
						$this->addRefundLineXML($billing_record, $line);
					} else {
						$this->addIldLineXML($billing_record, $line);
					}
				}

				$subscriber_sumup = $subscriber_inf->addChild('SUBSCRIBER_SUMUP');
				$total_cost = 0;
				foreach ($subscriber['cost'] as $ild => $cost) {
					if (isset($total_ilds[$ild])) {
						$total_ilds[$ild] += $cost;
					} else {
						$total_ilds[$ild] = $cost;
					}
					if (in_array($ild, $premium_providers)) {
						$ild_xml = $subscriber_sumup->addChild('PREMIUM'); //change?
					} else {
						$ild_xml = $subscriber_sumup->addChild('ILD'); //change?					
					}
					$ild_xml->NDC = $ild;
					$ild_xml->CHARGE_EXCL_VAT = $cost;
					$ild_xml->CHARGE_INCL_VAT = $cost * $this->vat;
					$total_cost += $cost;
				}
				$subscriber_sumup->TOTAL_CHARGE_EXCL_VAT = $total_cost;
				$subscriber_sumup->TOTAL_CHARGE_INCL_VAT = $total_cost * $this->vat;
				// TODO create file with the xml content and file name of invoice number (ILD000123...)
			}

			$xml->INV_INVOICE_TOTAL->INVOICE_DATE = date($short_format_date);
			$xml->INV_INVOICE_TOTAL->FIRST_GENERATION_TIME = date($short_format_date);
			$xml->INV_INVOICE_TOTAL->FROM_PERIOD = date($short_format_date, strtotime('first day of previous month'));
			$xml->INV_INVOICE_TOTAL->TO_PERIOD = date($short_format_date, strtotime('last day of previous month'));
			$xml->INV_INVOICE_TOTAL->SUBSCRIBER_COUNT = count($row);
			$xml->INV_INVOICE_TOTAL->INVOICE_TYPE = "ilds"; //change?

			$invoice_sumup = $xml->INV_INVOICE_TOTAL->addChild('INVOICE_SUMUP');
			$total = 0;
			foreach ($total_ilds as $ild => $total_ild_cost) {
				if (in_array($ild, $premium_providers)) { //todo: change condition!
					$ild_xml = $invoice_sumup->addChild('PREMIUM'); //change?
				} else {
					$ild_xml = $invoice_sumup->addChild('ILD'); //change?					
				}
//				$ild_xml = $invoice_sumup->addChild('ILD'); //change?
				$ild_xml->NDC = $ild; //?
				$ild_xml->CHARGE_EXCL_VAT = $total_ild_cost;
				$ild_xml->CHARGE_INCL_VAT = $total_ild_cost * $this->vat;
				$total += $total_ild_cost;
			}
			$totalVat = $total * $this->vat;
			$invoice_id = $this->saveInvoiceId($row->get('account_id'), $this->createInvoiceId($totalVat));
			$xml->INV_INVOICE_TOTAL->INVOICE_NUMBER = $invoice_id;
			$invoice_sumup->TOTAL_EXCL_VAT = $total;
			$invoice_sumup->TOTAL_INCL_VAT = $totalVat;
			Billrun_Factory::log()->log("invoice id created " . $invoice_id . " for the account", Zend_Log::INFO);
			return $xml->asXML();
		}
	}


}
