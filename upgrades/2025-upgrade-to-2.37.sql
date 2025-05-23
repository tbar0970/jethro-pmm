-- #1213 - update the installer-specified default SMS length of 140 to 160
update setting set value=160 where symbol='SMS_MAX_LENGTH' and value=140;

-- #1198 - fix any assignedon columns that haven't got the auto-update flag set.
--  (any existing null values become 1 Jan 2000)
UPDATE roster_role_assignment SET assignedon="2000-01-01" where assignedon IS NULL;
ALTER TABLE roster_role_assignment MODIFY COLUMN assignedon TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;