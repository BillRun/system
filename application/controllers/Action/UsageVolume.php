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
			)
		);

		$project = array(
			'$project' => array(
				'_id' => 0,
				'sid' => '$_id.sid',
				'country' => '$_id.country',
				'imsi' => '$imsi',
				'total_volume' => '$total_volume',
			)
		);
		$res = Billrun_Factory::db()->linesCollection()->aggregate($match, $group, $project);
		return $res;
	}

	protected function generateCsvToMail($filepath, $results) {
		$fp = fopen($filepath, 'w');
		$header = array('sid', 'imsi', 'country', 'total_volume');
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
