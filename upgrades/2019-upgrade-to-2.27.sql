ALTER TABLE roster_view
ADD COLUMN visibility VARCHAR(255) NOT NULL DEFAULT '';

UPDATE roster_view
SET visibility = 'public' WHERE is_public = 1;

ALTER TABLE roster_view
DROP COLUMN is_public;

INSERT INTO setting
(rank, heading, symbol, note, type, value)
SELECT rank-1, heading, 'PUBLIC_AREA_ENABLED', 'Whether to allow public access to certain info at <system_url>public', 'bool', 1
FROM setting WHERE symbol = 'SHOW_SERVICE_NOTES_PUBLICLY';

UPDATE setting SET heading = '' WHERE symbol = 'SHOW_SERVICE_NOTES_PUBLICLY';