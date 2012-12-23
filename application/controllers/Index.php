<?php

/**
 * @package			Billing
 * @copyright		Copyright (C) 2012 S.D.O.C. LTD. All rights reserved.
 * @license			GNU General Public License version 2 or later; see LICENSE.txt
 */

/**
 * Billing index controller class
 *
 * @package  Controller
 * @since    1.0
 */
class IndexController extends Yaf_Controller_Abstract {

	public function indexAction() {
		$path = __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'workspace';
		$options = array(
			'type' => 'files',
			'workspace' => $path,
		);

		$receiver = receiver::getInstance($options);
		print_R($receiver);die;
	}

}
