<?php


/**
 * This interface defines the interface needed to add parsing behavior to a plugin.
 * @author eran
 */
interface Billrun_Plugin_Interface_IParser {
	
	public function parseData($type, $data, Billrun_Parser &$parser);

	public function parseSingleField( $type, $data, Array $fieldDesc, Billrun_Parser  &$parser);

	public function parseHeader($type, $data, Billrun_Parser  &$parser);

	public function parseTrailer( $type, $data, Billrun_Parser  &$parser);
}

?>
