/* fix #654 and #652 part B */
DROP VIEW abstract_note;
create view abstract_note as
select an.* from _abstract_note an
WHERE ((an.assignee = getCurrentUserID() AND an.status = 'pending')
OR (`getCurrentUserID`() = -(1))
OR (48 = (SELECT permissions & 48 FROM staff_member WHERE id = getCurrentUserID())));
