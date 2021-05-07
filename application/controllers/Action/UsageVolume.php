<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 S.D.O.C. LTD. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */
require_once APPLICATION_PATH . '/application/controllers/Action/Api.php';

/**
 * Usage Volume action class
 *
 * @package  Action
 */
class UsageVolumeAction extends ApiAction {

	public function execute() {
		$request = $this->getRequest();
		$send_to = Billrun_Factory::config()->getConfigValue('datausage.email_recipients');
		$billrun_key = Billrun_Util::getBillrunKey(time());
		$start_time = Billrun_Util::getStartTime($billrun_key);
		$since = new MongoDate($start_time);
		$results = $this->calculateUsageVolume($since);
		$filepath = '/tmp/' . date('YmdHi') . '_volume_usage_abroad.csv';
		$attachment = $this->generateCsvToMail($filepath, $results);
		Billrun_Util::sendMail("Daily Report: Data usage abroad by sid", "File Attached.", $send_to, array($attachment));
	}

	protected function calculateUsageVolume($since) {
		$to_mb = 1024 * 1024;
		$match = array(
			'$match' => array(
				'type' => 'ggsn',
				'unified_record_time' => array(
					'$gt' => $since,
				),
			),
		);

		$group = array(
			'$group' => array(
				'_id' => array(
					'sid' => '$sid',
					'country' => '$alpha3'
				),
				'imsi' => array(
					'$first' => '$imsi',
				),
				'total_volume' => array(
					'$sum' => '$usagev',
				),
				'over_volume' => array(
						'$sum' => '$over_plan',
				),
				'out_volume' => array(
						'$sum' => '$out_plan',
				),
			)
		);

		$project = array(
			'$project' => array(
				'_id' => 0,
				'sid' => '$_id.sid',
				'country' => '$_id.country',
				'imsi' => '$imsi',
				'total_volume' => '$total_volume',
				'over_out_volume' => [ '$sum' => ['$over_volume', '$out_volume' ] ],
				)
		);
		
		$match2 = array(
			'$match' => array(
				'over_out_volume' => array(
					'$gt' => 10 * $to_mb, // minimum of 10MB
				)
			)
		);
		
		$sort = array(
			'$sort' => array(
				'over_out_volume' => -1,
			),
		);
		
		$res = Billrun_Factory::db()->linesCollection()->aggregate($match, $group, $project, $sort, $match2);
		return array_map(function($ele) use($to_mb){
			$ele['total_volume'] = $ele['total_volume'] / $to_mb;
			$ele['total_volume'] = number_format($ele['total_volume'], 4);
			return $ele;
		}, $res);	
	}

	protected function generateCsvToMail($filepath, $results) {
		$fp = fopen($filepath, 'w');
		$header = array('sid', 'imsi', 'country','over_out_volume', 'total_volume');
		fputcsv($fp, $header);
		foreach ($results as $result) {
			$csvLine = array();
			foreach ($header as $fieldKey) {
				$csvLine[$fieldKey] = $result[$fieldKey];
			}
			fputcsv($fp, $csvLine);
		}

		fclose($fp);
		$mime = new Zend_Mime_Part(file_get_contents($filepath));
		$mime->filename = basename($filepath);
		$mime->disposition = Zend_Mime::DISPOSITION_INLINE;
		$mime->encoding = Zend_Mime::ENCODING_BASE64;
		return $mime;
	}

}
