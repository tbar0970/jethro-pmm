-- Set flag causing the home page to warn sysadmin users that the database charset needs upgrading
INSERT IGNORE INTO setting
(symbol, type, value, note)
VALUES ('NEEDS_UTF8MB4_UPGRADE', 'hidden', "1", "Whether the database charset upgrade to utf8mb4 needs to be run");

-- Separate members-area identity from staff-account identity (#1173, #1433).
-- @current_user_id is the staff account; its congregation/group restrictions are
-- applied by the person and person_group views.  The members area now sets
-- @current_member_id instead, so a member who also has a restricted staff account
-- no longer loses sight of their own family / roster assignees in the members area.
-- See docs/docs/developer/reference/db-session-identity.md.  Synced with
-- include/installer.class.php, db_objects/person.class.php and
-- db_objects/person_group.class.php.  Idempotent.
-- DROP+CREATE rather than CREATE OR REPLACE FUNCTION: MariaDB supports the latter,
-- MySQL (8.0+, per README requirements) does not.  This is idempotent on both.
DROP FUNCTION IF EXISTS getCurrentMemberID;
CREATE FUNCTION getCurrentMemberID() RETURNS INTEGER NO SQL RETURN @current_member_id;

-- Shared data-permission vocabulary (include/data_permissions.php).  The
-- viewer-level staff check (currentUserHasDataPerm on the #1609 line) can be
-- built on hasDataPerm(); members-area consent tests the consent mask itself.
DROP FUNCTION IF EXISTS dataPermBit;
CREATE FUNCTION dataPermBit(symbol VARCHAR(32)) RETURNS INTEGER NO SQL
RETURN CASE symbol
         WHEN 'VIEWNAME' THEN 1
         WHEN 'VIEWADDRESS' THEN 134217728
         WHEN 'VIEWEMAIL' THEN 536870912
         WHEN 'VIEWMOBILE' THEN 268435456
         WHEN 'VIEWREMARKS' THEN 33554432
         WHEN 'VIEWCUSTOMFIELDS' THEN 67108864
         WHEN 'VIEWSENSITIVE' THEN 16777216
         ELSE 0
       END;

DROP FUNCTION IF EXISTS hasDataPerm;
CREATE FUNCTION hasDataPerm(mask BIGINT, symbol VARCHAR(32)) RETURNS TINYINT NO SQL
RETURN (mask & dataPermBit(symbol)) <> 0;

CREATE OR REPLACE VIEW person AS
SELECT * from _person p
WHERE
	/* members-area session: staff restrictions do not apply */
	(getCurrentMemberID() IS NOT NULL)
	OR (
	getCurrentUserID() IS NOT NULL
	AND (
		/* the person in question IS the current user */
		(`p`.`id` = `getCurrentUserID`())
		OR /* we've been set to public mode */
		(`getCurrentUserID`() = -(1))
		OR /* current user has no group/cong restrictions */
		((0 = (select count(congregationid) from account_congregation_restriction cr WHERE cr.personid = getCurrentUserID()))
		   AND (0 = (select count(gr.groupid) from account_group_restriction gr WHERE gr.personid = getCurrentUserID())))
		OR /* person is within a permitted cong */
		(`p`.`congregationid` in (select `cr`.`congregationid` AS `congregationid` from `account_congregation_restriction` `cr` where (`cr`.`personid` = `getCurrentUserID`())))
		OR /* person is within a permitted group */
		(`p`.`id` in (select `m`.`personid` AS `personid` from (`person_group_membership` `m` join `account_group_restriction` `gr` on((`m`.`groupid` = `gr`.`groupid`))) where (`gr`.`personid` = `getCurrentUserID`())))
	)
	);

CREATE OR REPLACE VIEW person_group AS
SELECT * from _person_group g
WHERE
  (getCurrentMemberID() IS NOT NULL AND g.owner IS NULL)
  OR (
  getCurrentUserID() IS NOT NULL
  AND
  ((g.owner IS NULL) OR (g.owner = getCurrentUserID()))
  AND
  (NOT EXISTS (SELECT * FROM account_group_restriction gr WHERE gr.personid = getCurrentUserID())
  OR g.id IN (SELECT groupid FROM account_group_restriction gr WHERE gr.personid = getCurrentUserID()))
  );

-- Members-area directory share consent (#792): one row per person+group,
-- data_permissions holding bits from include/data_permissions.php
-- (DATA_PERM_VIEWNAME = 1).  groupid 0 stores the person's DEFAULT consent,
-- which applies to could-share groups where no explicit row exists.  Rows are
-- independent of membership: they survive members leaving and rejoining a
-- group.  See docs/docs/developer/reference/member-share-consent.md.
-- Idempotent.  (No FK on groupid: the default row has no group to reference.)
CREATE TABLE IF NOT EXISTS `_person_share_consent` (
	`personid` INT NOT NULL,
	`groupid` INT NOT NULL,
	`data_permissions` INT UNSIGNED NOT NULL DEFAULT 0,
	`inherit_mask` TINYINT UNSIGNED NOT NULL DEFAULT 0,
	`consented_on` DATETIME NOT NULL,
	`granted_by` ENUM('user','staff','upgrade') NOT NULL,
	PRIMARY KEY (`personid`, `groupid`),
	CONSTRAINT `psc_personid` FOREIGN KEY (`personid`) REFERENCES `_person` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Existing installs that already created the table without per-column
-- inheritance (#792).  MySQL has no ADD COLUMN IF NOT EXISTS, so consult the
-- information schema first; works on MySQL and MariaDB.  Idempotent.
SET @psc_inherit_add = (
	SELECT IF(COUNT(*) = 0,
		'ALTER TABLE `_person_share_consent` ADD COLUMN `inherit_mask` TINYINT UNSIGNED NOT NULL DEFAULT 0',
		'DO 0')
	FROM information_schema.COLUMNS
	WHERE TABLE_SCHEMA = DATABASE()
	  AND TABLE_NAME = '_person_share_consent'
	  AND COLUMN_NAME = 'inherit_mask');
PREPARE psc_inherit_stmt FROM @psc_inherit_add;
EXECUTE psc_inherit_stmt;
DEALLOCATE PREPARE psc_inherit_stmt;

-- Existing installs may have created granted_by without the 'upgrade' value.
SET @psc_granted_by_enum = (
	SELECT IF(COLUMN_TYPE NOT LIKE '%upgrade%',
		'ALTER TABLE `_person_share_consent` MODIFY `granted_by` ENUM(''user'',''staff'',''upgrade'') NOT NULL',
		'DO 0')
	FROM information_schema.COLUMNS
	WHERE TABLE_SCHEMA = DATABASE()
	  AND TABLE_NAME = '_person_share_consent'
	  AND COLUMN_NAME = 'granted_by');
PREPARE psc_granted_by_stmt FROM @psc_granted_by_enum;
EXECUTE psc_granted_by_stmt;
DEALLOCATE PREPARE psc_granted_by_stmt;

-- One-time marker so re-running this upgrade can never re-grant consent a
-- member has since withdrawn (returning both columns to their default deletes
-- the row, so INSERT IGNORE alone would silently re-grant it).  Hidden setting
-- rows are not rendered or saved by the System Configuration page.
INSERT IGNORE INTO setting (`rank`, heading, symbol, note, type, value)
SELECT COALESCE(MAX(`rank`), 0) + 1, '', 'SHARE_CONSENT_BACKFILLED',
	'Internal: legacy directory-consent backfill has run (#792)', 'hidden', '0'
FROM setting;

-- Members of share-enabled groups consented implicitly under the old
-- group-wide model, so they keep sharing (805306369 = DATA_PERM_VIEWNAME |
-- DATA_PERM_VIEWEMAIL | DATA_PERM_VIEWMOBILE), recorded as staff-set at
-- upgrade time.  Runs at most once.
INSERT IGNORE INTO _person_share_consent (personid, groupid, data_permissions, consented_on, granted_by)
SELECT pgm.personid, pgm.groupid, 805306369, NOW(), 'upgrade'
FROM person_group_membership pgm
JOIN _person_group pg ON pg.id = pgm.groupid AND pg.share_member_details = 1
WHERE (SELECT value FROM setting WHERE symbol = 'SHARE_CONSENT_BACKFILLED') = '0';

UPDATE setting SET value = '1' WHERE symbol = 'SHARE_CONSENT_BACKFILLED';

-- Consent semantics: co-members of a could-share group are listed where their
-- effective Visible consent holds; each column falls back to the person's
-- default row (groupid 0) when it inherits (inherit_mask bits 1/2).  Contact
-- fields are blanked unless the effective contact bits are set.  The viewer's
-- own family is always listed with full details.  Synced with
-- include/installer.class.php (member view).
CREATE OR REPLACE VIEW member AS
SELECT d.id, d.first_name, d.last_name, d.gender, d.age_bracketid, d.congregationid,
CASE WHEN hasDataPerm(d.eff_mask, 'VIEWEMAIL') THEN d.email ELSE '' END AS email,
CASE WHEN hasDataPerm(d.eff_mask, 'VIEWMOBILE') THEN d.mobile_tel ELSE '' END AS mobile_tel,
CASE WHEN hasDataPerm(d.eff_mask, 'VIEWMOBILE') THEN d.work_tel ELSE '' END AS work_tel,
d.familyid,
d.family_name, d.address_street, d.address_suburb, d.address_state, d.address_postcode, d.home_tel
FROM (
SELECT mp.id,
MAX(mp.first_name) AS first_name, MAX(mp.last_name) AS last_name,
MAX(mp.gender) AS gender, MAX(mp.age_bracketid) AS age_bracketid,
MAX(mp.congregationid) AS congregationid,
MAX(mp.email) AS email, MAX(mp.mobile_tel) AS mobile_tel,
MAX(mp.work_tel) AS work_tel, MAX(mp.familyid) AS familyid,
MAX(mf.family_name) AS family_name, MAX(mf.address_street) AS address_street,
MAX(mf.address_suburb) AS address_suburb, MAX(mf.address_state) AS address_state,
MAX(mf.address_postcode) AS address_postcode, MAX(mf.home_tel) AS home_tel,
MAX(((CASE WHEN psc.personid IS NULL OR (psc.inherit_mask & 1) <> 0
       THEN COALESCE(pscd.data_permissions, 0) & 1
       ELSE psc.data_permissions & 1 END)
 | (CASE WHEN psc.personid IS NULL OR (psc.inherit_mask & 2) <> 0
       THEN COALESCE(pscd.data_permissions, 0) & (dataPermBit('VIEWEMAIL') | dataPermBit('VIEWMOBILE'))
       ELSE psc.data_permissions & (dataPermBit('VIEWEMAIL') | dataPermBit('VIEWMOBILE')) END))) AS eff_mask
FROM _person mp
JOIN person_status mps ON mps.id = mp.status
JOIN family mf ON mf.id = mp.familyid
JOIN person_group_membership pgm1 ON pgm1.personid = mp.id
LEFT JOIN _person_share_consent psc ON psc.personid = pgm1.personid AND psc.groupid = pgm1.groupid
LEFT JOIN _person_share_consent pscd ON pscd.personid = pgm1.personid AND pscd.groupid = 0
JOIN _person_group pg ON pg.id = pgm1.groupid AND pg.share_member_details = 1 AND NOT pg.is_archived
JOIN person_group_membership pgm2 ON pgm2.groupid = pg.id
JOIN _person up ON up.id = pgm2.personid
JOIN person_status ups ON ups.id = up.status
WHERE up.id = getCurrentMemberID()
   AND mp.familyid <> (SELECT familyid FROM _person WHERE id = getCurrentMemberID()) /* own family handled below */
   AND (NOT mps.is_archived)    /* dont show archived persons */
   AND mf.status <> "archived"  /* dont show archived families */
   AND (NOT ups.is_archived)	/* dont let persons who are themselves archived see anything */
GROUP BY mp.id
UNION ALL
SELECT mp.id, mp.first_name, mp.last_name, mp.gender, mp.age_bracketid, mp.congregationid,
mp.email, mp.mobile_tel, mp.work_tel, mp.familyid,
mf.family_name, mf.address_street, mf.address_suburb, mf.address_state, mf.address_postcode, mf.home_tel,
(dataPermBit('VIEWNAME') | dataPermBit('VIEWEMAIL') | dataPermBit('VIEWMOBILE')) AS eff_mask
FROM _person mp
JOIN person_status mps ON mps.id = mp.status
JOIN family mf ON mf.id = mp.familyid
JOIN _person self ON self.familyid = mp.familyid
JOIN person_status selfs ON selfs.id = self.status
WHERE
	self.id = getCurrentMemberID()
	AND ((NOT mps.is_archived) OR (mp.id = self.id))
	AND ((NOT selfs.is_archived) OR (mp.id = self.id))
	/* archived persons can only see themselves, not any family members */
) d
WHERE hasDataPerm(d.eff_mask, 'VIEWNAME') /* consented, or own family */
;

SET @rank = (SELECT `rank` FROM setting WHERE symbol = 'MEMBER_LOGIN_ENABLED');

INSERT IGNORE INTO setting
(`rank`, symbol, type, value, note)
VALUES (@rank+1, 'MEMBER_SWAP_ENABLED', 'bool', "0", "Should church members be able to swap roster assignments themselves?");

-- Idempotent column/FK additions via information_schema + PREPARE:
-- Unfortunately MySQL 8.0 lacks support for: ADD CONSTRAINT `rra_pending_personid` FOREIGN KEY IF NOT EXISTS (pending_personid)
SET @sql = IF(
	(SELECT COUNT(*) FROM information_schema.COLUMNS
	 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'roster_role_assignment' AND COLUMN_NAME = 'pending_personid') = 0,
	'ALTER TABLE roster_role_assignment ADD COLUMN pending_personid INT(11) DEFAULT NULL',
	'DO 0');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = IF(
	(SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
	 WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'roster_role_assignment' AND CONSTRAINT_NAME = 'rra_pending_personid') = 0,
	'ALTER TABLE roster_role_assignment ADD CONSTRAINT `rra_pending_personid` FOREIGN KEY (pending_personid) REFERENCES _person(id) ON DELETE SET NULL',
	'DO 0');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Expose the sendmail transport alternative on the System Configuration page.
-- Synced with include/installer.class.php and include/config_manager.class.php
-- (isFileOnlySetting). Idempotent.
INSERT IGNORE INTO setting (`rank`, heading, symbol, note, type, value)
SELECT `rank` + 1, '', 'SENDMAIL_COMMAND',
    'Send mail via this sendmail-compatible command instead of SMTP_SERVER. Include -bs to speak SMTP over the pipe.',
    'text', ''
FROM setting
WHERE symbol = 'SMTP_SERVER';
