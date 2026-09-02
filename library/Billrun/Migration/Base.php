<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2021 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Base class for one-file-per-task DB migrations.
 *
 * Each concrete migration lives in its own file under application/migrations/
 * and is discovered/loaded by DbmigrateAction. The runner wraps `run()` in a
 * runOnce guard keyed by `getTaskCode()`, so each migration applies at most
 * once per config revision.
 */
abstract class Billrun_Migration_Base {

	/**
	 * @var Billrun_Db
	 */
	protected $db;

	/**
	 * Reference to the in-flight config record being mutated by this run.
	 *
	 * @var array
	 */
	protected $lastConfig;

	/**
	 * CLI controller, used for status output.
	 *
	 * @var CliController
	 */
	protected $controller;

	/**
	 * Bind the migration to the runtime context. Called by the loader before run().
	 *
	 * @param Billrun_Db $db
	 * @param array      $lastConfig in-flight config record (by reference)
	 * @param mixed      $controller optional controller for addOutput; pass null when called from non-action context
	 */
	public function setContext(Billrun_Db $db, array &$lastConfig, $controller = null) {
		$this->db = $db;
		$this->lastConfig = &$lastConfig;
		$this->controller = $controller;
	}

	/**
	 * Unique task code for this migration. Must end with -<digits>, e.g. 'BRCD-1443'.
	 *
	 * @return string
	 */
	abstract public function getTaskCode();

	/**
	 * Apply the migration. Mutate $this->lastConfig and/or call collection ops via $this->db.
	 */
	abstract public function run();

	// =========================================================================
	// Shared helpers (ported from mongo/migration/script.js)
	// =========================================================================

	/**
	 * Idempotent append-by-field_name into $config[$entityName]['fields'].
	 */
	protected function addFieldToConfig(array &$config, $entityName, array $fieldConf) {
		if (!isset($config[$entityName]) || !is_array($config[$entityName])) {
			$config[$entityName] = ['fields' => []];
		}
		if (!isset($config[$entityName]['fields']) || !is_array($config[$entityName]['fields'])) {
			$config[$entityName]['fields'] = [];
		}
		foreach ($config[$entityName]['fields'] as $existing) {
			if (isset($existing['field_name']) && $existing['field_name'] === $fieldConf['field_name']) {
				return;
			}
		}
		$config[$entityName]['fields'][] = $fieldConf;
	}

	/**
	 * Remove one or more fields by field_name from $config[$entityName]['fields'].
	 *
	 * @param string|string[] $fieldNames
	 */
	protected function removeFieldFromConfig(array &$config, $entityName, $fieldNames) {
		if (!isset($config[$entityName]['fields']) || !is_array($config[$entityName]['fields'])) {
			return;
		}
		$toDelete = is_array($fieldNames) ? $fieldNames : [$fieldNames];
		$config[$entityName]['fields'] = array_values(array_filter(
			$config[$entityName]['fields'],
			function ($field) use ($toDelete) {
				return !isset($field['field_name']) || !in_array($field['field_name'], $toDelete, true);
			}
		));
	}

	/**
	 * Emit a status line to the controller output when a controller is available.
	 *
	 * No-op when no controller was passed to setContext() (e.g. when a
	 * migration runs from a non-CLI caller such as a test or admin tool).
	 */
	protected function log($message) {
		if ($this->controller && method_exists($this->controller, 'addOutput')) {
			$this->controller->addOutput($message);
		}
	}

}
