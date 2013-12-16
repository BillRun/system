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

	public function indexAction() {
		$this->subscriber = Billrun_Factory::subscriber();
		$this->initParticipants();
		$this->loadFiles();
		$this->loadSubscribers();
		$this->matchSubscribers();
		$this->printResults();
//		$this->compare();
		die();
	}

	public function xmls() {
		
	}

	protected function initParticipants() {
		$this->source = new Participant();
		$this->target = new Participant();
		$this->source->name = 'nsoft';
		$this->source->xmls_path = '/home/shani/projects/billrun/files/invoices/Checks/nsoft/';
		$this->target->name = 'billrun';
		$this->target->xmls_path = '/home/shani/projects/billrun/files/invoices/Checks/billrun/';
		//comment both lines at the end
		$this->source->xmls_path.= "invoice_20131126_9073496_0012691552.xml";
		$this->target->xmls_path.= "201311_009073496_00000000104.xml";
//		$this->source->xmls_path.= "";
//		$this->target->xmls_path.= "201311_004171195_00000000105.xml";
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

	protected function loadFiles() {
		$this->source->xml = simplexml_load_file($this->source->xmls_path);
		$this->target->xml = simplexml_load_file($this->target->xmls_path);
		$this->billrun_key = "201311"; //TODO get it from the filename
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
	}

	protected function getUniqueLinesData(Participant $sub, $sub_id) {
		$subscriber_lines = array();
		foreach ($sub->xml->SUBSCRIBER_INF[$sub_id]->BILLING_LINES->BILLING_RECORD as $billing_line) {
			if ((string) $billing_line->TARIFFKIND != 'Call' || (int) $billing_line->CHARGEDURATIONINSEC) {
				if (isset($subscriber_lines[(string) $billing_line->TIMEOFBILLING])) {
					$this->displayMsg("Skipping same date key $billing_line->TIMEOFBILLING");
				}
				if ($this->ignore_tap3_records && $billing_line->ROAMING == "1") {
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
					$key = strval($billing_line->TIMEOFBILLING);
				}
				if ($billing_line->ROAMING == "0" && $this->ignore_non_tap3_serving_network) {
					if (isset($value['serving_network'])) {
						unset($value['serving_network']);
					}
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
		$calling_number = preg_replace('/^\+?0*972/', '',(string) $billing_line->CTXT_CALL_IN_CLI);
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
							if ($this->different($value, $line2[$key])) {
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

	protected function different($value1, $value2) {
		if (is_numeric($value1) && is_numeric($value2)) {
			return $this->big_difference($value1, $value2);
		} else {
			return $value1 != $value2;
		}
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
				$target_lines = $target_sub['unique_lines_data'];
				$source_diff = array_diff_key($source_sub['unique_lines_data'], $target_lines);
				if ($source_diff) {
					$this->displayMsg($this->target->name . ' subscriber ' . $target_sub['identity'] . ' is missing ' . count($source_diff) . ' lines.');
				}
				$target_diff = array_diff_key($target_lines, $source_sub['unique_lines_data']);
				if ($target_diff) {
					$this->displayMsg($this->source->name . ' subscriber ' . $source_sub['identity'] . ' is missing ' . count($target_diff) . ' lines.');
				}
			}
		}
//		foreach ($this->target->subs as $target_id => $target_sub) {
//			$source_id = array_search($target_id, $this->subs_mappings);
//			if ($source_id !== false && isset($this->source->subs[$source_id]['unique_lines_data'])) {
//				$source_sub = $this->source->subs[$source_id];
//				$source_lines = $source_sub['unique_lines_data'];
//				$target_diff = array_diff_key($target_sub['unique_lines_data'], $source_lines);
//				if ($target_diff) {
//					echo $this->source->name . ' subscriber ' . $source_sub['identity'] . ' is missing ' . count($target_diff) . ' lines.';
//				}
//			}
//		}
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

}

class Participant {

	public $xml;
	public $name;
	public $xmls_path;
	public $subs;

}