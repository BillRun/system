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
		$matches=array();
		if( preg_match('/^([\-dl])([rwx\-]+)\s+(\d+)\s+(\w+)\s+(\w+)\s+(\d+)\s+(\w+\s+\d+\s+[\d\:]+)\s+(.*)$/', $fileDirListing, $matches) ) {
			list($trash, $type, $permissions, $unknown, $owner, $group, $bytes, $date, $name) = $matches;
			return array(	
							'date' => $date,
							'bytes' => intval($bytes,10),
							'name' => $name,
							'permissions' =>  $permissions ,
							'type' =>  $type ,
							'owner' => $owner ,
							'group' =>  $group,
						);
		}
		return FALSE;
	}
}

?>
