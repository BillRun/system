<?php

/**
 * @package			Billing
 * @copyright		Copyright (C) 2012 S.D.O.C. LTD. All rights reserved.
 * @license			GNU General Public License version 2 or later; see LICENSE.txt
 */

/**
 * Billing bootstrap class
 *
 * @package  Bootstrap
 * @since    1.0
 */
class Bootstrap extends Yaf_Bootstrap_Abstract {
        public function _initPlugin(Yaf_Dispatcher $dispatcher) {
            /* register a billrun plugin system from config */
			$config = Yaf_Application::app()->getConfig();
			$plugins = $config->plugins->toArray();
			foreach ($plugins as $plugin) {
				$dispatcher->registerPlugin(new $plugin);
			}
        }

}
