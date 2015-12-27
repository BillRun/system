<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2015 S.D.O.C. LTD. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Translator for the like operator.
 *
 * @author Tom Feigin
 */
class Admin_MongoOperatorTranslators_Like extends Admin_MongoOperatorTranslators_Regex {
	
	/**
	 * Return a pair of oprator and value in the mongo format based on user string
	 * input.
	 * @param string $value - The value to be coupled with the operator.
	 * @return pair - Operator as key and value as value.
	 */
	public function translate($value) {
		return array($this->getOperator() => "$value");
	}
}
