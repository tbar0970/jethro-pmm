ALTER TABLE setting MODIFY `type` varchar(512);

UPDATE `setting`
SET `type` = REPLACE(`type`, '}', ',"BIBLE_API":"Bible Reading Downloads"}')
WHERE `symbol` = 'ENABLED_FEATURES'
  AND `type` NOT LIKE '%"BIBLE_API"%';

-- Bible API settings (used by include/bibleapi.php)
SET @rankBase = (SELECT `rank` FROM setting WHERE symbol = 'MULTI_EMAIL_SEPARATOR');
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
