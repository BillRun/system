<?php

class Zend_Ftp_Directory_Iterator implements Zend_Ftp_Directory_IIterator {

	/**
	 * The directory
	 * 
	 * @var string
	 */
	protected $_dir = null;

	/**
	 * The converted files and folders
	 * 
	 * @var array
	 */
	protected $_rows = array();

	/**
	 * The raw files and folders
	 * 
	 * @var array
	 */
	protected $_data = array();

	/**
	 * The FTP connection
	 * 
	 * @var Zend_Ftp
	 */
	protected $_ftp = null;

	/**
	 * The number of rows
	 * 
	 * @var int
	 */
	protected $_count = 0;

	/**
	 * The iterator pointer
	 * 
	 * @var int
	 */
	protected $_pointer = 0;

	/**
	 * Instantiate
	 * 
	 * @param string $dir The full path
	 * @param Zend_Ftp $ftp The FTP connection
	 */
	public function __construct($dir, $ftp) {
		$this->_dir = $dir;
		$this->_ftp = $ftp;
		$lines = @ftp_rawlist($this->_ftp->getConnection(), $this->_dir->path);

		if (!is_array($lines)) {
			return false;
		}
		$this->processDirectoryData($lines);
	}

	
	/**
	 * Rewind the pointer, required by Iterator
	 * 
	 * @return Zend_Ftp_Directory_Iterator
	 */
	public function rewind() {
		$this->_pointer = 0;

		return $this;
	}

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
					$this->_rows[$this->_pointer] = new Zend_Ftp_Directory($this->_dir->path . $row['name'] . '/', $this->_ftp);
					break;
				case '-': // File
					$this->_rows[$this->_pointer] = new Zend_Ftp_File($this->_dir->path . $row['name'], $this->_ftp, $row);
					break;
				case 'l': // Symlink
				default:
			}
		}

		return $this->_rows[$this->_pointer];
	}

	/**
	 * Return the key of the current row, required by iterator
	 * 
	 * @return int
	 */
	public function key() {
		return $this->_pointer;
	}

	/**
	 * Continue the pointer to the next row, required by iterator
	 */
	public function next() {
		++$this->_pointer;
	}

	/**
	 * Whether or not there is another row, required by iterator
	 * 
	 * @return boolean
	 */
	public function valid() {
		return $this->_pointer < $this->_count;
	}

	/**
	 * Return the number of rows, required by countable
	 * 
	 * @return int
	 */
	public function count() {
		return $this->_count;
	}

	/**
	 * Seek to the given position, required by seekable
	 * 
	 * @param int $position
	 * @return Zend_Ftp_Directory_Iterator
	 */
	public function seek($position) {
		$position = (int) $position;
		if ($position < 0 || $position >= $this->_count) {
			throw new Zend_Exception('Illegal index ' . $position);
		}
		$this->_pointer = $position;

		return $this;
	}

	/**
	 * Whether or not the offset exists, required by seekable
	 * 
	 * @param int $offset
	 * @return boolean
	 */
	public function offsetExists($offset) {
		return isset($this->_data[(int) $offset]);
	}

	/**
	 * Get the item at the given offset, required by seekable
	 * 
	 * @param int $offset
	 * @return Zend_Ftp_Directory|Zend_Ftp_File|null
	 */
	public function offsetGet($offset) {
		$this->_pointer = (int) $offset;

		return $this->current();
	}

	/**
	 * Set the item at the given offset (ignored), required by seekable
	 * 
	 * @param int $offset
	 * @param mixed $value
	 */
	public function offsetSet($offset, $value) {
		
	}

	/**
	 * Unset the item at the given offset (ignored), required by seekable
	 * 
	 * @param int $offset
	 */
	public function offsetUnset($offset) {
		
	}

	/**
	 * Get a given row, required by seekable
	 * 
	 * @param int $position
	 * @param boolean $seek [optional]
	 * @return Zend_Ftp_Directory|Zend_Ftp_File|null
	 */
	public function getRow($position, $seek = false) {
		$key = $this->key();
		try {
			$this->seek($position);
			$row = $this->current();
		} catch (Zend_Exception $e) {
			throw new Zend_Exception('No row could be found at position ' . (int) $position);
		}
		if ($seek == false) {
			$this->seek($key);
		}

		return $row;
	}

	/**
	 * process the data returned from a directory content listing.
	 * @param type $lines the lines  that  were returned from the directoryt file listing
	 */
	protected function processDirectoryData($lines) {
		
		$parser = Zend_Ftp_Factory::getParser($this->_ftp->getSysType());
		foreach ($lines as $line) {
			$fileData = $parser->parseFileDirectoryListing($line);	
			if (!empty($fileData) && $fileData['type'] != 'l') {
				$this->_data[] = $fileData;
			}		
		}

		$this->_count = count($this->_data);
	}
}
