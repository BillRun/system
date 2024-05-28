
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

  public static function convertTimestamps(&$array) {
    foreach ($array['update']['services'] as &$service) {
        if (isset($service['from']['sec'])) {
            $service['from']= date('Y-m-d H:i:s', $service['from']['sec']);
        }
        if (isset($service['to']['sec'])) {
          $service['to']= date('Y-m-d H:i:s', $service['to']['sec']);
      }
        
        if (isset($service['creation_time']['sec'])) {
            $service['creation_time'] = date('Y-m-d H:i:s', $service['creation_time']['sec']);
        }
    }
}
  public static function bulidAPI($entity, $params){
    self::convertTimestamps($params);
    foreach ($params['update'] as $key => $val) {
      if (!is_array($val)) {
        $request['update'][$key] = $val;
      }else{
        $request['update'][$key] = $val;

      }
     
      $request['update']['generate_by_test']=true;
    }
    unset($request['update']['_id']);
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
