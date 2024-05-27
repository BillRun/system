
<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */
/**
 * 
 *
 * @package  update_test_data
 * @since    0.5
 */


class update_test_data{
  public  static $test_number;
  public static function setTestNumber($test_number=null){
     self::$test_number = $test_number;
  }
  public static function bulidAPI($entity, $params){
    foreach ($params['update'] as $key => $val) {
      if (!is_array($val)) {
        $request['update'][$key] = (string)$val;
      }else{
        $request['update'][$key] = $val;

      }
      $request['update']['generate_by_test']=true;
    }
    $request['update'] = json_encode($request['update']);
    $request['query'] = json_encode($params['query']);
    $baseUrl =  (Billrun_Factory::config()->getEnv() == 'container' ) ? "web":$_SERVER['SERVER_NAME'];
    $url = "http://$baseUrl/billapi/$entity/closeandnew";
    $secret = Billrun_Utils_Security::getValidSharedKey();
    $signed = Billrun_Utils_Security::addSignature($request, $secret['key']);
    $request['_sig_'] = $signed['_sig_'];
    $request['_t_'] = $signed['_t_'];
    return self::sendAPI($url, $request);
  }

  /**
   * 
   * @param type $url
   * @param type $request
   * @return type
   */
  public  static function sendAPI($url, $request){
    Billrun_Factory::log("send API to $url with params l" . print_r($request, 1), Zend_Log::INFO);
    $respons = json_decode(Billrun_Util::sendRequest($url, $request), true);
    Billrun_Factory::log("response is :" . print_r($respons, 1), Zend_Log::INFO);
    return $respons;
  }


 
  
}
