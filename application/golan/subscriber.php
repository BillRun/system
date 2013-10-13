<?php

class golan_subscriber
{
	static $subscribersCache = array();
	static public function get($customer_identification)
	{
		// @todo: refactoring
//		$conn = Mongodloid_Connection::getInstance();
//		$db = $conn->getDB('billing');
//		$accountCollectionName = 'accounts';
//		$collection = $db->getCollection($accountCollectionName);
//		$query = array('$where' => '');
//		$collection->query($query);
		// @todo: store the data and avoid too much requests
		$key_feild_name = key($customer_identification['key']);
		$time_feild_name = key($customer_identification['time']);
		if (empty($customer_identification['key'][$key_feild_name]) || empty($customer_identification['time'][$time_feild_name])) {
			Billrun_Factory::log()->log("time and customer_identification must be set - $key_feild_name: ".$customer_identification['key'][$key_feild_name]." time: ".$customer_identification['time'][$time_feild_name], Zend_Log::WARN);
			return false;
		}
		
		if( isset(self::$subscribersCache[$customer_identification['key'][$key_feild_name]]) && self::$subscribersCache[$customer_identification['key'][$key_feild_name]]) {
			return self::$subscribersCache[$customer_identification['key'][$key_feild_name]];
		}

		$params = array();
		$params['DATETIME'] = $customer_identification['time'][$time_feild_name];
		if ($key_feild_name == 'imsi') {
			$params['IMSI'] = $customer_identification['key'][$key_feild_name];
		}
		else {
			$params['NDC_SN'] = $customer_identification['key'][$key_feild_name];
		}
			
		$retSubscriber = self::request($params);

		if( !$retSubscriber || isset($retSubscriber['error']) || (isset($retSubscriber['success']) && $retSubscriber['success'] == true)  ) {
			Billrun_Factory::log()->log("Bad RPC result for the customer: - $key_feild_name: ".$customer_identification['key'][$key_feild_name]." time: ".$customer_identification['time'][$time_feild_name]. PHP_EOL ."result : ". print_r($retSubscriber), Zend_Log::WARN);
			return false;
		}

		self::$subscribersCache[$customer_identification['key'][$key_feild_name]] = $retSubscriber;
		
		return $retSubscriber;
		//return self::request($phone, $time);
	}

	static protected function request($params)
	{
		//http://192.168.37.10/gt-dev/dev/rpc/subscribers_by_date.rpc.php?date=2012-07-19 08:12&NDC_SN=502052428
//		$host = '192.168.37.10';
		//http://gtgt.no-ip.org/gt-dev/dev/rpc/subscribers_by_date.rpc.php?date=2012-07-19 08:12&NDC_SN=502052428
//		$host = 'gtgt.no-ip.org';
		$host = Billrun_Factory::config()->getConfigValue('provider.rpc.server');
		$url = Billrun_Factory::config()->getConfigValue('provider.rpc.url');

		$path = 'http://' . $host . '/' . $url . '?' . http_build_query($params);
		$json = self::send($path);
		
		if (!$json) {
			return false;
		}

		$object = @json_decode($json);

		if (!$object ||  (isset($object->data) && empty($object->data)) || (isset($object->result) && empty($object->result)) ) {
			return false;
		}

		if(isset($object->data)) {
			return (array) $object->data;
		}
		
		return (array) $object;
	}

	static protected function NDC_SN($phone)
	{
		if (substr($phone, 0, 1) == '0') {
			return substr($phone, 1, strlen($phone) - 1);
		}
		return $phone;
	}

	static function send($url)
	{
		// create a new cURL resource
		$ch = curl_init();

		// set URL and other appropriate options
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_HEADER, FALSE);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
		curl_setopt($ch, CURLOPT_USERPWD, 'eranu:free');

		// grab URL and pass it to the browser
		$output = curl_exec($ch);

		// close cURL resource, and free up system resources
		curl_close($ch);

		return $output;
	}
}