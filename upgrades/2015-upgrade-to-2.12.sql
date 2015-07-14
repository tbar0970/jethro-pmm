ALTER TABLE _person_group
ADD COLUMN attendance_recording_days INT NOT NULL DEFAULT 0;

DROP VIEW person_group;
CREATE VIEW person_group AS 
select *
from `_person_group` `g` 
where ((`getCurrentUserID`() is not null) 
and ((not(exists(select 1 from `account_group_restriction` `gr` where (`gr`.`personid` = `getCurrentUserID`())))) or `g`.`id` in (select `gr`.`groupid` from `account_group_restriction` `gr` where (`gr`.`personid` = `getCurrentUserID`()))));

UPDATE _person_group
SET attendance_recording_days = 127 WHERE can_record_attendance = 1;

ALTER TABLE _person_group
DROP COLUMN can_record_attendance;

ALTER TABLE congregation
ADD COLUMN attendance_recording_days INT NOT NULL DEFAULT 1;