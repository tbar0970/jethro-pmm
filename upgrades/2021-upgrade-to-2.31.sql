DROP VIEW person;

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
        ((0 = (select count(congregationid) from account_congregation_restriction cr WHERE cr.personid = getCurrentUserID()))
           AND (0 = (select count(congregationid) from account_group_restriction cr WHERE cr.personid = getCurrentUserID())))

        OR /* person is within a permitted cong */
        (`p`.`congregationid` in (select `cr`.`congregationid` AS `congregationid` from `account_congregation_restriction` `cr` where (`cr`.`personid` = `getCurrentUserID`())))

        OR
        /* person is within a permitted group */
        (`p`.`id` in (select `m`.`personid` AS `personid` from (`person_group_membership` `m` join `account_group_restriction` `gr` on((`m`.`groupid` = `gr`.`groupid`))) where (`gr`.`personid` = `getCurrentUserID`())))
    );