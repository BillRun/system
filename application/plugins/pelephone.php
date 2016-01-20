<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2015 S.D.O.C. LTD. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * PL plugin
 *
 * @package  Application
 * @subpackage Plugins
 * @since    0.5
 */
class pelephonePlugin extends Billrun_Plugin_BillrunPluginBase {

	/**
	 * plugin name
	 *
	 * @var string
	 */
	protected $name = 'pelephone';
	
	public function extendRateParamsQuery(&$query, &$row, &$calculator) {
		$now = date('w') . '-' . date('His');
		$query[0]['$match']['$or'] = array(
			array(
				'timeinweek' => array(
					'$ne' => true
				)
			),
			array(
				'timeinweek' => array(
					'from' => array(
						'$lte' => $now
					),
					'to' => array(
						'$gte' => $now
					),
				)
			),
		);
	}
	

}
