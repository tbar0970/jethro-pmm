<?php
/* These are bitmask values where some values include others - eg "edit note" includes "view note" */
$PERM_LEVELS = Array(
1 =>		Array('EDITPERSON',			'Persons & Families - add/edit/group',	''),

2 =>		Array('SENDSMS',			'SMS/Mailchimp - send',							''),

4 =>		Array('RUNREPORT',			'Reports - run reports & view stats',	''),
12 =>		Array('MANAGEREPORTS',		'Reports - save reports',				''),

16 =>		Array('VIEWNOTE',			'Notes - view',							'NOTES'),
48 =>		Array('EDITNOTE',			'Notes - add/edit',						'NOTES'),
112 =>		Array('BULKNOTE',			'Notes - bulk-assign',					'NOTES'),

128 =>		Array('VIEWATTENDANCE',		'Attendance - view and report',			'ATTENDANCE'),
384 =>		Array('EDITATTENDANCE',		'Attendance - record',					'ATTENDANCE'),

512 =>		Array('EDITGROUP',			'Groups - add/edit/delete',				''),
1536 =>		Array('MANAGEGROUPCATS',	'Groups - manage categories',			''),

2048 =>		Array('VIEWROSTER',			'Rosters - view assignments',			'ROSTERS&SERVICES'),
6144 =>		Array('EDITROSTER',			'Rosters - edit assignments',			'ROSTERS&SERVICES'),
14336 =>	Array('MANAGEROSTERS',		'Rosters - manage roles & views',		'ROSTERS&SERVICES'),

16384 =>	Array('VIEWSERVICE',		'Services - view',						'ROSTERS&SERVICES'),
49152 =>	Array('EDITSERVICE',		'Services - edit individual',			'SERVICEDETAILS'),
114688 =>	Array('BULKSERVICE',		'Services - edit service schedule',		'ROSTERS&SERVICES'),
180224 =>	Array('SERVICECOMPS',		'Services - manage component library',  'SERVICEDETAILS'),
/*311296 =>	Array('MANAGESONGS',		'Services - manage song repertoire',	'SERVICEDETAILS'),*/

524288 =>	Array('EDITREC',			'Sermon recordings - manage',			'SERMONRECORDINGS'),

1048576 =>	Array('VIEWDOC',			'Documents & Folders - view',			'DOCUMENTS'),
3145728 =>	Array('EDITDOC',			'Documents & Folders- add/edit/delete',	'DOCUMENTS'),
7340032 =>	Array('SERVICEDOC',			'Service Documents - generate',			'SERVICEDOCUMENTS'),

/* room for some more here... */
2147483647 => Array('SYSADMIN',			'SysAdmin - manage user accounts, congregations etc', ''),
);
