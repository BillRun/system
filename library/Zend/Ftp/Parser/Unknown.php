<?php


/**
 * Parse output form a standard unkonw ftp system, parse only basic information might return currupt data.
 *
 * @author eran
 */
class Zend_Ftp_Parser_Unknown implements Zend_Ftp_Parser_IParser {
	
	/**
	 * Extract the file data for a directory listing of the file
	 * @param type $fileDirListing a string  that was retrived from the remote host when doing ftp_rawlist on a ceatain directory
	 * @return Array An array conatining the parsed file data or false if pasrsing has failed.
	 */
	public function parseFileDirectoryListing($fileDirListing) {
		$matches=array();
		if( preg_match('/^.*\s+(.*)$/', $fileDirListing, $matches) ) {
			list( $name ) = $matches;
			return array(
							'date' => time(),
							'bytes' => 0,
							'name' => $name,
							'permissions' =>  "rwxrwxrwx" ,
							'type' =>  '-' ,
							'owner' => 0 ,
							'group' =>  0,
						);
		}
		return FALSE;
	}
}

?>
