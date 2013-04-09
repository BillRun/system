<?php

class Subscriber_Golan extends Billrun_Subscriber
{

	/**
	 * method to send request to Golan rpc
	 * 
	 * @param string $phone the phone number of the client
	 * @param string $time the time that phone requested
	 * 
	 * @return array subscriber details
	 */
	static protected function request($phone, $time)
	{
		
		$host = Billrun_Factory::config()->getConfigValue('provider.rpc.server', 'gtgt.no-ip.org');
		$url = Billrun_Factory::config()->getConfigValue('provider.rpc.url','gt-dev/dev/rpc/subscribers_by_date.rpc.php');
		$datetime_format = Billrun_Base::base_dateformat; // 'Y-m-d H:i:s';
		$params = array(
			'NDC_SN' => self::NDC_SN($phone),
			'date' => date($datetime_format, strtotime($time)),
		);

		$path = 'http://' . $host . '/' . $url . '?' . http_build_query($params);
		// @TODO: use Zend_Http_Client
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

	/**
	 * method to verify phone number is in NDC_SN format (with leading zero)
	 * 
	 * @param string $phone phone number
	 * 
	 * @return type string
	 */
	static protected function NDC_SN($phone)
	{
		if (substr($phone, 0, 1) == '0')
		{
			return substr($phone, 1, strlen($phone) - 1);
		}
		return $phone;
	}

	/**
	 * method to send http request via curl
	 * 
	 * @param string $url the url to send
	 * 
	 * @return string the request output
	 * 
	 * @todo use Zend_Http_Client
	 */
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

	/**
	 * method to load subsbscriber details
	 * 
	 * @param array $params the params to load by
	 * 
	 * @return Subscriber_Golan self object for chaining calls and use magic method for properties
	 */
	public function load($params) {
		if (isset($params['phone'])) {
			if (isset($params['time'])) {
				$time = $params['time'];
			} else {
				$time = date(Billrun_Base::base_dateformat);
			}
			$data = self::request($params['phone'], $time);
			$this->availableFields = array_keys($data);
			$this->data = $data;
		}
		return $this;
	}

	/**
	 * method to save subsbscriber details
	 */
	public function save() {
		return $this;
	}

	/**
	 * method to delete subsbscriber entity
	 */
	public function delete() {
		return TRUE;
	}

}