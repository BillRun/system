<<?php
/**
 * @package         Billing
 * @copyright       Copyright (C) 2012 S.D.O.C. LTD. All rights reserved.
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */

/**
 * Billing processor for premium class
 *
 * @package  Billing
 * @since    2.8
 */
class Billrun_Processor_Premium extends Billrun_Processor_Base_Ilds {

	/**
	 * the type of the object
	 *
	 * @var string
	 */
	static protected $type = "premium";

	public function __construct($options) {

		parent::__construct($options);

		$this->header_structure = array(
			'record_type' => 1,
			'file_type' => 15,
			'sending_company_id' => 10,
			'receiving_company_id' => 10,
			'sequence_no' => 6,
			'file_creation_date' => 12,
			'file_received_date' => 12,
			'file_status' => 2,
			'filler' => 80,
		);

		$this->trailer_structure = array(
			'record_type' => 1,
			'file_type' => 15,
			'sending_company_id' => 10,
			'receiving_company_id' => 10,
			'sequence_no' => 6,
			'file_creation_date' => 12,
			'total_phone_number' => 15,
			'total_charge_sign_from_operator' => 1,
			'total_charge_from_operator' => 15,
			'total_charge_sign' => 1,
			'total_charge' => 15,
			'total_rec_no' => 6,
			'total_err_rec_no' => 6,
			'filler' => 80,
		);

		$this->data_structure = array(
			'record_type' => 1,
			'call_type' => 2,
			'caller_phone_no' => 10,
			'called_no' => 18,
			'phone_pickup_dt' => 14,
			'call_start_dt' => 14,
			'call_end_dt' => 14,
			'pickup_to_hangup_dur' => 6,
			'call_dur' => 6,
			'pricing_code' => 1,
			'chrgbl_call_dur' => 6,
			'first_price_sign' => 1,
			'first_price' => 11,
			'second_price_sign' => 1,
			'second_price' => 11,
			'premium_price_sign' => 1,
			'premium_price' => 11,
			'collection_ind' => 2,
			'filler' => 80,
			//	'record_status' => 2,
		);
	}

	/**
	 * method to parse the data
	 */
	protected function parse() {
		if (!is_resource($this->fileHandler)) {
			Billrun_Factory::log()->log("Resource is not configured well", Zend_Log::ERR);
			return false;
		}

		while ($line = fgets($this->fileHandler)) {
			$record_type = $this->getLineType($line);

			// @todo: convert each case code snippet to protected method (including triggers)
			switch ($record_type) {
				case 'H': // header
					if (isset($this->data['header'])) {
						Billrun_Factory::log()->log("double header", Zend_Log::ERR);
						return false;
					}

					$this->parser->setStructure($this->header_structure);
					$this->parser->setLine($line);
					// @todo: trigger after header load (including $header)
					$header = $this->parser->parse();
					// @todo: trigger after header parse (including $header)
					$header['source'] = self::$type;
					$header['type'] = static::$type;
					$header['file'] = basename($this->filePath);
					$header['process_time'] = date(self::base_dateformat);
					$this->data['header'] = $header;

					break;
				case 'T': //trailer
					if (isset($this->data['trailer'])) {
						Billrun_Factory::log()->log("double trailer", Zend_Log::ERR);
						return false;
					}

					$this->parser->setStructure($this->trailer_structure);
					$this->parser->setLine($line);
					// @todo: trigger after trailer load (including $header, $data, $trailer)
					$trailer = $this->parser->parse();
					// @todo: trigger after trailer parse (including $header, $data, $trailer)
					$trailer['source'] = self::$type;
					$trailer['type'] = static::$type;
					$trailer['header_stamp'] = $this->data['header']['stamp'];
					$trailer['file'] = basename($this->filePath);
					$trailer['process_time'] = date(self::base_dateformat);
					$this->data['trailer'] = $trailer;

					break;
				case 'D': //data
					if (!isset($this->data['header'])) {
						Billrun_Factory::log()->log("No header found", Zend_Log::ERR);
						return false;
					}

					$this->parser->setStructure($this->data_structure); // for the next iteration
					$this->parser->setLine($line);
					// @todo: trigger after row load (including $header, $row)
					$row = $this->parser->parse();
					// @todo: trigger after row parse (including $header, $row)
//					$row['source'] = self::$type;
					$row['source'] = 'premium';
					$row['type'] = strtolower($this->data['header']['sending_company_id']);
					$row['header_stamp'] = $this->data['header']['stamp'];
					$row['file'] = basename($this->filePath);
					$row['process_time'] = date(self::base_dateformat);
					$row['unified_record_time'] = new MongoDate(Billrun_Util::dateTimeConvertShortToIso($row['call_start_dt'], $this->defTimeOffset));
					// hot fix cause this field contain iso-8859-8
//					if (isset($row['country_desc'])) {
//						$row['country_desc'] = mb_convert_encoding($row['country_desc'], 'UTF-8', 'ISO-8859-8');
//					}
					if ($this->isValidDataRecord($row)) {
						$this->data['data'][] = $row;
					}

					break;
				default:
					//raise warning
					break;
			}
		}
		return true;
	}
	
	protected function backup($move = true) {
		parent::backup(false);
	}

}
				