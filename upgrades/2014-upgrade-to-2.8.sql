ALTER TABLE _person
ADD COLUMN password VARCHAR(255) DEFAULT NULL;

ALTER TABLE _person
ADD COLUMN resethash VARCHAR(255) DEFAULT NULL;

ALTER TABLE _person 
ADD COLUMN resetexpires DATETIME DEFAULT NULL;

ALTER TABLE _person_group
ADD COLUMN share_member_details varchar(255) default 0;

ALTER VIEW person_group
AS select `g`.* from `_person_group` `g` where ((`getCurrentUserID`() is not null) and ((not(exists(select 1 from `account_group_restriction` `gr` where (`gr`.`personid` = `getCurrentUserID`())))) or `g`.`id` in (select `gr`.`groupid` from `account_group_restriction` `gr` where (`gr`.`personid` = `getCurrentUserID`()))))

