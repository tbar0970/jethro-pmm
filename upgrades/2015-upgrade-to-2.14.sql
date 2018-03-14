
CREATE TABLE `custom_field` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL DEFAULT '',
  `rank` int(11) NOT NULL DEFAULT '0',
  `type` varchar(255) NOT NULL DEFAULT 'text',
  `allow_multiple` varchar(255) NOT NULL DEFAULT '0',
  `params` varchar(255) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB;

CREATE TABLE `custom_field_option` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `value` varchar(255) NOT NULL DEFAULT '',
  `rank` int(11) NOT NULL DEFAULT '0',
  `fieldid` int(11) NOT NULL DEFAULT '0',
  CONSTRAINT `customfieldoption_fieldid` FOREIGN KEY (`fieldid`) REFERENCES `custom_field`(`id`) ON DELETE CASCADE,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB;

CREATE TABLE `custom_field_value` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `personid` int(11) NOT NULL,
  `fieldid` int(11) NOT NULL,
  `value_text` varchar(255) DEFAULT NULL,
  `value_date` char(10) DEFAULT NULL,
  `value_optionid` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  CONSTRAINT `customfieldvalue_fieldid` FOREIGN KEY (`fieldid`) REFERENCES `custom_field`(`id`) ON DELETE CASCADE,
  CONSTRAINT `customfieldvalue_personid` FOREIGN KEY (`personid`) REFERENCES `_person`(`id`) ON DELETE CASCADE,
  CONSTRAINT `customfieldvalue_optionid` FOREIGN KEY (`value_optionid`) REFERENCES `custom_field_option` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

SET @rank = -1;

INSERT INTO custom_field
(id, name, rank, type, allow_multiple, params)
SELECT id, name, @rank:=@rank+1, 'date', 1, 'a:2:{s:10:"allow_note";i:1;s:16:"allow_blank_year";i:1;}'
from date_type
order by name;

ALTER TABLE date_type RENAME TO _disused_date_type;

SET @rank = (SELECT IFNULL(MAX(rank), 0) FROM custom_field) + 1;

/* Some systems have date values with typeid=null */
INSERT INTO custom_field
(name, rank, type, allow_multiple, params)
SELECT
'Other Date', @rank, 'date', 1, 'a:2:{s:10:"allow_note";i:1;s:16:"allow_blank_year";i:1;}'
FROM person_date
WHERE (typeid IS NULL) OR (typeid = 0)
LIMIT 1;

SET @otherFieldID = (SELECT id FROM custom_field WHERE name = 'Other Date');

INSERT INTO custom_field_value
(personid, fieldid, value_date, value_text)
SELECT personid, IF((typeid IS NULL) OR (typeid = 0), @otherFieldID, typeid), `date`, note
FROM person_date;

ALTER TABLE person_date RENAME TO _disused_person_date;

ALTER TABLE person_query ADD COLUMN `owner` int(11) DEFAULT NULL;

CREATE TABLE `note_template` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL DEFAULT '',
  `subject` varchar(255) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB;

CREATE TABLE `note_template_field` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `templateid` int(11) NOT NULL DEFAULT '0',
  `rank` int(11) NOT NULL DEFAULT '0',
  `customfieldid` int(11) DEFAULT '0',
  `label` varchar(255) DEFAULT '',
  `type` varchar(255) NOT NULL DEFAULT '',
  `params` varchar(255) DEFAULT 'a:0:{}',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB;

ALTER TABLE `note_template_field`
  ADD CONSTRAINT `note_template_fieldcustomfieldid` FOREIGN KEY (`customfieldid`) REFERENCES `custom_field` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `note_template_fieldtemplateid` FOREIGN KEY (`templateid`) REFERENCES `note_template` (`id`) ON DELETE CASCADE;

ALTER TABLE roster_view
ADD COLUMN show_on_run_sheet varchar(255) not null default 0;

UPDATE service_component
SET show_in_handout = 'full' WHERE show_in_handout = 1;

ALTER TABLE service_component DROP COLUMN is_numbered;

ALTER TABLE service_component_category DROP COLUMN is_numbered_default;

UPDATE service_component_category SET show_in_handout_default = 'full' WHERE show_in_handout_default = 1;
UPDATE service_component_category SET show_in_handout_default = 'title' WHERE category_name = 'Songs';

ALTER TABLE service
ADD COLUMN comments TEXT NOT NULL DEFAULT '';

ALTER TABLE service_item ADD COLUMN  `title` varchar(255) NOT NULL DEFAULT '';

ALTER TABLE service_item ADD COLUMN  `show_in_handout` varchar(255)NOT NULL DEFAULT '';

UPDATE service_item si
JOIN service_component sc ON si.componentid = sc.id
SET si.show_in_handout = sc.show_in_handout;

ALTER TABLE service_component_category
ADD COLUMN personnel_default VARCHAR(255) NOT NULL DEFAULT '';

UPDATE service_component_category
SET personnel_default = '%SONG_LEADER_FIRSTNAME%' WHERE category_name = 'Songs';

UPDATE service_component_category
SET personnel_default = '%SERVICE_LEADER_FIRSTNAME%' WHERE category_name IN ('Creeds', 'Prayers');

ALTER TABLE service_component
ADD COLUMN personnel VARCHAR(255) NOT NULL DEFAULT '';

ALTER TABLE service_item
ADD COLUMN personnel VARCHAR(255) NOT NULL DEFAULT '';

