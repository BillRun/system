<?php


/**
 * Interface for Zend_Ftp_Directory class.
 */
interface Zend_Ftp_Directory_IDirectory {
	
	/**
	 * Get the contents of the current directory
	 * 
	 * @return Zend_Ftp_Iterator
	 */
	public function getContents();

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
	 * Create a directory with optional recursion
	 * 
	 * @param string|array $path The directory to create
	 * @param boolean $recursive [optional] Create all directories in the path
	 * @param string|int $permissions [optional] The permissions to set, can be a string e.g. 'rwxrwxrwx' or octal e.g. 0777
	 * @return Zend_Ftp_Directory
	 */
	public function makeDirectory($path, $recursive = false, $permissions = null);

	/**
	 * Create the directory
	 * 
	 * @return Zend_Ftp_Directory
	 */
	public function create($permissions = null) ;

	/**
	 * Upload a local file to the current directory
	 * 
	 * @param string $localFilepath The full path and filename to upload
	 * @param int $mode [optional] The transfer mode
	 * @param string $remoteFilename [optional] Filename to save to on the server
	 * @return Zend_Ftp_File
	 */
	public function put($localFilepath, $mode = null, $remoteFilename = null);

	/**
	 * Get a file within the current directory
	 * 
	 * @param string $filename The file to get
	 * @return Zend_Ftp_File
	 */
	public function getFile($filename);

	/**
	 * Get a folder within the current directory
	 * 
	 * @param string $filename The directory to get
	 * @return Zend_Ftp_Directory
	 */
	public function getDirectory($filename);

	/**
	 * Whether or not the directory exists
	 * 
	 * @return boolean
	 */
	public function exists();

	/**
	 * Delete the directory
	 * 
	 * @param boolean $recursive [optional] Recursively delete contents
	 * @return Zend_Ftp_Directory
	 */
	public function delete($recursive = false);

	/**
	 * Deletes the contents of the directory
	 * 
	 * @param boolean $recursive [optional] Recursively delete contents
	 * @return Zend_Ftp_Directory
	 */
	public function deleteContents($recursive = false);

	/**
	 * Rename the directory
	 * 
	 * @param string $filename The new name
	 * @return Zend_Ftp_Directory
	 */
	public function rename($filename);

	/**
	 * Copy the directory
	 * 
	 * @param string $filename The new name
	 * @return Zend_Ftp_Directory
	 */
	public function copy($filename);

	/**
	 * Move the directory
	 * 
	 * @param string $filename The new name
	 * @return Zend_Ftp_Directory
	 */
	public function move($filename);

	/**
	 * Change the directory permissions
	 * 
	 * @param int|string $permissions The permissions
	 * @return Zend_Ftp_Directory
	 */
	public function chmod($permissions);

	/**
	 * Save the directory to the given path
	 * 
	 * @param boolean $recursive [optional] Save the contents recursively
	 * @return Zend_Ftp_Directory
	 */
	public function saveToPath($recursive = false);

	/**
	 * Save the directory contents to the given path
	 * 
	 * @param boolean $recursive [optional] Save the contents recursively
	 * @return Zend_Ftp_Directory
	 */
	public function saveContentsToPath($recursive = false);
}

