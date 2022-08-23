SET @newRank = (SELECT `rank` FROM setting WHERE symbol = 'SMTP_SERVER');

UPDATE setting SET `rank` = rank + 10
WHERE `rank` > @newRank-1;

INSERT INTO setting (`rank`, heading, symbol, note, type, value)
VALUES
(@newRank := @newRank+1, 'Task Notifications', 'TASK_NOTIFICATION_ENABLED', '(This feature also requires the task_reminder.php script to be called by cron every 5 minutes)', 'bool', 0),
(@newRank := @newRank+1, '',                   'TASK_NOTIFICATION_FROM_NAME', 'Name from which task notifications should be sent', 'text', 'Jethro'),
(@newRank := @newRank+1, '',                   'TASK_NOTIFICATION_FROM_ADDRESS', 'Email address from which task notifications should be sent', 'text', ''),
(@newRank := @newRank+1, '',                   'TASK_NOTIFICATION_SUBJECT', '', 'text', 'New notes assigned to you');

/* some old systems had NOT NULL for this column */
alter table _person_group MODIFY COLUMN categoryid INT(11) DEFAULT NULL;
