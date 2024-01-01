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


class generat_discounts extends generat_test_data
{

  public static function generateDiscount($override = [])
  {
    //http://billrun/billapi/discounts/create
    $discount = array_merge([
      
        "description" => "nn",
        "key" => generat_test_data::getCurrentDateTimeWithMilliseconds(),
        "proration" => "inherited",
        "priority" => "",
        "params" => [
          "min_subscribers" => "",
          "max_subscribers" => "",
          "conditions" => [[]]
        ],
        "from" => "2023-05-12",
        "type" => "monetary"
      
    ], $override);

    $respons = self::bulidAPI('discounts', $discount);
    if ($respons['status'] == 0) {
      $respons = self::retry('discounts', $discount);
    }
    return $respons['status'] == 1 ? $respons['entity'] : $respons['message'];
  }

}