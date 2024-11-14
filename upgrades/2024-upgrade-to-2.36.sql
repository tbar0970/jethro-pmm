/* Issue #1040 - Setting to hide age brackets in members area */
INSERT INTO setting
(rank, symbol, type, value, note)
SELECT rank+1, 'MEMBERS_SEE_AGE_BRACKET', type, 1, 'Should members be able to see and edit the age bracket field?'
FROM setting
WHERE symbol = 'MEMBERS_SHARE_ADDRESS';

/* Issue 1035 - moving person_status to a table */

DROP TABLE IF EXISTS person_status;

CREATE TABLE `person_status` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `label` varchar(255) NOT NULL,
  `rank` int(11) NOT NULL DEFAULT 0,
  `active` tinyint(1) unsigned DEFAULT 1,
  `is_default` tinyint(1) unsigned DEFAULT 0,
  `is_archived` tinyint(1) unsigned DEFAULT 0, /* whether this status equals the classic 'archived' status */
  `require_congregation` tinyint(1) unsigned DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `label` (`label`)
) ENGINE=InnoDB;

SET @sOptions = (SELECT CONCAT(value, ",") FROM setting WHERE symbol = 'PERSON_STATUS_OPTIONS');

SELECT @sOptions;

CREATE TEMPORARY TABLE temp_statuses
SELECT distinct (status+1) as id, "TBA", 0
 FROM _person
WHERE status NOT IN ('archived', 'contact');

SET @rank = 0;
INSERT INTO person_status(id, label, `rank`)
SELECT ts.id, SUBSTRING_INDEX(SUBSTRING_INDEX(@sOptions, ',', ts.id), ',', -1), @rank:=@rank+1
FROM temp_statuses ts;

INSERT INTO person_status(label, `rank`, require_congregation)
VALUES
('Contact', @rank:=@rank+1, 0);

SET @contact_status_id = LAST_INSERT_ID();

INSERT INTO person_status(label, rank, is_archived, require_congregation)
VALUES
('Archived', @rank:=@rank+1, 1, 0);

SET @archived_status_id = LAST_INSERT_ID();

CREATE TABLE _person_status_backup
SELECT id, status FROM _person;

UPDATE _person
SET status = status+1
WHERE status NOT IN ('archived', 'contact');

UPDATE _person
SET status = @contact_status_id
WHERE status = 'contact';

UPDATE _person
SET status = @archived_status_id
WHERE status = 'archived';

ALTER TABLE `_person`
MODIFY COLUMN status INT(11) NOT NULL;

ALTER TABLE `_person`
ADD CONSTRAINT `person_status` FOREIGN KEY (`status`) REFERENCES `person_status` (`id`) ON DELETE RESTRICT;

UPDATE person_status
SET is_default = 1
WHERE label = (SELECT value FROM setting WHERE symbol = "PERSON_STATUS_DEFAULT");

DELETE FROM setting WHERE symbol = "PERSON_STATUS_DEFAULT";

UPDATE setting SET type="", note="" WHERE symbol = "PERSON_STATUS_OPTIONS"; /* turns it into a placeholder for custom interface */

DROP VIEW member;

CREATE VIEW member AS
SELECT mp.id, mp.first_name, mp.last_name, mp.gender, mp.age_bracketid, mp.congregationid,
mp.email, mp.mobile_tel, mp.work_tel, mp.familyid,
mf.family_name, mf.address_street, mf.address_suburb, mf.address_state, mf.address_postcode, mf.home_tel
FROM _person mp
JOIN person_status mps ON mps.id = mp.status
JOIN family mf ON mf.id = mp.familyid
JOIN person_group_membership pgm1 ON pgm1.personid = mp.id
JOIN _person_group pg ON pg.id = pgm1.groupid AND pg.share_member_details = 1
JOIN person_group_membership pgm2 ON pgm2.groupid = pg.id
JOIN _person up ON up.id = pgm2.personid
JOIN person_status ups ON ups.id = up.status
WHERE up.id = getCurrentUserID()
   AND (NOT mps.is_archived)    /* dont show archived persons */
   AND mf.status <> "archived"  /* dont show archived families */
   AND (NOT ups.is_archived)   /* dont let persons who are themselves archived see anything */

UNION

SELECT mp.id, mp.first_name, mp.last_name, mp.gender, mp.age_bracketid, mp.congregationid,
mp.email, mp.mobile_tel, mp.work_tel, mp.familyid,
mf.family_name, mf.address_street, mf.address_suburb, mf.address_state, mf.address_postcode, mf.home_tel
FROM _person mp
JOIN person_status mps ON mps.id = mp.status
JOIN family mf ON mf.id = mp.familyid
JOIN _person self ON self.familyid = mp.familyid
JOIN person_status selfs ON selfs.id = self.status
WHERE
   self.id = getCurrentUserID()
   AND ((NOT mps.is_archived) OR (mp.id = self.id))
   AND ((NOT selfs.is_archived) OR (mp.id = self.id))
   /* archived persons can only see themselves, not any family members */
;


-- Grant 'Groups - add/edit/delete' (1024) to everyone who had 'Persons & Families - add/edit' (1) and lacked it previously.
-- See https://github.com/tbar0970/jethro-pmm/issues/1075
update staff_member set permissions = permissions | 1024 where 1 = (permissions & 1) and 0 = (permissions & 1024) ;

-- Fix duplicate roster role assignment ranks (https://github.com/tbar0970/jethro-pmm/issues/1078).
UPDATE roster_role_assignment rra
INNER JOIN
  -- Use row_number() window function to compute the correct rank
  (SELECT *,
          (row_number() OVER (PARTITION BY assignment_date,
                                           roster_role_id
                              ORDER BY rank ASC) - 1) AS correctrank
   FROM roster_role_assignment
   ) a ON rra.assignment_date = a.assignment_date
AND rra.roster_role_id = a.roster_role_id
AND rra.personid = a.personid
SET rra.rank = a.correctrank
WHERE rra.rank != a.correctrank;

-- Relating to the #1078 fix above: ensure that every role (roster_role_id) assigned on a given date (assignment_date) has a distinct rank.
ALTER TABLE roster_role_assignment ADD CONSTRAINT unique_role_assignment UNIQUE (assignment_date, roster_role_id, rank);

-- Issue #890 - default value for configurable order for attendance recording
INSERT INTO setting (`rank`, heading, symbol, note, type, value)
SELECT `rank`+1, NULL, 'ATTENDANCE_ORDER_DEFAULT', 'Default order for recording/showing attendance',
'select{"status":"Status, then family name","family_name":"Family name, then age bracket","last_name":"Last name","first_name":"First name","age_bracket":"Age bracket"}',
'status'
FROM setting
WHERE symbol = 'ATTENDANCE_DEFAULT_DAY';


-- Deleting a congregation should delete associated headcounts. Fixes https://github.com/tbar0970/jethro-pmm/issues/1091
ALTER TABLE `congregation_headcount` DROP FOREIGN KEY `congregation_headcount_congid2`;
ALTER TABLE `congregation_headcount` ADD CONSTRAINT `congregation_headcount_congid2` FOREIGN KEY (`congregationid`) REFERENCES `congregation` (`id`) ON DELETE CASCADE;