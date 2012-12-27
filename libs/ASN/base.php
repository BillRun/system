<?php

// ASN.1 parsing library
// Attribution: http://www.krisbailey.com
// license: unknown
// modified: Mike Macgrivin hide@address.com 6-oct-2010 to support Salmon auto-discovery
// from openssl public keys


class ASN_BASE {
	public $asnData = null;
	public $parsedData = null;
	public $dataLength = 0;

	public static $ASN_TYPES = array(
		0x0	=> 'ASN_GENERAL',
		0x1	=> 'ASN_BOOLEAN',
		0x2	=> 'ASN_INTEGER',
		0x3	=> 'ASN_BIT_STR',
		0x4	=> 'ASN_OCTET_STR',
		0x5	=> 'ASN_NULL',
		0x6	=> 'ASN_OBJECT_ID',
		0x7	=> 'ASN_OBJECT_DESC',
		0x8	=> 'ASN_EXTERNAL',
		0x9	=> 'ASN_REAL',
		0xA	=> 'ASN_ENUMERATED',
		0xB	=> 'ASN_EMBEDDED_PDV',
		0xC	=> 'ASN_UTF_STR',
		0xD	=> 'ASN_RELATIVE_OID',
		0x10	=> 'ASN_SEQUENCE',
		0x11	=> 'ASN_SET',
		0x12	=> 'ASN_NUM_STR',
		0x13	=> 'ASN_PRINT_STR',
		0x14	=> 'ASN_T61_STR',
		0x15	=> 'ASN_VIDTEX_STR',
		0x16	=> 'ASN_IA5_STR',
		0x17	=> 'ASN_UTC_TIME',
		0x18	=> 'ASN_GENERAL_TIME',
		0x19	=> 'ASN_GRAPHIC_STR',
		0x1A	=> 'ASN_VISIBLE_STR',
		0x1B	=> 'ASN_GENERAL_STR',
		0x1C	=> 'ASN_UNIVERSAL_STR',
		0x1D	=> 'ASN_CHAR_STR',
		0x1E	=> 'ASN_BMP_STR',
	);

	function __construct($data = false)
	{
		if (false !== $data) {
			$this->asnData = $data;
		}
	}


	/**
	 * Parse an ASN.1 binary string.
	 *
	 * This function takes a binary ASN.1 string and parses it into it's respective
	 * pieces and returns it.  It can optionally stop at any depth.
	 *
	 * @param	string	$string		The binary ASN.1 String
	 * @param	int	$level		The current parsing depth level
	 * @param	int	$maxLevel	The max parsing depth level
	 * @return	ASN	The array representation of the ASN.1 data contained in $string
	 */
	public static function parseASNString($rawData){
		return new ASN_CONSTRUCT($rawData);
	}


	protected function getData($rawData) {
		$data = $rawData;
		$length = ord($this->shift($data));
		if (($length & ASN_MARKERS::ASN_LONG_LEN) == ASN_MARKERS::ASN_LONG_LEN){
			$tempLength = 0;
			for ($x=($length-ASN_MARKERS::ASN_LONG_LEN); $x > 0; $x--){
				$tempLength = ord($this->shift($data)) + ($tempLength << 8);
			}
			$length = $tempLength;
		}
		return substr($data,0,$length);
	}

	protected function shift(&$data,$from =0, $len=1){
		$shifted = substr($data,$from,$len);
		$data = substr($data,$from+$len);
		return $shifted;
	}



	public static function printASN($x, $indent=''){
		if (is_object($x)) {
			echo $indent.$x->typeName."\n";
			if (ASN_NULL == $x->type) return;
			if (is_array($x->data)) {
				while ($d = $x->value) {
					echo self::printASN($d, $indent.'.  ');
				}
				$x->reset();
			} else {
				echo self::printASN($x->data, $indent.'.  ');
			}
		} elseif (is_array($x)) {
			foreach ($x as $d) {
				echo self::printASN($d, $indent);
			}
		} else {
			if (preg_match('/[^[:print:]]/', $x))	// if we have non-printable characters that would
				$x = base64_encode($x);		// mess up the console, then print the base64 of them...
			echo $indent.$x."\n";
		}
	}

	public static function getDataArray($rootObj) {
		$retArr = array();
		foreach($rootObj->parsedData as $val) {
		    $retArr[] = is_array($val) ? self::getDataArray($val) : $val;
		}
		return $retArr;
	}
}


