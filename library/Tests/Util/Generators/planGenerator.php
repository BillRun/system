<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */
/**
 * 
 *
 * @package  generat_test_data
 * @since    0.5
 */


 
class generat_plans extends generat_test_data
{
  public static function generatePlan($override = [])
  {
    //http://billrun/billapi/plans/create
    $plan = array_merge(
      [
        "price" => [ ["price" => 1, "from" => 0, "to" => "UNLIMITED"]],
        "from" => "2023-05-12",
        "name" => '20240111134913717',
        "tax" => [["type" => "vat", "taxation" => "global"]],
        "upfront" => true,
        "recurrence" => ["frequency" => 1, "start" => 1],
        "prorated_end" => false,
        "rates" => [],
        "prorated_start" => true,
        "connection_type" => "postpaid",
        "prorated_termination" => true,
        "description" => "AA"

      ],
      $override
    );

    $respons = self::bulidAPI('plans', $plan);

    if ($respons['status'] == 0) {
      $respons = self::retry('plans', $plan);
    }
    return $respons['status'] == 1 ? $respons['entity'] : $respons['message'];
  }
}