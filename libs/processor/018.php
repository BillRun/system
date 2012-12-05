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
	 * method to get the data from the file
	 * @todo take to parent abstract
	 */
	public function process()
	{

		// @todo: trigger before parse (including $ret)
		if (!$this->parse()) {
			return false;			
		}

		// @todo: trigger after parse line (including $ret)
		// @todo: trigger before storage line (including $ret)

		if (!$this->logDB())
		{
			//raise error
			return false;
		}

		if (!$this->store())
		{
			//raise error
			return false;
		}

		// @todo: trigger after storage line (including $ret)

		return true;
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
						//raise error -> double header
						return false;
					}

					$this->parser->setStructure($this->header_structure);
					$this->data['header'] = $header = $this->parser->setLine($line)->parse();
					$header_stamp = $this->data['header']['stamp'];
					// @todo: trigger after row load (including $header)
					$this->parser->setStructure($this->data_structure); // for the next iteration

					break;
				case 'T': //trailer
					if (isset($this->data['trailer']))
					{
						//raise error -> double trailer
						return false;
					}

					$this->parser->setStructure($this->trailer_structure);
					// @todo: trigger after row load (including $header, $data, $trailer)
					$trailer = $this->parser->setLine($line)->parse();
					$trailer['header_stamp'] = $header_stamp;
					$this->data['trailer'] = $trailer;

					break;
				case 'D': //data
					$row = $this->parser->setLine($line)->parse();
					// @todo: trigger after row load (including $header, $row)
					$row['header_stamp'] = $header_stamp;
					$this->data['data'][] = $row;

					break;
				default:
					//raise warning
					break;
			}
		}
		return true;
	}

	/**
	 * method to log the processing
	 * @todo refactoring this method
	 */
	protected function logDB()
	{
		if (!isset($this->db) || !isset($this->data['trailer']))
		{
			// raise error
			return false;
		}

		$log = $this->db->getCollection(self::log_table);
		$entity = new Mongodloid_Entity($this->data['trailer']);

		return $entity->save($log);
	}

	/**
	 * method to store the processing data
	 * @todo refactoring this method
	 */
	protected function store()
	{
		if (!isset($this->db) || !isset($this->data['data']))
		{
			// raise error
			return false;
		}

		$lines = $this->db->getCollection(self::lines_table);

		foreach ($this->data['data'] as $row)
		{
			$entity = new Mongodloid_Entity($row);
			$entity->save($lines);
		}

		return true;
	}

}