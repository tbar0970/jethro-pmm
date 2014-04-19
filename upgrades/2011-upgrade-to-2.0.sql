/* fix timestamp col for notes */
alter table abstract_note
modify column created timestamp not null default CURRENT_TIMESTAMP;

/* fix work tel length */
ALTER TABLE person
MODIFY COLUMN work_tel varchar(12) not null default '';

/* add lock types */
ALTER TABLE `db_object_lock` ADD `lock_type` VARCHAR( 16 ) NOT NULL AFTER `userid` ;

/* add permissions to staff members */
alter table staff_member add column permissions int(10) unsigned;
update staff_member set permissions = 2147483647 WHERE is_admin = 1;
update staff_member set permissions = 7995391 WHERE is_admin = 0;
alter table staff_member drop column is_admin;

/* group and cong restrictions */
CREATE FUNCTION getCurrentUserID() RETURNS INTEGER RETURN @current_user_id;

ALTER TABLE person RENAME TO _person;

ALTER TABLE person_group RENAME TO _person_group;

CREATE TABLE account_group_restriction (
   personid INTEGER NOT NULL,
   groupid INTEGER NOT NULL,
   PRIMARY KEY (personid, groupid),
   CONSTRAINT account_group_restriction_personid FOREIGN KEY (personid) REFERENCES staff_member (id),
   CONSTRAINT account_group_restriction_groupid FOREIGN KEY (groupid) REFERENCES _person_group (id)
) engine=innodb;

CREATE TABLE account_congregation_restriction (
   personid INTEGER NOT NULL,
   congregationid INTEGER NOT NULL,
   PRIMARY KEY (personid, congregationid),
   CONSTRAINT account_congregation_restriction_personid FOREIGN KEY (personid) REFERENCES staff_member(id),
   CONSTRAINT account_group_restriction_congregationid FOREIGN KEY (congregationid) REFERENCES congregation(id)
) engine=innodb;

CREATE VIEW person AS
SELECT * from _person p
WHERE
  getCurrentUserID() IS NOT NULL
  AND (
    (p.id = getCurrentUserID())
    OR (getCurrentUserID() = -1)
    OR
      (NOT EXISTS (SELECT * FROM account_congregation_restriction cr WHERE cr.personid = getCurrentUserID())
       OR p.congregationid IN (SELECT cr.congregationid FROM account_congregation_restriction cr WHERE cr.personid = getCurrentUserID()))
    OR
      (NOT EXISTS (SELECT * FROM account_group_restriction gr WHERE gr.personid  = getCurrentUserID())
       OR p.id IN (SELECT m.personid FROM person_group_membership m JOIN account_group_restriction gr ON m.groupid = gr.groupid WHERE gr.personid = getCurrentUserID()))
  );


CREATE VIEW person_group AS
SELECT * from _person_group g
WHERE
  getCurrentUserID() IS NOT NULL
  AND
  (NOT EXISTS (SELECT * FROM account_group_restriction gr WHERE gr.personid  = getCurrentUserID())
       OR g.id IN (SELECT groupid FROM account_group_restriction gr WHERE gr.personid = getCurrentUserID()));

ALTER TABLE person_group_membership
ADD INDEX groupid (groupid);

ALTER TABLE person_group_membership
ADD INDEX personid (personid);

/* new tables */

CREATE TABLE IF NOT EXISTS `roster_role` (
  `id` int(11) NOT NULL auto_increment,
  `congregationid` int(11) default '0',
  `title` varchar(255) collate latin1_general_ci NOT NULL default '',
  `details` varchar(255) collate latin1_general_ci NOT NULL default '',
  `volunteer_group` int(11) default '0',
  `assign_multiple` varchar(255) collate latin1_general_ci NOT NULL default '0',
  `active` varchar(255) collate latin1_general_ci NOT NULL default '1',
  PRIMARY KEY  (`id`)
) ENGINE=InnoDB  DEFAULT CHARSET=latin1 COLLATE=latin1_general_ci;

CREATE TABLE IF NOT EXISTS `roster_role_assignment` (
  `assignment_date` date NOT NULL,
  `roster_role_id` int(11) NOT NULL,
  `personid` int(11) NOT NULL,
  `assigner` int(11) NOT NULL,
  `assignedon` timestamp NOT NULL default CURRENT_TIMESTAMP on update CURRENT_TIMESTAMP,
  PRIMARY KEY  (`roster_role_id`,`assignment_date`,`personid`),
  KEY `rra_assiger` (`assigner`),
  KEY `rra_personid` (`personid`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_general_ci;

CREATE TABLE IF NOT EXISTS `roster_view` (
  `id` int(11) NOT NULL auto_increment,
  `name` varchar(255) collate latin1_general_ci NOT NULL default '',
  `is_public` varchar(255) collate latin1_general_ci NOT NULL default '0',
  PRIMARY KEY  (`id`)
) ENGINE=InnoDB  DEFAULT CHARSET=latin1 COLLATE=latin1_general_ci;


CREATE TABLE IF NOT EXISTS `roster_view_role_membership` (
  `roster_role_id` int(5) NOT NULL,
  `roster_view_id` int(5) NOT NULL,
  `order_num` int(5) NOT NULL,
  PRIMARY KEY  (`roster_role_id`,`roster_view_id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_general_ci;


CREATE TABLE IF NOT EXISTS `roster_view_service_field` (
  `roster_view_id` int(5) NOT NULL,
  `congregationid` int(5) NOT NULL,
  `service_field` varchar(32) collate latin1_general_ci NOT NULL,
  `order_num` int(5) NOT NULL,
  PRIMARY KEY  (`congregationid`,`roster_view_id`,`service_field`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_general_ci;


CREATE TABLE IF NOT EXISTS `service` (
  `id` int(11) NOT NULL auto_increment,
  `date` date NOT NULL default '0000-00-00',
  `congregationid` int(11) NOT NULL default '0',
  `format_title` varchar(255) collate latin1_general_ci NOT NULL default '',
  `topic_title` varchar(255) collate latin1_general_ci NOT NULL default '',
  `notes` text collate latin1_general_ci NOT NULL,
  PRIMARY KEY  (`id`),
  UNIQUE KEY `datecong` (`date`,`congregationid`)
) ENGINE=InnoDB  DEFAULT CHARSET=latin1 COLLATE=latin1_general_ci;


CREATE TABLE IF NOT EXISTS `service_bible_reading` (
  `service_id` int(5) NOT NULL,
  `order_num` int(1) NOT NULL,
  `bible_ref` varchar(32) collate latin1_general_ci NOT NULL,
  `to_read` tinyint(1) unsigned default NULL,
  `to_preach` tinyint(1) unsigned default NULL,
  PRIMARY KEY  (`service_id`,`order_num`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_general_ci;
