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

CREATE TABLE person_photo (
   personid int(11) not null,
   photodata mediumblob not null,
   PRIMARY KEY (personid),
   CONSTRAINT photo_personid FOREIGN KEY (`personid`) REFERENCES `_person` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `date_type` (
  `id` int(11) NOT NULL auto_increment,
  `name` varchar(255) not null,
  PRIMARY KEY  (`id`)
) ENGINE=InnoDB;

CREATE TABLE person_date (
  `personid` int(11) NOT NULL,
  `typeid` int(11) default null,
  `date` char(10) not null,
  `note` varchar(255) default "",
  CONSTRAINT persondate_personid FOREIGN KEY (`personid`) REFERENCES `_person` (`id`) ON DELETE CASCADE,
  CONSTRAINT persondate_typeid FOREIGN KEY (`typeid`) REFERENCES `date_type` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE action_plan (
  id int(11) NOT NULL auto_increment,
  name varchar(255) not null,
  actions text not null,
  default_on_create_family tinyint(1) unsigned,
  default_on_add_person tinyint(1) unsigned,
  modified datetime not null,
  modifier int(11) not null,
  PRIMARY KEY  (`id`),
  CONSTRAINT actionplan_modifier FOREIGN KEY (`modifier`) REFERENCES `_person` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB;