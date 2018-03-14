ALTER TABLE roster_role_assignment
ADD COLUMN rank INT UNSIGNED NOT NULL DEFAULT 0
AFTER personid;


/* -- Remove NULL group membership statuses - Issue #303 */
/* make sure we have a default membership status */
INSERT INTO person_group_membership_status
(label, is_default, rank)
SELECT "Member", 1, 0 FROM DUAL
WHERE NOT EXISTS (SELECT * FROM person_group_membership_status);

UPDATE person_group_membership pgm
SET membership_status = (SELECT id FROM person_group_membership_status WHERE is_default)
WHERE membership_status IS NULL;

ALTER TABLE person_group_membership
DROP FOREIGN KEY membership_status_fk;

ALTER TABLE person_group_membership
MODIFY COLUMN membership_status INTEGER NOT NULL;

ALTER TABLE person_group_membership
ADD CONSTRAINT `membership_status_fk` FOREIGN KEY (membership_status) REFERENCES `person_group_membership_status` (`id`)
ON DELETE RESTRICT;