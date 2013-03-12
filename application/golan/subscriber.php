<?php

class golan_subscriber
{
	static $subscribersCache = array();
	static public function get($phone, $time)
	{
		// @todo: refactoring
//		$conn = Mongodloid_Connection::getInstance();
//		$db = $conn->getDB('billing');
//		$accountCollectionName = 'accounts';
//		$collection = $db->getCollection($accountCollectionName);
//		$query = array('$where' => '');
//		$collection->query($query);
		// @todo: store the data and avoid too much requests
		if( isset(self::$subscribersCache[$phone]) && self::$subscribersCache[$phone] &&
		   ( date_create(self::$subscribersCache[$phone]['last_modified']) < date_create($time) ) ) {
			return self::$subscribersCache[$phone];
		}

		$retSubscriber = self::request($phone, $time);
		if( $retSubscriber && (!isset($retSubscriber['end_date']) && $retSubscriber['status'] == "in_use" ) ) {
			self::$subscribersCache[$phone] = $retSubscriber;
		}
		return $retSubscriber;
		//return self::request($phone, $time);
	}

	static protected function request($phone, $time)
	{
		//http://192.168.37.10/gt-dev/dev/rpc/subscribers_by_date.rpc.php?date=2012-07-19 08:12&NDC_SN=502052428
		$host = '192.168.37.10';
		//http://gtgt.no-ip.org/gt-dev/dev/rpc/subscribers_by_date.rpc.php?date=2012-07-19 08:12&NDC_SN=502052428
//		$host = 'gtgt.no-ip.org';
		$url = 'gt-dev/dev/rpc/subscribers_by_date.rpc.php';
		$datetime_format = 'Y-m-d H:i:s';
		$params = array(
			'NDC_SN' => self::NDC_SN($phone),
			'date' => date($datetime_format, strtotime($time)),
		);

		$path = 'http://' . $host . '/' . $url . '?' . http_build_query($params);
		$json = self::send($path);

		if (!$json)
		{
			return false;
		}

		$object = @json_decode($json);

		if (!$object || !isset($object->result) || !$object->result || !isset($object->data))
		{
			return false;
		}

		return (array) $object->data;
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
		curl_setopt($ch, CURLOPT_USERPWD, 'free:free');

		// grab URL and pass it to the browser
		$output = curl_exec($ch);

		// close cURL resource, and free up system resources
		curl_close($ch);

		return $output;
	}

}