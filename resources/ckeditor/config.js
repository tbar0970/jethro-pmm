/*
Copyright (c) 2003-2011, CKSource - Frederico Knabben. All rights reserved.
For licensing, see LICENSE.html or http://ckeditor.com/license
*/

CKEDITOR.editorConfig = function( config )
{
	config.toolbar = 'Mytoolbar';
	config.toolbar_Mytoolbar = [['Format', 'Bold', 'Italic', '-', 'NumberedList', 'BulletedList', 'Table', '-', 'Image','Link', 'Unlink', '-', 'PasteFromWord', 'Source']];
	config.shiftEnterMode = CKEDITOR.ENTER_BR;
	config.enterMode = CKEDITOR.ENTER_P;

};
