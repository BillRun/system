<?php

class golan_subscriber
{
	static $subscribersCache = array();
	static public function get($time, $phone, $imsi)
	{
		// @todo: refactoring
//		$conn = Mongodloid_Connection::getInstance();
//		$db = $conn->getDB('billing');
//		$accountCollectionName = 'accounts';
//		$collection = $db->getCollection($accountCollectionName);
//		$query = array('$where' => '');
//		$collection->query($query);
		// @todo: store the data and avoid too much requests
		if (!empty($phone)) {
			$identifier = $phone;
		}
		elseif (!empty($imsi)) {
			$identifier = $imsi;
		}
		else {
			Billrun_Factory::log()->log("phone or imsi must be set, they null. phone:" . $phone . " imsi: " . $imsi, Zend_Log::WARN);
			return false;
		}
		
		if( isset(self::$subscribersCache[$identifier]) && self::$subscribersCache[$identifier]) {
			return self::$subscribersCache[$identifier];
		}

		$retSubscriber = self::request($time, $phone, $imsi);

		if( $retSubscriber && (!isset($retSubscriber['end_date']) && isset($retSubscriber['status']) && !empty($phone)) ) {
			if($retSubscriber['status'] == "in_use") {
				self::$subscribersCache[$identifier] = $retSubscriber;
			}
		}
		else {
			self::$subscribersCache[$identifier] = $retSubscriber;
		}
		
		return $retSubscriber;
		//return self::request($phone, $time);
	}

	static protected function request($time, $phone, $imsi)
	{
		//http://192.168.37.10/gt-dev/dev/rpc/subscribers_by_date.rpc.php?date=2012-07-19 08:12&NDC_SN=502052428
//		$host = '192.168.37.10';
		//http://gtgt.no-ip.org/gt-dev/dev/rpc/subscribers_by_date.rpc.php?date=2012-07-19 08:12&NDC_SN=502052428
//		$host = 'gtgt.no-ip.org';
		$host = Billrun_Factory::config()->getConfigValue('provider.rpc.server', '172.29.202.20');
		
		if (!empty($imsi)) {
			$url = 'gt-dev/dev/admin/subscriber_plan_by_date.rpc.php';
		}
		else {
			$url = 'gt-dev/dev/rpc/subscribers_by_date.rpc.php';
		}
		
		$datetime_format = 'Y-m-d H:i:s';
		$params = array(
			'IMSI' => $imsi,
			'NDC_SN' => self::NDC_SN($phone),
			'DATETIME' => date($datetime_format, strtotime($time)),
		);

		$path = 'http://' . $host . '/' . $url . '?' . http_build_query($params);
		$json = self::send($path);
		
		if (!$json) {
			return false;
		}

		$object = @json_decode($json);

		if (!$object ||  (!empty($phone) && (!isset($object->result) || !$object->result || !isset($object->data))) )
		{
			return false;
		}

		if(!empty($phone))
			return (array) $object->data;
		
		return (array) $object;
	}

	static protected function NDC_SN($phone)
	{
		if (substr($phone, 0, 1) == '0')
		{
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