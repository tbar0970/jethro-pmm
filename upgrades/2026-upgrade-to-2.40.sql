-- Set flag causing the home page to warn sysadmin users that the database charset needs upgrading
INSERT INTO setting
(symbol, type, value, note)
VALUES ('NEEDS_UTF8MB4_UPGRADE', 'hidden', "1", "Whether the database charset upgrade to utf8mb4 needs to be run");
