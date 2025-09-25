-- #1213 - update the installer-specified default SMS length of 140 to 160
update setting set value=160 where symbol='SMS_MAX_LENGTH' and value=140;

-- #1198 - fix any assignedon columns that haven't got the auto-update flag set.
--  (any existing null values become 1 Jan 2000)
UPDATE roster_role_assignment SET assignedon="2000-01-01" where assignedon IS NULL;
ALTER TABLE roster_role_assignment MODIFY COLUMN assignedon TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;

-- #1227 - fix CCLI song links
update setting set value='https://songselect.ccli.com/songs/__NUMBER__' where value='https://au.songselect.com/songs/__NUMBER__' and  symbol='CCLI_DETAIL_URL';
update setting set value='https://songselect.ccli.com/search/results?search=__TITLE__' where value='http://us.search.ccli.com/search/results?SearchText=__TITLE__' and  symbol='CCLI_SEARCH_URL';
-- #1224 - configurable member-visible folders
INSERT INTO setting
(`rank`, symbol, type, value, note)
SELECT `rank`+1, 'MEMBER_VISIBLE_FOLDERS', 'text', 'Member_Files', 'Folders in Documents which, if they exist, are visible to Members and can be used to share documents with them. Separate multiple directories with a pipe (''|'') character'
FROM setting
WHERE symbol = 'MEMBERS_SEE_AGE_BRACKET'
AND NOT EXISTS (
	select 1 from setting s2 where s2.symbol='MEMBER_VISIBLE_FOLDERS'
);
