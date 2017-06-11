<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 S.D.O.C. LTD. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * PL Red Button plugin
 *
 * @package  Application
 * @subpackage Plugins
 * @since    4.6
 */
class redbuttonPlugin extends Billrun_Plugin_BillrunPluginBase {

	/**
	 * method to extend rate query
	 * 
	 * @param array $query
	 * @param array $row
	 * @param Billrun_Calculator_Rate $calculator calculator instance that trigger this event
	 * 
	 * @return void
	 */
	public function extendRateParamsQuery(&$query, &$row, &$calculator) {
		$this->convertRatingGroups($query, $row);
	}
	
	protected function convertRatingGroups(&$query, &$row) {
		$rg_config_field = Billrun_Factory::config()->getConfigValue('rating_group_conversion.rg_config_field', 'rg_conversion');
		$rg_conversions = Billrun_Factory::config()->getConfigValue($rg_config_field, array());
		$mscc_data = $row->get('mscc_data', array());
		$service_data = $row->get('service', array());
		if (!isset($mscc_data[0]['rating_group']) || !isset($service_data['sgsnmccmnc'])) {
			return;
		}
		$ratingGroup = $mscc_data[0]['rating_group'];
		$mcc = $service_data['sgsnmccmnc'];
		$rg_conversion = array_filter(
			$rg_conversions, 
			function ($conversion) use ($ratingGroup, $mcc) {
				return ($conversion['from_rg'] == $ratingGroup) &&
					(($conversion['mcc'] == 'ROAMING') ||
					($conversion['mcc'] == 'ISRAEL' && $mcc == '425'));
			}
		);
		if (!$rg_conversion) {
			return;
		}
	
		$conversionRatingGroup = $rg_conversion[0]['to_rg'];
		foreach ($query as &$pipe) {
			if (!isset($pipe['$match']['params.rating_group'])) {
				continue;
			}
			$pipe['$match']['params.rating_group'] = array('$in' => array($conversionRatingGroup));
			$row['rating_group_conversion'] = $conversionRatingGroup;
		}
	}
}
