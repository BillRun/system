<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * class for mongo not equal operator translator.
 *
 */
class Admin_MongoOperatorTranslators_Equal extends Admin_MongoOperatorTranslators_Translator {

	/**
	 * Return the mongo operator string.
	 * @return string - Mongo operator string for this class.
	 */
	public function getOperator() {
		return '$in';
	}

	/**
	 * Return a pair of oprator and value in the mongo format based on user string
	 * input.
	 * @param string $value - The value to be coupled with the operator.
	 * @return pair - Operator as key and value as value.
	 */
	public function translate($value) {
		return array($this->getOperator() => array($value));
	}

}
