<?php

/**
 * @package			Billing
 * @copyright		Copyright (C) 2012 S.D.O.C. LTD. All rights reserved.
 * @license			GNU General Public License version 2 or later; see LICENSE.txt
 */

/**
 * Billing  processor binary class
 *
 * @package  Billing
 * @since    1.0
 */
class processor_binary extends processor {

	protected function parse() {
		// run all over the file with the parser helper
		if (!is_resource($this->fileHandler)) {
			echo "Resource is not configured well" . PHP_EOL;
			return false;
		}

		$count = $i = 0;
		while (!feof($this->fileHandler)) {
			$byte = fread($this->fileHandler, 1);
			print $i++ . " " . hexdec(bin2hex($byte)) . " " . bin2hex($byte) . " " . $byte . PHP_EOL;
			if (bin2hex($byte) == 13 || bin2hex($byte) == 10) {
				$count++;
				echo "===================" . PHP_EOL;
			}
			if ($i>10) {break;}
		}
		echo PHP_EOL . $count . PHP_EOL;
		die("!!!!!!!!!!!!!!!!!!!!!!!!!!!!" . PHP_EOL);
	}

}