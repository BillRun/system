<?php


/**
 * Nsn FTP directory listing class.
 * downloads files that are used to sort the files in the directory by thier states.
 */
class Zend_Ftp_Directory_Nsn extends Zend_Ftp_Directory  implements Zend_Ftp_Directory_IDirectory {

	 const OUTGOING_RECORD_SIZE = 7;
	const INCOMING_RECORD_SIZE = 9;
	
	const FILE_STATE_OPEN = 0;	
	const FILE_STATE_FULL = 1;
	const FILE_STATE_TRANSFERED = 2;
	const FILE_STATE_WAITING = 3;
	const FILE_STATE_COMPRESSING = 4;
	const FILE_STATE_UNUSEABLE = 5;
	
	/**
	 * the binary processed file timespamp (outgoing files) file name.
	 * @var string 
	 */
	protected $outgoingFilename = "TTTCOF00.IMG";
	
	/**
	 * the binary file state (incoming files) file name.
	 * @var string
	 */
	protected $incomingFilename = "TTSCOF00.IMG";
	
	/**
	 * the parsed file states of the corrent directory.
	 * @var array
	 */
	protected $filesState = array();
	
	/**
	 * the binaryy file state file handle
	 * @var resource.
	 */
	protected $incomingFile = null;
	
	public function __construct($path , $ftp) {	
		parent::__construct($path, $ftp);

		$this->incomingFile = $this->getRemoteFile( $this->_path . DIRECTORY_SEPARATOR .$this->incomingFilename );				
		$this->filesState = $this->parseFile( $this->incomingFile, array(
									'state'=>array('decimal'=>1),
									'time' => array('bcd_time'=> 7),
									'storing_state'=>array('decimal'=> 1)
							),  static::INCOMING_RECORD_SIZE );		
	}
	
	/**
	 * Close the processed files list on destruct.
	 */
	public function __destruct() {
		fclose($this->incomingFile);		
	}

	/**
	 * Mark a file as processed.
	 * @param Zend_Ftp_File_NsnCDRFile $processedFile the file to mark as processed.
	 */
	public function markProcessed($processedFile) {
		$this->filesState[$processedFile['id']]['state'] = static::FILE_STATE_FULL;
		$this->filesState[$processedFile['id']]['time'] = time();
		$this->filesState[$processedFile['id']]['updated'] = true;		
		$this->updateFilesState($this->filesState);
	}
	
	/**
	 * Update the NSN ftp server files states.
	 * @param type $filesToUpdate An array of updated files states.
	 * @throws Exception 
	 */
	public function updateFilesState($filesToUpdate) {
		$processedPath =  $this->_path . DIRECTORY_SEPARATOR . $this->outgoingFilename;
		$outgoingFile = $this->getRemoteFile( $processedPath );
		
		//do the updating : seek to the file record and update its timestamp.
		foreach ( $filesToUpdate as $id => $value ) {
			if( $id > 0 && $value['state'] == static::FILE_STATE_FULL &&  isset($value['updated']) && $value['updated'] == true) {				
				fseek( $outgoingFile , $id * static::OUTGOING_RECORD_SIZE );
				$strTime = date('siHdmy', $value['time']).substr(date('Y',$value['time']),0,2);
				fwrite( $outgoingFile, pack('H*', $strTime ), static::OUTGOING_RECORD_SIZE);			
			}
		}		
		
		fflush( $outgoingFile );
		fseek($outgoingFile, 0);
		//upload the updated file to the server.
		if (!ftp_fput( $this->_ftp->getConnection(), $processedPath, $outgoingFile, FTP_BINARY ) ) {
			throw new Exception('couldnt upload ' . static::OUTGOING_FILE_NAME . ' File from NSN server');
		}
		
		fclose($outgoingFile);
		
	}
	
	/**
	 * Parse a binary file.
	 * @param type $fileHandle the file to parse.
	 * @param array $recDesc an array that descibe how to decode the binary file.
	 * * @param array $recDesc an array that descibe how to decode the binary file.
	 */
	protected function parseFile($fileHandle, $recDesc, $recordLengh) {
		$filesState = array();		
		fseek($fileHandle, 0);

		while($record = fread($fileHandle, $recordLengh )) {
			$parsedRec = array();
			foreach ($recDesc as $key => $value) {
				$type = key($value);
				$length = $value[key($value)];
				$parsedRec[$key] = $this->parseField( $record, $type, $length );
				$record = substr( $record, $length );
			}
			
			$filesState[] = $parsedRec;
		}

		return $filesState;
	}
	
	/**
	 * Parse a field from raw data based on a field description
	 * @param string $data the raw data to be parsed.
	 * @param array $type the field type
	 * @param the field length (in bytes).
	 * @return mixed the parsed value from the field.
	 */
	protected function parseField($data, $type, $length) {
		$retValue = '';		
		switch($type) {
			case 'decimal' :
					$retValue = 0;
					for($i=$length-1; $i >= 0 ; --$i) {
						$retValue = ord($data[$i]) + ($retValue << 8);
					}
				break;												
			case 'bcd_time' :
			case 'bcd_encode' :
					$retValue = '';
					for($i=$length-1; $i >= 0 ;--$i) {
						$byteVal = ord($data[$i]);
						$retValue .=  ((($byteVal >> 4) < 10) ? ($byteVal >> 4) : '' ) . ((($byteVal & 0xF) < 10) ? ($byteVal & 0xF) : '') ;
					}
					if($type == 'bcd_time') {
						$retValue = strtotime($retValue);
					}
					break;						
		}

		return $retValue;		
	}
	
	/**
	 * Get the contents of the current directory
	 * (Overriden)
	 * @return Zend_Ftp_Iterator
	 */
	public function getContents() {
		if ($this->_contents === null) {
			$this->_changeToDir();
			$this->_contents = new Zend_Ftp_Directory_NsnUnprocessedIterator($this, $this->_ftp);
		}

		return $this->_contents;
	}
	
	/**
	 * General get values handler.
	 * @param type $name the name of the property to get.
	 */
	public function __get($name) {
		if(in_array($name, array('filesState'))) {
			return $this->{$name};
		}
		return null;
	}
	
	/**
	 * Retrive a remote file to a temporary file.
	 * @param type $path the path of the file to retirve.
	 * @return resource  the temporry file instance of the downloaded file.
	 * @throws Exception throws an exception if there was a problem to download the file.
	 */
	protected function getRemoteFile($path) {
		$tmpFile = tmpfile();
		//Get the updated file from the server.
		if (!ftp_fget( $this->_ftp->getConnection(), $tmpFile, $path, FTP_BINARY ) ) {
			throw new Exception('couldnt download ' . $path . ' File from NSN server');
		}
		return $tmpFile;
	}
	
}
