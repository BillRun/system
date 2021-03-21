<?php

/**
 * @package			ASN
 * @copyright		Copyright (C) 2012 BillRun Technologies Ltd. All rights reserved.
 * @license			GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * This class holds a mapping to all the types there is in ASN specifications to classes.
 *
 * @package  ASN
 * @since    0.5
 */
class Asn_Types {

	public static $TYPES = array(
		0x0	=> 'Asn_Type_Eoc',
		0x1 => 'Asn_Type_Boolean',
		0x2 => 'Asn_Type_Integer',
		0x3 => 'Asn_Type_BitStr',
		0x4 => 'Asn_Type_OctetStr',
		0x5 => 'Asn_Type_Null',
		0x6 => 'Asn_Type_ObjectID',
		0x7 => 'Asn_Type_ObjectDesc',
		0x8 => 'Asn_Type_External',
		0x9 => 'Asn_Type_Real',
		0xa => 'Asn_Type_Enumerated',
		0xb => 'Asn_Type_EmbeddedPdv',
		0xc => 'Asn_Type_UtfStr',
		0xd => 'Asn_Type_RelativeOID',
		0x10 => 'Asn_Type_Sequence',
		0x11 => 'Asn_Type_Set',
		0x12 => 'Asn_Type_NumStr',
		0x13 => 'Asn_Type_PrintStr',
		0x14 => 'Asn_Type_T61Str',
		0x15 => 'Asn_Type_VidTexStr',
		0x16 => 'Asn_Type_IA5Str',
		0x17 => 'Asn_Type_UtcTime',
		0x18 => 'Asn_Type_GeneralTime',
		0x19 => 'Asn_Type_GraphicStr',
		0x1a => 'Asn_Type_VisibleStr',
		0x1b => 'Asn_Type_GeneralStr',
		0x1c => 'Asn_Type_UniversalStr',
		0x1d => 'Asn_Type_CharStr',
		0x1e => 'Asn_Type_BmpStr',
	);

}
