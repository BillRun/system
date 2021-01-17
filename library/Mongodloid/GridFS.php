<?php


class Mongodloid_GridFS extends Mongodloid_Collection{
	
    /**
     * @var $chunks MongoDB\Collection
     */
    public $chunks;

    /**
     * @var $filesName string
     */
    protected $filesName;

    /**
     * @var $chunksName string
     */
    protected $chunksName;

    private $prefix;
	
	private $defaultChunkSize = 261120;
	
	public function __construct(Mongodloid_Db $db, $prefix = "fs")
    {
        if (empty($prefix)) {
            throw new \Exception('Mongodloid_GridFS::__construct(): invalid prefix');
        }

 
        $this->prefix = (string) $prefix;
        $this->filesName = $prefix . '.files';
        $this->chunksName = $prefix . '.chunks';

        $this->chunks = $db->getDB()->selectCollection($this->chunksName);
        parent::__construct($db->getDB()->selectCollection($this->filesName), $db);
    }
	
	/**
     * Drops the files and chunks collections
     * @return array The database response
     */
    public function drop()
    {
        $this->chunks->drop();
        return parent::drop();
    }
	
	/**
     * Stores a file in the database
     *
     * @param string $filename The name of the file
     * @param array $extra Other metadata to add to the file saved
     * @param array $options Options for the store. "safe": Check that this store succeeded
     * @return mixed Returns the _id of the saved object
     * @throws Mongodloid_Exception
     * @throws Exception
     */
    public function storeFile($filename, array $extra = [], array $options = [])
    {
        $this->createChunksIndex();

        $record = $extra;
        if (is_string($filename)) {
            $record += [
                'md5' => md5_file($filename),
                'length' => filesize($filename),
                'filename' => $filename,
            ];

            $handle = fopen($filename, 'r');
            if (! $handle) {
                throw new Mongodloid_Exception('could not open file: ' . $filename);
            }
        } elseif (! is_resource($filename)) {
            throw new Exception('first argument must be a string or stream resource');
        } else {
            $handle = $filename;
        }

        $md5 = null;
        try {
            $file = $this->insertFile($record, $options);
        } catch (Mongodloid_Exception $e) {
            throw new Mongodloid_Exception('Could not store file: ' . $e->getMessage(), $e->getCode(), $e);
        }

        try {
            $length = $this->insertChunksFromFile($handle, $file, $md5);
        } catch (Mongodloid_Exception $e) {
            $this->delete($file['_id']);
            throw new Mongodloid_Exception('Could not store file: ' . $e->getMessage(), $e->getCode(), $e);
        }


        // Add length and MD5 if they were not present before
        $update = [];
        if (! isset($record['length'])) {
            $update['length'] = $length;
        }
        if (! isset($record['md5'])) {
            try {
                $update['md5'] = $md5;
            } catch (Mongodloid_Exception $e) {
                throw new Mongodloid_Exception('Could not store file: ' . $e->getMessage(), $e->getCode(), $e);
            }
        }

        if (count($update)) {
            try {
                $result = $this->update(['_id' => $file['_id']], ['$set' => $update]);
                if (! $this->isOKResult($result)) {
                    throw new Mongodloid_Exception('Could not store file');
                }
            } catch (MongoException $e) {
                $this->delete($file['_id']);
                throw new Mongodloid_Exception('Could not store file: ' . $e->getMessage(), $e->getCode(), $e);
            }
        }

        return $file['_id'];
    }

    /**
     * Saves an uploaded file directly from a POST to the database
     *
     * @param string $name The name attribute of the uploaded file, from <input type="file" name="something"/>.
     * @param array $metadata An array of extra fields for the uploaded file.
     * @return mixed Returns the _id of the uploaded file.
     * @throws Mongodloid_Exception
     */
    public function storeUpload($name, array $metadata = [])
    {
        if (! isset($_FILES[$name]) || $_FILES[$name]['error'] !== UPLOAD_ERR_OK) {
            throw new Mongodloid_Exception("Could not find uploaded file $name");
        }
        if (! isset($_FILES[$name]['tmp_name'])) {
            throw new Mongodloid_Exception("Couldn't find tmp_name in the \$_FILES array. Are you sure the upload worked?");
        }

        $uploadedFile = $_FILES[$name];
        $uploadedFile['tmp_name'] = (array) $uploadedFile['tmp_name'];
        $uploadedFile['name'] = (array) $uploadedFile['name'];

        if (count($uploadedFile['tmp_name']) > 1) {
            foreach ($uploadedFile['tmp_name'] as $key => $file) {
                $metadata['filename'] = $uploadedFile['name'][$key];
                $this->storeFile($file, $metadata);
            }

            return null;
        } else {
            $metadata += ['filename' => array_pop($uploadedFile['name'])];
            return $this->storeFile(array_pop($uploadedFile['tmp_name']), $metadata);
        }
    }
	
	/**
     * Creates the index on the chunks collection
     */
    private function createChunksIndex()
    {
        try {
            $this->chunks->createIndex(['files_id' => 1, 'n' => 1], ['unique' => true]);
        } catch (Mongodloid_Exception $e) {
        }
    }
	
	/**
     * Writes a file record to the database
     *
     * @param $record
     * @param array $options
     * @return array
     */
    private function insertFile($record, array $options = [])
    {
        $record += [
            '_id' => new Mongodloid_Id(),
            'uploadDate' => new Mongodloid_Date(),
            'chunkSize' => $this->defaultChunkSize,
        ];

        $result = $this->insert($record, $options);

        if (! $this->isOKResult($result)) {
            throw new Mongodloid_Exception('error inserting file');
        }

        return $record;
    }

    private function isOKResult($result)
    {
        return (is_array($result) && $result['ok'] == 1.0) ||
               (is_bool($result) && $result);
    }
	
	/**
     * Delete a file from the database
     *
     * @param mixed $id _id of the file to remove
     * @return boolean Returns true if the remove was successfully sent to the database.
     */
    public function delete($id)
    {
        $this->createChunksIndex();

        $this->chunks->remove(['files_id' => $id], ['justOne' => false]);
        return parent::remove(['_id' => $id]);
    }
	
	/**
     * Reads chunks from a file and writes them to the database
     *
     * @param resource $handle
     * @param array $record
     * @param string $md5
     * @return int Returns the number of bytes written to the database
     */
    private function insertChunksFromFile($handle, $record, &$md5)
    {
        $written = 0;
        $offset = 0;
        $i = 0;

        $fileId = $record['_id'];
        $chunkSize = $record['chunkSize'];

        $hash = hash_init('md5');

        rewind($handle);
        while (! feof($handle)) {
            $data = stream_get_contents($handle, $chunkSize);
            hash_update($hash, $data);
            $this->insertChunk($fileId, $data, $i++);
            $written += strlen($data);
            $offset += $chunkSize;
        }

        $md5 = hash_final($hash);

        return $written;
    }
	
	/**
     * Inserts a single chunk into the database
     *
     * @param mixed $fileId
     * @param string $data
     * @param int $chunkNumber
     * @return array|bool
     */
    private function insertChunk($fileId, $data, $chunkNumber)
    {
        $chunk = [
            'files_id' => $fileId,
            'n' => $chunkNumber,
            'data' => new Mongodloid_Binary($data),
        ];
		$chunk = Mongodloid_TypeConverter::fromMongodloid($chunk);
        $result = $this->chunks->insertOne($chunk);

        if (! $this->isOKResult($result)) {
            throw new \MongoException('error inserting chunk');
        }

        return $result;
    }
	
	public function getChunks($file)
    {
		$file = Mongodloid_TypeConverter::fromMongodloid($file);
        return Mongodloid_Result::getResult($this->chunks->find(
            ['files_id' => $file['_id']],
            ['data' => 1],
            ['n' => 1])
        );
    }
	

    public function findOne($query, $want_array = false)
    {
        if (! is_array($query)) {
            $query = ['filename' => (string) $query];
        }

        $items = iterator_to_array($this->find($query)->limit(1));
        return count($items) ? new Mongodloid_GridFSFile($this, current($items)) : null;
    }

}
