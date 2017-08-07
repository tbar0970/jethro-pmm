/* Fix #390 */
DROP VIEW person_group;
CREATE VIEW person_group AS
SELECT * from _person_group g
WHERE
  getCurrentUserID() IS NOT NULL
  AND
  ((g.owner IS NULL) OR (g.owner = getCurrentUserID()))
  AND
  (NOT EXISTS (SELECT * FROM account_group_restriction gr WHERE gr.personid  = getCurrentUserID())
  OR g.id IN (SELECT groupid FROM account_group_restriction gr WHERE gr.personid = getCurrentUserID()));