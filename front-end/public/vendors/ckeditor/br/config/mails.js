/**
 * @license Copyright (c) 2003-2016, CKSource - Frederico Knabben. All rights reserved.
 * For licensing, see LICENSE.md or http://ckeditor.com/license
 *
 * CONFIG BY:
 * http://ckeditor.com/latest/samples/toolbarconfigurator/index.html#basic
 *
 */

CKEDITOR.editorConfig = function( config ) {
	// Define changes to default configuration here.
	// For complete reference see:
	// http://docs.ckeditor.com/#!/api/CKEDITOR.config

	// The toolbar groups arrangement, optimized for two toolbar rows.
	config.toolbarGroups = [
		{ name: 'basicstyles', groups: [ 'basicstyles', 'cleanup' ] },
		{ name: 'paragraph', groups: [ 'list', 'indent','BidiLtr', 'BidiRtl'] },
		{ name: 'styles', groups: [ 'Format', 'FontSize' ] },
		{ name: 'colors' },
		// { name: 'about' },
		'/',
		{ name: 'others' },
		{ name: 'document',	   groups: [ 'mode'/*, 'document', 'doctools'*/ ] },
		// { name: 'document', items: [ 'Source'] },
		{ name: 'tools', groups: [ 'Maximize' ] }
	];

	// Remove some buttons provided by the standard plugins, which are
	// not needed in the Standard(s) toolbar.
	config.removeButtons = 'Subscript,Superscript,ShowBlocks,Save,NewPage,Preview,Print,Font,Styles';



	// Set the most common block elements.
	config.format_tags = 'p;h1;h2;h3;pre';
  config.extraPlugins = 'placeholder_select,pastebase64,base64image';
	// Simplify the dialog windows.
	config.removeDialogTabs = 'image:advanced;link:advanced';
};
