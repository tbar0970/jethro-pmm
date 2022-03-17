/* issue 764 */
delete FROM `roster_view_role_membership` where roster_view_id not in (select id from roster_view);
ALTER TABLE roster_view_role_membership ADD CONSTRAINT FOREIGN KEY (`roster_view_id`) REFERENCES `roster_view`(`id`) ON DELETE CASCADE;
ALTER TABLE roster_view_role_membership ADD CONSTRAINT FOREIGN KEY (`roster_role_id`) REFERENCES `roster_role`(`id`) ON DELETE RESTRICT;
