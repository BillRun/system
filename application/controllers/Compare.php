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
	protected $billrun_key = "201312"; //TODO get it from the filename
	protected $relative_error = 0.05;
	protected $ignore_ggsn_calling_number = true;
	protected $ignore_non_tap3_serving_network = true;
	protected $ignore_tap3_records = false;
	protected $compare_only_special_prices = true;
	protected $ignore_mms_called_number = false;
	protected $phone_numbers_strict_comparison = false;
	protected $ignore_daylight_saving_time_lines = true;
	protected $ignore_IL_ILD = true;
	protected $ggsn_bytes_gap_to_ignore = 1000;
	protected $ignore_KT_USA_diff = true;
	protected $ignore_INTERNAL_VOICE_MAIL_CALL_diff = true;

	public function indexAction() {
		$this->subscriber = Billrun_Factory::subscriber();
		$this->compareParticipants();
		die();
	}

	protected function processDir(Participant $p, $dir_path) {
		$files = scandir($dir_path);
		foreach ($files as $file) {
			$billrun_key = Billrun_Util::regexFirstValue($p->billrun_key_regex, $file);
			$account_id = Billrun_Util::regexFirstValue($p->account_id_regex, $file);
			if ($billrun_key !== FALSE && $account_id !== FALSE) {
				$p->processed_files[$billrun_key][intval($account_id)] = $file;
			}
		}
	}

	protected function processDirs() {
		$this->processDir($this->source, $this->source->xmls_path);
		$this->processDir($this->target, $this->source->xmls_path);
	}

	protected function compareParticipants() {
		$this->processDirs();
		$this->showMissingFiles($this->source, $this->target);
		$this->showMissingFiles($this->target, $this->source);
		foreach ($this->source->processed_files as $billrun_key => $billruns) {
			foreach ($billruns as $account_id => $billrun) {
				if (isset($p2->processed_files[$billrun_key][$account_id])) {
					$this->compareBillruns($billrun_key, $account_id);
				}
			}
		}
	}

	protected function showMissingFiles(Participant $p1, Participant $p2) {
		foreach ($p1->processed_files as $billrun_key => $billruns) {
			foreach ($billruns as $account_id => $billrun) {
				if (!isset($p2->processed_files[$billrun_key][$account_id])) {
					$this->displayMsg($p1->name . ' is missing billrun ' . $billrun_key . ' of account ' . $account_id);
				}
			}
		}
	}

	protected function compareBillruns() {
		$this->initParticipants();
		$this->loadFiles($billrun_key, $account_id);
		$this->loadSubscribers();
		$this->matchSubscribers();
		$this->printResults();
//		$this->compare();
	}

	protected function getFilePathByPattern($folder_to_search, $pattern) {
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
//		$this->source->xmls_path = '/home/shani/projects/billrun/files/invoices/Checks/nsoft/';
//		$this->target->xmls_path = '/home/shani/projects/billrun/files/invoices/Checks/billrun/';
		$this->source->xmls_path = '/home/shani/Documents/S.D.O.C/BillRun/backups/invoices_compare/7813888/nsoft/';
		$this->target->xmls_path = '/home/shani/Documents/S.D.O.C/BillRun/backups/invoices_compare/7813888/blof/';
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

//	protected function loadFiles($billrun_key, $account_id) {
//		$this->source->loadFile($this->source->xmls_path . '/account_balance.201312.172.29.202.12.xml');
//		$this->target->loadFile($this->target->xmls_path . '/account_balance.201312.172.29.202.12.xml');
//	}

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
				if (($this->ignore_daylight_saving_time_lines && substr(strval($billing_line->TIMEOFBILLING), 0, 10) < "2013/10/27") || ($this->ignore_tap3_records && $billing_line->ROAMING == "1") || ($this->ignore_IL_ILD && $billing_line->TARIFFITEM == "IL_ILD")) {
					continue;
				}
				$value = $this->getUniqueLine($billing_line);
				$unique_key = strval($billing_line->TIMEOFBILLING) . '_' . substr($billing_line->CTXT_CALL_OUT_DESTINATIONPNB, -3);
				if (isset($subscriber_lines[$unique_key]) && !((string) $billing_line->TARIFFITEM == "INTERNET_BILL_BY_VOLUME" && (string) $billing_line->ROAMING == "0")) {
					$subscriber_lines[$unique_key]['number_of_records'] = isset($subscriber_lines[$unique_key]['number_of_records']) ? $subscriber_lines[$unique_key]['number_of_records'] ++ : 2;
				}
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
					$key = $unique_key;
//					}
				}
				if ($this->ignore_non_tap3_serving_network && isset($value['roaming']) && $value['roaming'] == "0" && isset($value['serving_network'])) {
					unset($value['serving_network']);
				}
				if ($this->compare_only_special_prices && isset($value['DISCOUNT_USAGE']) && $value['DISCOUNT_USAGE'] != 'DISCOUNT_NONE' && $value['usaget'] != 'Service') {
					unset($value['DISCOUNT_USAGE']);
					unset($value['charge']);
					unset($value['credit']);
				}
				if ($this->ignore_mms_called_number && isset($value['usaget']) && $value['usaget'] == 'MMS') {
					unset($value['called_number']);
				}
				if ($value['usaget'] == 'Service') {
					unset($value['interval']);
					unset($value['rate_price']);
					unset($value['intl']);
					unset($value['DISCOUNT_USAGE']);
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
							if ($this->different($line1, $line2, $key)) {
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

	protected function different($line1, $line2, $key = null) {
		if (is_array($line1)) {
			$value1 = $line1[$key];
			$value2 = $line2[$key];
		} else {
			$value1 = $line1;
			$value2 = $line2;
		}
		if ($value1 == $value2) {
			return false;
		}
		if ($key == 'arate') {
			if ($this->ignore_KT_USA_diff && array_intersect(array("KT_USA_NEW", "KT_USA_FIX"), array($value1, $value2))) {
				return false;
			}
			if ($this->ignore_INTERNAL_VOICE_MAIL_CALL_diff && array_intersect(array("INTERNAL_VOICE_MAIL_CALL", "IL_MOBILE"), array($value1, $value2))) {
				return false;
			}
		} else if ($key == 'rate_price') {
			if ($this->ignore_INTERNAL_VOICE_MAIL_CALL_diff && array_intersect(array("INTERNAL_VOICE_MAIL_CALL", "IL_MOBILE"), array($line1['arate'], $line2['arate']))) {
				return false;
			}
		} else if ($key == 'called_number') {
			if (!$this->phone_numbers_strict_comparison) {
				return strpos($value1, $value2) === false && strpos($value2, $value1) === false;
			}
		} else if ($key == 'usagev' && $line1['usaget'] == 'Internet Access' && $line1['roaming'] == '0') {
			if (abs($value1 - $value2) <= $this->ggsn_bytes_gap_to_ignore) {
				return false;
			}
		}
		if (is_numeric($value1) && is_numeric($value2)) {
			return $this->big_difference($value1, $value2, $key);
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
			return $portion > 1 + $this->relative_error / 100 || $portion < 1 - $this->relative_error / 100;
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
//				$msg.='</br>' . $key;
				$msg.='</br>' . $key . ' ' . $line['usaget'] . ($line['roaming'] == "1" ? " (Tap3)" : "");
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
	public $files;
	public $billrun_key_regex;
	public $account_id_regex;
	public $processed_files;

	abstract public function getInvoicePathPattern($billrun_key, $account_id);

	public function loadFile($filename) {
		$this->xml = simplexml_load_file($filename);
	}

}

class NsoftParticipant extends Participant {

	public $name = 'nsoft';
	public $billrun_key_regex = 'invoice_(\d{6})';
	public $account_id_regex = 'invoice_\d+_(\d+)_\d+';

	public function getInvoicePathPattern($billrun_key, $account_id) {
		return 'invoice_' . $billrun_key . '*_' . $account_id . '_*.xml';
	}

}

class BillrunParticipant extends Participant {

	public $name = 'billrun';
	public $billrun_key_regex = '^(\d{6})';
	public $account_id_regex = '\d+_0*(\d+)_\d+';

	public function getInvoicePathPattern($billrun_key, $account_id) {
		return $billrun_key . '*_*' . $account_id . '_*.xml';
	}

}

