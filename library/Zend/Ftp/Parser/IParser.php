<?php



/**
  Defines  an Ftp Praser interface
 * @author eran
 */
interface Zend_Ftp_Parser_IParser {
	/**
	 * Extract the file data for a directory listing of the file
	 * @param type $fileDirListing a string  that was retrived from the remote host when doing ftp_rawlist on a ceatain directory
	 * @return Array An array conatining the parsed file data or false if pasrsing has failed.
	 */
	public function parseFileDirectoryListing($fileDirListing);
}

?>
