ALTER TABLE _person
ADD COLUMN feed_uuid VARCHAR(64) DEFAULT NULL;

DROP VIEW person;

CREATE VIEW `person` AS 
select p.* from `_person` `p` where ((`getCurrentUserID`() is not null) and ((`p`.`id` = `getCurrentUserID`()) or (`getCurrentUserID`() = -(1)) or (((not(exists(select 1 AS `Not_used` from `account_congregation_restriction` `cr` where (`cr`.`personid` = `getCurrentUserID`())))) or `p`.`congregationid` in (select `cr`.`congregationid` AS `congregationid` from `account_congregation_restriction` `cr` where (`cr`.`personid` = `getCurrentUserID`()))) and ((not(exists(select 1 AS `Not_used` from `account_group_restriction` `gr` where (`gr`.`personid` = `getCurrentUserID`())))) or `p`.`id` in (select `m`.`personid` AS `personid` from (`person_group_membership` `m` join `account_group_restriction` `gr` on((`m`.`groupid` = `gr`.`groupid`))) where (`gr`.`personid` = `getCurrentUserID`()))))));

