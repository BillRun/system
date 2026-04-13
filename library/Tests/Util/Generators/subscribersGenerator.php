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


class generat_subscribers extends generat_test_data
{
  
  public static function generateAccount($override = [])
  {
    //http://billrun/billapi/accounts/create
    $account = array_merge([
      "invoice_shipping_method" => "email",
      "lastname" => "test",
      "invoice_detailed" => false,
      "from" => "2021-11-14",
      "zip_code" => "123ab",
      "payment_gateway" => "",
      "address" => "hshalom 7",
      "country" => "Israel",
      "salutation" => "",
      "firstname" => "yossi",
      "email" => "test@gmail.com",
     "aid" => mt_rand(100000, 100000000),
    ], $override);

    $respons = self::bulidAPI('accounts', $account);

    if ($respons['status'] == 0) {
      $respons = self::retry('accounts', $account);
    }
    return $respons['status'] == 1 ? $respons['entity'] : $respons['message'];
  }
  //Request URL: http://billrun/billapi/subscribers/create

  public static function generateSubscriber($override = [])
  {
    $subscriber = array_merge([
      "lastname" => "test",
      "plan" => "A",
      "from" => "2020-11-15",
      "play" => "Default",
      "address" => "hshalom 7",
      "country" => "Israel",
      "firstname" => "yossi",
      "aid" =>  mt_rand(100000, 100000000),
      "services" => []
    ], $override);
    $respons = self::bulidAPI('subscriber', $subscriber);
    if ($respons['status'] == 0) {
      $respons = self::retry('subscribers', $subscriber);
    }

    return $respons['status'] == 1 ? $respons['entity'] : $respons['message'];
  }
}