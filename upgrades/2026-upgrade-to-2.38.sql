-- #1224 - configurable member-visible folders
INSERT INTO setting
(`rank`, symbol, type, value, note)
SELECT `rank`+1, 'MEMBER_VISIBLE_FOLDERS', 'text', 'Member_Files', 'Folders in Documents which, if they exist, are visible to Members and can be used to share documents with them. Separate multiple directories with a pipe (''|'') character'
FROM setting
WHERE symbol = 'MEMBERS_SEE_AGE_BRACKET'
AND NOT EXISTS (
	select 1 from setting s2 where s2.symbol='MEMBER_VISIBLE_FOLDERS'
);

-- #1380 - allow for groups to have parent groups
CREATE TABLE `roster_role_team` (
	`roster_role_id` INT NOT NULL,
	`person_group_id` INT NOT NULL,
	PRIMARY KEY (roster_role_id, person_group_id),
	CONSTRAINT `roster_role_team_roster_role` FOREIGN KEY (`roster_role_id`) REFERENCES `roster_role` (`id`),
	CONSTRAINT `roster_role_team_person_group` FOREIGN KEY (`person_group_id`) REFERENCES `_person_group` (`id`)
);
