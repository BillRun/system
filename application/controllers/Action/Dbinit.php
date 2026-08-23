<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2021 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * DB initialization and migration action controller.
 *
 * Runs DbinitModel (schema + base data) then DbmigrateModel (data migrations)
 * against the active database.
 *
 * @package     Controllers
 * @subpackage  Action
 * @since 5.25.0
 */
class DbinitAction extends Action_Base {

	public function execute() {
		$controller = $this->getController();
		$controller->addOutput('Starting DB initialization and migration...');

		$db = Billrun_Factory::db();

		(new DbinitModel())->execute($db, $controller);
		$controller->addOutput('DB initialization done.');

		(new DbmigrateModel())->execute($db, $controller);
	}

}
