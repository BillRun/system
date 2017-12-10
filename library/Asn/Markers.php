<?php

/**
 * @package			ASN
 * @copyright		Copyright (C) 2012 BillRun Technologies Ltd. All rights reserved.
 * @license			GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * This class holds the constats that are use in ASN.
 *
 * @package  ASN
 * @since    0.5
 */
class Asn_Markers {

	const ASN_UNIVERSAL = 0x00;
	const ASN_APPLICATION = 0x40;
	const ASN_CONTEXT = 0x80;
	const ASN_PRIVATE = 0xC0;
	const ASN_PRIMITIVE = 0x00;
	const ASN_CONSTRUCTOR = 0x20;
	const ASN_LONG_LEN = 0x80;
	const ASN_EXTENSION_ID = 0x1F;
	const ASN_BIT = 0x80;
	const ASN_EOC = 0x00;	
	const ASN_INDEFINITE_LEN = 0x80;

}
