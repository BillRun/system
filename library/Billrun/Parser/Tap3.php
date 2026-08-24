<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2018 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * This defines an empty parser that does nothing but passing behavior to the outside plugins
 */
class Billrun_Parser_Tap3 extends Billrun_Parser_Base_Binary {

	use Billrun_Traits_AsnParsing;
	
	const FILE_READ_AHEAD_LENGTH = 65535;
	
	public function __construct($options) {
		parent::__construct($options);
		$this->tap3Config = (new Yaf_Config_Ini(Billrun_Factory::config()->getConfigValue('external_parsers_config.tap3')))->toArray();
		$this->initParsing();
		$this->addParsingMethods();
	}
	
	public function parse($fp) {
		$this->dataRows = array();
		$this->headerRows = array();
		$this->trailerRows = array();

		$bytes = '';
		do {
			$bytes .= fread($fp, self::FILE_READ_AHEAD_LENGTH);
		} while (!feof($fp));
		$parsedData = Asn_Base::parseASNString($bytes);
				$this->headerRows[] = $this->parseHeader($parsedData);

		if (!isset($this->tap3Config[$this->fileVersion])) {
			Billrun_Factory::log("Processing tap3 file with non supported version : {$this->fileVersion}", Zend_Log::NOTICE);
			throw new Exception("Processing tap3 file with non supported version : {$this->fileVersion}");
		}
		$trailer = $this->parseTrailer($parsedData);
		if (empty($this->currentFileHeader['notification']) || !empty($this->currentFileHeader['header'])) {
			foreach ($parsedData->getData() as $record) {
				if (in_array($record->getType(), $this->tap3Config['config']['data_records'])) {
					foreach ($record->getData() as $key => $data) {
						$row = $this->parseData('tap3', $data);
						if ($row) {
							$row['file_rec_num'] = $key + 1;
							$this->dataRows[] = $row;
						}
					}
				} else if (!isset($this->tap3Config['header'][$record->getType()]) && !isset($this->tap3Config['trailer'][$record->getType()])) {
					Billrun_Factory::log('No config for type : ' . $record->getType(), Zend_Log::DEBUG);
				}
			}
		} else {
			Billrun_Factory::log('Got notification/empty file, moving on...', Zend_Log::INFO);
		}

		$this->trailerRows[] = $trailer;

		return true;
	}
	
	public function parseData($type, $data) {
		$asnObject = $data;
		$type = $asnObject->getType();
		$cdrLine = false;

		if (isset($this->tap3Config[$this->fileVersion][$type])) {
			$cdrLine = $this->parseASNDataRecur($this->tap3Config[$this->fileVersion][$type], $asnObject, $this->tap3Config['fields']);
			if ($cdrLine) {
				$cdrLine['record_type'] = $type;
				$this->surfaceCDRFields($cdrLine, array_merge($this->tap3Config[$this->fileVersion]['mapping']['common'], $this->tap3Config[$this->fileVersion]['mapping'][$type]));
			}
		} else {
			//Billrun_Factory::log("Unidetifiyed type :  $type",Zend_Log::DEBUG);
		}

		return $cdrLine;
	}

	public function parseHeader($data) {
		$header = $this->parseASNDataRecur($this->tap3Config['header'], $data, $this->tap3Config['fields']);
		$this->currentFileHeader = $header;
		$this->fileVersion = $this->currentFileHeader['header']['version'] . "_" . $this->currentFileHeader['header']['minor_version'];
		if (empty($this->currentFileHeader)) {
			$header['notifcation'] = $this->parseASNDataRecur($this->tap3Config['notification'], $data, $this->tap3Config['fields']);
			$this->fileVersion = $header['notifcation']['version'] . "_" . $header['notifcation']['minor_version'];
		}
		Billrun_Factory::log("File Version :  {$this->fileVersion}", Zend_Log::DEBUG);
		return $header;
	}

	public function parseTrailer($data) {
		$trailer = $this->parseASNDataRecur($this->tap3Config['trailer'], $data, $this->tap3Config['fields']);
		return $trailer;
	}
	
	/**
	 * add Tap3 specific parsing methods.
	 */
	protected function addParsingMethods() {
		$newParsingMethods = array(
			'raw_data' => function($data) {
				return $this->utf8encodeArr($data);
			},
			'bcd_number' => function($fieldData) {
				$ret = $this->parsingMethods['bcd_encode']($fieldData);

				return preg_replace('/15$/', '', $ret);
			},
			'time_offset_list' => function($data) {
				return $this->parseTimeOffsetList($data);
			},
		);

		$this->parsingMethods = array_merge($this->parsingMethods, $newParsingMethods);
	}
	
	/**
	 * Parse time offset list that  conatin the time offset  refecenced in each line  cal start time
	 * @param type $asn the  time offset list
	 * @return rray  containing the time offset list  keyed by its offset code.
	 */
	protected function parseTimeOffsetList($data) {
		$timeOffsets = array();
		foreach ($data as $time_offset) {
			$time_offset_arr = array();
			foreach ($time_offset->getData() as $value) {
				$time_offset_arr[$value->getType()] = $value->getData();
			}
			$key = $this->parseField('number', $time_offset_arr['e8']);
			$timeOffsets[$key] = $time_offset_arr['e7'];
		}
		return $timeOffsets;
	}
	
	/**
	 * Pull required fields from the CDR nested tree to the surface.
	 * @param type $cdrLine the line to monipulate.
	 */
	protected function surfaceCDRFields(&$cdrLine, $mapping) {

		foreach ($mapping as $key => $fieldToMap) {
			$val = Billrun_Util::getNestedArrayVal($cdrLine, $fieldToMap, null);
			if ($val !== null && Billrun_Util::getFieldVal($this->tap3Config['fields_to_save'][$key], false)) {
				$cdrLine[$key] = $val;
			}
		}

//		if (Billrun_Util::getNestedArrayVal($cdrLine, $mapping['localTimeStamp']) !== null) {
//			$offset = $this->currentFileHeader['networkInfo']['UtcTimeOffsetInfoList'][Billrun_Util::getNestedArrayVal($cdrLine, $mapping['TimeOffsetCode'])];
//			if (empty($offset)) {
//				$offset = '+00:00';
//			}
//			$cdrLine['urt'] = new Mongodloid_Date(Billrun_Util::dateTimeConvertShortToIso(Billrun_Util::getNestedArrayVal($cdrLine, $mapping['localTimeStamp']), $offset));
//			$cdrLine['tzoffset'] = $offset;
//		}
	}
	
	/**
	 * sum up all values of nested array on various levels (as long as it doesnt exceed limit
	 * @param type $limit maximum recurrsion depth
	 * @return sum of all values
	 */
	protected static function sumup_arrays($maybe_arr, $limit) {
		if ($limit == 0) {
			Billrun_Factory::log('recurrsion is to deep, aborting...', Zend_Log::INFO);
			return;
		} if (!is_array($maybe_arr)) {
			return $maybe_arr;
		} else {
			$sum = 0;
			foreach ($maybe_arr as $var) {
				$sum += static::sumup_arrays($var, $limit - 1);
			}
		}
		return $sum;
	}

	/**
	 * Encode an array content in utf encoding
	 * @param $arr the array to encode.
	 * @return array with a recurcivly encoded values.
	 */
	protected function utf8encodeArr($arr) {
		if (is_object($arr)) {
			$val = array();
			foreach ($arr->getData() as $val) {
				$val[$arr->getType()][] = $this->utf8encodeArr($val);
			}
			return $val;
		}
		return utf8_encode($arr);
	}

}