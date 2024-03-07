<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2013 S.D.O.C. LTD. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * SidUsageVolume action class
 *
 * @package  Action
 * @since    2.8
 */
class CRMHelperAction extends Action_Base {

	/**
	 * method that outputs subscriber requested date usage
	 */
	public function execute() {
		Billrun_Factory::log()->log("Execute sid usage volume api", Zend_Log::INFO);
		$request = $this->getRequest()->getRequest();
		$body = json_decode(file_get_contents('php://input'),true);

		if(!empty($body) && is_array($body)) {
			$request = array_merge($request,$body);
		}

		$retVal = ['status'=> 0 ,'details' => []];
		switch($request['action']) {
				case 'top_5' :  $retVal = $this->getSIDTop5($request);
					break;

				default :
					$retVal['status'] = 0;
					$retVal['desc'] = "Action ${$request['action']} is not supported";
				break;

		}

		$this->getController()->setOutput([$retVal]);

		return true;
	}
	/**
     * Retrieves the top 5 usage records for a given subscriber ID within the last 60 days.
     *
     * @param array $request The request parameters.
     * @return array The top 5 usage records or error details.
     */
	protected function getSIDTop5($request) {
		if ($issues = $this->isRequestMissingArguments($request, ['sid']) ) {
			return $issues;
		}

		$sids =  is_array($request['sid']) ?  $request['sid'] : [intval($request['sid'])];
		$daysBack= !empty($request['days_back']) && is_numeric($request['days_back']) ? $request['days_back'] : '60';
		$now= !empty($request['date']) ?  strtotime($request['sid']) : time();
		$horizion = strtotime(date("-${daysBack} days", $now));

		$pipelines = [
			[ '$match'=>[
							'sid' => ['$in' => $sids ],
							'urt' => [ 	'$gte' => new MongoDate( $horizion ),
										'$lt' => new MongoDate( $now ) ],
							'type' => 'nsn',
							'usaget' => 'call',
							'billrun' =>['$exists'=> 1]
						 ] ],

			[ '$group' => [
								'_id' => [ 'called_number' => '$called_number'],
								'count_calls' => [ '$sum' => 1]
						   ] ],

			[ '$sort' =>[ 'count_calls' => -1 ] ],
			[ '$limit' => 5 ],
			[ '$project' => [
								'_id'=>0,
								'called_number' => '$_id.called_number',
								'count_calls' => 1
							 ] ]
		];

		$ret = Billrun_Factory::db()->linesCollection()->aggregate($pipelines, ["allowDiskUse" => true]);
		return [
			'status' => 1,
			'desc' => 'success',
			'details' => $ret
		];
	}
	/**
     * Checks if required parameters are present in the request.
     *
     * @param array $request The request array to validate.
     * @param array $params Required parameter keys to check.
     * @return mixed False if all parameters are present, or an array with error details if missing.
     */
	protected function isRequestMissingArguments($request,  $params) {
		foreach ($params as $param) {
			if (!isset($request[$param])) {
				$msg = 'Missing required parameter: ' . $param;
				Billrun_Factory::log()->log($msg, Zend_Log::ERR);
				return [
						'status' => 0,
						'desc' => 'failed',
						'output' => $msg,
				];
			}
		}
		return false;
	}
}

