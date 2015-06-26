/* Fix Issue #51 */
drop view member;
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
			WHERE up.id = getCurrentUserID()
			   AND mp.status <> "archived"
			   AND mf.status <> "archived"
			   AND up.status <> "archived"	/* archived persons cannot see members of any group */

			UNION

			SELECT mp.id, mp.first_name, mp.last_name, mp.gender, mp.age_bracket, mp.congregationid,
			mp.email, mp.mobile_tel, mp.work_tel, mp.familyid,
			mf.family_name, mf.address_street, mf.address_suburb, mf.address_state, mf.address_postcode, mf.home_tel
			FROM _person mp
			JOIN family mf ON mf.id = mp.familyid
			JOIN _person self ON self.familyid = mp.familyid
			WHERE
				self.id = getCurrentUserID()
				AND mp.status <> "archived"
				AND mf.status <> "archived"
				AND ((self.status <> "archived") OR (mp.id = self.id))
				/* archived persons can only see themselves, not any family members */
			;

/* ordering of group membership statuses */
alter table person_group_membership_status
add column rank int not null default 0;