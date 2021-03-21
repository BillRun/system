<?php

/**
 * @TODO change place in the directory structure Billrun/Receiver/ isn't the correct place!
 * Parse out from an Nsn system.
 *
 * @author eran
 */
class Zend_Ftp_Parser_NsnFtpParser implements Zend_Ftp_Parser_IParser {

	/**
	 * extract the file data for a directory listing of the file
	 * @param type $fileDirListing a string  that was retrived from the remote host when doing ftp_rawlist on a ceatain directory
	 * @return Array An array conatining the parsed file data.
	 */
	public function parseFileDirectoryListing($fileDirListing) {
		$matches = array();
		if (preg_match('/^([\dF]+\.[\dF]+\:[\dF]+\s+[\dF]+\.[\dF]+\.[\dF]+)\s+([\dF]+\.[\dF]+\:[\dF]+\s+[\dF]+\.[\dF]+\.[\dF]+)\s+(\d+)\s+([\w\.]+)$/', $fileDirListing, $matches)) {
			list($raw, $date, $unknown, $bytes, $name) = $matches;
			if (preg_match('(\d{2}\.\d{2}\:\d{2}\s\d{2}\.\d{2}\.\d{4})', $date, $time)) {
				$time = date_create_from_format("H.i:s d.m.Y", $time[0])->format('U');
				if ($time<=0) {
					$time = -1;
				}
			} else {
				$time = -1;
			}
			return array(
				'date' => $time,
				'bytes' => intval($bytes, 10),
				'name' => $name,
				'permissions' => "rwxrwxrwx",
				'type' => "-",
				'owner' => 0,
				'group' => 0,
			);
		}
	}

}

?>
