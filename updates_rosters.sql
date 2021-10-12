ALTER TABLE `service` ADD `categoryid` INT(11) NULL DEFAULT NULL AFTER `comments`; 
ALTER TABLE `service` DROP INDEX `datecong`, ADD UNIQUE `datecong` (`date`, `congregationid`, `categoryid`); 
ALTER TABLE `roster_view` ADD `categoryid` INT(11) NULL DEFAULT NULL AFTER `visibility`; 

CREATE TABLE `person_unavailable` ( `id` INT NOT NULL AUTO_INCREMENT, `personid` INT NOT NULL , `from_date` DATE NOT NULL, `to_date` DATE NULL DEFAULT NULL , PRIMARY KEY (`id`)); 
ALTER TABLE `person_unavailable` ADD UNIQUE `unaval_personid` (`personid`, `from_date`); 
ALTER TABLE `person_unavailable` ADD CONSTRAINT `unaval_person` FOREIGN KEY (`personid`) REFERENCES `_person`(`id`) ON DELETE CASCADE ON UPDATE RESTRICT; 
