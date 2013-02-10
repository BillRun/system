<?php

/*
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of IParser
 *
 * @author eran
 */
interface Billrun_Plugin_Interface_IParser {
	
	public function parse($type, $data, &$parser);

	public function parseField($type, $data, $fileDesc, &$parser);

	public function parseHeader($type, $data, &$parser);

	public function parseTrailer($type, $data, &$parser);
}

?>
