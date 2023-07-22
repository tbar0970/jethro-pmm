DROP view person;

CREATE VIEW person AS
SELECT * from _person p
WHERE
    getCurrentUserID() IS NOT NULL
    AND (
        /* the person in question IS the current user */
        (`p`.`id` = `getCurrentUserID`())

        OR /* we've been set to public mode */
        (`getCurrentUserID`() = -(1))

        OR /* current user has no group/cong restrictions */
        ((0 = (select count(cr.congregationid) from account_congregation_restriction cr WHERE cr.personid = getCurrentUserID()))
           AND (0 = (select count(gr.groupid) from account_group_restriction gr WHERE gr.personid = getCurrentUserID())))

        OR /* person is within a permitted cong */
        (`p`.`congregationid` in (select `cr`.`congregationid` AS `congregationid` from `account_congregation_restriction` `cr` where (`cr`.`personid` = `getCurrentUserID`())))

        OR
        /* person is within a permitted group */
        (`p`.`id` in (select `m`.`personid` AS `personid` from (`person_group_membership` `m` join `account_group_restriction` `gr` on((`m`.`groupid` = `gr`.`groupid`))) where (`gr`.`personid` = `getCurrentUserID`())))
    );

INSERT INTO setting (rank, heading, symbol, note, type, value)
SELECT rank-1, 'iCal Feeds', 'ROSTER_FEEDS_ENABLED', 'Whether users can access their roster assignments via an ical feed with secret URL', 'bool', 1
FROM setting WHERE symbol = 'PUBLIC_AREA_ENABLED';

INSERT INTO setting (rank, heading, symbol, note, type, value)
SELECT rank-1, '', 'DEFAULT_NOTE_STATUS', 'Default status when creating a new note', 'select{"no_action":"No Action Required","pending":"Requires Action"}', 'pending'
FROM setting WHERE symbol = 'NOTES_ORDER';

SELECT @newrank:=rank+1 from setting where symbol = 'NOTES_ORDER';
UPDATE setting SET rank = @newrank WHERE symbol = 'NOTES_LINK_TO_EDIT';


UPDATE roster_role set volunteer_group = NULL
where volunteer_group NOT IN (select id from _person_group);

ALTER TABLE roster_role
ADD CONSTRAINT `rr_groupid` FOREIGN KEY (volunteer_group) REFERENCES _person_group(id) ON DELETE RESTRICT;

ALTER TABLE service
ADD CONSTRAINT `service_congregationid` FOREIGN KEY (congregationid) REFERENCES congregation(id) ON DELETE RESTRICT;

ALTER TABLE congregation
DROP column print_quantity;

ALTER TABLE congregation
ADD COLUMN holds_persons VARCHAR(255) NOT NULL DEFAULT '1';