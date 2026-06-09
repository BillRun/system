<?php


class Mongodloid_Binary implements Mongodloid_TypeInterface{
	
	
	/**
     * @var $bin
     */
    public $bin;

    /**
     * @var $type
     */
    public $type;

    /**
     * Creates a new binary data object.
     *
     * @param string $data Binary data
     * @param int $type Data type
     */
    public function __construct($data, $type = 2)
    {
        if ($data instanceof MongoDB\BSON\Binary) {
            $this->bin = $data->getData();
            $this->type = $data->getType();
        } else {
            $this->bin = $data;
            $this->type = $type;
        }
    }
	
	/**
     * Converts this MongoBinData to the new BSON Binary type
     *
     * @return Binary
     * @internal This method is not part of the ext-mongo API
     */
    public function toBSONType()
    {
        return new MongoDB\BSON\Binary($this->bin, $this->type);
    }
}
