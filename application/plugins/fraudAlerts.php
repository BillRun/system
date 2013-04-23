<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012 S.D.O.C. LTD. All rights reserved.
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */

/**
 * Fraud alerts plugin
 *
 * @package  Application
 * @subpackage Plugins
 * @since    0.5
 */
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
	
	/**
	 * Is the  Alert plugin in a dry run mode (doesn't  actually sends alerts)
	 * @var timestamp
	 */
	protected $isDryRun = false;

	public function __construct(array $options = array()) {

		$this->alertServer = isset($options['alertHost']) ?
			$options['alertHost'] :
			Billrun_Factory::config()->getConfigValue('fraudAlerts.alert.host', '127.0.0.1');
		
		$this->alertPath = isset($options['alertPath']) ?
			$options['alertPath'] :
			Billrun_Factory::config()->getConfigValue('fraudAlerts.alert.path', '/');
		
		$this->alertTypes = isset($options['alertTypes']) ?
			$options['alertTypes'] :
			Billrun_Factory::config()->getConfigValue('fraudAlerts.alert.types', array( 'nrtrde','ggsn', 'deposit','ilds','nsn') );

		$this->isDryRun = isset($options['dryRun']) ?
			$options['dryRun'] :
			Billrun_Factory::config()->getConfigValue('fraudAlerts.alert.dry_run', false);
		
		$this->startTime = time();
		
		$this->eventsCol = Billrun_Factory::db()->eventsCollection();
		$this->linesCol = Billrun_Factory::db()->linesCollection();

	}

	/**
	 * Handle Notification that should be done on events that were logged in the system.
	 * @param type $handler the caller handler.
	 * @return type
	 */
	public function handlerNotify($handler) {
		
		$ret = $this->roamingNotify();

		return $ret;
	}

	/**
	 * Handle Roaming events and try to notify the remote server.
	 * @return array return value of each event status
	 */
	protected function roamingNotify() {
		$retValue = array();
		//Aggregate the  events by imsi  taking only the first one.
		$events = $this->gatherEvents($this->alertTypes);
		//Get the amount of alerts allow per run 0 means no limit (or a very high limit)
		$alertsLeft = Billrun_Factory::config()->getConfigValue('fraudAlerts.alert_limit', 0);
		
		foreach ($events as $event) {
			$ret = $this->notifyOnEvent($event);
			if (isset($ret['success']) && $ret['success']) {
				$event['deposit_stamp'] = $event['stamps'][0];// remember what event you sent to the remote server
				$event['returned_value'] = $ret;
				//Billrun_Log::getInstance()->log("handlerNotify ".print_r($event,1), Zend_Log::DEBUG);
				$this->markEvent($event);
				$this->markEventLine($event);				
				
				//Decrease the amount of alerts allowed in a single run if 0 is reached the break the loop.
				$alertsLeft--;
			}
			
			if($alertsLeft == 0) {break;}
			$retValue[] = $event;
		}
		
		return $retValue;
	}

	/**
	 * Method to send events to the appropiate hadling body. 
	 * @param array $args the arguments to send to the remote server.
	 * @return mixed the response from the remote server after json decoding
	 */
	protected function notifyOnEvent($args) {
		//Billrun_Log::getInstance()->log("notifyOnEvent {$args['imsi']} with type : {$args['event_type']} , value : {$excedingValue}", Zend_Log::DEBUG);	
		
		if (!(isset($args['imsi']) || isset($args['msisdn']))) {
			Billrun_Log::getInstance()->log("fraudAlertsPlugin::notifyOnEvent cannot find IMSI nor NDC_SN", Zend_Log::DEBUG);
		}
		$alertsFilter = Billrun_Factory::config()->getConfigValue('fraudAlerts.filter', array());
		if(!empty($alertsFilter) && !in_array($args['event_type'], $alertsFilter)) {
				return FALSE;
		}
		
		if (!Billrun_Factory::config()->isProd()) {
			$key = Billrun_Factory::config()->getConfigValue('fraudAlerts.alert.key', '');
			$testData = Billrun_Factory::config()->getConfigValue('fraudAlerts.alert.testKeys', array());

			$args['imsi'] = $testData[$key]['imsi'];
			$args['msisdn'] = $testData[$key]['msisdn'];
		}
		
		//unset uneeded fields...
		unset($args['id']);
		unset($args['deposit_stamp']);
		
		//move field that are required to a specific array leave extra field in the old one.
		$required_args = array('event_type' => 'event_type',
							'threshold' => 'threshold',
							'usage' => 'value',
							'units' => 'units',
							'IMSI' => 'imsi',
							'NDC_SN' => 'msisdn',
			);
		
		foreach($required_args as $key => $argsKey) {
			$required_args[$key] = isset ($args[$argsKey]) ? $args[$argsKey] : null;
			unset( $args[$argsKey] );
		}
		
		$ret =  $this->notifyRemoteServer($required_args, $args);
		return $ret;
	}
	
	/**
	 * Notify remote server on an event.
	 * @param type $query_args the argument to pass in the url query
	 * @param type $post_args extra data to pass as post data.
	 * @return the decoded values that was return  from the remote server (using json).
	 */
	protected function notifyRemoteServer($query_args, $post_args) {
		// TODO: use Zend_Http_Client instead
		// http://framework.zend.com/manual/1.12/en/zend.http.client.adapters.html#zend.http.client.adapters.curl
		$url = 'http://' . $this->alertServer . $this->alertPath .'?' . http_build_query($query_args);
		unset($post_args['stamps']);
		$post_array = array_diff($post_args, $query_args);
		$post_fields = array(
			'extra_data' => Zend_Json::encode($post_array)
		);
		Billrun_Log::getInstance()->log("fraudAlertsPlugin::notifyRemoteServer  URL :" . $url, Zend_Log::DEBUG);

		if(!$this->isDryRun) {
			$client = curl_init($url);
			curl_setopt($client, CURLOPT_POST, TRUE);
			curl_setopt($client, CURLOPT_POSTFIELDS, $post_fields);
			curl_setopt($client, CURLOPT_RETURNTRANSFER, TRUE);
			$response = curl_exec($client);
			curl_close($client);

			Billrun_Log::getInstance()->log("fraudAlertsPlugin::notifyRemoteServer " .$response, Zend_Log::DEBUG);
			Billrun_Log::getInstance()->log("fraudAlertsPlugin::notifyRemoteServer " . print_r(json_decode($response), 1), Zend_Log::DEBUG);

			return Zend_Json::decode($response);
		} else {
			Billrun_Log::getInstance()->log("fraudAlertsPlugin::notifyRemoteServer - Running in DRY RUN mode returning successful alert.", Zend_Log::DEBUG);
			return array('deposit_result' => 1 ,
						'transaction_status' => 000,
						'phase' => NULL, 
						'subscriber_id' => 1337, 
						'NDC_SN' => $query_args['NDC_SN'], 
						'IMSI' => $query_args['IMSI'], 
						'SECOND_IMSI' => $query_args['IMSI'], 
						'account_id' => 1337, 
						'SMS' => 1, 
						'EMAIL' => 1,
						'success' => 1);
		}
	}
	
	/**
	 * get unhandled events from the DB.
	 * @param Array $types the type (sources) of the events to gather.
	 * @return Array an array containg the events pulled from the data base.
	 */
	protected function gatherEvents($types) {
			$events = $this->eventsCol->aggregate(
					array(
							'$match' => array(
									'notify_time' => array(	'$exists' => false),
									'source' => array( '$in' => $types )
							),
						), 
					array(
							'$sort' => array( 'imsi' => 1, 'msisdn' => 1)
						), 
					array(
							'$group' => array(
									'_id' => array( 'imsi' => '$imsi', 'msisdn' => '$msisdn'),
									'id' => array( '$addToSet' => '$_id'),
									'imsi' => array( '$first' => '$imsi'),
									'value' => array( '$first' => '$value'),
									'event_type' => array( '$first' => '$event_type'),
									'units' => array( '$first' => '$units'),
									'msisdn' => array( '$first' => '$msisdn'),
									'threshold' => array( '$first' => '$threshold'),
									'deposit_stamp' => array( '$first' => '$_id'),
									'source' => array( '$first' => '$source' ),
									'stamps' => array( '$addToSet' => '$stamp' ),
								),
						),
					array(
							'$project' => array(
									'_id' => 0,
									'imsi' => '$_id.imsi',
									'msisdn' => '$_id.msisdn',
									'value' => 1,
									'event_type' => 1,
									'units' => 1,
									'threshold' => 1,
									'deposit_stamp' => 1,
									'id' => 1,
									'source' => 1,
									'stamps' => 1,
								),
						)
			);
		return $events;
	}
	
	/**
	 * Mark an specific event as finished event. 
	 * @param type $event the event to mark as dealt with.
	 */
	protected function markEvent($event) {
		//mark events as dealt with.
		$events_where = array(
			'notify_time' => array('$exists' => false),
			'_id' => array('$in' => $event['id']),
		);
		$events_update_set = array(
			'$set' => array(
				'notify_time' => time(),
				'deposit_stamp' => $event['deposit_stamp'],
				'returned_value' => $event['returned_value'],
			),
		);
		$update_options = array('multiple' => 1);
		$this->eventsCol->update($events_where, $events_update_set, $update_options);
	}
	
	/**
	 * Mark the lines that generated the event as dealt with.
	 * @param type $event the event that relate to the lines.
	 */
	protected function markEventLine($event) {
			//mark deposit for the lines on the current imsi 
			$imsi =  (isset($event['imsi']) && $event['imsi']) ? $event['imsi'] : null;
			$msisdn = (isset($event['msisdn']) && $event['msisdn']) ? $event['msisdn'] : null;

			$lines_where = array(
				'process_time' => array( '$gt' => date(Billrun_Base::base_dateformat, Billrun_Util::getLastChargeTime()) ),
				'process_time' => array( '$lt' => date(Billrun_Base::base_dateformat, $this->startTime) ),
				'deposit_stamp' => array( '$exists' => false ),
			);

			if ($imsi) {
				$lines_where['imsi'] = $imsi;
			}

			if ($msisdn) {
				$lines_where['msisdn'] = $msisdn;
			}
			$lines_update_set = array(
					'$set' => array( 'deposit_stamp' => $event['deposit_stamp'] )
				);
			$update_options = array( 'multiple' => 1 );
			
			if(!($imsi || $msisdn)) {
				Billrun_Log::getInstance()->log("fraudAlertsPlugin::markEventLine cannot find IMSI nor NDC_SN on event, marking CDR lines with event_stamp of : ". print_r($event['stamps'],1), Zend_Log::DEBUG);
				$lines_where['event_stamp'] = array( '$in' =>  $event['stamps']); 
			}
			
			$this->linesCol->update($lines_where, $lines_update_set, $update_options);				
	}
	
}
