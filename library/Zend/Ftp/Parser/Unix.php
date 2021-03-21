<?php

/**
 * Parse output form a standard unix ftp system
 *
 * @author eran
 */
class Zend_Ftp_Parser_Unix implements Zend_Ftp_Parser_IParser {

	/**
	 * Extract the file data for a directory listing of the file
	 * @param type $fileDirListing a string  that was retrived from the remote host when doing ftp_rawlist on a ceatain directory
	 * @return Array An array conatining the parsed file data or false if pasrsing has failed.
	 */
	public function parseFileDirectoryListing($fileDirListing) {
		$matches = array();
		if (preg_match('/^([\-dl])([rwx\-]+)\s+(\d+)\s+([\w\-]+)\s+(\w+)\s+(\d+)\s+(\w+\s+\d+\s+[\d\:]+)\s+(.*)$/', $fileDirListing, $matches)) {
			list($trash, $type, $permissions, $unknown, $owner, $group, $bytes, $date, $name) = $matches;
			$time_guess = date_create_from_format("M d Y", $date) ? date_create_from_format("M d Y", $date)->format("U") : false;
			if(!$time_guess) {
			$time_guess = date_create_from_format("YM d H:i", date('Y') . $date)->format('U');
			if ($time_guess > time()) {
				$time_guess = (new DateTime())->setTimestamp($time_guess)->sub(new DateInterval('P01Y'))->format('U'); // subtract 1 year
				}
			}
			return array(
				'date' => $time_guess,
				'bytes' => intval($bytes, 10),
				'name' => $name,
				'permissions' => $permissions,
				'type' => $type,
				'owner' => $owner,
				'group' => $group,
			);
		}
		Billrun_Factory::log()->log($fileDirListing . " is an illegal description of a file", Zend_Log::ALERT);
		return FALSE;
	}

}

?>
