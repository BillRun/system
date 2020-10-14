<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2020 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Billing rate (product) class
 *
 * @package  Rate
 * @since    5.12
 */
class Billrun_Rate extends Billrun_Entity {

    /**
     * see parent::getCollection
     */
    public static function getCollection() {
        return Billrun_Factory::db()->ratesCollection();
    }
	
	/**
     * see parent::getLoadQueryByParams
     */
	protected function getLoadQueryByParams($params = []) {
        if (isset($params['key'])) {
            return [
                'key' => $params['key'],
            ];
        }

        return false;
    }
}
