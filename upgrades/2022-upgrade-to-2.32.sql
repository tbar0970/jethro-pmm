/* issue 764 */
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