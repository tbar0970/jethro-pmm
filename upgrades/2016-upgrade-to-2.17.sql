ALTER TABLE custom_field
ADD COLUMN tooltip TEXT;

/* fix ssue #257 by making sure the foreign key has ON DELETE CASCADE */
ALTER TABLE person_group_headcount
RENAME TO person_group_hc_old;

CREATE TABLE `person_group_headcount` (
 `date` date NOT NULL,
 `person_groupid` int(11) NOT NULL,
 `number` int(11) NOT NULL,
 PRIMARY KEY (`date`,`person_groupid`),
 CONSTRAINT `person_group_headcount_groupid` FOREIGN KEY (`person_groupid`) REFERENCES `_person_group` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

INSERT INTO person_group_headcount select * from person_group_hc_old;

DROP TABLE person_group_hc_old;
