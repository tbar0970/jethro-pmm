/* Issue #1040 - Setting to hide age brackets in members area */
INSERT INTO setting
(`rank`, symbol, type, value, note)
SELECT `rank`+1, 'MEMBERS_SEE_AGE_BRACKET', type, 1, 'Should members be able to see and edit the age bracket field?'
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

-- Get the old comma-separated PERSON_STATUS_OPTIONS, to be replaced by our new person_status table.
-- E.g. 'Core,Flock,Connecting,Staff,Fringe,Satellite Members,Youth Contact,'
SET @soptions =
  (SELECT concat(value, ",")
   FROM setting
   WHERE symbol = 'PERSON_STATUS_OPTIONS');

-- Augment @soptions with built-in 'Contact' and 'Archived' statuses, to save us having to insert them (with correct rank) later. Throw in whitespace to ensure we handle it properly
-- E.g. 'Core,Flock,Connecting,Staff,Fringe,Satellite Members,Youth Contact,,Contact,  Archived'
SET @soptions = concat(@soptions, ',Contact,  Archived');

-- E.g. 'Core,Flock,Connecting,Staff,Fringe,Satellite Members,Youth Contact,Contact,  Archived'
SET @soptions = regexp_replace(@soptions, ',+', ',');

-- E.g. ["Core","Flock","Connecting","Staff","Fringe","Satellite Members","Youth Contact","Contact","  Archived"]
SET @soptions =
  (SELECT concat('["', replace(@soptions, ',', '","'), '"]'));

-- Use json_table() to turn @soptions into a table, with sequential id and rank
-- We GROUP BY the (trimmed, lowercased) label just in case there is more than one 'Contact' (#1115). If so, we use the first, user-supplied one's (via 'min(jt.id)') id and rank, not that of the 'Contact' we appended, so that id refs to it (in _person.status) remain valid.
INSERT INTO `person_status` (id, label, `rank`)
SELECT min(jt.id) AS id,
       trim(jt.value) AS label,
       min(jt.id) AS `rank`
FROM json_table(@soptions, "$[*]" columns(id
                                          FOR
                                          ORDINALITY, value varchar(255) PATH "$")) AS jt
WHERE trim(value) != ""
GROUP BY trim(lower(jt.value));

UPDATE `person_status`
SET require_congregation=0
WHERE label='Contact';

UPDATE `person_status`
SET is_archived=1,
    require_congregation=0
WHERE label='Archived';

UPDATE `person_status`
SET is_default = 1
WHERE label = (SELECT value FROM setting WHERE symbol = "PERSON_STATUS_DEFAULT");

-- At this point person_status is ready to go. In our example, it is:
-- +----+-------------------+------+--------+------------+-------------+----------------------+
-- | id | label             | rank | active | is_default | is_archived | require_congregation |
-- +----+-------------------+------+--------+------------+-------------+----------------------+
-- |  1 | Core              |    1 |      1 |          0 |           0 |                    1 |
-- |  2 | Flock             |    2 |      1 |          0 |           0 |                    1 |
-- |  3 | Connecting        |    3 |      1 |          0 |           0 |                    1 |
-- |  4 | Staff             |    4 |      1 |          0 |           0 |                    1 |
-- |  5 | Fringe            |    5 |      1 |          0 |           0 |                    1 |
-- |  6 | Satellite Members |    6 |      1 |          0 |           0 |                    1 |
-- |  7 | Youth Contact     |    7 |      1 |          0 |           0 |                    1 |
-- |  8 | Contact           |    8 |      1 |          1 |           0 |                    0 |
-- |  9 | Archived          |    9 |      1 |          0 |           1 |                    0 |
-- +----+-------------------+------+--------+------------+-------------+----------------------+


-- Deal with obsolete settings
DELETE FROM setting WHERE symbol = "PERSON_STATUS_DEFAULT";
UPDATE setting SET type="", note="" WHERE symbol = "PERSON_STATUS_OPTIONS"; /* turns it into a placeholder for custom interface */


-- Now we update _person.status to be a FK reference to person_status.id.
-- _person.status used to be a 0-based index into the PERSON_STATUS_OPTIONS string, plus 'archived' and 'contact' magic values.
-- PERSON_STATUS_OPTIONS has become the 1-based but otherwise identically ordered person_status table, so just increment the values.
UPDATE `_person`
SET status = status+1
WHERE status NOT IN ('archived', 'contact');

-- Get id of 'Contact' (or 'contact' if the user defined it prior), and set _person.status to it where applicable
SELECT id INTO @contact_status_id FROM person_status WHERE lower(label)='contact';
UPDATE `_person`
SET status = @contact_status_id
WHERE status = 'contact';

-- Same with 'Archived'
SELECT id INTO @archived_status_id FROM person_status WHERE lower(label)='archived';
UPDATE `_person`
SET status = @archived_status_id
WHERE status = 'archived';

ALTER TABLE `_person`
MODIFY COLUMN status INT(11) NOT NULL;

ALTER TABLE `_person`
ADD CONSTRAINT `person_status` FOREIGN KEY (`status`) REFERENCES `person_status` (`id`) ON DELETE RESTRICT;

-- Finished with _person


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
                              ORDER BY `rank` ASC) - 1) AS correctrank
   FROM roster_role_assignment
   ) a ON rra.assignment_date = a.assignment_date
AND rra.roster_role_id = a.roster_role_id
AND rra.personid = a.personid
SET rra.`rank` = a.correctrank
WHERE rra.`rank` != a.correctrank;

-- Relating to the #1078 fix above: ensure that every role (roster_role_id) assigned on a given date (assignment_date) has a distinct rank.
ALTER TABLE roster_role_assignment ADD CONSTRAINT unique_role_assignment UNIQUE (assignment_date, roster_role_id, `rank`);

-- Issue #890 - default value for configurable order for attendance recording
INSERT INTO setting (`rank`, heading, symbol, note, type, value)
SELECT `rank`+1, NULL, 'ATTENDANCE_ORDER_DEFAULT', 'Default order for recording/showing attendance',
'select{"status":"Status, then family name","family_name":"Family name, then age bracket","last_name":"Last name","first_name":"First name","age_bracket":"Age bracket"}',
'status'
FROM setting
WHERE symbol = 'ATTENDANCE_DEFAULT_DAY';

-- Issue #1091 - FK for headcount tables needs to be on delete cascade

RENAME TABLE congregation_headcount TO disused_congregation_headcount;
RENAME TABLE person_group_headcount TO disused_person_group_headcount;

CREATE TABLE congregation_headcount (
      `date` DATE NOT NULL,
      `congregationid` INT(11) NOT NULL,
      `number` INT(11) NOT NULL,
      PRIMARY KEY (`date`, `congregationid`),
      FOREIGN KEY (congregationid) REFERENCES congregation(id) ON DELETE CASCADE
) Engine=InnoDB;

INSERT INTO congregation_headcount SELECT * FROM disused_congregation_headcount;

CREATE TABLE person_group_headcount (
      `date` DATE NOT NULL,
      `person_groupid` INT(11) NOT NULL,
      `number` INT(11) NOT NULL,
      PRIMARY KEY (`date`, `person_groupid`),
      FOREIGN KEY (person_groupid) REFERENCES _person_group(id) ON DELETE CASCADE
) Engine=InnoDB;

INSERT INTO person_group_headcount SELECT * FROM disused_person_group_headcount;

DROP TABLE disused_congregation_headcount;
DROP TABLE disused_person_group_headcount;

-- Issue #1086
INSERT INTO setting
(symbol, type, value, note)
VALUES ('NEEDS_1086_CHECK', 'hidden', "1", "Whether the check for issue 1086 needs to be run");

-- Issue #1035
CREATE TABLE IF NOT EXISTS _disused_person_query_backup_1035 SELECT * from person_query;
CREATE TABLE IF NOT EXISTS _disused_action_plan_backup_1035 SELECT * from action_plan;
INSERT INTO setting
(symbol, type, value, note)
VALUES ('NEEDS_1035_UPGRADE', 'hidden', "1", "Whether the upgrade for person statuses needs to be run");

-- Issue #1069 - 'archived by system' notes should not require action
update _abstract_note set status = 'no_action' where subject = 'Archived by system';
