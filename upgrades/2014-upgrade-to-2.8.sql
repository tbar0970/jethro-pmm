ALTER TABLE _person
ADD COLUMN member_password VARCHAR(255) DEFAULT NULL;

ALTER TABLE _person
ADD COLUMN resethash VARCHAR(255) DEFAULT NULL;

ALTER TABLE _person 
ADD COLUMN resetexpires DATETIME DEFAULT NULL;

ALTER TABLE _person_group
ADD COLUMN share_member_details varchar(255) default "0";

DROP VIEW person_group;

CREATE VIEW person_group
AS select `g`.* from `_person_group` `g` where ((`getCurrentUserID`() is not null) and ((not(exists(select 1 from `account_group_restriction` `gr` where (`gr`.`personid` = `getCurrentUserID`())))) or `g`.`id` in (select `gr`.`groupid` from `account_group_restriction` `gr` where (`gr`.`personid` = `getCurrentUserID`()))));

DROP VIEW IF EXISTS member;
CREATE VIEW member AS
SELECT mp.id, mp.first_name, mp.last_name, mp.gender, mp.age_bracket, mp.congregationid,
mp.email, mp.mobile_tel, mp.work_tel, mp.familyid,
mf.family_name, mf.address_street, mf.address_suburb, mf.address_state, mf.address_postcode, mf.home_tel
FROM _person mp
JOIN family mf ON mf.id = mp.familyid
JOIN person_group_membership pgm1 ON pgm1.personid = mp.id
JOIN _person_group pg ON pg.id = pgm1.groupid AND pg.share_member_details = 1
JOIN person_group_membership pgm2 ON pgm2.groupid = pg.id
JOIN _person up ON up.id = pgm2.personid
WHERE up.id = getCurrentUserID() AND up.status <> "archived"
/* archived persons cannot see members of any group */
UNION

SELECT mp.id, mp.first_name, mp.last_name, mp.gender, mp.age_bracket, mp.congregationid,
mp.email, mp.mobile_tel, mp.work_tel, mp.familyid,
mf.family_name, mf.address_street, mf.address_suburb, mf.address_state, mf.address_postcode, mf.home_tel
FROM _person mp
JOIN family mf ON mf.id = mp.familyid
JOIN _person self ON self.familyid = mp.familyid
WHERE self.id = getCurrentUserID()
AND ((self.status <> "archived") OR (mp.id = self.id))
/* archived persons can only see themselves, not any family members */
;