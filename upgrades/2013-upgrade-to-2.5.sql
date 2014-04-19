
CREATE TABLE person_group_membership_status (
	id INT AUTO_INCREMENT PRIMARY KEY,
	label VARCHAR(255) NOT NULL,
	is_default TINYINT(1) UNSIGNED DEFAULT 0,
	CONSTRAINT UNIQUE INDEX (label)
) Engine=InnoDB;

INSERT INTO person_group_membership_status (label, is_default)
VALUES ('Member', 1);

ALTER TABLE person_group_membership
ADD membership_status INT DEFAULT NULL;

ALTER TABLE person_group_membership
ADD CONSTRAINT `membership_status_fk` 
FOREIGN KEY (membership_status) REFERENCES person_group_membership_status (id) ON DELETE SET NULL;
