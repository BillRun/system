<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012 S.D.O.C. LTD. All rights reserved.
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */

/**
 * 
 *
 * @package  calculator
 * @since    0.5
 */
class CompareController extends Yaf_Controller_Abstract {

	/**
	 *
	 * @var Participant
	 */
	protected $source;

	/**
	 *
	 * @var Participant
	 */
	protected $target;
	protected $subs_mappings;
	protected $subscriber;
	protected $billrun_key;
	protected $relative_error = 0.05;
	protected $ignore_ggsn_calling_number = true;
	protected $ignore_non_tap3_serving_network = true;
	protected $ignore_tap3_records = true;
	protected $compare_only_special_prices = true;
	protected $ignore_mms_called_number = true;
	protected $phone_numbers_strict_comparison = false;
	protected $ignore_daylight_saving_time_lines = true;
	protected $ignore_IL_ILD = true;

	public function indexAction() {
		$this->subscriber = Billrun_Factory::subscriber();
		$this->compareAccounts();
		die();
	}

	public function compareAccounts() {
		$this->initParticipants();
		$this->loadFiles("201311", 9073496);
		$this->loadSubscribers();
		$this->matchSubscribers();
		$this->printResults();
//		$this->compare();
	}

	public function getFilePathByPattern($folder_to_search, $pattern) {
		$file_path = '';
		$matches = glob($folder_to_search . DIRECTORY_SEPARATOR . $pattern);
		if ($matches) {
			$file_path = $matches[0];
		}
		return $file_path;
	}

	protected function initParticipants() {
		$this->source = new NsoftParticipant();
		$this->target = new BillrunParticipant();
		$this->source->xmls_path = '/home/shani/projects/billrun/files/invoices/Checks/nsoft/';
		$this->target->xmls_path = '/home/shani/projects/billrun/files/invoices/Checks/billrun/';
	}

	protected function compare() {
		$dir = new DirectoryIterator($this->billrun_xmls_path);
		foreach ($dir as $fileinfo) {
			if ($fileinfo->getExtension() == "xml") {
				$filename = $fileinfo->getFilename();
//				$billrun_xml = 
//				$matches = array();
//				preg_match("/^?*_(_)_/", $filename, $matches);
				echo ($filename);
			}
		}
	}

	protected function loadFiles($billrun_key, $account_id) {
		$source_pattern = $this->source->getInvoicePathPattern($billrun_key, $account_id);
		$source_filename = $this->getFilePathByPattern($this->source->xmls_path, $source_pattern);
		$this->source->loadFile($source_filename);
		$target_pattern = $this->target->getInvoicePathPattern($billrun_key, $account_id);
		$target_filename = $this->getFilePathByPattern($this->target->xmls_path, $target_pattern);
		$this->target->loadFile($target_filename);
	}

	protected function loadSubscribers() {
		for ($id = 0; $id < $this->source->xml->SUBSCRIBER_INF->count(); $id++) {
			$this->loadSubscriber($this->source, $id);
		}
		for ($id = 0; $id < $this->target->xml->SUBSCRIBER_INF->count(); $id++) {
			$this->loadSubscriber($this->target, $id);
		}
	}

	protected function loadSubscriber(Participant $sub, $sub_id) {
		$sub->subs[$sub_id]['identity'] = $this->getSubscriberIdentity($sub, $sub_id);
		$sub->subs[$sub_id]['unique_lines_data'] = $this->getUniqueLinesData($sub, $sub_id);
	}

//	protected function matchSubscribers() {
//		$this->subs_mappings = array();
//		foreach ($this->source->subs as $source_id => $source_sub) {
//			foreach ($this->target->subs as $target_id => $target_sub) {
//				$match_rank = count(array_intersect_key($source_sub['unique_lines_data'], $target_sub['unique_lines_data'])) / max(array(count($source_sub['unique_lines_data']), count($target_sub['unique_lines_data'])));
//				$match_ranks[$match_rank] = array($source_id => $target_id); //TODO it could be overridden
//			}
//		}
//		krsort($match_ranks);
//		foreach ($match_ranks as $match) {
//			$source_id = key($match);
//			if (isset($this->subs_mappings[$source_id])) {
//				continue;
//			} else {
//				$target_id = current($match);
//				$this->subs_mappings[$source_id] = $target_id;
//			}
//		}
//	}
	protected function matchSubscribers() {
		$this->subs_mappings = array();
		foreach ($this->source->subs as $source_id => $source_sub) {
			$params = array();
			$line_params['NDC_SN'] = intval($source_sub['identity']);
			$line_params['DATETIME'] = date(Billrun_Base::base_dateformat, Billrun_Util::getStartTime($this->billrun_key));
			$params[] = $line_params;
			$output = $this->subscriber->getSubscribersByParams($params, array('sid' => 'subscriber_id'));
			if ($output && $sid = current($output)->sid) {
				foreach ($this->target->subs as $target_id => $target_sub) {
					if ($target_sub['identity'] == strval($sid)) {
						$this->subs_mappings[$source_id] = $target_id;
						break;
					}
				}
			}
		}
	}

	protected function printResults() {
//		$aid = intval($xml->INV_CUSTOMER_INFORMATION->CUSTOMER_CONTACT->EXTERNALACCOUNTREFERENCE);
		$this->printMissingSubscribers();
		$this->printMissingLines();
		$this->printDifferencesInLines();
		$this->printDifferencesInTotals();
	}

	protected function getUniqueLinesData(Participant $sub, $sub_id) {
		$subscriber_lines = array();
		foreach ($sub->xml->SUBSCRIBER_INF[$sub_id]->BILLING_LINES->BILLING_RECORD as $billing_line) {
			if ((string) $billing_line->TARIFFKIND != 'Call' || (int) $billing_line->CHARGEDURATIONINSEC) {
				if (isset($subscriber_lines[(string) $billing_line->TIMEOFBILLING]) && !((string) $billing_line->TARIFFITEM == "INTERNET_BILL_BY_VOLUME" && (string) $billing_line->ROAMING == "0")) {
					$this->displayMsg("Skipping same date key $billing_line->TIMEOFBILLING");
				}
				if (($this->ignore_daylight_saving_time_lines && substr(strval($billing_line->TIMEOFBILLING), 0, 10) < "2013/10/27") || ($this->ignore_tap3_records && $billing_line->ROAMING == "1") || ($this->ignore_IL_ILD && $billing_line->TARIFFITEM == "IL_ILD")) {
					continue;
				}
				$value = $this->getUniqueLine($billing_line);
				if (substr($billing_line->TARIFFITEM, 0, 4) == "GIFT") { // gift lines all have the same date
					$key = strval($billing_line->TARIFFITEM);
					$value = isset($subscriber_lines[strval($billing_line->TARIFFITEM)]) ? $subscriber_lines[strval($billing_line->TARIFFITEM)]+=$value['charge'] : $value['charge'];
				} else if ((string) $billing_line->TARIFFITEM == "INTERNET_BILL_BY_VOLUME" && (string) $billing_line->ROAMING == "0") {
					if ($this->ignore_ggsn_calling_number) {
						unset($value['calling_number']);
					}
					$key = substr(strval($billing_line->TIMEOFBILLING), 0, 10);
				} else {
//					if ($this->fix_daylight_saving_time_bug && $sub->name == "billrun" && substr(strval($billing_line->TIMEOFBILLING), 0, 10) < "2013/10/27") {
//						$key = date("Y/m/d H:i:s", strtotime("-1 hour", strtotime(strval($billing_line->TIMEOFBILLING))));
//					} else {
					$key = strval($billing_line->TIMEOFBILLING);
//					}
				}
				if ($this->ignore_non_tap3_serving_network && isset($value['roaming']) && $value['roaming'] == "0" && isset($value['serving_network'])) {
					unset($value['serving_network']);
				}
				if ($this->compare_only_special_prices && isset($value['DISCOUNT_USAGE']) && $value['DISCOUNT_USAGE'] != 'DISCOUNT_NONE') {
					unset($value['DISCOUNT_USAGE']);
					unset($value['charge']);
					unset($value['credit']);
				}
				if ($this->ignore_mms_called_number && isset($value['usaget']) && $value['usaget'] == 'MMS') {
					unset($value['called_number']);
				}
				$subscriber_lines[$key] = $value;
			}
		}
		return $subscriber_lines;
	}

	protected function getSubscriberIdentity(Participant $sub, $sub_id) {
		if (isset($sub->xml->SUBSCRIBER_INF[$sub_id]->SUBSCRIBER_DETAILS->CLI)) {
			return (string) $sub->xml->SUBSCRIBER_INF[$sub_id]->SUBSCRIBER_DETAILS->CLI;
		} else if (isset($sub->xml->SUBSCRIBER_INF[$sub_id]->SUBSCRIBER_DETAILS->SUBSCRIBER_ID)) {
			return (string) $sub->xml->SUBSCRIBER_INF[$sub_id]->SUBSCRIBER_DETAILS->SUBSCRIBER_ID;
		}
		return '';
	}

	protected function getUniqueLine($billing_line) {
		$calling_number = preg_replace('/^\+?0*972/', '', (string) $billing_line->CTXT_CALL_IN_CLI);
		return array(
			'arate' => (string) $billing_line->TARIFFITEM,
			'called_number' => (string) $billing_line->CTXT_CALL_OUT_DESTINATIONPNB,
			'usagev' => (string) $billing_line->CHARGEDURATIONINSEC,
			'usaget' => (string) $billing_line->TARIFFKIND,
			'access_price' => (string) $billing_line->TTAR_ACCESSPRICE1,
			'interval' => (string) $billing_line->TTAR_SAMPLEDELAYINSEC1,
			'rate_price' => (string) $billing_line->TTAR_SAMPPRICE1,
			'calling_number' => $calling_number,
			'intl' => (string) $billing_line->INTERNATIONAL,
			'DISCOUNT_USAGE' => (string) $billing_line->DISCOUNT_USAGE, // maybe remove this
			'charge' => (string) $billing_line->CHARGE, // maybe remove this
			'credit' => (string) $billing_line->CREDIT, // maybe remove this
			'serving_network' => (string) $billing_line->SERVINGPLMN, // maybe remove this
			'roaming' => (string) $billing_line->ROAMING,
		);
	}

	protected function checkOutput() {
		
	}

	protected function getMissingLines($lines1, $lines2) {
		return array_diff($lines1, $lines2);
	}

	protected function printDifferencesInLines() {
		foreach ($this->source->subs as $source_id => $source_sub) {
			if (isset($this->subs_mappings[$source_id])) {
				$source_lines = $source_sub['unique_lines_data'];
				$target_sub = $this->target->subs[$this->subs_mappings[$source_id]];
				$target_lines = $target_sub['unique_lines_data'];
				$intersection = array_intersect_key($source_lines, $target_lines);
				foreach ($intersection as $line_key => $line1) {
					$line2 = $target_lines[$line_key];
					if (is_array($line2)) {
						foreach ($line1 as $key => $value) {
							if ($this->different($value, $line2[$key], $key)) {
								$this->displayMsg('Lines with key ' . $line_key . ' differ in ' . $key . ': ' . $value . ' (' . $this->source->name . ') / ' . $line2[$key] . ' (' . $this->target->name . ')');
							}
						}
					} else if ($this->different($line1, $line2)) {
						$this->displayMsg('Lines with key ' . $line_key . ' differ in value: ' . $line1 . '(' . $this->source->name . ') / ' . $line2 . '(' . $this->target->name . ')');
					}
				}
			}
		}
	}

	protected function different($value1, $value2, $key = null) {
		if ($value1 == $value2) {
			return false;
		}
		if ($key == 'called_number') {
			if (!$this->phone_numbers_strict_comparison) {
				return strpos($value1, $value2) === false && strpos($value2, $value1) === false;
			}
		} else if (is_numeric($value1) && is_numeric($value2)) {
			return $this->big_difference($value1, $value2);
		}
		return true;
	}

	/**
	 * 
	 * @param float|int $value1
	 * @param float|int $value2
	 * @return boolean
	 */
	protected function big_difference($value1, $value2) {
		if ($value2 != 0) {
			$portion = $value1 / $value2;
			return $portion > 1 + $this->relative_error || $portion < 1 - $this->relative_error;
		} else if ($value1 != 0) {
			return $this->big_difference($value2, $value1);
		}
		return false;
	}

	protected function printMissingLines() {
		foreach ($this->source->subs as $source_id => $source_sub) {
			if (isset($this->subs_mappings[$source_id]) && isset($this->target->subs[$this->subs_mappings[$source_id]]['unique_lines_data'])) {
				$target_sub = $this->target->subs[$this->subs_mappings[$source_id]];
				$this->printSubscribersMissingLines($source_sub, $target_sub, $this->target->name);
				$this->printSubscribersMissingLines($target_sub, $source_sub, $this->source->name);
			}
		}
	}

	protected function printSubscribersMissingLines($sub1, $sub2, $participant2_name) {
		$sub1_diff = array_diff_key($sub1['unique_lines_data'], $sub2['unique_lines_data']);
		if ($sub1_diff) {
			$msg = $participant2_name . ' subscriber ' . ($participant2_name == "billrun" ? $sub2['identity'] : $sub1['identity']) . ' is missing ' . count($sub1_diff) . ' lines:';
			foreach ($sub1_diff as $key => $line) {
				$msg.='</br>' . $key;
			}
			$this->displayMsg($msg);
		}
	}

	protected function printMissingSubscribers() {
		$source_diff = array_diff_key(array_keys($this->source->subs), array_keys($this->subs_mappings));
		$target_diff = array_diff_key(array_keys($this->target->subs), array_values($this->subs_mappings));
		foreach ($source_diff as $source_id) {
			$this->displayMsg($this->target->name . ' miss subscriber ' . $this->source->subs[$source_id]['identity']);
		}
		foreach ($target_diff as $target_id) {
			$this->displayMsg($this->source->name . ' miss subscriber ' . $this->target->subs[$target_id]['identity']);
		}
	}

	protected function displayMsg($msg) {
		echo $msg . "</br>";
	}

	protected function printDifferencesInTotals() {
		$source_total_charge = floatval($this->source->xml->INV_INVOICE_TOTAL->TOTAL_CHARGE);
		$target_total_charge = floatval($this->target->xml->INV_INVOICE_TOTAL->TOTAL_CHARGE);
		if ($this->different($source_total_charge, $target_total_charge)) {
			$this->displayMsg('Total charge is different: ' . $this->source->name . ': ' . $source_total_charge . '. ' . $this->target->name . ': ' . $target_total_charge);
		}
	}

}

abstract class Participant {

	public $xml;
	public $name;
	public $xmls_path;
	public $subs;

	abstract public function getInvoicePathPattern($billrun_key, $account_id);

	public function loadFile($filename) {
		$this->xml = simplexml_load_file($filename);
	}

}

class NsoftParticipant extends Participant {

	public $name = 'nsoft';

	public function getInvoicePathPattern($billrun_key, $account_id) {
		return 'invoice_' . $billrun_key . '*_' . $account_id . '_*.xml';
	}

}

class BillrunParticipant extends Participant {

	public $name = 'billrun';

	public function getInvoicePathPattern($billrun_key, $account_id) {
		return $billrun_key . '*_*' . $account_id . '_*.xml';
	}

}

