<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Util class for the git repo
 *
 * @package  Util
 * @since    5.2
 */
class Billrun_Git_Util {

	/**
	 * method to return current git branch (if working on git repo)
	 * 
	 * @return mixed string when success else false
	 */
	public static function getGitBranch() {
		if (!file_exists(APPLICATION_PATH . '/.git/HEAD')) {
			return false;
		}
		$HEAD = file_get_contents(APPLICATION_PATH . '/.git/HEAD');
		return rtrim(end(explode('/', $HEAD)));
	}

	/**
	 * method to return last git commit (if working on git repo)
	 * 
	 * @return mixed string when success else false
	 */
	public static function getGitLastCommit() {
		$branch = self::getGitBranch();
		if (empty($branch)) {
			return false;
		}
		if (!file_exists($branchFilePath = APPLICATION_PATH . '/.git/refs/heads/' . $branch)) {
			return false;
		}
		return trim(file_get_contents($branchFilePath), "\n");
	}

}
