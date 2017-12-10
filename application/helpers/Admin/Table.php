<?php

class Admin_Table {

	/**
	 * Converts a value to uppercase or lowercase
	 * @param mixed $value
	 * @param string $ctype either "upper" or "lower"
	 */
	public static function convertValueByCaseType($value, $ctype) {
		switch ($ctype) {
			case "upper":
				$ret = strtoupper($value);
				break;
			case "lower":
				$ret = strtolower($value);
				break;

			default:
				$ret = $value;
				break;
		}
		return $ret;
	}

	public static function translateField($entity, $key) {
		switch ($key) {
			case 'urt':
				$d = new Zend_Date($entity[$key]->sec, null, new Zend_Locale('he_IL'));
				return $d->getIso();
				break;
			case 'arate':
			case 'pzone':
			case 'wcs':
			case 'wcs_in':
				$data = $entity->get($key, false);
				if ($data instanceof Mongodloid_Entity) {
					return $data->get('key');
				}
				break;
			case 'rat_type':
				return self::translateRat_Type($entity[$key]);
			default:
				return $entity[$key];
		}
	}

	public static function setEntityFields(Mongodloid_Entity &$entity) {
		foreach ($entity->getRawData() as $key => $val) {
			$entity[$key] = self::translateField($entity, $key);
		}
	}

	public static function translateRat_Type($number) {
		switch ($number) {
			case 1:
				return 'UTRAN';
				break;
			case 2:
				return 'GERAN';
				break;
			case 3:
				return 'WLAN';
				break;
			case 4:
				return 'GAN';
				break;
			case 5:
				return 'HSPA';
				break;
			default :
				return $number;
				break;
		}
	}

}
