/* issue 764 */
delete FROM `roster_view_role_membership` where roster_view_id not in (select id from roster_view);
ALTER TABLE roster_view_role_membership ADD CONSTRAINT FOREIGN KEY (`roster_view_id`) REFERENCES `roster_view`(`id`) ON DELETE CASCADE;
ALTER TABLE roster_view_role_membership ADD CONSTRAINT FOREIGN KEY (`roster_role_id`) REFERENCES `roster_role`(`id`) ON DELETE RESTRICT;

/* issue #698 */
DELETE FROM service_item WHERE serviceid NOT IN (select id from service);
ALTER TABLE service_item ADD CONSTRAINT `service_item_componentid` FOREIGN KEY (`componentid`) REFERENCES `service_component` (`id`) ON DELETE RESTRICT;
ALTER TABLE service_item ADD CONSTRAINT `service_item_serviceid` FOREIGN KEY (`serviceid`) REFERENCES `service` (`id`) ON DELETE CASCADE;
