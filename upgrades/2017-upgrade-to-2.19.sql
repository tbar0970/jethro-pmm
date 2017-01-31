ALTER TABLE custom_field
ADD COLUMN show_add_family varchar(255) not null default 0;

ALTER TABLE _person_group
ADD COLUMN show_add_family varchar(255) not null default 'no';

DROP VIEW person_group;

CREATE VIEW person_group AS
SELECT * from _person_group g
WHERE
  getCurrentUserID() IS NOT NULL
  AND
  (NOT EXISTS (SELECT * FROM account_group_restriction gr WHERE gr.personid  = getCurrentUserID())
  OR g.id IN (SELECT groupid FROM account_group_restriction gr WHERE gr.personid = getCurrentUserID()));

