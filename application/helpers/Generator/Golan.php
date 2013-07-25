<?php

/**
 * @category   Application
 * @package    Helpers
 * @subpackage Generator
 * @copyright  Copyright (C) 2013 S.D.O.C. LTD. All rights reserved.
 * @license    GNU General Public License version 2 or later
 */

/**
 * Golan invoices generator
 *
 * @package    Generator
 * @subpackage Golan
 * @since      1.0
 */
class Generator_Golan extends Billrun_Generator {

	protected $server_id = 1;
	protected $server_count = 1;
	protected $data = null;

	public function __construct($options) {
		self::$type = 'golan';
		parent::__construct($options);
	}

	public function generate() {
		$this->xml();
	}

	protected function xml() {
		// use $this->export_directory
		$lines_coll = Billrun_Factory::db()->linesCollection();
		foreach ($this->data as $row) {
			$invoice_total_gift = 0;
			$invoice_total_above_gift = 0;
			$invoice_total_outside_gift_vat = 0;
			$invoice_total_manual_correction = 0;
			$invoice_total_manual_correction_credit = 0;
			$invoice_total_manual_correction_charge = 0;
			$invoice_total_outside_gift_novat = 0;
			$invoice_total_vat = 0;
			$invoice_total_charge_no_vat = 0;
			$invoice_total_charge = 0;
			$billrun_key = $row->get('billrun_key');
			$account_id = $row->get('account_id');
			Billrun_Factory::log()->log("xml account " . $account_id, Zend_Log::INFO);
			// @todo refactoring the xml generation to another class
			$xml = $this->basic_xml();
			$xml->TELECOM_INFORMATION->VAT_VALUE = $this->displayVAT($row['vat']);
			$xml->INV_CUSTOMER_INFORMATION->CUSTOMER_CONTACT->EXTERNALACCOUNTREFERENCE = $account_id;

			foreach ($row->get('subs') as $subscriber) {
				$subscriber_inf = $xml->addChild('SUBSCRIBER_INF');
				$subscriber_inf->SUBSCRIBER_DETAILS->SUBSCRIBER_ID = $row->get('subscriber_id');

				$billing_records = $subscriber_inf->addChild('BILLING_LINES');

				$subscriber_lines_refs = $this->get_subscriber_lines_refs($subscriber);
				foreach ($subscriber_lines_refs as $ref) {
					$line = $lines_coll->getRef($ref);
					if (!$line->isEmpty()) {
						$billing_record = $billing_records->addChild('BILLING_RECORD');
						$billing_record->TIMEOFBILLING = $this->getGolanDate($line['unified_record_time']->sec);
						$billing_record->TARIFFITEM = $this->getTariffItem($line);
						$billing_record->CTXT_CALL_OUT_DESTINATIONPNB = $this->getCalledNo($line); //@todo maybe save dest_no in all processors and use it here
						$billing_record->CTXT_CALL_IN_CLI = $this->getCallerNo($line); //@todo maybe save it in all processors and use it here
						$billing_record->CHARGEDURATIONINSEC = $this->getUsageVolume($line);
						$billing_record->CHARGE = $this->getCharge($line);
						$billing_record->CREDIT = $this->getCredit($line);
						$billing_record->TARIFFKIND = $this->getTariffKind($line);
						$billing_record->TTAR_ACCESSPRICE1 = $this->getAccessPrice($line);
						$billing_record->TTAR_SAMPLEDELAYINSEC1 = $this->getInterval($line);
						$billing_record->TTAR_SAMPPRICE1 = $this->getRate($line);
						$billing_record->INTERNATIONAL = $this->getIntlFlag($line);
						$billing_record->DISCOUNT_USAGE = $this->getDiscountUsage($line);
					}
				}

				$subscriber_gift_usage = $subscriber_inf->addChild('SUBSCRIBER_GIFT_USAGE');
				$subscriber_gift_usage->GIFTID_GIFTCLASSNAME = "GC_GOLAN";
				$flat_line = $lines_coll->getRef($subscriber['lines']['flat']['refs'][0]);
				$flat_line->collection($lines_coll);
				$subscriber_gift_usage->GIFTID_GIFTNAME = $flat_line['plan_ref']['name'];
				$subscriber_gift_usage->TOTAL_FREE_COUNTER_COST = $subscriber['costs']['flat']['vatable'] + $subscriber['costs']['flat']['vat_free'];
				//$subscriber_gift_usage->VOICE_COUNTERVALUEBEFBILL = ???;
				//$subscriber_gift_usage->VOICE_FREECOUNTER = ???;
				//$subscriber_gift_usage->VOICE_FREECOUNTERCOST = ???;
				$subscriber_gift_usage->VOICE_FREEUSAGE = 0; // flat calls usage
				$subscriber_gift_usage->VOICE_ABOVEFREECOST = 0; // over plan calls cost
				$subscriber_gift_usage->VOICE_ABOVEFREEUSAGE = 0; // over plan calls usage
				$subscriber_gift_usage->SMS_FREEUSAGE = 0; // flat sms usage
				$subscriber_gift_usage->SMS_ABOVEFREECOST = 0; // over plan sms cost
				$subscriber_gift_usage->SMS_ABOVEFREEUSAGE = 0; // over plan sms usage
				$subscriber_gift_usage->DATA_FREEUSAGE = 0; // flat data usage
				$subscriber_gift_usage->DATA_ABOVEFREECOST = 0; // over plan data cost
				$subscriber_gift_usage->DATA_ABOVEFREEUSAGE = 0; // over plan data usage
				$subscriber_gift_usage->MMS_FREEUSAGE = 0; // flat mms usage
				$subscriber_gift_usage->MMS_ABOVEFREECOST = 0; // over plan mms cost
				$subscriber_gift_usage->MMS_ABOVEFREEUSAGE = 0; // over plan mms usage
				foreach ($subscriber['breakdown']['over_plan'] as $category) {
					foreach ($category as $zone) {
						$subscriber_gift_usage->VOICE_ABOVEFREECOST+=$zone['totals']['call']['cost'];
						$subscriber_gift_usage->SMS_ABOVEFREECOST+=$zone['totals']['sms']['cost'];
						$subscriber_gift_usage->DATA_ABOVEFREECOST+=$zone['totals']['data']['cost'];
						$subscriber_gift_usage->MMS_ABOVEFREECOST+=$zone['totals']['mms']['cost'];
						$subscriber_gift_usage->VOICE_ABOVEFREEUSAGE+= $zone['totals']['call']['usagev'];
						$subscriber_gift_usage->SMS_ABOVEFREEUSAGE+= $zone['totals']['sms']['usagev'];
						$subscriber_gift_usage->DATA_ABOVEFREEUSAGE+=$this->bytesToKB($zone['totals']['data']['usagev']);
						$subscriber_gift_usage->MMS_ABOVEFREEUSAGE+= $zone['totals']['mms']['usagev'];
					}
				}
				foreach ($subscriber['breakdown']['in_plan'] as $category) {
					foreach ($category as $zone) {
						$subscriber_gift_usage->VOICE_FREEUSAGE+=$zone['totals']['call']['usagev'];
						$subscriber_gift_usage->SMS_FREEUSAGE+=$zone['totals']['sms']['usagev'];
						$subscriber_gift_usage->DATA_FREEUSAGE+=$this->bytesToKB($zone['totals']['data']['usagev']);
						$subscriber_gift_usage->MMS_FREEUSAGE+=$zone['totals']['mms']['usagev'];
					}
				}

				$subscriber_sumup = $subscriber_inf->addChild('SUBSCRIBER_SUMUP');
				$subscriber_sumup->TOTAL_GIFT = floatval($subscriber['costs']['flat']['vatable'] + $subscriber['costs']['flat']['vat_free']);
				$subscriber_sumup->TOTAL_ABOVE_GIFT = floatval($subscriber['costs']['over_plan']['vatable'] + $subscriber['costs']['out_plan']['vatable']); // vatable over/out plan cost
				$subscriber_sumup->TOTAL_OUTSIDE_GIFT_VAT = floatval($subscriber['costs']['out_plan']['vatable']);
				$subscriber_sumup->TOTAL_MANUAL_CORRECTION_CHARGE = floatval($subscriber['costs']['manual']['charge']['vatable']);
				$subscriber_sumup->TOTAL_MANUAL_CORRECTION_CREDIT = floatval($subscriber['costs']['manual']['refund']['vatable']);
				$subscriber_sumup->TOTAL_MANUAL_CORRECTION = floatval($subscriber_sumup->TOTAL_MANUAL_CORRECTION_CHARGE + $subscriber_sumup->TOTAL_MANUAL_CORRECTION_CREDIT);
				$subscriber_sumup->TOTAL_OUTSIDE_GIFT_NOVAT = floatval($subscriber['costs']['out_plan']['vat_free']);
				$total_vatable = floatval($subscriber['costs']['flat']['vatable'] + $subscriber['costs']['over_plan']['vatable'] + $subscriber['costs']['out_plan']['vatable']);
				$total_vat_free = floatval($subscriber['costs']['flat']['vat_free'] + $subscriber['costs']['over_plan']['vat_free'] + $subscriber['costs']['out_plan']['vat_free']);
				$subscriber_sumup->TOTAL_VAT = floatval($row->get('vat') * $total_vatable);
				$subscriber_sumup->TOTAL_CHARGE_NO_VAT = $total_vatable + $total_vat_free; // non_vatable_total+vatable_total
				$subscriber_sumup->TOTAL_CHARGE = floatval($subscriber_sumup->TOTAL_CHARGE_NO_VAT) + floatval($subscriber_sumup->TOTAL_VAT);

				$invoice_total_gift+= floatval($subscriber_sumup->TOTAL_GIFT);
				$invoice_total_above_gift+= floatval($subscriber_sumup->TOTAL_ABOVE_GIFT);
				$invoice_total_outside_gift_vat+= floatval($subscriber_sumup->TOTAL_OUTSIDE_GIFT_VAT);
				$invoice_total_manual_correction += floatval($subscriber_sumup->TOTAL_MANUAL_CORRECTION);
				$invoice_total_manual_correction_credit += floatval($subscriber_sumup->TOTAL_MANUAL_CORRECTION_CREDIT);
				$invoice_total_manual_correction_charge += floatval($subscriber_sumup->TOTAL_MANUAL_CORRECTION_CHARGE);
				$invoice_total_outside_gift_novat +=floatval($subscriber_sumup->TOTAL_OUTSIDE_GIFT_NOVAT);
				$invoice_total_vat+=floatval($subscriber_sumup->TOTAL_VAT);
				$invoice_total_charge_no_vat+=floatval($subscriber_sumup->TOTAL_CHARGE_NO_VAT);
				$invoice_total_charge +=floatval($subscriber_sumup->TOTAL_CHARGE);

				$subscriber_breakdown = $subscriber_inf->addChild('SUBSCRIBER_BREAKDOWN');
				$breakdown_topic_over_plan = $subscriber_breakdown->addChild('BREAKDOWN_TOPIC');
				$breakdown_topic_over_plan->addAttribute('name', 'GIFT_XXX_OUT_OF_USAGE');
				$out_of_usage_entry = $breakdown_topic_over_plan->addChild('BREAKDOWN_ENTRY');
//				$out_of_usage_entry->addChild('TITLE', 'SERVICE-GIFT-GC_GOLAN-' . current($subscriber['lines']['flat']['refs'])['plan_ref']['name']);
				$out_of_usage_entry->addChild('UNITS', 1);
				$out_of_usage_entry->addChild('COST_WITHOUTVAT', $subscriber['breakdown']['in_plan']['base']['service']['cost']);
				$out_of_usage_entry->addChild('VAT', $this->displayVAT($row['vat']));
				$out_of_usage_entry->addChild('VAT_COST', floatval($out_of_usage_entry->COST_WITHOUTVAT) * floatval($out_of_usage_entry->VAT) / 100);
				$out_of_usage_entry->addChild('TOTAL_COST', floatval($out_of_usage_entry->COST_WITHOUTVAT) + floatval($out_of_usage_entry->VAT_COST));
				$out_of_usage_entry->addChild('TYPE_OF_BILLING', 'GIFT');
//				$out_of_usage_entry->addChild('TYPE_OF_BILLING_CHAR', ?);
				foreach ($subscriber['breakdown']['over_plan'] as $category_key => $category) {
					foreach ($category as $zone_name => $zone) {
						if ($zone_name != 'service') {
//							$out_of_usage_entry->addChild('TITLE', ?);
							foreach (array('call', 'sms', 'data', 'incoming_call', 'mms', 'incoming_sms') as $type) {
								$usagev = $subscriber['breakdown']['over_plan'][$category_key][$zone_name]['totals'][$type]['usagev'];
								if ($usagev > 0) {
									$out_of_usage_entry = $breakdown_topic_over_plan->addChild('BREAKDOWN_ENTRY');
//									$out_of_usage_entry->addChild('TITLE', 'SERVICE-GIFT-GC_GOLAN-' . current($subscriber['lines']['flat']['refs'])['plan_ref']['name']);
									$out_of_usage_entry->addChild('UNITS', $usagev);
									$out_of_usage_entry->addChild('COST_WITHOUTVAT', $subscriber['breakdown']['over_plan'][$category_key][$zone_name]['totals'][$type]['cost']);
									$out_of_usage_entry->addChild('VAT', $this->displayVAT($zone['totals']['vat']));
									$out_of_usage_entry->addChild('VAT_COST', floatval($out_of_usage_entry->COST_WITHOUTVAT) * floatval($out_of_usage_entry->VAT) / 100);
									$out_of_usage_entry->addChild('TOTAL_COST', floatval($out_of_usage_entry->COST_WITHOUTVAT) + floatval($out_of_usage_entry->VAT_COST));
									$out_of_usage_entry->addChild('TYPE_OF_BILLING', strtoupper($type));
//									$out_of_usage_entry->addChild('TYPE_OF_BILLING_CHAR', ?);
								}
							}
						}
					}
				}

				foreach ($subscriber['breakdown']['over_plan']['base'] as $zone_name => $zone) {
					if ($zone_name != 'service') {
//						$out_of_usage_entry->addChild('TITLE', ?);
						foreach (array('call', 'sms', 'data', 'incoming_call', 'mms', 'incoming_sms') as $type) {
							$usagev = $subscriber['breakdown']['over_plan']['base'][$zone_name]['totals'][$type]['usagev'];
							if ($usagev > 0) {
								$out_of_usage_entry = $breakdown_topic_over_plan->addChild('BREAKDOWN_ENTRY');
//								$out_of_usage_entry->addChild('TITLE', 'SERVICE-GIFT-GC_GOLAN-' . current($subscriber['lines']['flat']['refs'])['plan_ref']['name']);
								$out_of_usage_entry->addChild('UNITS', $usagev);
								$out_of_usage_entry->addChild('COST_WITHOUTVAT', $subscriber['breakdown']['over_plan']['base'][$zone_name]['totals'][$type]['cost']);
								$out_of_usage_entry->addChild('VAT', $this->displayVAT($zone['totals']['vat']));
								$out_of_usage_entry->addChild('VAT_COST', floatval($out_of_usage_entry->COST_WITHOUTVAT) * floatval($out_of_usage_entry->VAT) / 100);
								$out_of_usage_entry->addChild('TOTAL_COST', floatval($out_of_usage_entry->COST_WITHOUTVAT) + floatval($out_of_usage_entry->VAT_COST));
								$out_of_usage_entry->addChild('TYPE_OF_BILLING', strtoupper($type));
//								$out_of_usage_entry->addChild('TYPE_OF_BILLING_CHAR', ?);
							}
						}
					}
				}

				$breakdown_topic_international = $subscriber_breakdown->addChild('BREAKDOWN_TOPIC');
				$breakdown_topic_international->addAttribute('name', 'INTERNATIONAL');
				$subscriber_intl = array();
				foreach ($subscriber['breakdown'] as $plan) {
					if (isset($plan['intl'])) {
						foreach ($plan['intl'] as $zone_name => $zone) {
							foreach ($zone['totals'] as $usage_type => $usage_totals) {
								if ($usage_totals['cost'] > 0 || $usage_totals['usagev'] > 0) {
									if (isset($subscriber_intl[$zone_name][$usage_type])) {
										$subscriber_intl[$zone_name]['totals'][$usage_type]['usagev']+=$usage_totals['usagev'];
										$subscriber_intl[$zone_name]['totals'][$usage_type]['cost']+=$usage_totals['cost'];
									} else {
										$subscriber_intl[$zone_name]['totals'][$usage_type]['usagev'] = $usage_totals['usagev'];
										$subscriber_intl[$zone_name]['totals'][$usage_type]['cost'] = $usage_totals['cost'];
										$subscriber_intl[$zone_name]['vat'] = $zone['vat'];
									}
								}
							}
						}
					}
				}
				foreach ($subscriber_intl as $zone) {
					foreach ($zone['totals'] as $usage_type => $usage_totals) {
//						$out_of_usage_entry->addChild('TITLE', ?);
						$international_entry = $breakdown_topic_international->addChild('BREAKDOWN_ENTRY');
//						$international_entry->addChild('TITLE', 'SERVICE-GIFT-GC_GOLAN-' . current($subscriber['lines']['flat']['refs'])['plan_ref']['name']);
						$international_entry->addChild('UNITS', $usage_totals['usagev']);
						$international_entry->addChild('COST_WITHOUTVAT', $usage_totals['cost']);
						$international_entry->addChild('VAT', $this->displayVAT($zone['vat']));
						$international_entry->addChild('VAT_COST', floatval($international_entry->COST_WITHOUTVAT) * floatval($international_entry->VAT) / 100);
						$international_entry->addChild('TOTAL_COST', floatval($international_entry->COST_WITHOUTVAT) + floatval($international_entry->VAT_COST));
						$international_entry->addChild('TYPE_OF_BILLING', strtoupper($usage_type));
//						$international_entry->addChild('TYPE_OF_BILLING_CHAR', ?);
					}
				}

				$breakdown_topic_special = $subscriber_breakdown->addChild('BREAKDOWN_TOPIC');
				$breakdown_topic_special->addAttribute('name', 'SPECIAL_SERVICES');
				$subscriber_special = array();
				foreach ($subscriber['breakdown'] as $plan) {
					if (isset($plan['special'])) {
						foreach ($plan['special'] as $zone_name => $zone) {
							foreach ($zone['totals'] as $usage_type => $usage_totals) {
								if ($usage_totals['cost'] > 0 || $usage_totals['usagev'] > 0) {
									if (isset($subscriber_special[$zone_name][$usage_type])) {
										$subscriber_special[$zone_name]['totals'][$usage_type]['usagev']+=$usage_totals['usagev'];
										$subscriber_special[$zone_name]['totals'][$usage_type]['cost']+=$usage_totals['cost'];
									} else {
										$subscriber_special[$zone_name]['totals'][$usage_type]['usagev'] = $usage_totals['usagev'];
										$subscriber_special[$zone_name]['totals'][$usage_type]['cost'] = $usage_totals['cost'];
										$subscriber_special[$zone_name]['vat'] = $zone['vat'];
									}
								}
							}
						}
					}
				}
				foreach ($subscriber_special as $zone) {
					foreach ($zone['totals'] as $usage_type => $usage_totals) {
//						$out_of_usage_entry->addChild('TITLE', ?);
						$special_entry = $breakdown_topic_special->addChild('BREAKDOWN_ENTRY');
//						$special_entry->addChild('TITLE', 'SERVICE-GIFT-GC_GOLAN-' . current($subscriber['lines']['flat']['refs'])['plan_ref']['name']);
						$special_entry->addChild('UNITS', $usage_totals['usagev']);
						$special_entry->addChild('COST_WITHOUTVAT', $usage_totals['cost']);
						$special_entry->addChild('VAT', $this->displayVAT($zone['vat']));
						$special_entry->addChild('VAT_COST', floatval($special_entry->COST_WITHOUTVAT) * floatval($special_entry->VAT) / 100);
						$special_entry->addChild('TOTAL_COST', floatval($special_entry->COST_WITHOUTVAT) + floatval($special_entry->VAT_COST));
						$special_entry->addChild('TYPE_OF_BILLING', strtoupper($usage_type));
//						$special_entry->addChild('TYPE_OF_BILLING_CHAR', ?);
					}
				}

				$breakdown_topic_roaming = $subscriber_breakdown->addChild('BREAKDOWN_TOPIC');
				$breakdown_topic_roaming->addAttribute('name', 'ROAMING');
				$subscriber_roaming = array();
				foreach ($subscriber['breakdown'] as $plan) {
					if (isset($plan['roaming'])) {
						foreach ($plan['roaming'] as $zone_name => $zone) {
							foreach ($zone['totals'] as $usage_type => $usage_totals) {
								if ($usage_totals['cost'] > 0 || $usage_totals['usagev'] > 0) {
									if (isset($subscriber_roaming[$zone_name][$usage_type])) {
										$subscriber_roaming[$zone_name]['totals'][$usage_type]['usagev']+=$usage_totals['usagev'];
										$subscriber_roaming[$zone_name]['totals'][$usage_type]['cost']+=$usage_totals['cost'];
									} else {
										$subscriber_roaming[$zone_name]['totals'][$usage_type]['usagev'] = $usage_totals['usagev'];
										$subscriber_roaming[$zone_name]['totals'][$usage_type]['cost'] = $usage_totals['cost'];
										$subscriber_roaming[$zone_name]['vat'] = $zone['vat'];
									}
								}
							}
						}
					}
				}
				foreach ($subscriber_roaming as $zone_key => $zone) {
					$subtopic_entry = $breakdown_topic_roaming->addChild('BREAKDOWN_SUBTOPIC');
					$subtopic_entry->addAttribute("name", "");
					$subtopic_entry->addAttribute("plmn", $zone_key);
					foreach ($zone['totals'] as $usage_type => $usage_totals) {
//						$out_of_usage_entry->addChild('TITLE', ?);
						$roaming_entry = $subtopic_entry->addChild('BREAKDOWN_ENTRY');
//						$roaming_entry->addChild('TITLE', 'SERVICE-GIFT-GC_GOLAN-' . current($subscriber['lines']['flat']['refs'])['plan_ref']['name']);
						$roaming_entry->addChild('UNITS', $usage_totals['usagev']);
						$roaming_entry->addChild('COST_WITHOUTVAT', $usage_totals['cost']);
						$roaming_entry->addChild('VAT', $this->displayVAT($zone['vat']));
						$roaming_entry->addChild('VAT_COST', floatval($roaming_entry->COST_WITHOUTVAT) * floatval($roaming_entry->VAT) / 100);
						$roaming_entry->addChild('TOTAL_COST', floatval($roaming_entry->COST_WITHOUTVAT) + floatval($roaming_entry->VAT_COST));
						$roaming_entry->addChild('TYPE_OF_BILLING', strtoupper($usage_type));
//						$roaming_entry->addChild('TYPE_OF_BILLING_CHAR', ?);
					}
				}

				$breakdown_topic_charge = $subscriber_breakdown->addChild('BREAKDOWN_TOPIC');
				$breakdown_topic_charge->addAttribute('name', 'CHARGE_PER_CLI');
				foreach ($subscriber['breakdown']['manual']['charge'] as $reason => $cost) {
					$charge_entry = $breakdown_topic_charge->addChild('BREAKDOWN_ENTRY');
					//						$charge_entry->addChild('TITLE', 'SERVICE-GIFT-GC_GOLAN-' . current($subscriber['lines']['flat']['refs'])['plan_ref']['name']);
					$charge_entry->addChild('UNITS', 1);
					$charge_entry->addChild('COST_WITHOUTVAT', $cost);
					$charge_entry->addChild('VAT', $xml->TELECOM_INFORMATION->VAT_VALUE);
					$charge_entry->addChild('VAT_COST', $charge_entry->COST_WITHOUTVAT * $charge_entry->VAT / 100);
					$charge_entry->addChild('TOTAL_COST', $charge_entry->COST_WITHOUTVAT + $charge_entry->VAT_COST);
//					$charge_entry->addChild('TYPE_OF_BILLING', strtoupper($type));
//						$charge_entry->addChild('TYPE_OF_BILLING_CHAR', ?);
				}

				$breakdown_topic_refund = $subscriber_breakdown->addChild('BREAKDOWN_TOPIC');
				$breakdown_topic_refund->addAttribute('name', 'REFUND_PER_CLI');
				foreach ($subscriber['breakdown']['manual']['refund'] as $reason => $cost) {
					$refund_entry = $breakdown_topic_refund->addChild('BREAKDOWN_ENTRY');
					//						$refund_entry->addChild('TITLE', 'SERVICE-GIFT-GC_GOLAN-' . current($subscriber['lines']['flat']['refs'])['plan_ref']['name']);
					$refund_entry->addChild('UNITS', 1);
					$refund_entry->addChild('COST_WITHOUTVAT', $cost);
					$refund_entry->addChild('VAT', $xml->TELECOM_INFORMATION->VAT_VALUE);
					$refund_entry->addChild('VAT_COST', $refund_entry->COST_WITHOUTVAT * $refund_entry->VAT / 100);
					$refund_entry->addChild('TOTAL_COST', $refund_entry->COST_WITHOUTVAT + $refund_entry->VAT_COST);
//					$refund_entry->addChild('TYPE_OF_BILLING', strtoupper($type));
//						$refund_entry->addChild('TYPE_OF_BILLING_CHAR', ?);
				}
			}


			$inv_invoice_total = $xml->addChild('INV_INVOICE_TOTAL');
			$inv_invoice_total->addChild('INVOICE_NUMBER', $row->get('invoice_id'));
			$inv_invoice_total->addChild('FROM_PERIOD', date('Y/m/d', Billrun_Util::getStartTime($billrun_key)));
			$inv_invoice_total->addChild('TO_PERIOD', date('Y/m/d', Billrun_Util::getEndTime($billrun_key)));
			$inv_invoice_total->addChild('SUBSCRIBER_COUNT', count($row->get('subs')));
			$inv_invoice_total->addChild('TOTAL_CHARGE', $invoice_total_charge);
			$inv_invoice_total->addChild('TOTAL_CREDIT', $invoice_total_manual_correction_credit);
			$gifts = $inv_invoice_total->addChild('GIFTS');
			$invoice_sumup = $inv_invoice_total->addChild('INVOICE_SUMUP');
			$invoice_sumup->addChild('TOTAL_GIFT', $invoice_total_gift);
			$invoice_sumup->addChild('TOTAL_ABOVE_GIFT', $invoice_total_above_gift);
			$invoice_sumup->addChild('TOTAL_OUTSIDE_GIFT_VAT', $invoice_total_outside_gift_vat);
			$invoice_sumup->addChild('TOTAL_MANUAL_CORRECTION', $invoice_total_manual_correction);
			$invoice_sumup->addChild('TOTAL_MANUAL_CORRECTION_CREDIT', $invoice_total_manual_correction_credit);
			$invoice_sumup->addChild('TOTAL_MANUAL_CORRECTION_CHARGE', $invoice_total_manual_correction_charge);
			$invoice_sumup->addChild('TOTAL_OUTSIDE_GIFT_NOVAT', $invoice_total_outside_gift_novat);
			$invoice_sumup->addChild('TOTAL_VAT', $invoice_total_vat);
			$invoice_sumup->addChild('TOTAL_CHARGE_NO_VAT', $invoice_total_charge_no_vat);
			$invoice_sumup->addChild('TOTAL_CHARGE', $invoice_total_charge);


//			$row->{'xml'} = $xml->asXML();
			$invoice_id = $row->get('invoice_id');
			$this->createXml($invoice_id, $xml->asXML());
			$this->setFileStamp($row, $invoice_id);
			Billrun_Factory::log()->log("invoice file " . $invoice_id . " created for account " . $account_id, Zend_Log::INFO);
//			$this->addRowToCsv($invoice_id, $row->get('account_id'), $total, $total_ilds);
		}
	}

	/**
	 * 
	 * @param type $fileName
	 * @param type $xmlContent
	 * @return type
	 * @todo do not override files?
	 */
	protected function createXml($fileName, $xmlContent) {
		$path = $this->export_directory . '/' . $fileName . '.xml';
		return file_put_contents($path, $xmlContent);
	}

	/**
	 * 
	 * @param array $subscriber subscriber billrun entry
	 * @return type
	 */
	protected function get_subscriber_lines_refs($subscriber) {
		$refs = array();
		foreach ($subscriber['lines'] as $lines_by_usage_type) {
			if (is_array($lines_by_usage_type["refs"])) {
				$refs = array_merge($refs, $lines_by_usage_type["refs"]);
			}
		}
		return $refs;
	}

	protected function getUsageVolume($line) {
		if (isset($line['usagev']) && isset($line['usaget'])) {
			switch ($line['usaget']) {
				case 'call':
				case 'incoming_call':
				case 'sms':
				case 'incoming_sms':
					return $line['usagev'];
				case 'data':
					return $this->bytesToKB($line['usagev']);
				default:
					break;
			}
		}
		return 0;
	}

	protected function getCharge($line) {
		if (isset($line['price_customer'])) {
			return isset($line['price_customer']);
		} else if ($line['type'] == 'credit' && isset($line['amount_without_vat']) && isset($line['charge_type']) && $line['charge_type'] == 'charge') {
			return $line['amount_without_vat'];
		}
		return 0;
	}

	protected function getCredit($line) {
		if ($line['type'] == 'credit' && isset($line['amount_without_vat']) && isset($line['charge_type']) && $line['charge_type'] == 'refund') {
			return $line['amount_without_vat'];
		}
		return 0;
	}

	protected function getAccessPrice($line) {
		if (isset($line['usaget']) && isset($line['customer_rate']['rates'][$line['usaget']]['access'])) {
			return $line['customer_rate']['rates'][$line['usaget']]['access'];
		}
		return 0;
	}

	protected function getInterval($line) {
		if (isset($line['usaget']) && isset($line['customer_rate']['rates'][$line['usaget']]['rate']['interval'])) {
			return $line['customer_rate']['rates'][$line['usaget']]['rate']['interval'];
		}
		return 0;
	}

	protected function getRate($line) {
		if (isset($line['usaget']) && isset($line['customer_rate']['rates'][$line['usaget']]['rate']['price'])) {
			return $line['customer_rate']['rates'][$line['usaget']]['rate']['price'];
		}
		return 0;
	}

	protected function getIntlFlag($line) {
		if (isset($line['usaget']) && isset($line['customer_rate']['rates'][$line['usaget']]['category'])) {
			$category = $line['customer_rate']['rates'][$line['usaget']]['category'];
			if ($category == 'intl' || $category == 'roaming') {
				return 1;
			}
		}
		return 0;
	}

	protected function getTariffKind($line) {
		switch ($line['type']) {
			case 'refund':
			case 'charge':
			case 'flat':
				return 'Service';
			default:
				switch ($line['usage_type']) {
					case 'call':
						return 'Call';
					case 'data':
						return 'Internet Access';
					case 'sms':
						return 'SMS';
					case 'incoming_call':
						return 'Incoming Call';
					case 'incoming_sms': // in theory...
						return 'Incoming SMS';
					default:
						return '';
				}
		}
	}

	protected function getTariffItem($line) {
		if (isset($line['customer_rate']['key'])) {
			return $line['customer_rate']['key']; //@todo they may expect ROAM_ALL_DEST / $DEFAULT etc. which we don't keep
		} else if ($line['type'] == 'credit' && isset($line['reason'])) {
			return $line['reason'];
		} else {
			return '';
		}
	}

	protected function getCallerNo($line) {
		switch ($line['type']) {
			case "nsn":
				return $line['called_number'];
			case "tap3":
				$tele_service_code = $line['BasicServiceUsedList']['BasicServiceUsed']['BasicService']['BasicServiceCode']['TeleServiceCode'];
				$record_type = $line['record_type'];
				if ($record_type == 'a' && ($tele_service_code == '11' || $tele_service_code == '21')) {
					return $line['basicCallInformation']['callOriginator']['callingNumber'];
				}
				break;
			case "smsc":
			case "smpp": //@todo didn't really check smpp records but they should be the same
				return $line['calling_number'];
				break;
			default:
				break;
		}
		return '';
	}

	protected function getCalledNo($line) {
		switch ($line['type']) {
			case "nsn":
				return $line['called_number'];
			case "tap3":
				$tele_service_code = $line['BasicServiceUsedList']['BasicServiceUsed']['BasicService']['BasicServiceCode']['TeleServiceCode'];
				$record_type = $line['record_type'];
				if ($record_type == '9') {
					if ($tele_service_code == '11') {
						return $line['basicCallInformation']['Desination']['CalledNumber'];
					} else if ($tele_service_code == '22') {
						return $line['basicCallInformation']['Desination']['DialedDigits']; // @todo check with sefi
					}
				}
				break;
			case "smsc":
			case "smpp": //@todo check smpp
				return $line['called_number'];
			case "mmsc":
				//@todo recipent_addr field is not the pure called number
				break;
			default:
				break;
		}
		return '';
	}

	protected function getDiscountUsage($line) {
		if (isset($line['over_plan']) || isset($line['out_plan'])) {
			return 'DISCOUNT_NONE';
		} else if ($line['type'] == 'flat') {
			return 'DISCOUNT_PARTIAL';
		} else {
			return 'DISCOUNT_FULL';
		}
	}

	protected function basic_xml() {
		$xml = <<<EOI
<?xml version="1.0" encoding="UTF-8"?>
<INVOICE>
	<TELECOM_INFORMATION>
	</TELECOM_INFORMATION>
	<INV_CUSTOMER_INFORMATION>
		<CUSTOMER_CONTACT>
		</CUSTOMER_CONTACT>
	</INV_CUSTOMER_INFORMATION>
</INVOICE>
EOI;
		return simplexml_load_string($xml);
	}

	public function load() {
		$billrun = Billrun_Factory::db()->billrunCollection();

		$this->data = $billrun
			->query('billrun_key', $this->stamp)
			->exists('invoice_id')
			->notExists('invoice_file')
			->mod('account_id', $this->server_count, $this->server_id - 1)
			->cursor();

		Billrun_Factory::log()->log("aggregator entities loaded: " . $this->data->count(), Zend_Log::INFO);

		Billrun_Factory::dispatcher()->trigger('afterGeneratorLoadData', array('generator' => $this));
	}

	protected function setFileStamp($line, $filename) {
		$current = $line->getRawData();
		$added_values = array(
			'invoice_file' => $filename,
		);

		$newData = array_merge($current, $added_values);
		$line->setRawData($newData);
		$line->save(Billrun_Factory::db()->billrunCollection());
		return true;
	}

	/**
	 * 
	 * @param int $bytes
	 * @return int
	 */
	protected function bytesToKB($bytes) {
		return ceil($bytes / 1024);
	}

	/**
	 * 
	 * @param float $vat vat value
	 * @return mixed
	 */
	protected function displayVAT($vat) {
		return $vat * 100;
	}

	protected function getGolanDate($datetime) {
		return date('Y/m/d H:i:s', $datetime);
	}

}
