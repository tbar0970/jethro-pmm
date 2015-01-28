ALTER TABLE roster_role
MODIFY COLUMN details text default "";


CREATE TABLE `service_component` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `categoryid` int(11) NOT NULL DEFAULT '0',
  `title` varchar(255) NOT NULL DEFAULT '',
  `alt_title` varchar(255) NOT NULL DEFAULT '',
  `length_mins` int(11) NOT NULL DEFAULT '0',
  `is_numbered` varchar(255) NOT NULL DEFAULT '',
  `runsheet_title_format` varchar(255) NOT NULL DEFAULT '',
  `handout_title_format` varchar(255) NOT NULL DEFAULT '',
  `show_in_handout` varchar(255) NOT NULL DEFAULT '',
  `show_on_slide` varchar(255) NOT NULL DEFAULT '',
  `content_html` text NOT NULL,
  `credits` text NOT NULL,
  `ccli_number` int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB ;


CREATE TABLE `service_component_category` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `category_name` varchar(255) NOT NULL DEFAULT '',
  `runsheet_title_format` varchar(255) NOT NULL DEFAULT '%title%',
  `handout_title_format` varchar(255) NOT NULL DEFAULT '%title%',
  `is_numbered_default` varchar(255) NOT NULL DEFAULT '1',
  `show_in_handout_default` varchar(255) NOT NULL DEFAULT '1',
  `show_on_slide_default` varchar(255) NOT NULL DEFAULT '1',
  `length_mins_default` int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB;

INSERT INTO service_component_category
				  (category_name, runsheet_title_format, handout_title_format, length_mins_default)
				  VALUES 
				  ("Songs", "Song: %title%", "Song: %title%", 3),
				  ("Prayers", "%title%", "%title%", 2),
				  ("Creeds", "The %title%", "The %title%", 2),
				  ("Other", "%title%", "%title%", 1);


CREATE TABLE `service_component_tag` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tag` varchar(255) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB ;


CREATE TABLE `service_component_tagging` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tagid` int(11) NOT NULL DEFAULT '0',
  `componentid` int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `comptag` (`tagid`,`componentid`)
) ENGINE=InnoDB ;

CREATE TABLE `service_item` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `serviceid` int(11) NOT NULL DEFAULT '0',
  `rank` int(11) NOT NULL DEFAULT '0',
  `componentid` int(11) DEFAULT '0',
  `length_mins` int(11) NOT NULL DEFAULT '0',
  `heading_text` varchar(255) NOT NULL DEFAULT '',
  `note` text NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `servicerank` (`serviceid`,`rank`)
) ENGINE=InnoDB  ;

ALTER TABLE `service_component_tagging`
  ADD CONSTRAINT `tagid` FOREIGN KEY (`tagid`) REFERENCES `service_component_tag` (`id`) ON DELETE CASCADE;

CREATE TABLE IF NOT EXISTS `congregation_service_component` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `congregationid` int(11) NOT NULL DEFAULT '0',
  `componentid` int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `congcomp` (`congregationid`,`componentid`)
) ENGINE=InnoDB;

-- fix all the collations to avoid trouble with unions...
 ALTER TABLE _person convert to CHARACTER SET utf8 COLLATE utf8_unicode_ci; 
 ALTER TABLE _person_group convert to CHARACTER SET utf8 COLLATE utf8_unicode_ci; 
 ALTER TABLE abstract_note convert to CHARACTER SET utf8 COLLATE utf8_unicode_ci; 
 ALTER TABLE account_congregation_restriction convert to CHARACTER SET utf8 COLLATE utf8_unicode_ci; 
 ALTER TABLE account_group_restriction convert to CHARACTER SET utf8 COLLATE utf8_unicode_ci; 
 ALTER TABLE action_plan convert to CHARACTER SET utf8 COLLATE utf8_unicode_ci; 
 ALTER TABLE attendance_record convert to CHARACTER SET utf8 COLLATE utf8_unicode_ci; 
 ALTER TABLE congregation convert to CHARACTER SET utf8 COLLATE utf8_unicode_ci; 
 ALTER TABLE congregation_service_component convert to CHARACTER SET utf8 COLLATE utf8_unicode_ci; 
 ALTER TABLE date_type convert to CHARACTER SET utf8 COLLATE utf8_unicode_ci; 
 ALTER TABLE db_object_lock convert to CHARACTER SET utf8 COLLATE utf8_unicode_ci; 
 ALTER TABLE family convert to CHARACTER SET utf8 COLLATE utf8_unicode_ci; 
 ALTER TABLE family_note convert to CHARACTER SET utf8 COLLATE utf8_unicode_ci; 
 ALTER TABLE note_comment convert to CHARACTER SET utf8 COLLATE utf8_unicode_ci; 
 ALTER TABLE person_date convert to CHARACTER SET utf8 COLLATE utf8_unicode_ci; 
 ALTER TABLE person_group_category convert to CHARACTER SET utf8 COLLATE utf8_unicode_ci; 
 ALTER TABLE person_group_membership convert to CHARACTER SET utf8 COLLATE utf8_unicode_ci; 
 ALTER TABLE person_group_membership_status convert to CHARACTER SET utf8 COLLATE utf8_unicode_ci; 
 ALTER TABLE person_note convert to CHARACTER SET utf8 COLLATE utf8_unicode_ci; 
 ALTER TABLE person_photo convert to CHARACTER SET utf8 COLLATE utf8_unicode_ci; 
 ALTER TABLE person_query convert to CHARACTER SET utf8 COLLATE utf8_unicode_ci; 
 ALTER TABLE roster_role convert to CHARACTER SET utf8 COLLATE utf8_unicode_ci; 
 ALTER TABLE roster_role_assignment convert to CHARACTER SET utf8 COLLATE utf8_unicode_ci; 
 ALTER TABLE roster_view convert to CHARACTER SET utf8 COLLATE utf8_unicode_ci; 
 ALTER TABLE roster_view_role_membership convert to CHARACTER SET utf8 COLLATE utf8_unicode_ci; 
 ALTER TABLE roster_view_service_field convert to CHARACTER SET utf8 COLLATE utf8_unicode_ci; 
 ALTER TABLE service convert to CHARACTER SET utf8 COLLATE utf8_unicode_ci; 
 ALTER TABLE service_bible_reading convert to CHARACTER SET utf8 COLLATE utf8_unicode_ci; 
 ALTER TABLE service_component convert to CHARACTER SET utf8 COLLATE utf8_unicode_ci; 
 ALTER TABLE service_component_category convert to CHARACTER SET utf8 COLLATE utf8_unicode_ci; 
 ALTER TABLE service_component_tag convert to CHARACTER SET utf8 COLLATE utf8_unicode_ci; 
 ALTER TABLE service_component_tagging convert to CHARACTER SET utf8 COLLATE utf8_unicode_ci; 
 ALTER TABLE service_item convert to CHARACTER SET utf8 COLLATE utf8_unicode_ci; 
 ALTER TABLE staff_member convert to CHARACTER SET utf8 COLLATE utf8_unicode_ci; 

/* this table should not exist but is present in some systems */
DROP TABLE IF EXISTS action_plan_note ;
 
