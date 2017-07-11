INSERT INTO setting(rank, heading, symbol, note, type, value) VALUES (98,"Attendance Recording","EXTRA_ATTENDANCE_CATEGORIES","","multitext_cm","Extras");

CREATE TABLE congregation_category_headcount (
					`date` DATE NOT NULL,
					`congregationid` INT(11) NOT NULL,
                    `category` varchar(30) NOT NULL,
					`number` INT(11) NOT NULL,
					PRIMARY KEY (`date`, `congregationid`)
				) Engine=InnoDB;

CREATE TABLE person_group_category_headcount (
					`date` DATE NOT NULL,
					`person_groupid` INT(11) NOT NULL,
                    `category` varchar(30) NOT NULL,
					`number` INT(11) NOT NULL,
					PRIMARY KEY (`date`, `person_groupid`)
				) Engine=InnoDB;
