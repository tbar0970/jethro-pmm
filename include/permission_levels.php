<?php
/* These are bitmask values where some values include others - eg "edit note" includes "view note" */
/* WARNING: These numbers are referenced in the 2.28 upgrade script and installer */

$PERM_LEVELS = Array(
1 =>		Array('EDITPERSON',			'Persons & Families - add/edit',	''),

2 =>		Array('SENDSMS',			'SMS/Mailchimp - send',							''),

4 =>		Array('RUNREPORT',			'Reports - run reports & view stats',	''),
12 =>		Array('MANAGEREPORTS',		'Reports - save shared reports',''),

16 =>		Array('VIEWMYNOTES',		'Notes - view&edit notes assigned to me','NOTES'),
48 =>		Array('VIEWNOTE',			'Notes - view all',						'NOTES'),
112 =>		Array('EDITNOTE',			'Notes - add/edit all',					'NOTES'),
240 =>		Array('BULKNOTE',			'Notes - bulk-assign',					'NOTES'),

256 =>		Array('VIEWATTENDANCE',		'Attendance - view and report',			'ATTENDANCE'),
768 =>		Array('EDITATTENDANCE',		'Attendance - record',					'ATTENDANCE'),

1024 =>		Array('EDITGROUP',			'Groups - add/edit/delete',				''),
3072 =>		Array('MANAGEGROUPCATS',	'Groups - manage categories',			''),

4096 =>		Array('VIEWROSTER',			'Rosters - view assignments',			'ROSTERS&SERVICES'),
12288 =>	Array('EDITROSTER',			'Rosters - edit assignments',			'ROSTERS&SERVICES'),
28672 =>	Array('MANAGEROSTERS',		'Rosters - manage roles & views',		'ROSTERS&SERVICES'),

32768 =>	Array('VIEWSERVICE',		'Services - view',						'ROSTERS&SERVICES'),
98304 =>	Array('EDITSERVICE',		'Services - edit individual',			'SERVICEDETAILS'),
229376 =>	Array('BULKSERVICE',		'Services - edit service schedule',		'ROSTERS&SERVICES'),
360448 =>	Array('SERVICECOMPS',		'Services - manage component library',  'SERVICEDETAILS'),
//311296 =>	Array('MANAGESONGS',		'Services - manage song repertoire',	'SERVICEDETAILS'),

1048576 =>	Array('EDITREC',			'Sermon recordings - manage',			'SERMONRECORDINGS'),

2097152 =>	Array('VIEWDOC',			'Documents & Folders - view',			'DOCUMENTS'),
6291456 =>	Array('EDITDOC',			'Documents & Folders- add/edit/delete',	'DOCUMENTS'),
14680064 =>	Array('SERVICEDOC',			'Service Documents - generate',			'SERVICEDOCUMENTS'),

// room for some more here...
2147483647 => Array('SYSADMIN',			'SysAdmin - manage user accounts, congregations etc', ''),
);
