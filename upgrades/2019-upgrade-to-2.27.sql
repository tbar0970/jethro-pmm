ALTER TABLE roster_view
ADD COLUMN visibility VARCHAR(255) NOT NULL DEFAULT '';

UPDATE roster_view
SET visibility = 'public' WHERE is_public = 1;

ALTER TABLE roster_view
DROP COLUMN is_public;
