<?php

class Mongodloid_GridFSFile {
	/**
     * @var array
     */
    public $file;

    /**
     * @var $gridfs
     */
    protected $gridfs;

    /**
     *
     * @param MongoGridFS $gridfs The parent MongoGridFS instance
     * @param array $file A file from the database
     */
    public function __construct(Mongodloid_GridFS $gridfs, array $file)
    {
        $this->gridfs = $gridfs;
        $this->file = $file;
    }

    /**
     * Returns this file's filename
     * @return string Returns the filename
     */
    public function getFilename()
    {
        return isset($this->file['filename']) ? $this->file['filename'] : null;
    }

    /**
     * Returns this file's size
     * @return int Returns this file's size
     */
    public function getSize()
    {
        return $this->file['length'];
    }

    /**
     * This will load the file into memory. If the file is bigger than your memory, this will cause problems!
     * @return string Returns a string of the bytes in the file
     */
    public function getBytes()
    {
        $result = '';
        foreach ($this->gridfs->getChunks($this->file) as $chunk) {
            $result .= $chunk['data']->bin;
        }

        return $result;
    }
}
