<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Translator for the endswith operator.
 *
 */
class Admin_MongoOperatorTranslators_EndsWith extends Admin_MongoOperatorTranslators_Regex {

	/**
	 * Return a pair of oprator and value in the mongo format based on user string
	 * input.
	 * @param string $value - The value to be coupled with the operator.
	 * @return pair - Operator as key and value as value.
	 */
	public function translate($value) {
		return array($this->getOperator() => "$value$");
	}

}
