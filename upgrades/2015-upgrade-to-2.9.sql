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
