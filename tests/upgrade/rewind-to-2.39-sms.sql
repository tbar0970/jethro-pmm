-- =============================================================================
-- Reset an already-upgraded SMS schema to its 2.39 state.
--
-- The functional-test dumps (tests/functional/testdata/demodata-*.sql.gz) were
-- generated from a dev instance that already had the 2.40 SMS tables, views and
-- settings, so loading one and then applying upgrades/*.sql skips the 2.40 SMS
-- upgrade entirely: CREATE TABLE IF NOT EXISTS and INSERT IGNORE find nothing
-- to do. The upgrade test applies this script first, so
-- 2026-upgrade-to-2.40.sql really creates the SMS schema and
-- 2026-upgrade-to-2.40-sms.sql really migrates legacy notes.
--
-- Only SMS/2FA settings and objects are touched; all other data stays.
-- Idempotent. Run with the mysql/mariadb client after loading the dump.
-- =============================================================================

DROP VIEW IF EXISTS smsdelivery;
DROP VIEW IF EXISTS sms_usage_daily;
DROP TABLE IF EXISTS sms_note;
DROP TABLE IF EXISTS _smsdelivery;
DROP TABLE IF EXISTS sms;
DROP TABLE IF EXISTS sms_registered_sender;

-- Settings that 2.40 introduces (see the INSERTs in 2026-upgrade-to-2.40.sql),
-- or that the 2.39 installer never created.
DELETE FROM setting WHERE symbol IN (
	'SMS_SENDER', 'SMS_SENDER_OPTIONS', 'SMS_SENDER_DEFAULT', 'SMS_TESTMODE', 'SMS_VERBOSE',
	'SMS_SEGMENT_COST', 'SMS_BALANCE_LOW_THRESHOLD', 'SMS_BALANCE_LOW_NOTIFICANT_EMAIL',
	'SMS_PROVIDER', 'SMS_5CENTSMS_APIKEY_ID', 'SMS_5CENTSMS_APIKEY', 'SMS_CELLCAST_APIKEY',
	'SMS_MOBILEMESSAGE_APIKEY_USERNAME', 'SMS_MOBILEMESSAGE_APIKEY_PASSWORD',
	'SMS_SEND_COOLOFF', 'SMS_SHORTEN_URLS', 'SMS_UNICODE_PERMITTED',
	'URLSHORTENER_API_KEY', 'SMS_VIEWSMS_GRANTED', 'NEEDS_UTF8MB4_UPGRADE'
);

-- Settings that 2.39 had, set to the values the 2.40 upgrade has to handle:
--   SMS_SAVE_TO_NOTE_SUBJECT: custom, so the migration must find notes with it
--     (and with the 'SMS Sent' default used before it was customised);
--   SMS_INTERNATIONAL_PREFIX '+61': a value an admin entered, so the upgrade
--     must keep it (it deletes only the '61' default);
--   SMS_HTTP_URL at the 5CentSMS v4 endpoint, so migrated deliveries get that
--     provider label;
--   SMS_HTTP_HEADER_TEMPLATE blank, so the upgrade's blank-setting cleanup must
--     delete it;
--   SMS_SEND_LOGFILE, which the upgrade removes;
--   2FA_SENDER_ID 'Jethro', which the upgrade clears (unregistered IDs show as
--     'Unverified' since 1 July 2026).
DELETE FROM setting WHERE symbol IN (
	'SMS_SAVE_TO_NOTE_SUBJECT', 'SMS_INTERNATIONAL_PREFIX', 'SMS_LOCAL_PREFIX',
	'SMS_HTTP_URL', 'SMS_HTTP_HEADER_TEMPLATE', 'SMS_SEND_LOGFILE', '2FA_SENDER_ID'
);
INSERT INTO setting (symbol, type, value, note, `rank`) VALUES
	('2FA_SENDER_ID', 'text', 'Jethro', 'Sender ID for 2-factor auth messages. Mirrors SMS_SENDER.', 5),
	('SMS_SAVE_TO_NOTE_SUBJECT', 'text', 'Text message log', '', 10),
	('SMS_INTERNATIONAL_PREFIX', 'text', '+61', 'Used for converting local to international numbers. eg +61', 20),
	('SMS_LOCAL_PREFIX', 'text', '0', 'Used for converting local to international numbers. eg 0', 25),
	('SMS_HTTP_URL', 'text', 'https://www.5centsms.com.au/api/v4/sms', 'URL of the SMS messaging service', 30),
	('SMS_HTTP_HEADER_TEMPLATE', 'text_ml', '', 'Template for the headers of a request to the SMS messaging service', 35),
	('SMS_SEND_LOGFILE', 'text', '/tmp/sms.log', 'File on the server to save a log of sent SMS messages', 40);
