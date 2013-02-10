<?php


/**
 * This interface defines the interface needed to add parsing behavior to a plugin.
 * @author eran
 */
interface Billrun_Plugin_Interface_IParser {
	
	public function parse($type, $data, &$parser);

	public function parseSingleField($type, $data, $fileDesc, &$parser);

	public function parseHeader($type, $data, &$parser);

	public function parseTrailer($type, $data, &$parser);
}

?>
