<?php

/**
 * @package			Billing
 * @copyright		Copyright (C) 2012 S.D.O.C. LTD. All rights reserved.
 * @license			GNU General Public License version 2 or later; see LICENSE.txt
 */

/**
 * Billing processor for 018 class
 *
 * @package  Billing
 * @since    1.0
 */
class processor_018 extends processor
{

	protected $type = '018';

	public function __construct($options)
	{
		parent::__construct($options);

		$this->data_structure = array(
			'record_type' => 1,
			'call_type' => 2,
			'caller_phone_no' => 10,
			'called_no' => 18,
			'call_start_dt' => 14,
			'call_end_dt' => 14,
			'actual_call_dur' => 6,
			'chrgbl_call_dur' => 6,
			'call_charge_sign' => 1,
			'call_charge' => 11,
			'collection_ind' => 1,
			'record_status' => 2,
			'sequence_no' => 6,
			'correction_code' => 2,
			'filler' => 96,
		);


		$this->header_structure = array(
			'record_type' => 1,
			'file_type' => 3,
			'sending_company_id' => 4,
			'receiving_company_id' => 4,
			'sequence_no' => 6,
			'file_creation_date' => 14,
			'file_received_date' => 14,
			'file_status' => 2,
			'version_no' => 2,
			'filler' => 140,
		);

		$this->trailer_structure = array(
			'record_type' => 1,
			'file_type' => 3,
			'sending_company_id' => 4,
			'receiving_company_id' => 4,
			'sequence_no' => 6,
			'file_creation_date' => 14,
			'total_charge_sign' => 1,
			'total_charge' => 15,
			'total_rec_no' => 6,
			'total_err_rec_no' => 6,
			'filler' => 130,
		);
	}

	/**
	 * method to parse the data
	 */
	protected function parse()
	{
		while ($line = fgets($this->fileHandler))
		{
			$record_type = substr($line, 0, 1);

			// @todo: convert each case code snippet to protected method (including triggers)
			switch ($record_type)
			{
				case 'H': // header
					if (isset($this->data['header']))
					{
						echo "double header" . PHP_EOL;
						return false;
					}

					$this->parser->setStructure($this->header_structure);
					$this->parser->setLine($line);
					// @todo: trigger after header load (including $header)
					$header = $this->parser->parse();
					// @todo: trigger after header parse (including $header)
					$header['type'] = $this->type;
					$row['file'] = basename($this->filePath);
					$this->data['header'] = $header;

					break;
				case 'T': //trailer
					if (isset($this->data['trailer']))
					{
						echo "double trailer" . PHP_EOL;
						return false;
					}

					$this->parser->setStructure($this->trailer_structure);
					$this->parser->setLine($line);
					// @todo: trigger after trailer load (including $header, $data, $trailer)
					$trailer = $this->parser->parse();
					// @todo: trigger after trailer parse (including $header, $data, $trailer)
					$trailer['type'] = $this->type;
					$trailer['header_stamp'] = $this->data['header']['stamp'];
					$trailer['file'] = basename($this->filePath);
					$this->data['trailer'] = $trailer;

					break;
				case 'D': //data
					if (!isset($this->data['header']))
					{
						echo "No header found" . PHP_EOL;
						return false;
					}

					$this->parser->setStructure($this->data_structure); // for the next iteration
					$this->parser->setLine($line);
					// @todo: trigger after row load (including $header, $row)
					$row = $this->parser->parse();
					// @todo: trigger after row parse (including $header, $row)
					$row['type'] = $this->type;
					$row['header_stamp'] = $this->data['header']['stamp'];
					$row['file'] = basename($this->filePath);
					$this->data['data'][] = $row;

					break;
				default:
					//raise warning
					break;
			}
		}
		return true;
	}

}