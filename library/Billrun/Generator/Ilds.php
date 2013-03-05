<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012 S.D.O.C. LTD. All rights reserved.
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */

/**
 * Billing abstract generator ilds class
 * require to generate xml for each account
 * require to generate csv contain how much to credit each account
 *
 * @package  Billing
 * @since    1.0
 */
class Billrun_Generator_Ilds extends Billrun_Generator {
	/**
	 * The VAT value (TODO get from outside/config).
	 */

	const VAT_VALUE = 1.17;

	/**
	 * load the container the need to be generate
	 */
	public function load($initData = true) {
		$billrun = $this->db->getCollection(self::billrun_table);

		if ($initData) {
			$this->data = array();
		}

		$resource = $billrun->query()
			->equals('stamp', $this->getStamp())
//			->in('account_id', array('7024774','1218460', '8348358'))
			->notExists('invoice_id');

		foreach ($resource as $entity) {
			$this->data[] = $entity;
		}

		$this->log->log("aggregator entities loaded: " . count($this->data), Zend_Log::INFO);
		
		$this->dispatcher->trigger('afterGeneratorLoadData', array('generator' => $this));

	}

	/**
	 * execute the generate action
	 */
	public function generate() {
		// generate xml
		$this->xml($this->data);

		// generate csv
//		$this->csv();
	}

	protected function get_subscriber_lines($subscriber_id) {
		$lines = $this->db->getCollection(self::lines_table);

		$ret = array();

		$resource = $lines->query()
			->equals('billrun', $this->getStamp())
			->equals('subscriber_id', "$subscriber_id")
			->notExists('billrun_excluded')
			->cursor()->sort(array('call_start_dt' => 1));
			
		foreach ($resource as $entity) {
			$ret[] = $entity->getRawData();
		}

		return $ret;
	}

	protected function xml($rows) {
		// use $this->export_directory
		$short_format_date = 'd/m/Y';
		foreach ($rows as $row) {
			$this->log->log("xml account " . $row->get('account_id'), Zend_Log::INFO);
			// @todo refactoring the xml generation to another class
			$xml = $this->basic_xml();
			$xml->TELECOM_INFORMATION->LASTTIMECDRPROCESSED = date('Y-m-d h:i:s');
			$xml->TELECOM_INFORMATION->VAT_VALUE = '17';
			$xml->TELECOM_INFORMATION->COMPANY_NAME_IN_ENGLISH = 'GOLAN';
			$xml->INV_CUSTOMER_INFORMATION->CUSTOMER_CONTACT->EXTERNALACCOUNTREFERENCE = $row->get('account_id');
			;
			$total_ilds = array();
			foreach ($row->get('subscribers') as $id => $subscriber) {
				$subscriber_inf = $xml->addChild('SUBSCRIBER_INF');
				$subscriber_inf->SUBSCRIBER_DETAILS->SUBSCRIBER_ID = $id;
				$billing_records = $subscriber_inf->addChild('BILLING_LINES');

				$subscriber_lines = $this->get_subscriber_lines($id);
				foreach ($subscriber_lines as $line) {
					$billing_record = $billing_records->addChild('BILLING_RECORD');
					$billing_record->TIMEOFBILLING = $line['call_start_dt'];
					$billing_record->TARIFFITEM = 'IL_ILD';
					$billing_record->CTXT_CALL_OUT_DESTINATIONPNB = $line['called_no'];
					$billing_record->CTXT_CALL_IN_CLI = $line['caller_phone_no'];
					$billing_record->CHARGEDURATIONINSEC = $line['chrgbl_call_dur'];
					$billing_record->CHARGE = $line['price_customer'];
					$billing_record->TARIFFKIND = 'Call';
					$billing_record->INTERNATIONAL = '1';
					$billing_record->ILD = $line['type'];
				}

				$subscriber_sumup = $subscriber_inf->addChild('SUBSCRIBER_SUMUP');
				$total_cost = 0;
				foreach ($subscriber['cost'] as $ild => $cost) {
					if (isset($total_ilds[$ild])) {
						$total_ilds[$ild] += $cost;
					} else {
						$total_ilds[$ild] = $cost;
					}
					$ild_xml = $subscriber_sumup->addChild('ILD');
					$ild_xml->NDC = $ild;
					$ild_xml->CHARGE_EXCL_VAT = $cost;
					$ild_xml->CHARGE_INCL_VAT = $cost * self::VAT_VALUE;
					$total_cost += $cost;
				}
				$subscriber_sumup->TOTAL_CHARGE_EXCL_VAT = $total_cost;
				$subscriber_sumup->TOTAL_CHARGE_INCL_VAT = $total_cost * self::VAT_VALUE;
				// TODO create file with the xml content and file name of invoice number (ILD000123...)
			}

			$invoice_id = $this->saveInvoiceId($row->get('account_id'), $this->createInvoiceId());
			// update billrun with the invoice id
			$xml->INV_INVOICE_TOTAL->INVOICE_NUMBER = $invoice_id;
			$xml->INV_INVOICE_TOTAL->INVOICE_DATE = date($short_format_date);
			$xml->INV_INVOICE_TOTAL->FIRST_GENERATION_TIME = date($short_format_date);
			$xml->INV_INVOICE_TOTAL->FROM_PERIOD = date($short_format_date, strtotime('2012-05-14'));
			$xml->INV_INVOICE_TOTAL->TO_PERIOD = date($short_format_date, strtotime('2012-11-30'));
			$xml->INV_INVOICE_TOTAL->SUBSCRIBER_COUNT = count($row);
			$xml->INV_INVOICE_TOTAL->INVOICE_TYPE = "ilds";
				
			$invoice_sumup = $xml->INV_INVOICE_TOTAL->addChild('INVOICE_SUMUP');
			$total = 0;
			foreach ($total_ilds as $ild => $total_ild_cost) {
				$ild_xml = $invoice_sumup->addChild('ILD');
				$ild_xml->NDC = $ild;
				$ild_xml->CHARGE_EXCL_VAT = $total_ild_cost;
				$ild_xml->CHARGE_INCL_VAT = $total_ild_cost * self::VAT_VALUE;
				$total += $total_ild_cost;
			}
			$invoice_sumup->TOTAL_EXCL_VAT = $total;
			$invoice_sumup->TOTAL_INCL_VAT = $total * self::VAT_VALUE;
			//$row->{'xml'} = $xml->asXML();
			$this->log->log("invoice id created " . $invoice_id . " for the account", Zend_Log::INFO);
			$this->createXml($invoice_id, $xml->asXML());

			$this->addRowToCsv($invoice_id, $row->get('account_id'), $total, $total_ilds);
		}
	}

	protected function addRowToCsv($invoice_id, $account_id, $total, $cost_ilds) {
		//empty costs for each of the providers
		foreach (array('012', '013', '014', '015', '018') as $key) {
			if (!isset($cost_ilds[$key])) {
				$cost_ilds[$key] = 0;
			}
		}

		ksort($cost_ilds);
		$seperator = ',';
		$row = $invoice_id . $seperator . $account_id . $seperator .
			$total . $seperator . ($total * self::VAT_VALUE) . $seperator . implode($seperator, $cost_ilds) . PHP_EOL;
		$this->csv($row);
	}

	protected function createXml($fileName, $xmlContent) {
		$path = $this->export_directory . '/' . $fileName . '.xml';
		return file_put_contents($path, $xmlContent);
	}

	protected function saveInvoiceId($account_id, $invoice_id) {
		$billrun = $this->db->getCollection(self::billrun_table);

		$resource = $billrun->query()
			->equals('stamp', $this->getStamp())
			->equals('account_id', (string) $account_id)
//			->notExists('invoice_id')
		;

		foreach ($resource as $billrun_line) {
			$data = $billrun_line->getRawData();
			if (!isset($data['invoice_id'])) {
				$data['invoice_id'] = $invoice_id;
				$billrun_line->setRawData($data);
				$billrun_line->save($billrun);
			} else {
				$invoice_id = $data['invoice_id'];
			}
		}

		return $invoice_id;
	}

	protected function createInvoiceId() {
		$invoices = $this->db->getCollection(self::billrun_table);
		$resource = $invoices->query()->cursor()->sort(array('invoice_id' => -1))->limit(1);
		foreach ($resource as $e) {
			// demi loop
		}
		if (isset($e['invoice_id'])) {
			return (string) ($e['invoice_id'] + 1); // convert to string cause mongo cannot store bigint
		}
		return '3100000000';
	}

	protected function basic_xml() {
		//$xml_path = $this->config->ilds->export . 'ilds.xml';
		//return simplexml_load_file($xml_path);
		$xml = <<<EOI
<?xml version="1.0" encoding="UTF-8"?>
<INVOICE>
	<TELECOM_INFORMATION>
		<LASTTIMECDRPROCESSED></LASTTIMECDRPROCESSED>
		<VAT_VALUE></VAT_VALUE>
		<COMPANY_NAME_IN_ENGLISH></COMPANY_NAME_IN_ENGLISH>
		<COMPANY_NAME_IN_NATIVE></COMPANY_NAME_IN_NATIVE>
	</TELECOM_INFORMATION>
	<INV_CUSTOMER_INFORMATION>
		<CUSTOMER_CONTACT>
		</CUSTOMER_CONTACT>
	</INV_CUSTOMER_INFORMATION>
</INVOICE>
EOI;
		return simplexml_load_string($xml);
	}

}
