<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012 S.D.O.C. LTD. All rights reserved.
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */

/**
 * Billing generator ilds class
 * require to generate xml for each account
 * require to generate csv contain how much to credit each account
 *
 * @package  Billing
 * @since    1.0
 */
class Billrun_Generator_Ilds extends Billrun_Generator {

	/**
	 * The VAT value including the complete; for example 1.17
	 * 
	 * @var float
	 */
	protected $vat;

	public function __construct($options) {
		parent::__construct($options);
		$this->vat = Billrun_Factory::config()->getConfigValue('pricing.vat', 1.18);
	}

	/**
	 * load the container the need to be generate
	 */
	public function load() {
		$billrun = Billrun_Factory::db()->billrunCollection();

		$this->data = $billrun->query()
			->equals('stamp', $this->getStamp())
			->equals('source', 'ilds')
			->notExists('invoice_id');

		Billrun_Factory::log()->log("aggregator entities loaded: " . $this->data->count(), Zend_Log::INFO);

		Billrun_Factory::dispatcher()->trigger('afterGeneratorLoadData', array('generator' => $this));
	}

	/**
	 * execute the generate action
	 */
	public function generate() {
		// generate xml
		$this->xml();

		// generate csv
//		$this->csv();
	}

	protected function get_subscriber_lines($subscriber_id) {
		$lines = Billrun_Factory::db()->linesCollection();

		$ret = array();

		$resource = $lines->query(array(
				'$or' => array(
					array('source' => array('$in' => array('ilds', 'premium'))),
					array('source' => 'api', 'type' => 'refund', 'reason' => 'ILDS_DEPOSIT')
				)
			))
			//->equals('source', 'ilds')
			->equals('billrun', $this->getStamp())
			->equals('subscriber_id', "$subscriber_id")
			->notExists('billrun_excluded')
			// todo check how to use hint with 2 indexes
			->cursor()
//			->cursor()->hint(array('subscriber_id' => 1)) //todo :bring back 
			->sort(array('unified_record_time' => 1));
		$resource->count();
		foreach ($resource as $entity) {
			$ret[] = $entity->getRawData();
		}

		return $ret;
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
			$invoice_id = $this->saveInvoiceId($row->get('account_id'), $this->createInvoiceId($totalVat, $row->get('account_id')));
			$this->setLinesInvoiceId($stamps, $invoice_id);
			// update billrun with the invoice id
			$xml->INV_INVOICE_TOTAL->INVOICE_NUMBER = $invoice_id;			
			
			$invoice_sumup->TOTAL_EXCL_VAT = $total;
			$invoice_sumup->TOTAL_INCL_VAT = $totalVat;
			//$row->{'xml'} = $xml->asXML();
			Billrun_Factory::log()->log("invoice id created " . $invoice_id . " for the account", Zend_Log::INFO);
			$fileName = $row->get('stamp') . '_' . str_pad($row->get('account_id'), 9, '0', STR_PAD_LEFT) . '_' . str_pad($invoice_id, 17, '0', STR_PAD_LEFT);
			$this->createXml($fileName, $xml->asXML());
			if ($add_header) {
				$this->addRowToCsv(null, null, null, null, $add_header);
				$add_header = false;
			}
			$this->addRowToCsv($invoice_id, $row->get('account_id'), $total, $total_ilds);
		}
	}

	protected function addRowToCsv($invoice_id, $account_id, $total, $cost_ilds, $addHeader = false) {
		//empty costs for each of the providers
		$providers = Billrun_Factory::config()->getConfigValue('ilds.providers', array());
		$providers = array_merge($providers, Billrun_Factory::config()->getConfigValue('premium.provider_ids', array()));
		$providers = array_merge($providers, array('refund'));
		foreach ($providers as $key) {
			if (!isset($cost_ilds[$key])) {
				$cost_ilds[$key] = floatval(0);
			}
		}

		ksort($cost_ilds);
		$refund = $cost_ilds['refund'];
		unset($cost_ilds['refund']);
		$keys = array_keys($cost_ilds);
		foreach($keys as $i => $key) {
			if (is_numeric($key)) {
				$keys[$i] = 'c'.$key;
			}
		}
		$seperator = ',';
		if ($addHeader) {
			$row = "invoice_id" . $seperator . "account_id" . $seperator
				. "total" . $seperator . "total_incl_vat" . $seperator . implode($seperator, $keys) . $seperator . 'refund' . PHP_EOL;
		} else {
			$total_incl_vat = $total * $this->vat;
			$row = $invoice_id . $seperator . $account_id . $seperator
				. $total . $seperator . $total_incl_vat . $seperator . implode($seperator, $cost_ilds) . $seperator . $refund . PHP_EOL;
			//			. $seperator . round($total_incl_vat, 2) . PHP_EOL;
		}
		$this->csv($row);
	}

	protected function createXml($fileName, $xmlContent) {
		$path = $this->export_directory . '/' . $fileName . '.xml';
		return file_put_contents($path, $xmlContent);
	}

	protected function saveInvoiceId($account_id, $invoice_id) {
		$billrun = Billrun_Factory::db()->billrunCollection();

		$resource = $billrun->query()
			->equals('stamp', $this->getStamp())
			->equals('account_id', $account_id)
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
	
	protected function setLinesInvoiceId($stamps, $invoice_id) {
		$lines = Billrun_Factory::db()->linesCollection();
		$where = array(
			'stamp' => array(
				'$in' => $stamps,
			),
			// the next or is for protection (source query that populate the lines)
			'$or' => array(
				array('source' => array('$in' => array('ilds', 'premium'))),
				array('source' => 'api', 'type' => 'refund', 'reason' => 'ILDS_DEPOSIT')
			),
		);
		$update = array(
			'$set' => array(
				'invoice_id' => $invoice_id,
			)
		);
		$lines->update($where, $update, array("multiple" => true));
	}

	protected function createInvoiceId($totalAmount, $aid = FALSE) {
		$invoiceIdStructure = Billrun_Config::getInstance()->getConfigValue('ilds.generator.invoice_id_pattern', '');
		if (empty($invoiceIdStructure)) {
			Billrun_Factory::log()->log("Missing invoice id structure", Zend_Log::ALERT);
		}
		$invoiceIdParams = Billrun_Config::getInstance()->getConfigValue('ilds.generator.invoice_id_params', array());
		$translations = array();
		$invoiceGroup = $totalAmount < 0 ? '30' : '25';
		foreach ($invoiceIdParams as $paramObj) {
			$translations[$paramObj['param']] = $this->getTranslationValue($paramObj, $invoiceGroup, $translations, $aid);
		}
		$ret = Billrun_Util::translateTemplateValue($invoiceIdStructure, $translations, null, true);
		if (is_null($ret)) {
			Billrun_Factory::log()->log("Failed to generate invoice for account", Zend_Log::INFO);
		} else {
			Billrun_Factory::log()->log("Failed to generate invoice for account", Zend_Log::INFO);
			return $ret;
		}
	}

	protected function addIldLineXML(&$billing_record, $line) {
		$billing_record->TIMEOFBILLING = $line['call_start_dt'];
		if ($line['source'] == 'premium') {
			$billing_record->TARIFFITEM = 'IL_PREMIUM';
			$billing_record->INTERNATIONAL = '0';
			$billing_record->PREMIUM = $line['type'];
		} else {
			$billing_record->TARIFFITEM = 'IL_ILD';
			$billing_record->INTERNATIONAL = '1';
			$billing_record->ILD = $line['type'];
		}
		$billing_record->CTXT_CALL_OUT_DESTINATIONPNB = $line['called_no'];
		$billing_record->CTXT_CALL_IN_CLI = $line['caller_phone_no'];
		$billing_record->CHARGEDURATIONINSEC = $line['chrgbl_call_dur'];
		$billing_record->CHARGE = $line['price_customer'];
		$billing_record->TARIFFKIND = 'Call';
	}

	protected function addRefundLineXML(&$billing_record, $line) {
		$billing_record->TIMEOFBILLING = date("Y/m/t H:i:s", $line['unified_record_time']->sec);
		$billing_record->TARIFFITEM = $line['reason']; //
		$billing_record->CTXT_CALL_OUT_DESTINATIONPNB = "";
		$billing_record->CHARGEDURATIONINSEC = "";
		$billing_record->CHARGE = 0;
		$billing_record->CREDIT = -$line['price_customer'];
		$billing_record->NEWBALANCE = 0;
		$billing_record->PREVIOUSBALANCE = 0;
		$billing_record->TTAR_TCLASSNAME_EXT = "";
		$billing_record->TTAR_TIMEPERIOD = "";
		$billing_record->TTAR_ACCESSPRICE1 = 0;
		$billing_record->TTAR_SAMPLEDELAYINSEC1 = 0;
		$billing_record->TTAR_SAMPPRICE1 = 0;
		$billing_record->CTXT_CALL_IN_CLI = "";
		$billing_record->ACCESSTYPENAME = "";
		$billing_record->CTXT_CALL_OUT_CARRIERNAME_EXT = "";
		$billing_record->SERVINGPLMN = "";
		$billing_record->TYPE_OF_BILLING_CHAR = "R";
		//<!-- eXSWI_ID_BILLING_CALCULATED_VALUES!-->
		$billing_record->ROAMING = 0;
		//<!-- eXSWI_ID_BILLING_CALCULATED_VALUES!-->
		$billing_record->TARIFFKIND = "Service";
		//<!-- eXSWI_ID_BILLING_CALCULATED_VALUES!-->
		$billing_record->INTERNATIONAL = 1;
		//<!-- eXSWI_ID_BILLING_CALCULATED_VALUES!-->
		$billing_record->DISCOUNT_USAGE = "DISCOUNT_NONE";
	}

	protected function basic_xml() {
		//$xml_path = Billrun_Factory::config()->ilds->export . 'ilds.xml';
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
	
	protected function getTranslationValue($paramObj, $invoiceGroup, $formerTranslations, $aid= FALSE) {
		if (!isset($paramObj['type']) || !isset($paramObj['value'])) {
			Billrun_Factory::log("Missing invoice_id params definitions when generating invoice id", Zend_Log::DEBUG);
		}
		switch ($paramObj['type']) {
			case 'date':
				$dateValue = ($paramObj['value'] == 'cycle') ? $this->getStamp() : (($paramObj['value'] == 'now') ?  time() : strtotime($paramObj['value']));
				return substr($dateValue, 0, 4);
			case 'autoinc':
				if (!isset($paramObj['min_value']) && !isset($paramObj['max_value'])) {
					Billrun_Factory::log("Missing range when generating invoice id", Zend_Log::DEBUG);
					return;
				}
				if (!isset($formerTranslations['param1_date']) && !isset($formerTranslations['param2_id'])) {
					Billrun_Factory::log("Missing group value params when generating invoice id", Zend_Log::DEBUG);
					return;
				}
				$minValue = intval($paramObj['min_value']);
				$maxValue = intval($paramObj['max_value']);		
				$group = $formerTranslations['param1_date'] . $formerTranslations['param2_id'];
				$fakeCollectionName = '$invoice_id_generation_' . $group;
				$oid = $group . $aid . $this->getStamp();
				$seq = Billrun_Factory::db()->countersCollection()->createAutoInc($oid, $minValue, $fakeCollectionName);
				if ($seq > $maxValue) {
					throw new Exception("Sequence exceeded max value when generating invoice id");
				}		
				return str_pad($seq, $paramObj['length'], '0', STR_PAD_LEFT);
			case 'identifier':
				if ($paramObj['value'] == 'by_type') {
					$padLength = $paramObj['length'];
					return str_pad($invoiceGroup, $padLength, '0', STR_PAD_LEFT);
				}
			default:
				Billrun_Factory::log("Unsupported invoice_id params", Zend_Log::DEBUG);
				break;
		}
	}

	protected function getAccountId() {
		return $this->aid;
	}

}
