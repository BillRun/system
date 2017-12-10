<?php

/**
 * @package			ASN
 * @copyright		Copyright (C) 2012 BillRun Technologies Ltd. All rights reserved.
 * @license			GNU Affero General Public License Version 3; see LICENSE.txt
 */
class Asn_Type_ObjectID extends Asn_Object {

	/**
	 * Parse an ASN.1 OID value.
	 *
	 * This takes the raw binary string that represents an OID value and parses it into its
	 * dot notation form.  example - 1.2.840.113549.1.1.5
	 * look up OID's here: http://www.oid-info.com/
	 * (the multi-byte OID section can be done in a more efficient way, I will fix it later)
	 *
	 * @param	string	$data		The raw binary data string
	 */
	protected function parse($string) {
		return parent::parse($string);
		/* 	$ret = floor(ord($string[0])/40).".";
		  $ret .= (ord($string[0]) % 40);
		  $build = array();
		  $cs = 0;

		  for ($i=1; $i<strlen($string); $i++){
		  $v = ord($string[$i]);
		  if ($v>127){
		  $build[] = ord($string[$i])-Asn_Markers::ASN_BIT;
		  } elseif ($build){
		  // do the build here for multibyte values
		  $build[] = ord($string[$i])-Asn_Markers::ASN_BIT;
		  // you know, it seems there should be a better way to do this...
		  $build = array_reverse($build);
		  $num = 0;
		  for ($x=0; $x<count($build); $x++){
		  $mult = $x==0?1:pow(256, $x);
		  if ($x+1==count($build)){
		  $value = ((($build[$x] & (Asn_Markers::ASN_BIT-1)) >> $x)) * $mult;
		  } else {
		  $value = ((($build[$x] & (Asn_Markers::ASN_BIT-1)) >> $x) ^ ($build[$x+1] << (7 - $x) & 255)) * $mult;
		  }
		  $num += $value;
		  }
		  $ret .= ".".$num;
		  $build = array(); // start over
		  } else {
		  $ret .= ".".$v;
		  $build = array();
		  }
		  }
		  $this->parsedData = $ret;
		 */
	}

}
