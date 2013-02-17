<?php

class fraudAlertsPlugin extends Billrun_Plugin_BillrunPluginBase {

	/**
	 * plugin name
	 *
	 * @var string
	 */
	protected $name = 'fraudAlerts';

	/**
	 * The timestamp of the start of the script
	 * 
	 * @var timestamp
	 */
	protected $startTime;

	public function __construct($options = array(
	)) {
		parent::__construct($options);

		$this->alertServer = isset($options['alertHost']) ?
			$options['alertHost'] :
			Billrun_Factory::config()->getConfigValue('fraudAlerts.alert.host', '127.0.0.1');

		$this->startTime = $_SERVER['REQUEST_TIME'];
	}

	public function handlerNotify() {
		$db = Billrun_Factory::db();
		$eventsCol = $db->getCollection($db::events_table);
		$lineCol = $db->getCollection($db::lines_table);

		$ret = $this->roamingNotify($eventsCol, $lineCol);

		return $ret;
	}

	/**
	 * Handle Roaming events and try to notify the remote server.
	 * 
	 * @param Mongo_Collection $eventsCol events mongo collection
	 * @param Mongo_Collection $linesCol lines mongo collection
	 * 
	 * @return array return value of each event status
	 */
	protected function roamingNotify($eventsCol, $linesCol) {
		$retValue = array(
			);
		//Aggregate the  events by imsi  taking only the first one.
		$events = $eventsCol->aggregate(array(
			'$match' => array(
				'notify_time' => array(
					'$exists' => false),
				'source' => array(
					'$in' => array(
						'nrtrde',
						'ggsn',
						'deposit',
						'ilds')))), array(
			'$sort' => array(
				'imsi' => 1,
				'msisdn' => 1)), array(
			'$group' => array(
				'_id' => array(
					'imsi' => '$imsi',
					'msisdn' => '$msidn'),
				'id' => array(
					'$addToSet' => '$_id'),
				'imsi' => array(
					'$first' => '$imsi'),
				'value' => array(
					'$first' => '$value'),
				'event_type' => array(
					'$first' => '$event_type'),
				'units' => array(
					'$first' => '$units'),
				'msisdn' => array(
					'$first' => '$msisdn'),
				'threshold' => array(
					'$first' => '$threshold'),
				'deposit_stamp' => array(
					'$first' => '$_id'),
			),));

		foreach ($events as $event) {
			if (isset($event['imsi']) && $event['imsi']) {
				$imsi = $event['imsi'];
			} else {
				$imsi = null;
			}

			if (isset($event['msisdn']) && $event['msisdn']) {
				$msisdn = $event['msisdn'];
			} else {
				$msisdn = null;
			}

			if ($this->notifyOnEvent($event)) {
				//Billrun_Log::getInstance()->log("handlerNotify ".print_r($event,1), Zend_Log::DEBUG);
				//mark events has dealt with.
				$events_where = array(
					'notify_time' => array(
						'$exists' => false
					),
					'_id' => array(
						'$in' => $event['id']),
				);
				$events_update_set = array(
					'$set' => array(
						'notify_time' => time(),
						'deposit_stamp' => $event['deposit_stamp'],
					)
				);
				$update_options = array(
					'multiple' => 1
				);
				$eventsCol->update($events_where, $events_update_set, $update_options);

				//mark deposit for the lines on the current imsi 
				$lines_where = array(
					'process_time' => array(
						'$lt' => date('Y-m-d H:i:s', $this->startTime),
					)
				);
				
				if ($imsi) {
					$lines_where['imsi'] = $imsi;
				}
				
				if ($msisdn) {
					$lines_where['msisdn'] = $msisdn;
				}
				// what happened if no imsi and no msisdn ??
				$lines_update_set = array(
					'$set' => array(
						'deposit_stamp' => $event['deposit_stamp']
					)
				);
				$linesCol->update($lines_where, $lines_update_set, $update_options);
				$retValue[] = $event;
			}
		}
		return $retValue;
	}

	/**
	 * method to send events to the remote server
	 * 
	 * @param array $args the arguments to send to the remote server
	 * 
	 * @return mixed the response from the remote server after json encoding
	 */
	protected function notifyOnEvent($args) {
		//Billrun_Log::getInstance()->log("notifyOnEvent {$args['imsi']} with type : {$args['event_type']} , value : {$excedingValue}", Zend_Log::DEBUG);	
		$query_args = array(
			);
		$query_args['event_type'] = $args['event_type'];
		$query_args['threshold'] = $args['threshold'];
		$query_args['usage'] = $args['value'];
		unset($args['value']);
		$query_args['units'] = $args['units'];

		if (!Billrun_Factory::config()->isProd()) {
			$key = Billrun_Factory::config()->getConfigValue('fraudAlerts.alert.host', 'ofer');
			;

			$imsis = array(
				'ofer' => '425089209101076',
				'eran' => '425089109239847',
			);

			$msisdns = array(
				'ofer' => '546918666',
				'eran' => '547371030',
			);

			$args['imsi'] = $imsis[$key];
			$args['msisdn'] = $msisdns[$key];
		}

		if (isset($args['imsi'])) {
			$query_args['IMSI'] = $args['imsi'];
			unset($args['imsi']);
		} else if (isset($args['msisdn'])) {
			$query_args['NDC_SN'] = $args['msisdn'];
			unset($args['msisdn']);
		} else {
			Billrun_Log::getInstance()->log("fraudAlertsPlugin::notifyOnEvent cannot find IMSI nor NDC_SN", Zend_Log::DEBUG);
		}

//		$url = $this->alertServer."?event_type={$args['event_type']}".
//														(isset($args['imsi']) && $args['imsi'] ? "&IMSI={$args['imsi']}" : "").
//														(isset($args['msisdn']) && $args['msisdn'] ?"&NDC_SN={$args['msisdn']}" : "").
//														"&threshold={$args['threshold']}".
//														"&usage={$args['value']}".
//														"&units={$args['units']}";
		// TODO: use Zend_Http_Client instead
		// http://framework.zend.com/manual/1.12/en/zend.http.client.adapters.html#zend.http.client.adapters.curl
		$url = 'http://' . $this->alertServer . http_build_query($query_args);
		$post_array = array_diff($args, $query_args);
		$client = curl_init($url);
		curl_setopt($client, CURLOPT_POST, TRUE);
		curl_setopt($client, CURLOPT_POSTFIELDS, array(
			'extra_data' => Zend_Json::encode($post_array)));
		curl_setopt($client, CURLOPT_RETURNTRANSFER, TRUE);
		$response = curl_exec($client);
		curl_close($client);

		Billrun_Log::getInstance()->log("fraudAlertsPlugin::notifyOnEvent " . print_r(json_decode($response), 1), Zend_Log::DEBUG);

		return Zend_Json::decode($response);
	}

}