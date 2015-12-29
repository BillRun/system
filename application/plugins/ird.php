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
class irdPlugin extends Billrun_Plugin_BillrunPluginBase {

	use Billrun_Traits_FraudAggregation;
	
	/**
	 * plugin name
	 *
	 * @var string
	 */
	protected $name = 'ird';

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
	
	/**
	 * Alert proirity array
	 * @var array
	 */
	protected $priority = array();

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
		
		$this->priority = isset($options['priority']) ?
			$options['priority'] :
			Billrun_Factory::config()->getConfigValue('alert.priority', array());
		
		$this->initFraudAggregation();
	}
	
	///  =======================  Pricing override  for IRD  lines =====================
	protected $ird_daily = null;
	protected $line_type = null;

	public function beforeUpdateSubscriberBalance($balance, $row, $rate, $calculator) {
		if (isset($row['daily_ird_plan']) && $row['daily_ird_plan']) {
			$this->daily_ird = true;
		} else {
			$this->daily_ird = false;
		}
		$this->line_type = $row['type'];
	}

	/**
	 * method to override the plan group limits
	 * 
	 * @param type $rateUsageIncluded
	 * @param type $groupSelected
	 * @param type $limits
	 * @param type $plan
	 * @param type $usageType
	 * @param type $rate
	 * @param type $subscriberBalance
	 * 
	 * @todo need to verify when lines does not come in chronological order
	 */
	public function planGroupRule(&$rateUsageIncluded, &$groupSelected, $limits, $plan, $usageType, $rate, $subscriberBalance) {
		if ($groupSelected != 'IRD') {
			return;
		}
		if ($this->daily_ird) {
			$rateUsageIncluded = 'UNLIMITED';
		} else {
			$rateUsageIncluded = FALSE; // usage is not associated with ird, let's remove it from the plan usage association
			$groupSelected = FALSE; // we will cancel the usage as group plan when set to false groupSelected
		}

	}
	
	
	///  ==============================  IRD events =====================

	/**
	 * Handle Notification that should be done on events that were logged in the system.
	 * @param type $handler the caller handler.
	 * @return type
	 */
	public function handlerNotify($handler, $options) {

		if ($options['type'] != 'ird') {
			return FALSE;
		}
		$retValue = array();
		//Aggregate the  events by imsi  taking only the first one.
		$events = $this->gatherEvents($this->alertTypes);
		//Get the amount of alerts allow per run 0 means no limit (or a very high limit)
		$alertsLeft = Billrun_Factory::config()->getConfigValue('ird.alert_limit', 0);

		$alertsFilter = Billrun_Factory::config()->getConfigValue('ird.filter', array());
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
		$msg .= "Event : stamp : {$event['stamps'][0]} , imsi :  " . @$event['imsi'] . " ,  msisdn :  " . (@$event['msisdn']) . PHP_EOL;
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
				Billrun_Log::getInstance()->log("fraudAlertsPlugin::notifyRemoteServer response is empty, null or not json string: ", Zend_Log::ERR);
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
				'success' => 1,
				'ignored' => 0,
				);
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
				'event_type' => array( '$in' => array('70_IRD','90_IRD','100_IRD')),
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
		//mark deposit for the lines on the current imsi
		Billrun_Log::getInstance()->log("Fraud alerts mark event lines " . $event['deposit_stamp'], Zend_Log::INFO);
		$imsi = (isset($event['imsi']) && $event['imsi']) ? $event['imsi'] : null;
		$msisdn = (isset($event['msisdn']) && $event['msisdn']) ? $event['msisdn'] : null;
		$sid = (isset($event['sid']) && $event['sid']) ? $event['sid'] : null;
		// backward compatibility
		$subscriber_id = (isset($event['subscriber_id']) && $event['subscriber_id']) ? $event['subscriber_id'] : null;
		$lines_where = array();

		if (isset($subscriber_id)) {
			$lines_where['subscriber_id'] = $subscriber_id;
			$hint = array('subscriber_id' => 1);
		} else if (isset($sid)) {
			$lines_where['sid'] = $sid;
			$hint = array('sid' => 1);
		}

		if (isset($imsi)) {
			$lines_where['imsi'] = $imsi;
			if (!isset($hint)) {
				$hint = array('imsi' => 1);
			}
		}


		$lines_where['process_time'] = array('$gt' => date(Billrun_Base::base_dateformat, Billrun_Util::getLastChargeTime()));
		$lines_where['process_time'] = array('$lt' => date(Billrun_Base::base_dateformat, $this->startTime));
		$lines_where['ird_stamp'] = array('$lt' => $event['event_type']);

		if (!($imsi || $msisdn || $sid )) {
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
			$row->set('ird_stamp', $event['event_type']);
			$row->save($this->linesCol);
		}
				
	}

	
	//------------------------------------------------------
	
	
	/**
	 * method to collect data which need to be handle by event
	 */
	public function handlerCollect($options) {
		if ($options['type'] != 'ird') {
			return FALSE;
		}
		$events =array();
		//@TODO  switch  these lines  once  you have the time to test it.
		//$charge_time = new MongoDate($this->get_last_charge_time(true) - date_default_timezone_get() );
		$charge_time = Billrun_Util::getLastChargeTime(true);
		
		$advancedEvents = array();
		if (isset($this->fraudConfig['groups'])) {
			foreach ($this->fraudConfig['groups'] as $groupName => $groupIds) {
				$baseQuery = $this->getBaseAggregateQuery($charge_time, $groupName, $groupIds, true);				
				$advancedEvents = $this->collectFraudEvents($groupName, $groupIds, $baseQuery);
										
				$events = array_merge($events,$advancedEvents);
			}
		}

		



		return $events;
	}
	
	/**
	 * Collect events  using  the configured values.
	 * @param type $groupName
	 * @param type $groupIds
	 */
	protected function collectFraudEvents($groupName, $groupIds, $baseQuery) {
		$lines = Billrun_Factory::db()->linesCollection();
		$events = array();
		$timeField = $this->getTimeField();

		if (isset($this->fraudConfig['events']) && is_array($this->fraudConfig['events'])) {
			foreach ($this->fraudConfig['events'] as $key => $eventRules) {
				// check to see if the event included in a group if not continue to the next one
				if (isset($eventRules['groups']) && !in_array($groupName, $eventRules['groups'])) {
					continue;
				}

				foreach ($eventRules['rules'] as $eventQuery) {
					Billrun_Factory::log()->log("irdPlugin::collectFraudEvents collecting {$eventQuery['name']} exceeders in group {$groupName}", Zend_Log::DEBUG);

					$query = $baseQuery;
					$eventQuery = $this->prepareRuleQuery($eventQuery, $key);
					$charge_time = new MongoDate(isset($eventQuery['time_period']) ? strtotime($eventQuery['time_period']) : Billrun_Util::getLastChargeTime(true));
					$query['base_match']['$match'][$timeField]['$gte'] = $charge_time;
					$query['base_match']['$match']['$or'] = array(
																array('ird_level' => array( '$lt' => intval($eventQuery['threshold']))),
																array('ird_level' => array( '$exists' => false)),
																);
					$project = $query['project'];
					$project['$project'] = array_merge($project['$project'], $this->addToProject((!empty($eventRules['added_values']) ? $eventRules['added_values'] : array())), $this->addToProject(array('units' => $eventQuery['units'], 'event_type' => $key,
								'threshold' => $eventQuery['threshold'], 'target_plans' => $eventRules['target_plans'])));
					$project['$project']['value'] = $eventQuery['value'];
					$project['$project'][$eventQuery['name']] = $eventQuery['value'];
					$query['project'] = $project;

					$query['where']['$match'] = array_merge($query['where']['$match'], (isset($eventQuery['query']) ? $this->parseEventQuery($eventQuery['query']) : array()), (isset($eventRules['group_rules'][$groupName]) ? $this->parseEventQuery($eventRules['group_rules'][$groupName]) : array()));
					$ruleMatch = array('$match' => (isset($eventQuery['match']) ? $eventQuery['match'] : array('value' => array('$gte' => intval($eventQuery['threshold']))) ));

					$ret = $lines->aggregate($query['base_match'], $query['where'], $query['group_match'], $query['group'], $query['translate'], $query['project'], $ruleMatch);

					if ($this->postProcessEventResults($events, $ret, $eventQuery, $key)) {
						$events = array_merge($events, $ret);
					}

					Billrun_Factory::log()->log("irdPlugin::collectFraudEvents found " . count($ret) . " exceeders on rule {$eventQuery['name']} ", Zend_Log::INFO);
				}
			}
		}

		return $events;
	}
	
	/**
	 * Get the base aggregation query.
	 * @param type $charge_time the charge time of the billrun (records will not be pull before that)
	 * @return Array containing a standard PHP mongo aggregate query to retrive  ggsn entries by imsi.
	 */
	protected function getBaseAggregateQuery($charge_time, $groupName, $groupMatch ) {
		$ret = array(
			'base_match' => array(
				'$match' => array(
					'type' => 'ggsn',
				)
			),
			'where' => array(
				'$match' => array(
					'$or' => array(
						array('rating_group' => array('$exists' => false)),
						array('rating_group' => 0)
					),
				),
			),
			'group_match' => array(
				'$match' => $groupMatch,
				),
			'group' => array(
				'$group' => array(
					"_id" => array('imsi' => '$served_imsi', 'msisdn' => '$served_msisdn'),
					"download" => array('$sum' => '$fbc_downlink_volume'),
					"upload" => array('$sum' => '$fbc_uplink_volume'),					
					"usagev" => array('$sum' => '$usagev'), 
					"duration" => array('$sum' => '$duration'),
					'lines_stamps' => array('$addToSet' => '$stamp'),
				),
			),
			'translate' => array(
				'$project' => array(
					'_id' => 0,
					'download' => array('$divide' => array('$download', 1024)),
					'upload' => array('$divide' => array('$upload', 1024)),
					'usagev' => array('$divide' => array('$usagev', 1024)),
					'duration' => 1,
					'imsi' => '$_id.imsi',
					'msisdn' => array('$substr' => array('$_id.msisdn', 5, 10)),
					'lines_stamps' => 1,
				),
			),
			'project' => array(
				'$project' => array_merge(array(
					'download' => 1,
					'upload' => 1,
					'usagev' => 1,
					'duration' => 1,
					'imsi' => 1,
					'msisdn' => 1,
					'lines_stamps' => 1,
				), $this->addToProject( array('group' =>  $groupName,))),
			),
		);
		
		
		return $ret;
	}
	
	//------------------------------------------------------------------------------------------------------
	
	
	/**
	 * helper method to receive the last time of the monthly charge
	 * 
	 * @param boolean $return_timestamp if set to true return time stamp else full format of yyyymmddhhmmss
	 * 
	 * @return mixed timestamp or full format of time
	 * @deprecated since version 0.4 use Billrun_Util::getLastChargeTime instead
	 */
	protected function get_last_charge_time($return_timestamp = false) {
		Billrun_Factory::log()->log("Billrun_Plugin_BillrunPluginFraud::get_last_charge_time is deprecated; please use Billrun_Util::getLastChargeTime()", Zend_Log::DEBUG);
		return Billrun_Util::getLastChargeTime($return_timestamp);
	}
	
	/**
	 * Write all the threshold that were broken as events to the db events collection 
	 * @param type $items the broken  thresholds
	 * @param type $pluginName the plugin that identified the threshold breakage
	 * @return type
	 */
	public function handlerAlert(&$items,$pluginName) {
		if($pluginName != $this->getName() || !$items ) {
			return;	
		}

		$events = Billrun_Factory::db()->eventsCollection();
		
		$ret = array();
		foreach($items as &$item) { 
			$event = new Mongodloid_Entity($item);
			unset($event['lines_stamps']);
			
			$newEvent = $this->addAlertData($event);
			$newEvent['source']	= $this->getName();
			$newEvent['stamp'] = md5(serialize($newEvent));
			$newEvent['creation_time'] = date(Billrun_Base::base_dateformat);
			foreach($this->priority as $key => $pri) {
				$newEvent['priority'] = $key;	
				if($event['event_type'] == $pri) {
					break;
				}
			}
			$item['event_stamp'] = $newEvent['stamp'];
			
			$ret[] = $events->save($newEvent);
		}
		return $ret; 
	}

	/**
	 * method to markdown all the lines that triggered the event
	 * 
	 * @param array $items the lines
	 * @param string $pluginName the plugin name which triggered the event
	 * 
	 * @return array affected lines
	 */
	public function handlerMarkDown(&$items, $pluginName) {
		if ($pluginName != $this->getName() || !$items) {
			return;
		}
		
		$ret = array();
		$lines = Billrun_Factory::db()->linesCollection();
		foreach ($items as &$item) {
			$ret[] = $lines->update(	array('stamp' => array('$in' => $item['lines_stamps'])),
									array('$set' => array('ird_level' => (int) $item['threshold'], 'ird_stamp' => $item['event_stamp'] )),
									array('multiple' => 1));
		}
		return $ret;
	}
	
	/**
	 * @see Billrun_Plugin_BillrunPluginFraud::addAlertData
	 */
	protected function addAlertData(&$event) {
		$event['effects'] = array(
			'key' => 'type',
//			'filter' => array('$in' => array('ird'))
		);
		return $event;
	}
}
