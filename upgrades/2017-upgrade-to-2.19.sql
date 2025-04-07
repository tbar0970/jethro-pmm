ALTER TABLE family CONVERT TO CHARACTER SET utf8 COLLATE utf8_general_ci;
ALTER TABLE _person CONVERT TO CHARACTER SET utf8 COLLATE utf8_general_ci;

ALTER TABLE custom_field
ADD COLUMN show_add_family varchar(255) not null default 0;

ALTER TABLE custom_field
ADD COLUMN searchable varchar(255) not null default 0;

ALTER TABLE _person_group
ADD COLUMN show_add_family varchar(255) not null default 'no';

ALTER TABLE _person_group
ADD COLUMN owner int(11) default null;

DROP VIEW person_group;

CREATE VIEW person_group AS
SELECT * from _person_group g
WHERE
  getCurrentUserID() IS NOT NULL
  AND
  ((g.owner IS NULL) OR (g.owner = getCurrentUserID()))
  AND
  (NOT EXISTS (SELECT * FROM account_group_restriction gr WHERE gr.personid  = getCurrentUserID())
  OR g.id IN (SELECT groupid FROM account_group_restriction gr WHERE gr.personid = getCurrentUserID()));

CREATE TABLE `age_bracket` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `label` varchar(255) NOT NULL DEFAULT '',
  `rank` int(11) not null,
  `is_adult` VARCHAR(255) NOT NULL DEFAULT 0,
  `is_default` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY (`label`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

/* once age_bracket table is populated by the upgrade PHP
   we'll need 1-based IDs instead of 0-based indexes
  so we move the old values into a separate table for reference */
CREATE TABLE _disused_person_age_brackets SELECT id, age_bracket FROM _person;
ALTER TABLE _person DROP COLUMN age_bracket;
ALTER TABLE _person ADD COLUMN age_bracketid INT(11) DEFAULT NULL;
ALTER TABLE _person ADD CONSTRAINT `person_age_bracket` FOREIGN KEY (`age_bracketid`) REFERENCES `age_bracket`(`id`) ON DELETE RESTRICT;

UPDATE _person SET congregationid = NULL WHERE congregationid = 0;

CREATE TABLE _disused_action_plan_backup SELECT * from action_plan;
CREATE TABLE _disused_person_query_backup SELECT * from person_query;

DROP VIEW person;
CREATE VIEW person AS
SELECT * from _person p
WHERE
getCurrentUserID() IS NOT NULL
AND (
(`p`.`id` = `getCurrentUserID`())
OR (`getCurrentUserID`() = -(1))
OR (
	(
	(not(exists(select 1 AS `Not_used` from `account_congregation_restriction` `cr` where (`cr`.`personid` = `getCurrentUserID`()))))
	OR `p`.`congregationid` in (select `cr`.`congregationid` AS `congregationid` from `account_congregation_restriction` `cr` where (`cr`.`personid` = `getCurrentUserID`()))
	)
	AND
	(
	(not(exists(select 1 AS `Not_used` from `account_group_restriction` `gr` where (`gr`.`personid` = `getCurrentUserID`()))))
	OR `p`.`id` in (select `m`.`personid` AS `personid` from (`person_group_membership` `m` join `account_group_restriction` `gr` on((`m`.`groupid` = `gr`.`groupid`))) where (`gr`.`personid` = `getCurrentUserID`()))
	)
)
);

DROP VIEW member;
CREATE VIEW member AS
SELECT mp.id, mp.first_name, mp.last_name, mp.gender, mp.age_bracketid, mp.congregationid,
mp.email, mp.mobile_tel, mp.work_tel, mp.familyid,
mf.family_name, mf.address_street, mf.address_suburb, mf.address_state, mf.address_postcode, mf.home_tel
FROM _person mp
JOIN family mf ON mf.id = mp.familyid
JOIN person_group_membership pgm1 ON pgm1.personid = mp.id
JOIN _person_group pg ON pg.id = pgm1.groupid AND pg.share_member_details = 1
JOIN person_group_membership pgm2 ON pgm2.groupid = pg.id
JOIN _person up ON up.id = pgm2.personid
WHERE up.id = getCurrentUserID()
   AND mp.status <> "archived"
   AND mf.status <> "archived"
   AND up.status <> "archived"	/* archived persons cannot see members of any group */

UNION

SELECT mp.id, mp.first_name, mp.last_name, mp.gender, mp.age_bracketid, mp.congregationid,
mp.email, mp.mobile_tel, mp.work_tel, mp.familyid,
mf.family_name, mf.address_street, mf.address_suburb, mf.address_state, mf.address_postcode, mf.home_tel
FROM _person mp
JOIN family mf ON mf.id = mp.familyid
JOIN _person self ON self.familyid = mp.familyid
WHERE
	self.id = getCurrentUserID()
	AND mp.status <> "archived"
	AND mf.status <> "archived"
	AND ((self.status <> "archived") OR (mp.id = self.id))
	/* archived persons can only see themselves, not any family members */
;

CREATE TABLE setting (
  `rank`  int(11) unsigned,
  heading VARCHAR(255) DEFAULT NULL,
  symbol VARCHAR(255) NOT NULL,
  note VARCHAR(255) NOT NULL,
  type VARCHAR(255) NOT NULL,
  value VARCHAR(255) NOT NULL,
  CONSTRAINT UNIQUE KEY `setting_symbol` (`symbol`)
);

SET @rank = 1;

INSERT INTO setting (`rank`, heading, symbol, note, type, value)
 VALUES
(@rank:=@rank+5, '','SYSTEM_NAME','Label displayed at the top of every page','text',''),

(@rank:=@rank+5, 'Permissions and Security','ENABLED_FEATURES','Which Jethro features are visible to users?','multiselect{"NOTES":"Notes","PHOTOS":"Photos","ATTENDANCE":"Attendance","ROSTERS&SERVICES":"Rosters & Services","SERVICEDETAILS":"Service Details","DOCUMENTS":"Documents","SERVICEDOCUMENTS":"Service documents"}','NOTES,PHOTOS,ATTENDANCE,ROSTERS&SERVICES,SERVICEDETAILS,DOCUMENTS,SERVICEDOCUMENTS'),
(@rank:=@rank+5, '',                         'DEFAULT_PERMISSIONS','Permissions to grant to new user accounts by default','int','7995391'),
(@rank:=@rank+5, '',                         'RESTRICTED_USERS_CAN_ADD','Can users with group or congregation restrictions add new persons and families?','bool','0'),
(@rank:=@rank+5, '',                         'PASSWORD_MIN_LENGTH','Minimum password length','int','8'),
(@rank:=@rank+5, '',                         'SESSION_TIMEOUT_MINS','Inactive sessions will be logged out after this number of minutes','int','90'),
(@rank:=@rank+5, '',                         'SESSION_MAXLENGTH_MINS','Every session will be logged out this many minutes after login','int','480'),

(@rank:=@rank+5, 'Jethro Behaviour Options','REQUIRE_INITIAL_NOTE','Whether an initial note is required when adding new family','bool','1'),
(@rank:=@rank+5, '',                         'NOTES_ORDER','Order to display person and family notes','select{\"ASC\":\"Oldest first\",\"DESC\":\"Newest first\"}','ASC'),
(@rank:=@rank+5, '',                         'LOCK_LENGTH','Number of minutes users have to edit an object before their lock expires','int','10'),
(@rank:=@rank+5, '',                         'PERSON_LIST_SHOW_GROUPS','Show all groups when listing persons?','bool','0'),
(@rank:=@rank+5, '',                         'NOTES_LINK_TO_EDIT','Should the homepage notes list link to the edit-note page?','bool','0'),
(@rank:=@rank+5, '',                         'CHUNK_SIZE','Batch size to aim for when dividing lists of items','int','100'),
(@rank:=@rank+5, '',                         'REPEAT_DATE_THRESHOLD','When a roster has this many columns, show the date on the right as well as the left','int','10'),
(@rank:=@rank+5, '',                         'ROSTER_WEEKS_DEFAULT','Number of weeks to show in rosters by default','int','8'),
(@rank:=@rank+5, '',                         'ATTENDANCE_LIST_ORDER','Order to list persons when recording/displaying attendance','text','status ASC, family_name ASC, familyid, age_bracket ASC, gender DESC'),
(@rank:=@rank+5, '',                         'ATTENDANCE_DEFAULT_DAY','Default day to record attendance','select[\"Sunday\",\"Monday\",\"Tuesday\",\"Wednesday\",\"Thursday\",\"Friday\",\"Saturday\"]','Sunday'),
(@rank:=@rank+5, '',                         'ENVELOPE_WIDTH_MM','Envelope width (mm)','int','220'),
(@rank:=@rank+5, '',                         'ENVELOPE_HEIGHT_MM','Envelope height (mm)','int','110'),

(@rank:=@rank+5, 'Data Structure options',   'PERSON_STATUS_OPTIONS','(The system-defined statuses \'Contact\' and \'Archived\' will be added to this list)','multitext_cm','Core,Crowd'),
(@rank:=@rank+5, '',                         'PERSON_STATUS_DEFAULT','','text','Contact'),
(@rank:=@rank+5, '',                         'AGE_BRACKET_OPTIONS','','',''),
(@rank:=@rank+5, '',                         'GROUP_MEMBERSHIP_STATUS_OPTIONS','','',''),
(@rank:=@rank+5, '',                         'TIMEZONE','','text','Australia/Sydney'),
(@rank:=@rank+5, '',                         'ADDRESS_STATE_OPTIONS','(Leave blank to hide the state field)','multitext_cm', 'ACT,NSW,NT,QLD,SA,TAS,VIC,WA,NSW'),
(@rank:=@rank+5, '',                         'ADDRESS_STATE_LABEL','Label for the \"state\" field. (Leave blank to hide the state field)','text','State'),
(@rank:=@rank+5, '',                         'ADDRESS_STATE_DEFAULT','Default state', 'text', 'NSW'),
(@rank:=@rank+5, '',                         'ADDRESS_SUBURB_LABEL','Label for the \"suburb\" field','text','Suburb'),
(@rank:=@rank+5, '',                         'ADDRESS_POSTCODE_LABEL','Label for the \"postcode\" field','text','Postcode'),
(@rank:=@rank+5, '',                         'ADDRESS_POSTCODE_WIDTH','Width of the postcode box','int','4'),
(@rank:=@rank+5, '',                         'ADDRESS_POSTCODE_REGEX','Regex to validate postcodes; eg /^[0-9][0-9][0-9][0-9]$/ for 4 digits','text','/^[0-9][0-9][0-9][0-9]$/'),
(@rank:=@rank+5, '',                         'HOME_TEL_FORMATS','Valid formats for home phone; use X for a digit','multitext_nl','XXXX-XXXX\n(XX) XXXX-XXXX'),
(@rank:=@rank+5, '',                         'WORK_TEL_FORMATS','Valid formats for work phone; use X for a digit','multitext_nl','XXXX-XXXX\n(XX) XXXX-XXXX'),
(@rank:=@rank+5, '',                         'MOBILE_TEL_FORMATS','Valid formats for mobile phone; use X for a digit','multitext_nl','XXXX-XXX-XXX'),

(@rank:=@rank+5, 'Member area',              'MEMBER_LOGIN_ENABLED','Should church members be able to log in at <system_url>members?','bool','0'),
(@rank:=@rank+5, '',                         'MEMBER_REGO_EMAIL_FROM_NAME','Sender name for member rego emails','text',''),
(@rank:=@rank+5, '',                         'MEMBER_REGO_EMAIL_FROM_ADDRESS','Sender address for member rego emails','text',''),
(@rank:=@rank+5, '',                         'MEMBER_REGO_EMAIL_SUBJECT','Subject for member rego emails','text',''),
(@rank:=@rank+5, '',                         'MEMBER_REGO_EMAIL_CC','CC all member rego emails to...','text',''),
(@rank:=@rank+5, '',                         'MEMBER_REGO_FAILURE_EMAIL','Address to notifiy when member rego fails','text',''),
(@rank:=@rank+5, '',                         'MEMBER_PASSWORD_MIN_LENGTH','Minimum length for member passwords','int','7'),
(@rank:=@rank+5, '',                         'MEMBERS_SHARE_ADDRESS','Should addresses be visible in the members area?','bool','0'),

(@rank:=@rank+5, 'Public area',              'SHOW_SERVICE_NOTES_PUBLICLY','Should service notes be visible in the public area at <system_url>public?','bool',''),
(@rank:=@rank+5, '',                         'PUBLIC_ROSTER_SECRET','Advanced: Only allow access to public rosters if the URL contains \"&secret=<this-secret>\"','text',''),

(@rank:=@rank+5, 'External Links',           'BIBLE_URL','URL Template for bible passage links, with the keyword __REFERENCE__','text','https://www.biblegateway.com/passage/?search=__REFERENCE__&version=NIVUK'),
(@rank:=@rank+5, '',                         'CCLI_SEARCH_URL','URL Template for searching CCLI, with the keyword __TITLE__','text','http://us.search.ccli.com/search/results?SearchTerm=__TITLE__'),
(@rank:=@rank+5, '',                         'CCLI_DETAIL_URL','URL Template for CCLI song details by song number, with the keyword __NUMBER__','text','https://au.songselect.com/songs/__NUMBER__'),
(@rank:=@rank+5, '',                         'POSTCODE_LOOKUP_URL','URL template for looking up postcodes, with the keyword __SUBURB__','text','https://m.auspost.com.au/view/findpostcode/__SUBURB__'),
(@rank:=@rank+5, '',                         'MAP_LOOKUP_URL','URL template for map links, with the keywords __ADDRESS_STREET__, __ADDRESS_SUBURB__, __ADDRESS_POSTCODE__, __ADDRESS_STATE__','text','http://maps.google.com.au?q=__ADDRESS_STREET__,%20__ADDRESS_SUBURB__,%20__ADDRESS_STATE__,%20__ADDRESS_POSTCODE__'),
(@rank:=@rank+5, '',                         'EMAIL_CHUNK_SIZE','When displaying mailto links for emails, divide into batches of this size','int','25'),
(@rank:=@rank+5, '',                         'MULTI_EMAIL_SEPARATOR','When displaying mailto links for emails, separate addresses using this character','text',','),

(@rank:=@rank+5, 'SMTP Email Server',        'SMTP_SERVER','SMTP server for sending emails','text',''),
(@rank:=@rank+5, '',                         'SMTP_ENCRYPTION','Encryption method for SMTP server','select{\"ssl\":\"SSL\",\"tls\":\"TLS\",\"\":\"(None)\"}',''),
(@rank:=@rank+5, '',                         'SMTP_USERNAME','Username for SMTP server','text',''),
(@rank:=@rank+5, '',                         'SMTP_PASSWORD','Password for SMTP server','text',''),

(@rank:=@rank+5, 'SMS Gateway',              'SMS_MAX_LENGTH','','int','140'),
(@rank:=@rank+5, '',                         'SMS_HTTP_URL','URL of the SMS messaging service. (Leave blank to disable SMS messaging)','text',''),
(@rank:=@rank+5, '',                         'SMS_HTTP_HEADER_TEMPLATE','Template for the headers of a request to the SMS messaging service','text_ml',''),
(@rank:=@rank+5, '',                         'SMS_HTTP_POST_TEMPLATE','Template for the body of a request to the SMS messaging service','text_ml',''),
(@rank:=@rank+5, '',                         'SMS_RECIPIENT_ARRAY_PARAMETER','','text',''),
(@rank:=@rank+5, '',                         'SMS_HTTP_RESPONSE_OK_REGEX','Regex for recognising a successful send','text_ml',''),
(@rank:=@rank+5, '',                         'SMS_HTTP_RESPONSE_ERROR_REGEX','Regex for recognising an API error','text_ml',''),
(@rank:=@rank+5, '',                         'SMS_LOCAL_PREFIX','Used for converting local to international numbers.  eg 0','text',''),
(@rank:=@rank+5, '',                         'SMS_INTERNATIONAL_PREFIX','Used for converting local to international numbers. eg +61','text',''),
(@rank:=@rank+5, '',                         'SMS_SAVE_TO_NOTE_BY_DEFAULT','Whether to save each sent SMS as a person note by default','bool',''),
(@rank:=@rank+5, '',                         'SMS_SAVE_TO_NOTE_SUBJECT','','text',''),
(@rank:=@rank+5, '',                         'SMS_SEND_LOGFILE','File on the server to save a log of sent SMS messages','text','');

CREATE TABLE action_plan_age_bracket (
   action_planid INT NOT NULL,
   age_bracketid INT NOT NULL,
   PRIMARY KEY (action_planid, age_bracketid)
) ENGINE=InnoDB;