/* recreate the person view to apply 'or' logic between restriction groups and congregations */
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

/* add support for planned absences */
CREATE TABLE `planned_absence` (
        `id` int(11) NOT NULL auto_increment,
        `personid` int(11) NOT NULL DEFAULT '0',
        `start_date` date NOT NULL,
        `end_date` date NOT NULL,
        `comment` text NOT NULL,
        `created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `creator` int(11) NOT NULL DEFAULT '0',
        PRIMARY KEY (`id`)
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8;

/* adjust setting label */
UPDATE setting SET note = "This feature sends you an email when somebody else assigns a note to you. (NB the task_reminder.php script must be called ever 5 minutes by cron)"
WHERE symbol = "TASK_NOTIFICATION_ENABLED";