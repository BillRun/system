<?php

/**
 * @package			Billing
 * @copyright		Copyright (C) 2012 S.D.O.C. LTD. All rights reserved.
 * @license			GNU General Public License version 2 or later; see LICENSE.txt
 */

/**
 * Billing abstract generator ilds class
 * require to generate xml for each account 
 * require to generate csv contain how much to credit each account
 *
 * @package  Billing
 * @since    1.0
 */
class generator_ilds extends generator
{

	/**
	 * load the container the need to be generate
	 */
	public function load($initData = true)
	{
		$billrun = $this->db->getCollection(self::billrun_table);

		if ($initData)
		{
			$this->data = array();
		}

		$resource = $billrun->query()
			->equals('stamp', $this->getStamp());

		foreach ($resource as $entity)
		{
			$this->data[] = $entity;
		}

		print "aggregator entities loaded: " . count($this->data) . PHP_EOL;
	}

	/**
	 * execute the generate action
	 */
	public function generate()
	{
		$data = $this->normailze();
//		print_R($data);die;
		// generate xml
		$this->xml($data);
		// generate csv
	}

	protected function normailze($field = 'account_id')
	{
		$ret = array();
		foreach ($this->data as $row)
		{
			$subscriber_id = $row->get('subscriber_id');
			$lines = $this->get_subscriber_lines($subscriber_id);
			$subscriber_data = array(
				'sum' => $row->getRawData(),
				'lines' => $lines,
			);
			$account_id = $row->get($field);
			$ret[$account_id][$subscriber_id] = $subscriber_data;
		}

		return $ret;
	}

	protected function get_subscriber_lines($subscriber_id)
	{
		$lines = $this->db->getCollection(self::lines_table);


		$resource = $lines->query()
			->equals('billrun', $this->getStamp())
			->equals('subscriber_id', $subscriber_id);

		$ret = array();
		foreach ($resource as $entity)
		{
			$ret[] = $entity->getRawData();
		}

		return $ret;
	}

	protected function xml($rows)
	{
//		print_R($rows);die;
		// use $this->export_directory
		foreach ($rows as $key => $row)
		{
			// @todo refactoring the xml generation to another class
			$xml = $this->basic_xml();
			$xml->TELECOM_INFORMATION->LASTTIMECDRPROCESSED = date('Y-m-d h:i:s');
			$xml->TELECOM_INFORMATION->VAT_VALUE = '17';
			$xml->TELECOM_INFORMATION->COMPANY_NAME_IN_ENGLISH = 'GOLAN';
			$xml->INV_CUSTOMER_INFORMATION->CUSTOMER_CONTACT->NUMBER = $key;
			foreach ($row as $id => $subscriber)
			{
				$subscriber_inf = $xml->addChild('SUBSCRIBER_INF');
				$subscriber_inf->SUBSCRIBER_DETAILS->NUMBER = $id;
				$billing_records = $subscriber_inf->addChild('BILLING_LINES');
				foreach ($subscriber['lines'] as $line)
				{
					$billing_record = $billing_records->addChild('BILLING_RECORD');
					$billing_record->TIMEOFBILLING = $line['call_start_dt'];
					$billing_record->CTXT_CALL_OUT_DESTINATIONPNB = $line['called_no'];
					$billing_record->CHARGEDURATIONINSEC = $line['chrgbl_call_dur'];
					$billing_record->CHARGE = $line['price_customer'];
				}
				$subscriber_sumup = $subscriber_inf->addChild('SUBSCRIBER_SUMUP');
				$subscriber_sumup->TOTAL_CHARGE = $subscriber['sum']['cost'];
				$subscriber_sumup->TOTAL_VAT = 17;
				$subscriber['xml'] = $xml->asXML();
				// TODO create file with the xml content and file name of invoice number (ILD000123...)
			}
		}
	}

	protected function basic_xml()
	{
		$xml_path = LIBS_PATH . '/../files/ilds.xml';
		return simplexml_load_file($xml_path);
	}

	protected function csv()
	{
		
	}

}