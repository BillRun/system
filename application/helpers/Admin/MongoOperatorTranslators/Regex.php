<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Abstract class for regex operator translator.
 *
 */
abstract class Admin_MongoOperatorTranslators_Regex extends Admin_MongoOperatorTranslators_Translator {

	/**
	 * Return the mongo operator string.
	 * @return string - Mongo operator string for this class.
	 */
	public function getOperator() {
		return '$regex';
	}

}
