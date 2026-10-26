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

CREATE OR REPLACE VIEW member AS
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
WHERE up.id = getCurrentMemberID()
   AND (NOT mps.is_archived)    /* dont show archived persons */
   AND mf.status <> "archived"  /* dont show archived families */
   AND (NOT ups.is_archived)	/* dont let persons who are themselves archived see anything */
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
	self.id = getCurrentMemberID()
	AND ((NOT mps.is_archived) OR (mp.id = self.id))
	AND ((NOT selfs.is_archived) OR (mp.id = self.id))
	/* archived persons can only see themselves, not any family members */
;
