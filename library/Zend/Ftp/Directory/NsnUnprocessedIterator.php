<?php

/**
 * Zend Framework
 *
 * LICENSE
 *
 * This source file is subject to the new BSD license that is bundled
 * with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://framework.zend.com/license/new-bsd
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@zend.com so we can send you a copy immediately.
 *
 * @category   Zend
 * @package    Zend
 * @copyright  Copyright (c) 2005-2012 Zend Technologies USA Inc. (http://www.zend.com)
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 * @version    $Id: Exception.php 24593 2012-01-05 20:35:02Z matthew $
 */

/**
 * Iterates through NSN ftp server directory files and get all the unprocessed files.
 */
class Zend_Ftp_Directory_NsnUnprocessedIterator extends Zend_Ftp_Directory_Iterator {

	/**
	 * Get the current row, required by iterator
	 * 
	 * @return Zend_Ftp_Directory|Zend_Ftp_File|null
	 */
	public function current() {
		if ($this->valid() === false) {
			return null;
		}

		if (empty($this->_rows[$this->_pointer])) {
			$row = $this->_data[$this->_pointer];
			switch ($row['type']) {
				case 'd': // Directory
					$this->_rows[$this->_pointer] = new Zend_Ftp_Directory( $this->_dir->path . $row['name'] . '/', $this->_ftp );
					break;
				case '-': // File
					$this->_rows[$this->_pointer] = new Zend_Ftp_File_NsnCDRFile( $this->_dir->path . $row['name'], $this->_ftp, $row , $this->_dir );
					break;
				case 'l': // Symlink
				default:
			}
		}

		return $this->_rows[$this->_pointer];
	}
	
	
	/**
	 * Process the data returned from a directory content listing.
	 * (Overriden)
	 * @param type $lines the lines  that  were returned from the directoryt file listing
	 */
	protected function processDirectoryData($lines) {			
		
		$parser = Zend_Ftp_Factory::getParser($this->_ftp->getSysType());
		
		foreach ($lines as $line) {
			$fileData = $parser->parseFileDirectoryListing($line);	
			$id = intval(substr($fileData['name'], 2,4),10);
			if (isset($this->_dir->filesState[$id]) && $this->_dir->filesState[$id]['state'] == Zend_Ftp_Directory_Nsn::FILE_STATE_FULL) {
				$fileData['id'] = $id;
				$this->_data[] = $fileData;
				
			}		
		}

		$this->_count = count($this->_data);
	}
}

