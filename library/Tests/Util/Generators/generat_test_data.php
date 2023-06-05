
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


class generat_test_data{
  public  static $test_number;
  public static function setTestNumber($test_number=null){
     self::$test_number = $test_number;
  }
  public static function bulidAPI($entity, $params){
    foreach ($params as $key => $val) {
      if (!is_array($val)) {
        $request['update'][$key] = (string)$val;
      }else{
        $request['update'][$key] = $val;

      }
      $request['update']['generate_by_test']=true;
    }
    $request['update'] = json_encode($request['update']);
    $url = "http://{$_SERVER['SERVER_NAME']}/billapi/$entity/create";
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

  public function retry($entity, $params){
    $count =0;
   while($count < 10){
      $response = self::bulidAPI($entity, $params);
      $count++;
      if($response['status']=='1'||$response['status']==1){
        $count+=10;
      }
   };
   if(!$response['status']=='1'|| !$response['status']==1){
      throw new Exception ("test number: ".self::$test_number ." can't generate entity $entity with params: ".print_r($params)." response is: {$response['message']}");
   }
    return $response;
  }
 
  private function get()
  {
  }
}
