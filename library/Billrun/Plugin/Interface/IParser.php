<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * This interface defines the interface needed to add parsing behavior to a plugin.
 */
interface Billrun_Plugin_Interface_IParser {

	/**
	 * Parse data from a files
	 * @param type $type the name of the type that is being parsed.
	 * @param type $data the data that was retrieved from the file to parse.
	 * @param Billrun_Parser $parser the instance of the parser parsing the file.
	 */
	public function parseData($type, $data, Billrun_Parser &$parser);

	/**
	 * Parse a single field.
	 * @param type $type the type of the file being parsed
	 * @param type $data the field raw data to be parsed.
	 * @param array $fieldDesc the field description array.
	 * @param Billrun_Parser $parser the instance of the parser parsing the file.
	 */
	public function parseSingleField($type, $data, Array $fieldDesc, Billrun_Parser &$parser);

	/**
	 * Parse the header 
	 * @param type $type the type of the file being parsed
	 * @param type $data raw (unparsed) data of the header.
	 * @param Billrun_Parser $parser the instance of the parser parsing the file.
	 */
	public function parseHeader($type, $data, Billrun_Parser &$parser);

	/**
	 * 
	 * @param type $type the type of the file being parsed
	 * @param type $data the raw data of the trailer.
	 * @param Billrun_Parser $parser the instance of the parser parsing the file.
	 */
	public function parseTrailer($type, $data, Billrun_Parser &$parser);
}
