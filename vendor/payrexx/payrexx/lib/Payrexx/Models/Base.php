<?php
/**
 * The Base model class for request and response models.
 * @author    Ueli Kramer <ueli.kramer@comvation.com>
 * @copyright 2014 Payrexx AG
 * @since     v1.0
 */
namespace Payrexx\Models;

/**
 * Class Base
 * @package Payrexx\Models
 */
abstract class Base
{
    /** @var string */
    protected $uuid;

    /** @var int */
    protected $id;

    /**
     * Converts array to response model
     *
     * @param array $data
     *
     * @return $this
     */
    public function fromArray($data)
    {
        foreach ($data as $param => $value) {
            if (!method_exists($this, 'set' . ucfirst($param))) {
                continue;
            }
            $this->{'set' . ucfirst($param)}($value);
        }
        return $this;
    }

    /**
     * Convert object to an associative array
     *
     * @param string $method The API method called
     *
     * @return array
     */
    public function toArray($method)
    {
        $vars = get_object_vars($this);
        $className = explode('\\', get_called_class());
        return $vars + array('model' => end($className));
    }

    /**
     * Returns the corresponding response model object
     *
     * @return \Payrexx\Models\Response\Base
     */
    public abstract function getResponseModel();

    /**
     * @return integer
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @param integer $id
     */
    public function setId($id)
    {
        $this->id = $id;
    }

    /**
     * @return string
     */
    public function getUuid()
    {
        return $this->uuid;
    }

    /**
     * @param string $uuid
     */
    public function setUuid($uuid)
    {
        $this->uuid = $uuid;
    }
}
