/* Front page reports */
ALTER table person_query
ADD COLUMN `show_on_homepage` varchar(16) not null default '';

/* Issue #457 - clean up zero dates just for tidyness */
UPDATE _person SET status_last_changed = NULL where CAST(status_last_changed AS CHAR(20)) = '0000-00-00 00:00:00' ;

/* issue #506 - is_adult col sometimes wrong definition */
UPDATE age_bracket set is_adult = 0 where is_adult <> 1;
ALTER TABLE age_bracket MODIFY COLUMN is_adult TINYINT(1) UNSIGNED NOT NULL DEFAULT 0;

/* issue #492 - correct the wording for the RESTRICTED_USERS_CAN_ADD setting */
UPDATE setting SET note = 'Can users with congregation restrictions add new persons and families?' where symbol = 'RESTRICTED_USERS_CAN_ADD';

/* issue #30 - delete people altogether */
/* To make sure FKs are right on roster_role_assignment we recreate the table
since the FK names are historically inconsistent */

ALTER TABLE roster_role_assignment
RENAME TO _disused_roster_role_assn;

create table roster_role_assignment (
assignment_date	date not null,
roster_role_id	int(11) not null,
personid		int(11) not null,
rank            int unsigned not null default 0,
assigner		int(11) not null,
assignedon		timestamp,
primary key (roster_role_id, assignment_date, personid),
constraint `rra_assiger` foreign key (assigner) references _person(id),
constraint `rra_personid` foreign key (personid) references _person(id) ON DELETE CASCADE,
constraint `rra_roster_role_id` foreign key (roster_role_id) references roster_role(id)
) ENGINE=InnoDB ;

INSERT INTO roster_role_assignment
SELECT * FROM _disused_roster_role_assn;

DELETE FROM attendance_record WHERE personid NOT IN (select id FROM _person);
ALTER TABLE attendance_record
ADD CONSTRAINT `ar_personid` FOREIGN KEY (personid) REFERENCES _person(id) ON DELETE CASCADE;

DELETE FROM person_note WHERE personid NOT IN (select id FROM _person);
ALTER TABLE person_note
ADD CONSTRAINT `pn_personid` FOREIGN KEY (personid) REFERENCES _person(id) ON DELETE CASCADE;

DELETE FROM family_note WHERE familyid NOT IN (select id from family);
ALTER TABLE family_note
ADD CONSTRAINT `fn_familyid` FOREIGN KEY (familyid) REFERENCES family(id) ON DELETE CASCADE;

DELETE FROM person_group_membership WHERE personid NOT IN (select id FROM _person);
ALTER TABLE person_group_membership
ADD CONSTRAINT `pgm_personid` FOREIGN KEY (personid) REFERENCES _person(id) ON DELETE CASCADE;

DELETE FROM person_note where id NOT IN (SELECT id from abstract_note);
ALTER TABLE person_note
ADD CONSTRAINT pn_id FOREIGN KEY (id) REFERENCES abstract_note(id) ON DELETE CASCADE;

DELETE FROM family_note WHERE id NOT IN (SELECT id from abstract_note);
ALTER TABLE family_note
ADD CONSTRAINT fn_id FOREIGN KEY (id) REFERENCES abstract_note(id) ON DELETE CASCADE;

DELETE FROM abstract_note WHERE id not IN (SELECT id from person_note UNION SELECT id from family_note);

ALTER TABLE family_photo
DROP FOREIGN KEY `famliyphotofamilyid`;

ALTER TABLE family_photo
ADD CONSTRAINT `famliyphotofamilyid` FOREIGN KEY (familyid) REFERENCES family(id) ON DELETE CASCADE;
