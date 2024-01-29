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
		{ name: 'paragraph',   groups: [ 'list', 'indent', /*'blocks',*/ 'align', 'BidiLtr', 'BidiRtl'] },
		{ name: 'styles' },
		{ name: 'colors' },
		// { name: 'about' },
		'/',
		{ name: 'others' },
		{ name: 'insert', groups: [ 'insert' ] },

		{ name: 'document',	   groups: [ 'mode'/*, 'document', 'doctools'*/ ] },
		// { name: 'document', items: [ 'Source'] },
		{ name: 'tools', groups: [ 'Maximize' ] }
	];

	// Remove some buttons provided by the standard plugins, which are
	// not needed in the Standard(s) toolbar.
	config.removeButtons = 'Subscript,Superscript,ShowBlocks,Save,NewPage,Print,Placeholder,Smiley,PageBreak,Iframe,Flash,Image';



	// Set the most common block elements.
	config.format_tags = 'p;h1;h2;h3;pre';

  config.allowedContent = true;

	// Simplify the dialog windows.
	config.removeDialogTabs = 'image:advanced;link:advanced';
  config.extraPlugins = 'placeholder_select,pastebase64,base64image';
  config.contentsCss = [
    'vendors/ckeditor/br/css/font-awesome.min.css',
    'vendors/ckeditor/br/css/invoice.css',
  ];
  config.entities_latin = false;
};
