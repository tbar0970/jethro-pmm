/* Issue #440 - fix collations on several tables so they are all consistent */
ALTER TABLE family convert to CHARACTER SET utf8 COLLATE utf8_unicode_ci;
ALTER TABLE note_comment convert to CHARACTER SET utf8 COLLATE utf8_unicode_ci;
ALTER TABLE db_object_lock convert to CHARACTER SET utf8 COLLATE utf8_unicode_ci;
ALTER TABLE _person CONVERT TO CHARACTER SET utf8 COLLATE utf8_unicode_ci;

/* update emty setting where necessary to avoid errors */
UPDATE setting set value = 'SMS Sent'
WHERE symbol = 'SMS_SAVE_TO_NOTE_SUBJECT'
AND value = '';

/* fix #452 - using insert ignore because these settings MAY already be there */
SET @newRank = (SELECT rank FROM setting WHERE symbol = 'SMTP_SERVER');

UPDATE setting SET rank = rank + 10
WHERE rank > @newRank-1;

INSERT IGNORE INTO setting (rank, heading, symbol, note, type, value)
VALUES
(@newRank := @newRank+1, 'Task Notifications', 'TASK_NOTIFICATION_ENABLED', '(This feature also requires the task_reminder.php script to be called by cron every 5 minutes)', 'bool', 0),
(@newRank := @newRank+1, '',                   'TASK_NOTIFICATION_FROM_NAME', 'Name from which task notifications should be sent', 'text', 'Jethro'),
(@newRank := @newRank+1, '',                   'TASK_NOTIFICATION_FROM_ADDRESS', 'Email address from which task notifications should be sent', 'text', ''),
(@newRank := @newRank+1, '',                   'TASK_NOTIFICATION_SUBJECT', '', 'text', 'New notes assigned to you');

ALTER TABLE person_query
ADD COLUMN mailchimp_list_id VARCHAR(255) NOT NULL DEFAULT '';

SET @newRank = (SELECT rank FROM setting WHERE symbol = 'TASK_NOTIFICATION_SUBJECT');
INSERT INTO setting (rank, heading, symbol, note, type, value)
VALUES (@newRank+1, 'Mailchimp Sync', 'MAILCHIMP_API_KEY', 'API Key for mailchimp integration. NB the mailchimp sync script must also be called regularly by cron.', 'text', '');