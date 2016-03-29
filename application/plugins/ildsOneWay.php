<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2013 S.D.O.C. LTD. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Calculator ildsOneWay plugin create csv file for ilds one way process
 *
 * @package  Application
 * @subpackage Plugins
 * @since    0.9
 */
class ildsOneWayPlugin extends Billrun_Plugin_BillrunPluginBase {

	/**
	 * plugin name
	 *
	 * @var string
	 */
	protected $name = 'ildsOneWay';

	/**
	 * Record types relevant for pricing.
	 * @var array
	 */
	protected $record_types = null;

	/**
	 * List of all possible provider names. Array key is the called_number prefix.
	 * @var array 
	 */
	protected $providers = array(
		'GNTV' => 'ILD_013', // netvision
		'GBZQ' => 'ILD_BEZ', // bezeq
		'GBZI' => 'ILD_014', // bezeqint
		'GSML' => 'ILD_012', // smile
//		'996' => 'ILD_CEL', // cellcom mapa
		'GHOT' => 'ILD_HOT'  // hot mapa
	);

	/**
	 * the data structure of the output file, with each column's fixed width
	 * @var array
	 */
	protected $data_structure = array(
		'records_type' => 3,
		'calling_number' => 15,
		'charging_start_time' => 13,
		'charging_end_time' => 13,
		'called_number' => 18,
		'is_in_glti' => 1,
		'prepaid' => 1,
		'usagev' => 10,
		'sampleDurationInSec' => 8,
		'aprice' => 10,
		'origin_carrier' => 10,
		'file' => 100,
	);

	/**
	 * an access price that would be added to the final price
	 * @var float
	 */
	protected $access_price;

	/**
	 * The output filename 
	 * @var string
	 */
	protected $filename = null;
	protected $ild_prefix_field_name = "ild_prefix";

	/**
	 * The output file path
	 * @var string
	 */
	protected $output_path = null;
	protected $pricingField = Billrun_Calculator_CustomerPricing::DEF_CALC_DB_FIELD;

	/**
	 *
	 * @var Mongodloid_Collection
	 */
	protected $lines_coll;

	public function __construct() {
		$this->record_types = Billrun_Factory::config()->getConfigValue('016_one_way.identifications.record_types', array('30'));
		$this->filename = date('Ymd', time()) . '.TXT';
		$this->output_path = Billrun_Factory::config()->getConfigValue('016_one_way.export.path', '/var/www/billrun/workspace/016_one_way/Treated/') . DIRECTORY_SEPARATOR . $this->filename;
		$this->access_price = round(Billrun_Factory::config()->getConfigValue('016_one_way.access_price', 1.00), 2);
		$this->lines_coll = Billrun_Factory::db()->linesCollection();
	}

	public function afterCalculatorUpdateRow($row, $calculator) {
		if ($calculator->getCalculatorQueueType() == 'rate' && $row['type'] == 'nsn' && in_array($row['record_type'], $this->record_types) && isset($row[$this->ild_prefix_field_name]) && ($row['usagev'] > 0)) {
			$result = $this->createRow($row);
			if (!empty($result)) {
				if (!$this->createTreatedFile($result)) {
					Billrun_Factory::log()->log('Failed inserting 016 one way line with stamp: ' . $row['stamp'] . ' , time: ' . time(), Zend_Log::ERR);
				} else {
					Billrun_Factory::log()->log('line stamp: ' . $row['stamp'] . ' file: ' . $row['file'] . ' was inserted to 016 one way process', Zend_Log::INFO);
				}
			}
		}
	}

	/**
	 * @see Billrun_Generator_Csv::createRow
	 */
	public function createRow($row) {
		$row->collection($this->lines_coll);
		$res = $row->getRawData();
		$res['file'] = 'cdrFile:' . $row['file'] . ' cdrNb:' . $row['record_number'];
		if (!empty($row['charging_start_time'])) {
			$res['charging_start_time'] = substr($row['charging_start_time'], 2);
			$res['charging_end_time'] = substr($row['charging_end_time'], 2);
		} else if (!empty($row['call_reference_time'])) {
			$res['charging_start_time'] = substr($row['call_reference_time'], 2);
			$res['charging_end_time'] = date('ymdhis', strtotime($row['call_reference_time']) + $row['usagev']);
		} else {
			$res['charging_start_time'] = date('ymdhis', strtotime($row['process_time']));
			$res['charging_end_time'] = date('ymdhis', strtotime($row['process_time']) + $row['usagev']);
		}
		$res['prepaid'] = '0';
		$res['is_in_glti'] = '0';
		$res['origin_carrier'] = $this->providers[$row[$this->ild_prefix_field_name]];
		$res['records_type'] = '000';
		$res['sampleDurationInSec'] = '1';

		$row[$this->pricingField] = $res['aprice'] = round($this->access_price + Billrun_Calculator_CustomerPricing::getPriceByRate($row['arate'], $row['usaget'], $row['usagev']), 4);

		if ($row['usagev'] == '0') {
			return;
//			$res['records_type'] = '005';
		} else if (!$row['arate']) {
			$res['records_type'] = '002';
			$res['sampleDurationInSec'] = '0';
		}

		$str = '';
		foreach ($this->data_structure as $column => $width) {
			$str .= str_pad($res[$column], $width, " ", STR_PAD_LEFT);
		}

		$str .= PHP_EOL;
		return $str;
	}

	/**
	 * @see Billrun_Generator_Csv::createTreatedFile
	 */
	public function createTreatedFile($str) {
		if (!is_dir(dirname($this->output_path))) {
			mkdir(dirname($this->output_path), 0777, true);
			if (!is_dir(dirname($this->output_path))) {
				return false;
			}
		}
		return file_put_contents($this->output_path, $str, FILE_APPEND);
	}

}
