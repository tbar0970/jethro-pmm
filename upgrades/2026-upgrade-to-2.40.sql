-- Set flag causing the home page to warn sysadmin users that the database charset needs upgrading
INSERT INTO setting
(symbol, type, value, note)
VALUES ('NEEDS_UTF8MB4_UPGRADE', 'hidden', "1", "Whether the database charset upgrade to utf8mb4 needs to be run");

SET @rank = (SELECT `rank` FROM setting WHERE symbol = 'MEMBER_LOGIN_ENABLED');

INSERT INTO setting
(`rank`, symbol, type, value, note)
VALUES (@rank+1, 'MEMBER_SWAP_ENABLED', 'bool', "0", "Should church members be able swap roster assignments themselves?");

ALTER TABLE roster_role_assignment
ADD COLUMN pending_personid INT(11) DEFAULT NULL,
ADD CONSTRAINT `rra_pending_personid` FOREIGN KEY (pending_personid) REFERENCES _person(id) ON DELETE SET NULL;