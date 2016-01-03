<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2015 S.D.O.C. LTD. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * This is a prototype for a decoder.
 *
 */
abstract class Billrun_Decoder_Base {
	/**
	 * Decode string to array
	 * 
	 * @param type $str the string to decode
	 * @return array the decoded value
	 */
	public abstract function decode($str);
}
