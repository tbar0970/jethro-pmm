SET @smsrank = (SELECT `rank` FROM setting WHERE symbol = 'SMS_SEND_LOGFILE');

INSERT IGNORE INTO `setting` (`symbol`, `type`, `value`, `note`, `rank`)
VALUES
('SMS_UNICODE_PERMITTED', 'select{"when_free":"When it costs nothing extra","true":"Permitted (may cost extra)","false":"Not permitted"}', 'when_free', 'Whether to permit emojis and other unicode in SMSes', @smsrank+1);
