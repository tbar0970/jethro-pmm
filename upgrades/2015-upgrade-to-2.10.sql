CREATE TABLE person_group_headcount (
					`date` DATE NOT NULL,
					`person_groupid` INT(11) NOT NULL,
					`number` INT(11) NOT NULL,
					PRIMARY KEY (`date`, `person_groupid`),
					CONSTRAINT FOREIGN KEY (`person_groupid`) REFERENCES `_person_group` (`id`)
				) Engine=InnoDB;

CREATE TABLE congregation_headcount (
					`date` DATE NOT NULL,
					`congregationid` INT(11) NOT NULL,
					`number` INT(11) NOT NULL,
					PRIMARY KEY (`date`, `congregationid`),
					CONSTRAINT FOREIGN KEY (`congregationid`) REFERENCES `congregation` (`id`)
				) Engine=InnoDB;