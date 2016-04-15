<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 S.D.O.C. LTD. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * class for mongo less than operator translator.
 *
 * @author Tom Feigin
 */
class Admin_MongoOperatorTranslators_LessThanEquals extends Admin_MongoOperatorTranslators_Translator {

	/**
	 * Return the mongo operator string.
	 * @return string - Mongo operator string for this class.
	 */
	public function getOperator() {
		return '$lte';
	}

}
