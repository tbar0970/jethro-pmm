/* fix #654 and #652 part B */
DROP VIEW abstract_note;
create view abstract_note as
select an.* from _abstract_note an
WHERE ((an.assignee = getCurrentUserID() AND an.status = 'pending')
OR (`getCurrentUserID`() = -(1))
OR (48 = (SELECT permissions & 48 FROM staff_member WHERE id = getCurrentUserID())));

/* Issue #613 - headcount tables missing its 2-part unique key
   Where there are multi headcounts due to the bug, keep the biggest ones */

DROP TABLE IF EXISTS _disused_cong_headcount;
ALTER TABLE congregation_headcount RENAME TO _disused_cong_headcount;
CREATE TABLE `congregation_headcount` (
 `date` date NOT NULL,
 `congregationid` int(11) NOT NULL,
 `number` int(11) NOT NULL,
 PRIMARY KEY (`date`,`congregationid`),
 KEY `congregationid` (`congregationid`),
 CONSTRAINT `congregation_headcount_congid2` FOREIGN KEY (`congregationid`) REFERENCES `congregation` (`id`)
) ENGINE=InnoDB;
INSERT INTO congregation_headcount
SELECT `date`, congregationid, MAX(number)
FROM _disused_cong_headcount dch
join congregation c on c.id = dch.congregationid
group by `date`, congregationid
having max(number) > 0;

DROP TABLE IF EXISTS _disused_group_headcount;
ALTER TABLE person_group_headcount RENAME TO _disused_group_headcount;
CREATE TABLE `person_group_headcount` (
 `date` date NOT NULL,
 `person_groupid` int(11) NOT NULL,
 `number` int(11) NOT NULL,
 PRIMARY KEY (`date`,`person_groupid`),
 KEY `person_group_headcount_groupid` (`person_groupid`),
 CONSTRAINT `person_group_headcount_groupid5` FOREIGN KEY (`person_groupid`) REFERENCES `_person_group` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB;
INSERT INTO person_group_headcount
SELECT `date`, person_groupid, MAX(number)
FROM _disused_group_headcount dgh
join _person_group pg on pg.id = dgh.person_groupid
group by `date`, person_groupid
having max(number) > 0;
