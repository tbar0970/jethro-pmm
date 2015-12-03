
CREATE TABLE IF NOT EXISTS `custom_field` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL DEFAULT '',
  `rank` int(11) NOT NULL DEFAULT '0',
  `type` varchar(255) NOT NULL DEFAULT 'text',
  `allow_multiple` varchar(255) NOT NULL DEFAULT '0',
  `params` varchar(255) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `custom_field_option` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `value` varchar(255) NOT NULL DEFAULT '',
  `rank` int(11) NOT NULL DEFAULT '0',
  `fieldid` int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `custom_field_value` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `personid` int(11) NOT NULL,
  `fieldid` int(11) NOT NULL,
  `value_text` varchar(255) DEFAULT NULL,
  `value_date` date DEFAULT NULL,
  `value_optionid` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `personid` (`personid`),
  KEY `fieldid` (`fieldid`),
  KEY `value_optionid` (`value_optionid`)
) ENGINE=InnoDB;


ALTER TABLE `custom_field_value`
  ADD CONSTRAINT `value_optionid` FOREIGN KEY (`value_optionid`) REFERENCES `custom_field_option` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fieldid` FOREIGN KEY (`fieldid`) REFERENCES `custom_field` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `personid` FOREIGN KEY (`personid`) REFERENCES `_person` (`id`) ON DELETE CASCADE;

SET @rank = -1;

INSERT INTO custom_field
(name, rank, type, allow_multiple, params)
SELECT name, @rank:=@rank+1, 'date', 1, 'a:1:{s:10:"allow_note";b:1;}'
from date_type
order by name;

ALTER TABLE date_type RENAME TO _disused_date_type;
