<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2021 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * DB migration model class.
 *
 * Discovers per-file migrations under application/migrations/, each one a
 * Billrun_Migration_Base subclass with a unique task code. Wraps each in a
 * runOnce guard so applied codes are recorded in lastConfig.past_migration_tasks.
 *
 * Used by DbmigrateAction for CLI migration execution and by CreatetenantAction
 * to apply migrations to a newly created tenant database.
 *
 * @package  Models
 * @since    5.25.0
 */
class DbmigrateModel {

	/**
	 * In-memory copy of the latest config record (without _id) - mutated by
	 * migration steps and re-inserted at the end as a new revision.
	 *
	 * @var array|null
	 */
	protected $lastConfig;

	/**
	 * Full migration flow: load config, apply migrations, save config.
	 *
	 * @param Billrun_Db $db
	 * @param mixed      $controller optional controller for addOutput
	 * @return bool true if migrations were applied (or skipped because none pending), false if no config found
	 */
	public function execute(Billrun_Db $db, $controller = null) {
		$this->log($controller, 'Starting DB data migrations...');

		if (!$this->loadLastConfig($db)) {
			$this->log($controller, 'Warning: no config record found, skipping db migrations');
			return false;
		}

		$this->applyMigrations($db, $controller);
		$this->saveConfig($db);
		$this->log($controller, 'DB data migrations done.');
		return true;
	}

	/**
	 * Return the list of task codes already applied to this database.
	 *
	 * Loads the latest config record on demand. Useful for diagnostics
	 * ("why didn't migration X run?") from CLI tools, tests, or admin pages.
	 *
	 * @param Billrun_Db $db
	 * @return string[] task codes recorded in lastConfig.past_migration_tasks
	 */
	public function getAppliedTasks(Billrun_Db $db) {
		if (!$this->loadLastConfig($db)) {
			return [];
		}
		if (!isset($this->lastConfig['past_migration_tasks']) || !is_array($this->lastConfig['past_migration_tasks'])) {
			return [];
		}
		return $this->lastConfig['past_migration_tasks'];
	}

	/**
	 * Discover migration files, instantiate each, and run any whose task code
	 * is not already recorded in lastConfig.past_migration_tasks.
	 *
	 * @param Billrun_Db $db
	 * @param mixed      $controller optional controller for addOutput
	 */
	protected function applyMigrations(Billrun_Db $db, $controller = null) {
		$files = glob(APPLICATION_PATH . '/application/migrations/*.php');
		sort($files);
		foreach ($files as $file) {
			$basename = basename($file);
			// Only load files that match the documented migration filename
			// convention: YYYYMMDD_NNN_<JIRA_REF>.php.
			if (!preg_match('/^\d{8}_\d{3}_[A-Za-z0-9-]+\.php$/', $basename)) {
				$this->log($controller, 'Skipping ' . $basename . ': filename does not match migration convention');
				continue;
			}
			$migration = require $file;
			if (!$migration instanceof Billrun_Migration_Base) {
				$this->log($controller, 'Skipping ' . $basename . ': not a Billrun_Migration_Base');
				continue;
			}
			$migration->setContext($db, $this->lastConfig, $controller);
			$this->runOnce($migration->getTaskCode(), function () use ($migration) {
				$migration->run();
			}, $controller);
		}
	}

	/**
	 * Load the latest config revision into $this->lastConfig (without _id).
	 *
	 * @param Billrun_Db $db
	 * @return bool true if a config record was loaded, false otherwise
	 */
	public function loadLastConfig(Billrun_Db $db) {
		$cursor = $db->configCollection()
			->query()
			->cursor()
			->setReadPreference('RP_PRIMARY')
			->sort(['urt' => -1, '_id' => -1])
			->limit(1)
			->current();
		if (!$cursor || $cursor->isEmpty()) {
			$this->lastConfig = null;
			return false;
		}
		$data = $cursor->getRawData();
		unset($data['_id']);
		$this->lastConfig = $data;
		return true;
	}

	/**
	 * Insert the (mutated) lastConfig as a new revision into the capped config
	 * collection.
	 *
	 * @param Billrun_Db $db
	 */
	public function saveConfig(Billrun_Db $db) {
		$this->lastConfig['urt'] = new Mongodloid_Date();
		$db->configCollection()->insert($this->lastConfig);
	}

	/**
	 * Guard a callback so it runs at most once per task code across migration
	 * runs. The task code is recorded in lastConfig.past_migration_tasks.
	 *
	 * @param string   $taskCode e.g. 'BRCD-1443' - must end in -<digits>
	 * @param callable $callback no-arg callable performing the migration
	 * @param mixed    $controller optional controller for addOutput
	 */
	public function runOnce($taskCode, callable $callback, $controller = null) {
		if (!isset($this->lastConfig['past_migration_tasks']) || !is_array($this->lastConfig['past_migration_tasks'])) {
			$this->lastConfig['past_migration_tasks'] = [];
		}
		$taskCode = strtoupper($taskCode);
		if (in_array($taskCode, $this->lastConfig['past_migration_tasks'], true)) {
			return;
		}
		if (!preg_match('/.*-\d+$/', $taskCode)) {
			$this->log($controller, 'Illegal task code ' . $taskCode);
			return;
		}
		$this->log($controller, 'running task ' . $taskCode);
		$callback();
		$this->lastConfig['past_migration_tasks'][] = $taskCode;
	}

	/**
	 * Emit a status line to the controller output when a controller is available.
	 *
	 * @param mixed  $controller
	 * @param string $message
	 */
	protected function log($controller, $message) {
		if ($controller && method_exists($controller, 'addOutput')) {
			$controller->addOutput($message);
		}
	}

}
