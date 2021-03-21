<?php


/**
 * This  define  an interface for  Ftp file.
 */
interface Zend_Ftp_File_IFile {
	
	/**
	 * Whether or not this FTP resource is a file
	 * 
	 * @return boolean
	 */
	public function isFile();

	/**
	 * Whether or not this FTP resource is a directory
	 * 
	 * @return boolean
	 */
	public function isDirectory();

	/**
	 * Set the transfer mode for this file, overrides the FTP connection default
	 * 
	 * @param int $mode [optional] The transfer mode
	 * @return Zend_Ftp_File
	 */
	public function setMode($mode = null);

	/**
	 * Save to a local path using the remote file name
	 * 
	 * @param string $path The full path to save to
	 * @param int $mode [optional] The transfer mode
	 * @param int $offset [optional] The offset to start from for resuming
	 * @param boolean $autoRecover [optional] try to auto recover connection if set so (on some unstable connections is usable)
	 * @return Zend_Ftp_File
	 */
	public function saveToPath($path, $mode = null, $offset = 0, $autoRecover = false);

	/**
	 * Save to a local file
	 * 
	 * @param string $file The full path to the local file
	 * @param int $mode [optional] The transfer mode
	 * @param int $offset [optional] The offset to start from for resuming
	 * @param boolean $autoRecover [optional] try to auto recover connection if set so (on some unstable connections is usable)
	 * @return Zend_Ftp_File
	 */
	public function saveToFile($file, $mode = null, $offset = 0, $autoRecover = false);

	/**
	 * Upload a local file
	 * 
	 * @param string $localFilepath The full path to the local file
	 * @param int $mode [optional] The transfer mode
	 * @param int $startPos [optional] The offset to start from for resuming
	 * @return Zend_Ftp_File
	 */
	public function put($localFilepath, $mode = null, $startPos = 0);

	/**
	 * Change the file permissions
	 * 
	 * @param int|string $mode
	 * @return Zend_Ftp_File
	 */
	public function chmod($mode);

	/**
	 * Rename the file
	 * 
	 * @param string $filename The new filename
	 * @return Zend_Ftp_File
	 */
	public function rename($filename);

	/**
	 * Copy the file to another filename or location
	 * 
	 * @param string $filename
	 * @return Zend_Ftp_File
	 */
	public function copy($filename);
	/**
	 * Move the file to another location
	 * 
	 * @param string $path
	 * @return Zend_Ftp_File
	 */
	public function move($path);

	/**
	 * Delete the file
	 * 
	 * @return Zend_Ftp_File
	 */
	public function delete();

	/**
	 * Whether or not the file exists
	 * 
	 * @return boolean
	 */
	public function exists();
}
