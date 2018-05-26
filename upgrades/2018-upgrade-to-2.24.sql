/* Fix #344 - SMS sending fails when current user has blank mobile number */
SET @newRank = (SELECT rank FROM setting WHERE symbol = 'SMS_MAX_LENGTH');

INSERT IGNORE INTO setting (rank, heading, symbol, note, type, value)
VALUES
(@newRank := @newRank+1, '', 'SMS_OVERRIDE_SENDER_NUMBER', 'Phone number from which SMS messages are to be sent. Leave this blank to use phone number associated with the user sending the SMS.', 'text', '');

/* Let users know what variables they can use in SMS_POST_TEMPLATE. */
ALTER TABLE setting MODIFY COLUMN note VARCHAR(350);

UPDATE setting SET note = 'Template for the body of a request to the SMS messaging service. Supports the variables:_USER_MOBILE_, _USER_INTERNATIONAL_MOBILE_, _USER_EMAIL_, _MESSAGE_, _RECIPIENTS_COMMAS_, _RECIPIENTS_NEWLINES_, _RECIPIENTS_ARRAY_, _RECIPIENTS_INTERNATIONAL_COMMAS_, _RECIPIENTS_INTERNATIONAL_NEWLINES_, _RECIPIENTS_INTERNATIONAL_ARRAY_'
WHERE symbol = 'SMS_HTTP_POST_TEMPLATE';
