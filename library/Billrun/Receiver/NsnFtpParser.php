<?php


/**
 * @TODO change  placce in the directory structure Billrun/Receiver/ isn't the correct place!
 * Parse out from an Nsn system.
 *
 * @author eran
 */
class Billrun_Receiver_NsnFtpParser implements Zend_Ftp_Parser_IParser {
	
	/**
	 * extract the file data for a directory listing of the file
	 * @param type $fileDirListing a string  that was retrived from the remote host when doing ftp_rawlist on a ceatain directory
	 * @return Array An array conatining the parsed file data.
	 */
	public function parseFileDirectoryListing($fileDirListing) {
		$matches=array();		
		if( preg_match('/^([\dF]+\.[\dF]+\:[\dF]+\s+[\dF]+\.[\dF]+\.[\dF]+)\s+([\dF]+\.[\dF]+\:[\dF]+\s+[\dF]+\.[\dF]+\.[\dF]+)\s+(\d+)\s+([\w\.]+)$/', $fileDirListing, $matches)) {
			list($date, $unknown, $unknown1, $bytes,  $name) = $matches;

			return array(	
							'date' => $date,
							'bytes' => intval($bytes,10),
							'name' => $name,
							'permissions' => "rwxrwxrwx" ,
							'type' => "-",
							'owner' =>  0 ,
							'group' => 0,
						);
		}
	}
}

?>
