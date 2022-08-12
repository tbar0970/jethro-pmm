/* issue #764 */
delete FROM `roster_view_role_membership` where roster_view_id not in (select id from roster_view);
ALTER TABLE roster_view_role_membership ADD CONSTRAINT FOREIGN KEY (`roster_view_id`) REFERENCES `roster_view`(`id`) ON DELETE CASCADE;
ALTER TABLE roster_view_role_membership ADD CONSTRAINT FOREIGN KEY (`roster_role_id`) REFERENCES `roster_role`(`id`) ON DELETE RESTRICT;

/* issue #698,#626 */
DELETE FROM service_item WHERE serviceid NOT IN (select id from service);
ALTER TABLE service_item ADD CONSTRAINT `service_item_componentid` FOREIGN KEY (`componentid`) REFERENCES `service_component` (`id`) ON DELETE RESTRICT;
ALTER TABLE service_item ADD CONSTRAINT `service_item_serviceid` FOREIGN KEY (`serviceid`) REFERENCES `service` (`id`) ON DELETE CASCADE;

alter table service_component add constraint `service_component_cat` foreign key (`categoryid`) references `service_component_category` (`id`) ON DELETE RESTRICT;

DELETE FROM congregation_service_component 
   WHERE (congregationid NOT IN (select id FROM congregation))
      OR (componentid NOT IN (select id from service_component));
alter table congregation_service_component add constraint `congregation_service_component_cong` foreign key (`congregationid`) references `congregation` (`id`) ON DELETE CASCADE;
alter table congregation_service_component add constraint `congregation_service_component_comp` foreign key (`componentid`) references `service_component` (`id`) ON DELETE CASCADE;

/* issue #465 */
UPDATE _person_group
SET categoryid = NULL where categoryid not in (select id from person_group_category);

alter table _person_group add constraint `person_group_cat` foreign key (`categoryid`) REFERENCES `person_group_category` (`id`) ON DELETE SET NULL;

/* issue 813 - fix (or add) FKs on the planned_absence table */

CREATE TABLE planned_absence_temp select * from planned_absence;
DROP TABLE planned_absence;

CREATE TABLE `planned_absence` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `personid` int(11) NOT NULL DEFAULT '0',
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `comment` text NOT NULL,
  `created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `creator` int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  CONSTRAINT `planned_absencecreator` FOREIGN KEY (`creator`) REFERENCES `_person` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `planned_absencepersonid` FOREIGN KEY (`personid`) REFERENCES `_person` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

INSERT INTO planned_absence
SELECT * from planned_absence_temp;

DROP TABLE planned_absence_temp;