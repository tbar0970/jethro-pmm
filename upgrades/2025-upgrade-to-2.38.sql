-- #1224 - configurable member-visible folders
INSERT INTO setting
(`rank`, symbol, type, value, note)
SELECT `rank`+1, 'MEMBER_VISIBLE_FOLDERS', 'text', 'Member_Files', 'Folders in Documents which, if they exist, are visible to Members and can be used to share documents with them. Separate multiple directories with a pipe (''|'') character'
FROM setting
WHERE symbol = 'MEMBERS_SEE_AGE_BRACKET'
AND NOT EXISTS (
	select 1 from setting s2 where s2.symbol='MEMBER_VISIBLE_FOLDERS'
);
