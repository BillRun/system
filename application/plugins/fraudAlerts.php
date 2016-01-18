<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2013 S.D.O.C. LTD. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
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
	 * @var boolean
	 */
	protected $isDryRun = false;

	public function __construct($options = array()) {

		$this->alertServer = isset($options['alertHost']) ?
				$options['alertHost'] :
				Billrun_Factory::config()->getConfigValue('fraudAlerts.alert.host', '127.0.0.1');

		$this->alertPath = isset($options['alertPath']) ?
				$options['alertPath'] :
				Billrun_Factory::config()->getConfigValue('fraudAlerts.alert.path', '/');

		$this->alertTypes = isset($options['alertTypes']) ?
				$options['alertTypes'] :
				Billrun_Factory::config()->getConfigValue('fraudAlerts.alert.types', array('nrtrde', 'ggsn', 'deposit', 'ilds', 'nsn', 'billing1', 'billing2'));

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
	public function handlerNotify($handler, $options) {

		if ($options['type'] != 'notify') {
			return FALSE;
		}
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

		$alertsFilter = Billrun_Factory::config()->getConfigValue('fraudAlerts.filter', array());
		foreach ($events as $event) {
			// check if alert is filter by configuration
			if (!empty($alertsFilter) && !in_array($event['event_type'], $alertsFilter)) {
				continue;
			}

			$ret = $this->notifyOnEvent($event);
			if (isset($ret['success']) && $ret['success']) {
				$event['deposit_stamp'] = $event['stamps'][0]; // remember what event you sent to the remote server
				$event['returned_value'] = (array) $ret;
				$this->markEvent($event);
				if (!isset($ret['ignored']) || !$ret['ignored']) {
					$this->markEventLines($event);
				}
			} else if (isset($ret['success']) && !$ret['success']) {
				$this->sendEmailOnFailure($event, $ret);
				$this->markEvent($event, FALSE);
			} else {
				//some connection failure - mark event as paused
				$this->sendEmailOnFailure($event, $ret);
				$this->markEvent($event, FALSE);
				$this->markEventLines($event);
			}

			//Decrease the amount of alerts allowed in a single run if 0 is reached the break the loop.
			$alertsLeft--;

			if ($alertsLeft <= 0) {
				break;
			}
			$retValue[] = $event;
		}

		return $retValue;
	}

	/**
	 * Send an  email alert on events that failed to notify the  remote RPC (CRM)
	 * @param type $event the event that  was  suppose  to be passed to the RPC.
	 * @param type $rpcRet the  remote rpc returned value
	 */
	protected function sendEmailOnFailure($event, $rpcRet) {
		$msg = "Failed  when sending event to RPC" . PHP_EOL;
		$msg .= "Event : stamp : {$event['stamps'][0]} , imsi :  " . @$event['imsi'] . " ,  msisdn :  " . (@$event['msisdn']) .  ", sid :  " . (@$event['sid']) . PHP_EOL;
		$msg .= (isset($rpcRet['message']) ? "Message From RPC: " . $rpcRet['message'] : "No  failure  message  from the RPC.") . PHP_EOL;
		$tmpStr = "";
		if (is_array($rpcRet)) {
			foreach ($rpcRet as $key => $val) {
				$tmpStr .= " $key : $val,";
			}
		}
		$msg .= "RPC Result : " . $tmpStr . PHP_EOL;
		Billrun_Factory::log()->log($msg, Zend_Log::ALERT);
		return Billrun_Util::sendMail("Failed Fraud Alert, " . date(Billrun_Base::base_dateformat), $msg, Billrun_Factory::config()->getConfigValue('fraudAlert.failed_alerts.recipients', array()));
	}

	/**
	 * Method to send events to the appropiate hadling body. 
	 * @param array $args the arguments to send to the remote server.
	 * @return mixed the response from the remote server after json decoding
	 */
	protected function notifyOnEvent($args) {
		if (!(isset($args['imsi']) || isset($args['msisdn']) || isset($args['sid']) )) {
			Billrun_Log::getInstance()->log("fraudAlertsPlugin::notifyOnEvent cannot find IMSI nor NDC_SN", Zend_Log::NOTICE);
		}

		$forceTest = Billrun_Factory::config()->getConfigValue('fraudAlerts.forceTest', FALSE);
		if ($forceTest || !Billrun_Factory::config()->isProd()) {
			$key = Billrun_Factory::config()->getConfigValue('fraudAlerts.alert.key', '');
			$testData = Billrun_Factory::config()->getConfigValue('fraudAlerts.alert.testKeys', array());

			$args['imsi'] = $testData[$key]['imsi'];
			$args['msisdn'] = $testData[$key]['msisdn'];
			$args['aid'] = $testData[$key]['aid'];
			$args['sid'] = $testData[$key]['sid'];
		}

		//unset uneeded fields...
		unset($args['id']);
		unset($args['deposit_stamp']);

		//move field that are required to a specific array leave extra field in the old one.
		$required_args = array(
			'event_type' => 'event_type',
			'threshold' => 'threshold',
			'usage' => 'value',
			'units' => 'units',
			'IMSI' => 'imsi',
			'NDC_SN' => 'msisdn',
			'account_id' => 'aid',
			'subscriber_id' => 'sid',
			'plan' => 'plan',
			'target_plans' => 'target_plans',
			'group' => 'group'
		);

		foreach ($required_args as $key => $argsKey) {
			$required_args[$key] = isset($args[$argsKey]) ? (is_array($args[$argsKey]) ? implode(",", $args[$argsKey]) : $args[$argsKey] ) : null;
			unset($args[$argsKey]);
		}

		$ret = $this->notifyRemoteServer($required_args, $args);
		return $ret;
	}

	/**
	 * Notify remote server on an event.
	 * @param type $query_args the argument to pass in the url query
	 * @param type $post_args extra data to pass as post data
	 * 
	 * @return mixed on success - the decoded values that was return  from the remote server (using json). on failure - false
	 */
	protected function notifyRemoteServer($query_args, $post_args) {
		// TODO: use Zend_Http_Client instead
		// http://framework.zend.com/manual/1.12/en/zend.http.client.adapters.html#zend.http.client.adapters.curl
		$url = 'http://' . $this->alertServer . $this->alertPath . '?' . http_build_query($query_args);
		unset($post_args['stamps']);
		$post_array = @array_diff($post_args, $query_args);
		$post_fields = array(
			'extra_data' => Zend_Json::encode($post_array)
		);
		Billrun_Log::getInstance()->log("fraudAlertsPlugin::notifyRemoteServer URL: " . $url, Zend_Log::INFO);
		Billrun_Log::getInstance()->log("fraudAlertsPlugin::notifyRemoteServer Post: " . print_r($post_fields, 1), Zend_Log::INFO);

		if (!$this->isDryRun) {
			$timeout = intval(Billrun_Factory::config()->getConfigValue('fraudAlerts.timeout', 30));
			$output = Billrun_Util::sendRequest($url, $post_fields, Zend_Http_Client::POST, array('Accept-encoding' => 'deflate'), $timeout);

			Billrun_Log::getInstance()->log("fraudAlertsPlugin::notifyRemoteServer response: " . $output, Zend_Log::INFO);

			$ret = json_decode($output, true);

			if (is_null($ret)) {
				Billrun_Log::getInstance()->log("fraudAlertsPlugin::notifyRemoteServer response is empty, null or not json string: " .$output , Zend_Log::ERR);
				return FALSE;
			}

			Billrun_Log::getInstance()->log("fraudAlertsPlugin::notifyRemoteServer decode: " . print_r($ret, 1), Zend_Log::INFO);

			return $ret;
		} else {
			Billrun_Log::getInstance()->log("fraudAlertsPlugin::notifyRemoteServer - Running in DRY RUN mode returning successful alert.", Zend_Log::INFO);
			return array('deposit_result' => 1,
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
				'notify_time' => array('$exists' => false),
				'source' => array('$in' => $types)
			),
				), array(
			'$sort' => array('priority' => 1, 'creation_time' => 1)
				), array(
			'$group' => array(
				'_id' => array('imsi' => '$imsi', 'msisdn' => '$msisdn', 'sid' => '$sid','channel' => '$channel'),
				'id' => array('$addToSet' => '$_id'),
				'imsi' => array('$first' => '$imsi'),
				'msisdn' => array('$first' => '$msisdn'),
				'aid' => array('$first' => '$aid'),
				'value' => array('$first' => '$value'),
				'event_type' => array('$first' => '$event_type'),
				'units' => array('$first' => '$units'),
				'threshold' => array('$first' => '$threshold'),
				'priority' => array('$first' => '$priority'),
				//'deposit_stamp' => array('$first' => '$_id'),
				'source' => array('$first' => '$source'),
				'plan' => array('$first' => '$plan'),
				'target_plans' => array('$first' => '$target_plans'),
				'group' => array('$first' => '$group'),
				'stamps' => array('$addToSet' => '$stamp'),
			),
			), array(
			'$sort' => array('priority' => 1)
			), array(
			'$project' => array(
				'_id' => 0,
				'imsi' => '$_id.imsi',
				'msisdn' => '$_id.msisdn',
				'sid' => '$_id.sid',
				'aid' => 1,
				'value' => 1,
				'event_type' => 1,
				'units' => 1,
				'threshold' => 1,
				//	'deposit_stamp' => 1,
				'id' => 1,
				'source' => 1,
				'stamps' => 1,
				'plan' => 1,
				'target_plans' => 1,
				'group' => 1,
			),
				)
		);
		return $events;
	}

	/**
	 * Mark an specific event as finished event. 
	 * @param array $event the event to mark as dealt with.
	 * @param mixed $failure info on failure
	 */
	protected function markEvent($event, $failure = null) {
		Billrun_Log::getInstance()->log("Fraud alerts mark event " . ($failure !== null ? join(",",$event['stamps']) : $event['deposit_stamp'] ), Zend_Log::INFO);
		//mark events as dealt with.
		$events_where = array(
			'notify_time' => array('$exists' => false),
			'_id' => array('$in' => $event['id']),
		);

		if (is_null($failure)) { // no failure
			$events_update_set = array(
				'$set' => array(
					'notify_time' => new MongoDate(),
					'deposit_stamp' => $event['deposit_stamp'],
					'returned_value' => $event['returned_value'],
				),
			);
		} else {
			$events_update_set = array(
				'$set' => array(
					'notify_time' => new MongoDate(),
					'deposit_stamp' => 'ERROR-' . date(Billrun_Base::base_dateformat),
					'returned_value' => $failure,
				),
			);
		}
		$update_options = array('multiple' => 1);
		return $this->eventsCol->update($events_where, $events_update_set, $update_options);
	}

	/**
	 * Mark the lines that generated the event as dealt with.
	 * @param type $event the event that relate to the lines.
	 */
	protected function markEventLines($event) {
		if ($event['source'] == 'billing') { // HACK to prvent the fraud from trying to mark  the lines of an event  that originated in the billing system
			return;
		}
		//mark deposit for the lines on the current imsi
		Billrun_Log::getInstance()->log("Fraud alerts mark event lines " . $event['deposit_stamp'], Zend_Log::INFO);
		$imsi = (isset($event['imsi']) && $event['imsi']) ? $event['imsi'] : null;
		//$msisdn = (isset($event['msisdn']) && $event['msisdn']) ? $event['msisdn'] : null;
		$sid = (isset($event['sid']) && $event['sid']) ? $event['sid'] : null;
		// backward compatibility
		$subscriber_id = (isset($event['subscriber_id']) && $event['subscriber_id']) ? $event['subscriber_id'] : null;
		$lines_where = array();

		if (isset($subscriber_id)) {
			$lines_where['subscriber_id'] = $subscriber_id;
			$hint = array('subscriber_id' => 1);
		} else if (isset($sid)) {
			$lines_where['subscriber_id'] = $sid;
			$hint = array('subscriber_id' => 1);
		}

		if (isset($imsi)) {
			$lines_where['imsi'] = $imsi;
			if (!isset($hint)) {
				$hint = array('imsi' => 1);
			}
		}

		/* TODO no lines have this field reinstate once all lines have this field.
		 * if (isset($msisdn)) {
			$lines_where['msisdn'] = $msisdn;
			if (!isset($hint)) {
				$hint = array('msisdn' => 1);
			}
		}*/

//		if (isset($event['effects'])) {
//			$lines_where[$event['effects']['key']] = $event['effects']['filter'];
//		} else {
//			$lines_where['type'] = $event['source'];
//		}
//
		$lines_where['process_time'] = array('$gt' => date(Billrun_Base::base_dateformat, Billrun_Util::getLastChargeTime()));
		$lines_where['process_time'] = array('$lt' => date(Billrun_Base::base_dateformat, $this->startTime));
		$lines_where['deposit_stamp'] = array('$exists' => false);

		if (!($imsi || $sid || $subscriber_id )) {
			Billrun_Log::getInstance()->log("fraudAlertsPlugin::markEventLines cannot find IMSI nor NDC_SN  or SID on event, marking CDR lines with event_stamp of : " . print_r($event['stamps'], 1), Zend_Log::INFO);
			$lines_where['event_stamp'] = array('$in' => $event['stamps']);
		}

		// the update will done manually due to performance with collection update (not supported with hint)
		// @TODO: when update command will suport hint will use update (see remark code after foreach loop)
		$rows = $this->linesCol->query($lines_where)->cursor(); //->hint($hint);
		if (isset($hint)) {
			$rows->hint($hint);
		}
		foreach ($rows as $row) {
			$row->collection($this->linesCol);
			$row->set('deposit_stamp', $event['deposit_stamp']);
			$row->save($this->linesCol);
		}

		// forward compatibility
//			$lines_update_set = array(
//					'$set' => array( 'deposit_stamp' => $event['deposit_stamp'] )
//			);
//			$update_options = array( 'multiple' => 1 );
//			
//			$this->linesCol->update($lines_where, $lines_update_set, $update_options);				
	}

}
