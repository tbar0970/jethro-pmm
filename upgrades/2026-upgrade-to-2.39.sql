ALTER TABLE setting MODIFY `type` varchar(512);


-- Bible API settings (used by include/bibleapi.php)
-- COALESCE: if MULTI_EMAIL_SEPARATOR is absent (older installs), fall back to
-- the current highest rank so the new settings land at the bottom.
SET @rankBase = COALESCE(
	(SELECT `rank` FROM setting WHERE symbol = 'MULTI_EMAIL_SEPARATOR'),
	(SELECT MAX(`rank`) FROM setting)
);
UPDATE setting SET `rank` = `rank` + 15 WHERE `rank` > @rankBase;

INSERT IGNORE INTO `setting` (`symbol`, `type`, `heading`, `value`, `note`, `rank`)
VALUES
	('BIBLE_API_URL', 'text', 'Bible Reading Lookup', 'https://rest.api.bible/v1', 'Bible Reading Lookups — Base URL for the REST API, e.g. https://rest.api.bible/v1', @rankBase+5),
	('BIBLE_API_APIKEY', 'text', null, '', 'API key for https://api.bible', @rankBase+10),
	('BIBLE_TRANSLATION_PREFERRED', 'text', null, '', 'Preferred Bible translation for bible reading lookups in service handouts.', @rankBase+15);

-- Should the instance happen to have these defined, enable content in handouts
UPDATE service_component SET content_html='<p>%SERVICE_BIBLE_READ_1_CONTENT%</p>', show_in_handout='full' where title='Bible Reading 1';
UPDATE service_component SET content_html='<p>%SERVICE_BIBLE_READ_2_CONTENT%</p>', show_in_handout='full' where title='Bible Reading 2';
UPDATE service_component SET content_html='<p>%SERVICE_BIBLE_READ_3_CONTENT%</p>',show_in_handout='full' where title='Bible Reading 3';
UPDATE service_component SET content_html='<p>%SERVICE_BIBLE_READ_4_CONTENT%</p>', show_in_handout='full' where title='Bible Reading 4';
SET @smsrank = (SELECT `rank` FROM setting WHERE symbol = 'SMS_SEND_LOGFILE');

INSERT IGNORE INTO `setting` (`symbol`, `type`, `value`, `note`, `rank`)
VALUES
('SMS_UNICODE_PERMITTED', 'select{"when_free":"When it costs nothing extra","true":"Permitted (may cost extra)","false":"Not permitted"}', 'when_free', 'Whether to permit emojis and other unicode in SMSes', @smsrank+1);
