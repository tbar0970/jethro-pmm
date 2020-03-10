update _person_group
set share_member_details = '0' where share_member_details = '';

alter table _person_group
modify column share_member_details varchar(255) not null default '0';

/* Issue #633 - add missing setting if needed */
INSERT IGNORE INTO setting
(rank, heading, symbol, note, type, value)
SELECT rank-1, heading, 'PUBLIC_AREA_ENABLED', 'Whether to allow public access to certain info at <system_url>public', 'bool', 1
FROM setting WHERE symbol = 'SHOW_SERVICE_NOTES_PUBLICLY';

UPDATE setting SET heading = '' WHERE symbol = 'SHOW_SERVICE_NOTES_PUBLICLY';


/* Iintroduce the new VIEWMYNOTES permission level. */
/* We add an extra bit in the 4th place from the right, and shift the other bits up */
update staff_member
set permissions = CONV(concat(left(bin(permissions), length(bin(permissions))-4),"1",right(bin(permissions),4)), 2, 10)
where permissions <> 2147483647
AND permissions & 16 = 16;

update staff_member
set permissions = CONV(concat(left(bin(permissions), length(bin(permissions))-4),"0",right(bin(permissions),4)), 2, 10)
where permissions <> 2147483647
AND permissions & 16 = 0;

ALTER TABLE abstract_note
MODIFY COLUMN creator INT(11) DEFAULT NULL;

UPDATE abstract_note
SET creator = NULL where creator = 0;

update abstract_note set assignee = null where assignee = 0;

/* Introduce a new view to filter the visible notes */
CREATE TABLE `_abstract_note` (
 `id` int(11) NOT NULL AUTO_INCREMENT,
 `subject` varchar(255) NOT NULL DEFAULT '',
 `details` text NOT NULL,
 `status` varchar(255) NOT NULL DEFAULT 'no_action',
 `status_last_changed` datetime DEFAULT NULL,
 `assignee` int(11) DEFAULT NULL,
 `assignee_last_changed` datetime DEFAULT NULL,
 `action_date` date NOT NULL,
 `creator` int(11) DEFAULT NULL,
 `created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
 `editor` int(11) DEFAULT NULL,
 `edited` datetime DEFAULT NULL,
 `history` text NOT NULL,
 PRIMARY KEY (`id`)
) ENGINE=InnoDB;

ALTER TABLE _abstract_note ADD CONSTRAINT FOREIGN KEY (`assignee`) REFERENCES `_person`(`id`) ON DELETE RESTRICT;
ALTER TABLE _abstract_note ADD CONSTRAINT FOREIGN KEY (`creator`) REFERENCES `_person`(`id`) ON DELETE RESTRICT;
ALTER TABLE _abstract_note ADD CONSTRAINT FOREIGN KEY (`editor`) REFERENCES `_person`(`id`) ON DELETE RESTRICT;

INSERT INTO _abstract_note
(id, subject, details, status, status_last_changed, assignee, assignee_last_changed, action_date, creator, created, editor, edited, history)
SELECT id, subject, details, status, status_last_changed, assignee, assignee_last_changed, action_date, creator, created, editor, edited, history FROM abstract_note;

ALTER TABLE abstract_note RENAME TO _abstract_note_old_backup;

create view abstract_note as
select an.* from _abstract_note an
WHERE ((an.assignee = getCurrentUserID() AND an.status = 'pending') OR (48 = (SELECT permissions & 48 FROM staff_member WHERE id = getCurrentUserID())));

/* Fix FKs on headcount table - ensure they have on-delete-cascade issue #613 */

ALTER TABLE congregation_headcount RENAME TO _disused_cong_headcount;
CREATE TABLE congregation_headcount SELECT * FROM _disused_cong_headcount;
DROP TABLE _disused_cong_headcount;
ALTER TABLE congregation_headcount ADD CONSTRAINT `congregation_headcountcongregationid` FOREIGN KEY (`congregationid`) REFERENCES `congregation` (`id`) ON DELETE CASCADE;

ALTER TABLE person_group_headcount RENAME TO _disused_group_headcount;
CREATE TABLE person_group_headcount SELECT * FROM _disused_group_headcount;
DROP TABLE _disused_group_headcount;
ALTER TABLE person_group_headcount ADD CONSTRAINT `person_group_headcountperson_groupid` FOREIGN KEY (`person_groupid`) REFERENCES `_person_group` (`id`) ON DELETE CASCADE;

ALTER TABLE service_component ADD COLUMN comments TEXT DEFAULT '';

alter table service_component modify column ccli_number int(11) default null;
update service_component set ccli_number = null where ccli_number = 0;