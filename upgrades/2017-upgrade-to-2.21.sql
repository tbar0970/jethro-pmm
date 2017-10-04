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

/* Fix #397 */
SET @rank = (SELECT rank FROM setting WHERE symbol = 'SMTP_SERVER');
INSERT INTO setting
(rank, heading, symbol, note, type, value)
VALUES
(@rank+1, NULL, 'SMTP_PORT', 'Port to connect to the SMTP server. Usually 25, 465 for SSL, or 587 for TLS.', 'int', '25');
>>>>>>> master
