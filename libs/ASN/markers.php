<?php

class ASN_MARKERS {
	public static const	ASN_UNIVERSAL = 0x00;
	public static const	ASN_APPLICATION = 0x40;
	public static const	ASN_CONTEXT	= 0x80;
	public static const	ASN_PRIVATE	= 0xC0;

	public static const	ASN_PRIMITIVE	= 0x00;
	public static const	ASN_CONSTRUCTOR	= 0x20;

	public static const	ASN_LONG_LEN	= 0x80;
	public static const	ASN_EXTENSION_ID	= 0x1F;
	public static const	ASN_BIT	= 0x80;
	public static const	ASN_EOC	= 0x00;

}