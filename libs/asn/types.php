<?php
/**
 * @package			ASN
 * @copyright		Copyright (C) 2012 S.D.O.C. LTD. All rights reserved.
 * @license			GNU General Public License version 2 or later; see LICENSE.txt
 */

/**
 * This class holds a mappingto  all the types there is in ASN specifications to classes.
 *
 * @package  ASN
 * @since    1.0
 */

class ASN_TYPES {
	public static $TYPES = array(
		//0x0	=> 'ASN_TYPE_EOC',
		0x1	=> 'ASN_TYPE_BOOLEAN',
		0x2	=> 'ASN_TYPE_INTEGER',
		0x3	=> 'ASN_TYPE_BITSTR',
		0x4	=> 'ASN_TYPE_OCTETSTR',
		0x5	=> 'ASN_TYPE_NULL',
		0x6	=> 'ASN_TYPE_OBJECTID',
		0x7	=> 'ASN_TYPE_OBJECTDESC',
		0x8	=> 'ASN_TYPE_EXTERNAL',
		0x9	=> 'ASN_TYPE_REAL',
		0xA	=> 'ASN_TYPE_ENUMERATED',
		0xB	=> 'ASN_TYPE_EMBEDDEDPDV',
		0xC	=> 'ASN_TYPE_UTFSTR',
		0xD	=> 'ASN_TYPE_RELATIVEOID',
		0x10	=> 'ASN_TYPE_SEQUENCE',
		0x11	=> 'ASN_TYPE_SET',
		0x12	=> 'ASN_TYPE_NUMSTR',
		0x13	=> 'ASN_TYPE_PRINTSTR',
		0x14	=> 'ASN_TYPE_T61STR',
		0x15	=> 'ASN_TYPE_VIDTEXSTR',
		0x16	=> 'ASN_TYPE_IA5STR',
		0x17	=> 'ASN_TYPE_UTCTIME',
		0x18	=> 'ASN_TYPE_GENERALTIME',
		0x19	=> 'ASN_TYPE_GRAPHICSTR',
		0x1A	=> 'ASN_TYPE_VISIBLESTR',
		0x1B	=> 'ASN_TYPE_GENERALSTR',
		0x1C	=> 'ASN_TYPE_UNIVERSALSTR',
		0x1D	=> 'ASN_TYPE_CHARSTR',
		0x1E	=> 'ASN_TYPE_BMPSTR',
	);

}