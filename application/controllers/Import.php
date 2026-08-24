<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Billing index controller class
 *
 * @package  Controller
 * @since    1.0
 */
class ImportController extends Yaf_Controller_Abstract {

	public function indexAction() {
		die;
		
		// Move interconnect data to the international rate itself
		// Remove the interconnect
		// Add interconnect flag
		$notZeroRates = array();
		$query = array(
			'params.prefix' => array('$in' => array(new Mongodloid_Regex( "/^01/" ))),
		);
		$internationalRates = Billrun_Factory::db()->ratesCollection()->query($query);
		foreach ($internationalRates as $internationalRate) {
			foreach ($internationalRate['rates'] as $usaget => $rate) {
				foreach ($rate as $planName => $rateDetails) {
					if (is_null($rateDetails['interconnect'])) {
						continue;
					}
					foreach ($rateDetails['rate'] as $r) {
						if ($r['price'] != 0) {
							if ($rateDetails['interconnect'] != 'A_ZERO_INTERCONNECT') {
								$notZeroRates[] = array(
									'key' => $internationalRate['key'],
									'interconnect' => $rateDetails['interconnect'],
								);
							} else {
								$find = array(
									'key' => $internationalRate['key'],
								);
								$update = array(
									'$set' => array(
										"rates.$usaget.$planName.interconnect" => null,
									)
								);
								Billrun_Factory::db()->ratesCollection()->update($find, $update);
							}
						} else {
							print "going to copy " . $rateDetails['interconnect'] . " to " . $internationalRate['key'] . "<br />" . PHP_EOL;
							$interConnect = Billrun_Factory::db()->ratesCollection()->query(array('key' => $rateDetails['interconnect']))->cursor()->current();
							$interconnectRate = $interConnect->get('rates')[$usaget][$planName]['rate'];
							if (is_null($interconnectRate)) {
								$interconnectRate = $interConnect->get('rates')[$usaget]['BASE']['rate'];
							}
							
							$find = array(
								'key' => $internationalRate['key'],
							);
							if ($interconnectRate[0]['interval'] == $interconnectRate[1]['interval'] && $interconnectRate[0]['price'] == $interconnectRate[1]['price']) {
								$interconnectRate = array(
									array(
										'interval' => $interconnectRate[0]['interval'],
										'price' => $interconnectRate[0]['price'],
										'to' => 2147483647,
									),
								);
							}
							$update = array(
								'$set' => array(
									"params.interconnect" => true,
									"params.chargable" => true,
									"rates.$usaget.$planName.rate" => $interconnectRate,
									"rates.$usaget.$planName.interconnect" => null,
								)
							);
							Billrun_Factory::db()->ratesCollection()->update($find, $update);
						}
					}
				}
			}
		}
		print_R($notZeroRates);
		die;
	}

	public function csvAction() {}


}
