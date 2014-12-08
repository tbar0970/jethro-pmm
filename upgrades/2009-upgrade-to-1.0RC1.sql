########################################
# add status-last-changed col for person
########################################
alter table person
add column status_last_changed datetime null default null;

########################################
# add status-last-changed and assignee-last-changed for notes
########################################
alter table abstract_note
add column status_last_changed datetime null default null;
alter table abstract_note
add column assignee_last_changed datetime null default null;

########################################
# create group categories
########################################
CREATE TABLE person_group_category (
  `id` int(11) NOT NULL auto_increment,
  `name` varchar(255) not null,
  `parent_category` int(11) not null default 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB ;

########################################
# add archiving and categorisation to groups
########################################
alter table person_group
add column is_archived tinyint(1) unsigned not null;

alter table person_group
add column categoryid int(11) not null default 0;

########################################
# allow attendance recording for groups
########################################
alter table attendance_record
add column groupid integer not null default 0;

ALTER TABLE `attendance_record` DROP PRIMARY KEY ,
ADD PRIMARY KEY ( `date` , `personid` , `groupid` );

alter table person_group
add column can_record_attendance tinyint(1) unsigned not null default 0;

