<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2021 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * DB data migration action controller.
 *
 * Discovers per-file migrations under application/migrations/, each one a
 * Billrun_Migration_Base subclass with a unique task code. Wraps each in a
 * runOnce guard so applied codes are recorded in lastConfig.past_migration_tasks.
 *
 * @package     Controllers
 * @subpackage  Action
 * @since 5.25.0
 */
class DbmigrateAction extends Action_Base {

	public function execute() {
		// Local variable, not $this->db - Yaf's Action_Base doesn't declare
		// the property, so assigning it would emit a PHP 8.2 dynamic-property
		// deprecation notice.
		$db = Billrun_Factory::db();
		(new DbmigrateModel())->execute($db, $this->getController());
	}

}
