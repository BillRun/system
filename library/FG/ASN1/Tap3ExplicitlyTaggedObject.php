<?php
namespace FG\ASN1;

class Tap3ExplicitlyTaggedObject extends ExplicitlyTaggedObject {
	
	/** @var \FG\ASN1\ExplicitlyTaggedObject[] */
    private $decoratedObjects;
    private $tag;
	
	protected $objects = array();

	/**
     * @param int $tag
     * @param \FG\ASN1\Object $objects,...
     */
    public function __construct($tag, $objects) {
        $this->tag = $tag;
		if (!is_array($objects)) {
			$objects = array($objects);
		}

		$this->decoratedObjects = array();
		foreach ($objects as $object) {
			$obj = $object['object'];
			$type = $object['type'];
			$this->decoratedObjects[] = $obj;
			$isConstructed = $obj instanceof BaseObject;
			$this->objects[] = array(
				'object' => $this->getObjectByType($obj, $type),
				'isConstructed' => $isConstructed,
			);
		}
    }
	
	protected function calculateContentLength() {
        $length = 0;
		foreach ($this->objects as $object) {
			if ($object['isConstructed']) {
				$length += $object['object']->getObjectLength();
			} else {
				$length += $object['object']->calculateContentLength();
			}
        }

        return $length;
    }

    protected function getEncodedValue() {
        $encoded = '';
        foreach ($this->objects as $object) {
			if ($object['isConstructed']) {
				$encoded .= $object['object']->getBinary();
			} else {
				$encoded .= $object['object']->getEncodedValue();
			}
        }

        return $encoded;
    }

    public function getIdentifier() {
		$isConstructed = isset($this->objects[0]) ? $this->objects[0]['isConstructed'] : false;
		$identifier = Identifier::create(Identifier::CLASS_APPLICATION, $isConstructed, $this->tag);

        return is_int($identifier) ? chr($identifier) : $identifier;
    }
	
	public function getObjectByType($object, $type) {
		if ($object instanceof BaseObject) {
			return $object;
		}
		switch (strtolower($type)) {
			case 'integer':
				$object = is_numeric($object) === false ? 0 : intval($object);
				return new Universal\Integer($object);

			case 'octetstring':
				return new Universal\OctetString($object);

			default:
				$object = empty($object) ? ' ' : $object;
				return new Universal\CharacterString($object);
		}
	}
}
