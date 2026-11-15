<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Billing Job Manager of Confirm Cycle
 *
 * @package  Job Manager
 * @since    5.16
 */
class Billrun_Job_Confirm extends Billrun_Job_Abstract {

	protected $method = 'Confirm';
	
	public function fetch() {
		$options = [
			'type' => 'billrunToBill',
			'stamp' => (string) $this->config['billrun_key'],
			'load_accounts' => false, // the accounts (GAD) are loaded in batches on run()
		];
		// the generator load supports the job filtration options
		foreach (['include', 'exclude', 'include_invoices', 'exclude_invoices'] as $filtrationOption) {
			if (isset($this->config[$filtrationOption])) {
				$options[$filtrationOption] = $this->config[$filtrationOption];
			}
		}
		if (!empty($this->config['invoicing_day'])) {
			$options['invoicing_days'] = $this->config['invoicing_day'];
		}
		$generator = Billrun_Generator::getInstance($options);
		return $generator->load();
	}

	public function run() {
		$jobSettings = array();

		if (!empty($this->config['invoicing_day'])) {
			$jobSettings['invoicing_day'] = $this->config['invoicing_day'];
		}

		$count = 0;
		$parent = $this->queueMsg->md5;
		$account = Billrun_Factory::account();
		br_yaf_register_autoload('Generator', APPLICATION_PATH . '/application/helpers');
		// accounts preload, disabled by default: load each batch accounts with per-aid
		// queries, so the confirmation GAD cache stores them for the child jobs
		$preloadAccountsCache = Billrun_Factory::config()->getConfigValue('billrun.confirm.accounts_cache.enabled', false)
			&& Generator_BillrunToBill::setupConfirmationCache();
		$gadBatchLimit = Billrun_Factory::config()->getConfigValue('subscribers.account.gad_limit', 5000, "int");
		foreach (array_chunk($this->data, $gadBatchLimit) as $entriesBatch) {
			if ($preloadAccountsCache) {
				// a single GAD call per batch, and the batch child jobs are created right
				// after it, before the cache records expire
				$batchAids = array_values(array_unique(array_map(function ($entry) {
					return $entry['aid'];
				}, $entriesBatch)));
				Billrun_Factory::log("Preloading " . count($batchAids) . " accounts to the confirmation cache", Zend_Log::INFO);
				$accounts = $account->loadAccountsForQueriesByAidsWithBatches($batchAids);
				Billrun_Factory::log("Preloaded " . count($accounts) . " accounts to the confirmation cache", Zend_Log::INFO);
			} else {
				Billrun_Factory::log("Accounts preload to the confirmation cache is disabled - the batch " . count($entriesBatch) . " child jobs will load their accounts by themselves", Zend_Log::INFO);
			}
			foreach ($entriesBatch as $entry) {
				$jobSettings['aid'] = $entry['aid'];
				$jobSettings['invoices'] = $entry['invoice_id'];
				$jobSettings['stamp'] = $entry['billrun_key'];

				Billrun_Factory::log("Going to create job confirm account " . $entry['aid']);
				Billrun_Jobsmanager::getInstance()->push('Confirm_Account', $jobSettings, $parent);
				$count++;
			}
		}
		Billrun_Factory::log("Created " . $count . " confirm account jobs into queue", Zend_Log::INFO);
	}

}
