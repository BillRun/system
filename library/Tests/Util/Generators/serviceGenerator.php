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


class generat_services extends generat_test_data
{

  public static function generateservice($override = [])
  {
    $service = array_merge(
      [
        "description" => "aaa",
        "name" => '202401111349137122',
        "price" => [
          [
            "from" => 0,
            "to" => "UNLIMITED",
            "price" => 2
          ]
        ],
        "tax" => [
          [
            "type" => "vat",
            "taxation" => "global"
          ]
        ],
        "from" => "2023-05-12",
        "prorated" => true,
        "recurrence" => [
          "frequency" => 1,
          "start" => 1
        ]
      ],
      $override
    );
    $respons = self::bulidAPI('services', $service);
    if ($respons['status'] == 0) {
      $respons = self::retry('services', $service);
    }

    return $respons['status'] == 1 ? $respons['entity'] : $respons['message'];
  }
}