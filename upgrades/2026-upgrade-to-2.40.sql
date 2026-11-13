-- Set flag causing the home page to warn sysadmin users that the database charset needs upgrading
INSERT IGNORE INTO setting
(symbol, type, value, note)
VALUES ('NEEDS_UTF8MB4_UPGRADE', 'hidden', "1", "Whether the database charset upgrade to utf8mb4 needs to be run");

-- Separate members-area identity from staff-account identity (#1173, #1433).
-- @current_user_id is the staff account; its congregation/group restrictions are
-- applied by the person and person_group views.  The members area now sets
-- @current_member_id instead, so a member who also has a restricted staff account
-- no longer loses sight of their own family / roster assignees in the members area.
-- See docs/docs/developer/reference/db-session-identity.md.  Synced with
-- include/installer.class.php, db_objects/person.class.php and
-- db_objects/person_group.class.php.  Idempotent.
-- DROP+CREATE rather than CREATE OR REPLACE FUNCTION: MariaDB supports the latter,
-- MySQL (8.0+, per README requirements) does not.  This is idempotent on both.
DROP FUNCTION IF EXISTS getCurrentMemberID;
CREATE FUNCTION getCurrentMemberID() RETURNS INTEGER NO SQL RETURN @current_member_id;

CREATE OR REPLACE VIEW person AS
SELECT * from _person p
WHERE
	/* members-area session: staff restrictions do not apply */
	(getCurrentMemberID() IS NOT NULL)
	OR (
	getCurrentUserID() IS NOT NULL
	AND (
		/* the person in question IS the current user */
		(`p`.`id` = `getCurrentUserID`())
		OR /* we've been set to public mode */
		(`getCurrentUserID`() = -(1))
		OR /* current user has no group/cong restrictions */
		((0 = (select count(congregationid) from account_congregation_restriction cr WHERE cr.personid = getCurrentUserID()))
		   AND (0 = (select count(gr.groupid) from account_group_restriction gr WHERE gr.personid = getCurrentUserID())))
		OR /* person is within a permitted cong */
		(`p`.`congregationid` in (select `cr`.`congregationid` AS `congregationid` from `account_congregation_restriction` `cr` where (`cr`.`personid` = `getCurrentUserID`())))
		OR /* person is within a permitted group */
		(`p`.`id` in (select `m`.`personid` AS `personid` from (`person_group_membership` `m` join `account_group_restriction` `gr` on((`m`.`groupid` = `gr`.`groupid`))) where (`gr`.`personid` = `getCurrentUserID`())))
	)
	);

CREATE OR REPLACE VIEW person_group AS
SELECT * from _person_group g
WHERE
  (getCurrentMemberID() IS NOT NULL AND g.owner IS NULL)
  OR (
  getCurrentUserID() IS NOT NULL
  AND
  ((g.owner IS NULL) OR (g.owner = getCurrentUserID()))
  AND
  (NOT EXISTS (SELECT * FROM account_group_restriction gr WHERE gr.personid = getCurrentUserID())
  OR g.id IN (SELECT groupid FROM account_group_restriction gr WHERE gr.personid = getCurrentUserID()))
  );

CREATE OR REPLACE VIEW member AS
SELECT mp.id, mp.first_name, mp.last_name, mp.gender, mp.age_bracketid, mp.congregationid,
mp.email, mp.mobile_tel, mp.work_tel, mp.familyid,
mf.family_name, mf.address_street, mf.address_suburb, mf.address_state, mf.address_postcode, mf.home_tel
FROM _person mp
JOIN person_status mps ON mps.id = mp.status
JOIN family mf ON mf.id = mp.familyid
JOIN person_group_membership pgm1 ON pgm1.personid = mp.id
JOIN _person_group pg ON pg.id = pgm1.groupid AND pg.share_member_details = 1
JOIN person_group_membership pgm2 ON pgm2.groupid = pg.id
JOIN _person up ON up.id = pgm2.personid
JOIN person_status ups ON ups.id = up.status
WHERE up.id = getCurrentMemberID()
   AND (NOT mps.is_archived)    /* dont show archived persons */
   AND mf.status <> "archived"  /* dont show archived families */
   AND (NOT ups.is_archived)	/* dont let persons who are themselves archived see anything */
UNION
SELECT mp.id, mp.first_name, mp.last_name, mp.gender, mp.age_bracketid, mp.congregationid,
mp.email, mp.mobile_tel, mp.work_tel, mp.familyid,
mf.family_name, mf.address_street, mf.address_suburb, mf.address_state, mf.address_postcode, mf.home_tel
FROM _person mp
JOIN person_status mps ON mps.id = mp.status
JOIN family mf ON mf.id = mp.familyid
JOIN _person self ON self.familyid = mp.familyid
JOIN person_status selfs ON selfs.id = self.status
WHERE
	self.id = getCurrentMemberID()
	AND ((NOT mps.is_archived) OR (mp.id = self.id))
	AND ((NOT selfs.is_archived) OR (mp.id = self.id))
	/* archived persons can only see themselves, not any family members */
;
--
-- SMS database tables
-- sms:          one row per send operation (body text, sender, timing)
-- smsdelivery:  one row per recipient (person, remote ref, delivery status);
--               table _smsdelivery, read through the restricted smsdelivery view

CREATE TABLE IF NOT EXISTS `sms` (
	`id` INT NOT NULL AUTO_INCREMENT,
	`body` TEXT NOT NULL,
	`sender` INT NULL DEFAULT NULL COMMENT 'Person ID of the sender, or NULL if a script',
	`wire_sender` VARCHAR(20) NULL DEFAULT NULL COMMENT 'Sender identity (alphanumeric ID or phone number) actually used on the wire',
	`created` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
	`scheduled_send_at` DATETIME NULL DEFAULT NULL COMMENT 'Scheduled delivery time, or NULL for immediate',
	PRIMARY KEY (`id`),
	INDEX `sender` (`sender`),
	INDEX `created` (`created`)
) ENGINE=InnoDB;  -- No CHARSET: inherit the database default (see docs/docs/developer/reference/db-charset.md)


-- Align sms.body collation with _abstract_note.details so the INSERT ... SELECT
-- JOINs in the note migration don't fail with collation mismatch errors.
-- Idempotent: when collations already match, the ALTER is a no-op. Restates
-- NOT NULL, which MODIFY COLUMN would otherwise drop.
SET @target_collation = (
	SELECT collation_name FROM information_schema.columns
	WHERE table_schema = DATABASE() AND table_name = '_abstract_note' AND column_name = 'details'
);
SET @sql = CONCAT('ALTER TABLE sms MODIFY COLUMN body TEXT COLLATE ', @target_collation, ' NOT NULL');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- The table is _smsdelivery; smsdelivery (below) is a restricted view of it.
CREATE TABLE IF NOT EXISTS `_smsdelivery` (
	`id` INT NOT NULL AUTO_INCREMENT,
	`sms_id` INT NOT NULL,
	`personid` INT NULL DEFAULT NULL COMMENT 'Person ID of the recipient, or NULL if sent to a raw mobile number',
	`remote_id` VARCHAR(255) NULL DEFAULT NULL COMMENT 'Upstream SMS provider message ID for this recipient',
	`raw_response` TEXT NULL DEFAULT NULL COMMENT 'Raw upstream provider response for this recipient (send response or status query)',
	`body` TEXT NULL DEFAULT NULL COMMENT 'Expanded message body for this recipient, or NULL if the batch message was used',
	`provider` ENUM('5centsmsv5','5centsmsv4','cellcastv1','mobilemessagev1') NULL DEFAULT NULL COMMENT 'Upstream SMS API (company + version) that sent this delivery, so raw_response can be interpreted; NULL if unknown',
	`delivered_at` DATETIME NULL DEFAULT NULL COMMENT 'When delivery was confirmed: the carrier timestamp when the provider reports one, else when a status check first saw it delivered',
	`status` ENUM('queued','sent','delivered','failed','in-progress','scheduled','cancelled','sending','test-message','unknown') NOT NULL DEFAULT 'sending' COMMENT 'Delivery status',
	`segments_used` INT NULL DEFAULT NULL COMMENT 'SMS segments charged, as reported by the provider; NULL if not reported',
	`per_segment_cost` DECIMAL(10,5) NULL DEFAULT NULL COMMENT 'Dollars per segment at send time (provider-reported, else SMS_SEGMENT_COST or provider default)',
	`cost_bearer` TINYINT(1) UNSIGNED NOT NULL DEFAULT 1 COMMENT 'TRUE if this row bears the segments/cost its (possibly shared) message was charged; 0 on the secondary persons of a shared number',
	PRIMARY KEY (`id`),
	INDEX `sms_id` (`sms_id`),
	INDEX `personid` (`personid`),
	INDEX `remote_id` (`remote_id`),
	CONSTRAINT `smsdelivery_ibfk_1` FOREIGN KEY (`sms_id`) REFERENCES `sms` (`id`) ON DELETE CASCADE,
	CONSTRAINT `smsdelivery_ibfk_2` FOREIGN KEY (`personid`) REFERENCES `_person` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Restate _smsdelivery.delivered_at's comment for installs that created the
-- table from an earlier revision of this file. Idempotent: re-applying the same
-- definition is a no-op. MODIFY COLUMN must restate the whole definition.
ALTER TABLE `_smsdelivery` MODIFY COLUMN `delivered_at` DATETIME NULL DEFAULT NULL COMMENT 'When delivery was confirmed: the carrier timestamp when the provider reports one, else when a status check first saw it delivered';

-- smsdelivery.provider holds the upstream API *and version* (SmsProvider::getKey()),
-- so raw_response can always be interpreted; see
-- docs/docs/developer/reference/sms/database.mdx "Provider column".
--
-- Shared mobile numbers: when several recipients share one number,
-- DeduplicatingSmsProvider sends one gateway message for the group and
-- DbLogging writes one smsdelivery row per person, all sharing the group's
-- remote_id. Only the group's primary row bears the charge (segments_used as
-- reported); the other rows record segments_used = 0 and cost_bearer = 0, so
-- the Messages page's cost sums count the message once, and status polls guard
-- their cost updates on cost_bearer.


-- Join table associating Notes and SMSes created together
CREATE TABLE IF NOT EXISTS `sms_note` (
	`note_personid` INT NOT NULL COMMENT 'Person ID of the note owner',
	`note_id` INT NOT NULL COMMENT 'Note ID from _abstract_note / person_note',
	`smsdelivery_id` INT NOT NULL COMMENT 'smsdelivery ID of the associated SMS delivery',
	PRIMARY KEY (`note_personid`, `note_id`, `smsdelivery_id`),
	INDEX `smsdelivery_id` (`smsdelivery_id`),
	CONSTRAINT `sms_note_ibfk_1` FOREIGN KEY (`note_personid`, `note_id`) REFERENCES `person_note` (`personid`, `id`) ON DELETE CASCADE,
	CONSTRAINT `sms_note_ibfk_2` FOREIGN KEY (`smsdelivery_id`) REFERENCES `_smsdelivery` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- smsdelivery: the deliveries the current user may see, as `person` does for
-- _person: those to persons visible through the (restricted) person view, plus
-- those to raw numbers. Pages read this; writers and system-level code
-- (logging, status caching, history sync, CLI) use _smsdelivery. Recreate it
-- after adding columns to _smsdelivery (SELECT * is expanded when created).
CREATE OR REPLACE VIEW `smsdelivery` AS
	SELECT sd.* FROM `_smsdelivery` sd
	WHERE sd.personid IS NULL OR sd.personid IN (SELECT p.id FROM person p);

-- Table for persisting sender number registrations (e.g. Cellcast).
-- DbLoggingSmsProvider reads/writes here so registration survives sessions.
-- personid: the person the number is verified for. A number belongs to the
-- first person verified for it, so a user who sets their own mobile to
-- someone else's verified number can't send as them (NULL: not yet claimed).
CREATE TABLE IF NOT EXISTS sms_registered_sender (
    phone VARCHAR(20) PRIMARY KEY,
    personid INT NULL DEFAULT NULL COMMENT 'Person the number is verified for (first person wins)',
    registered_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT sms_registered_sender_person FOREIGN KEY (personid) REFERENCES _person (id) ON DELETE CASCADE
) ENGINE=InnoDB;



-- Now to update Settings.
-- First delete all blank settings of the deprecated 5centSMSv4 provider. They are still settable in conf.php, or in the config page if used, but from the config page we only want v5 settings visible.
DELETE FROM setting WHERE (symbol LIKE 'SMS_HTTP_%' or symbol='SMS_RECIPIENT_ARRAY_PARAMETER') AND (value = '' OR value IS NULL);
-- Delete only values that equal the code default, so the effective behaviour is
-- unchanged. '+61' is a value admins entered (the 2.39 note said "eg +61") and
-- is not the default: deleting it would strip the '+' from migrated template
-- gateways' international numbers.
DELETE FROM setting WHERE symbol='SMS_INTERNATIONAL_PREFIX' and value='61';
DELETE FROM setting WHERE symbol='SMS_LOCAL_PREFIX' and value='0';

-- Reorder all SMS settings into logical groups matching the send-page workflow:
--   Sender → Message body → Behaviour → Debugging → Provider (set once, last)
--
-- We UPDATE existing settings to their new ranks first, then INSERT IGNORE
-- any settings that are new in 2.40.  Both operations are idempotent.
SET @sms_base = (SELECT MIN(`rank`) FROM setting WHERE symbol LIKE 'SMS_%');

-- Clear old headings; the first setting below gets the heading.
UPDATE setting SET heading='' WHERE symbol LIKE 'SMS_%';

-- =========================================================================
-- Group 1: Sender — who the message appears to come from
-- (Matches the "From" dropdown at the top of the SMS send page.)
-- =========================================================================

-- =========================================================================
-- Group 2: Message body — limits on what the user can type
-- =========================================================================
UPDATE setting SET `rank` = @sms_base + 15 WHERE symbol = 'SMS_MAX_LENGTH';
UPDATE setting SET `rank` = @sms_base + 20 WHERE symbol = 'SMS_UNICODE_PERMITTED';

-- =========================================================================
-- Group 4: Debugging / troubleshooting / low-balance alerts
-- =========================================================================
UPDATE setting SET `rank` = @sms_base + 45 WHERE symbol = 'SMS_SEND_LOGFILE';

-- =========================================================================
-- Group 5: Provider — gateway configuration (set once, rarely changed)
-- =========================================================================
UPDATE setting SET `rank` = @sms_base + 80 WHERE symbol = 'SMS_HTTP_URL';
UPDATE setting SET `rank` = @sms_base + 85 WHERE symbol = 'SMS_HTTP_HEADER_TEMPLATE';
UPDATE setting SET `rank` = @sms_base + 90 WHERE symbol = 'SMS_HTTP_POST_TEMPLATE';
UPDATE setting SET `rank` = @sms_base + 95 WHERE symbol = 'SMS_HTTP_RESPONSE_ERROR_REGEX';
UPDATE setting SET `rank` = @sms_base + 100 WHERE symbol = 'SMS_HTTP_RESPONSE_OK_REGEX';
UPDATE setting SET `rank` = @sms_base + 105 WHERE symbol = 'SMS_RECIPIENT_ARRAY_PARAMETER';
UPDATE setting SET `rank` = @sms_base + 110 WHERE symbol = 'SMS_LOCAL_PREFIX';
UPDATE setting SET `rank` = @sms_base + 115 WHERE symbol = 'SMS_INTERNATIONAL_PREFIX';

-- Insert any settings that are new in 2.40 (idempotent via INSERT IGNORE).
-- Existing settings already got their rank updated above.
INSERT IGNORE INTO `setting` (`symbol`, `type`, `value`, `note`, `rank`)
VALUES
	('SMS_SENDER', 'text', '', 'Hardcoded sender ID or mobile number that SMSes will appear to come from', @sms_base),
	('SMS_SENDER_OPTIONS', 'text', '_USER_MOBILE_,_SENDER_IDS_', 'Sender options for the SMS dropdown. Tokens: _USER_MOBILE_ (user''s mobile), _SENDER_IDS_ (upstream-registered IDs). Other entries are literal IDs. Blank = default _USER_MOBILE_,_SENDER_IDS_.', @sms_base + 5),
	('SMS_SENDER_DEFAULT', 'text', '', 'Default sender selected in the SMS sender dropdown. Use _USER_MOBILE_ to indicate the current user''s mobile.', @sms_base + 10),
	('SMS_TESTMODE', 'bool', false, 'In Test Mode no SMSes are actually sent', @sms_base + 35),
	('SMS_VERBOSE', 'bool', false, 'Log HTTP requests to upstream SMS provider', @sms_base + 40),
	('SMS_SEGMENT_COST', 'text', '', 'Your price per SMS segment in dollars, e.g. 0.029. Used for the compose cost estimate, and recorded with each new send when the provider does not report its own price (Cellcast does). Blank = provider default.', @sms_base + 44),
	('SMS_BALANCE_LOW_THRESHOLD', 'int', '0', 'Notify a staff member when SMS account balance drops below this amount (0 = disabled)', @sms_base + 50),
	('SMS_BALANCE_LOW_NOTIFICANT_EMAIL', 'text', '', 'Email address to notify when SMS balance is low', @sms_base + 55),
	('SMS_PROVIDER', 'select{"":"Auto-detect (based on settings)","5centsmsv5":"5CentSMS v5","cellcast":"Cellcast","mobilemessage":"MobileMessage"}', '', 'Which SMS provider to use for sending', @sms_base + 60),
	('SMS_5CENTSMS_APIKEY_ID', 'text', '', 'FiveCent SMS v5 API key ID', @sms_base + 65),
	('SMS_5CENTSMS_APIKEY', 'text', '', 'FiveCent SMS v5 API key secret', @sms_base + 70),
	('SMS_CELLCAST_APIKEY', 'text', '', 'Cellcast API bearer token', @sms_base + 75),
	('SMS_MOBILEMESSAGE_APIKEY_USERNAME', 'text', '', 'MobileMessage API username', @sms_base + 80),
	('SMS_MOBILEMESSAGE_APIKEY_PASSWORD', 'text', '', 'MobileMessage API password', @sms_base + 85),
	('SMS_SEND_COOLOFF', 'int', '0', 'Undo window: seconds an immediate SMS is held, cancellable, before dispatch. 0 (default) disables it. 5CentSMS v5, Cellcast: any value. MobileMessage: at least 90 (API sends 60s-ahead schedules immediately). Others, 2FA, reminders: immediate.', @sms_base + 33),
	('SMS_SHORTEN_URLS', 'bool', false, 'Whether the ''Shorten URLs'' checkbox is checked by default when sending an SMS', @sms_base + 28),
	('SMS_UNICODE_PERMITTED', 'select{"when_free":"When it costs nothing extra","true":"Permitted (may cost extra)","false":"Not permitted"}', 'when_free', 'Whether to permit emojis and other unicode in SMSes', @sms_base + 20);

UPDATE setting SET note = 'Character limit for SMS messages sent from the web UI (scripts such as roster reminders are not limited). SMS is billed per 160 chars per person, so this is best set to 160 (1x), 306 (2x), 459 (3x), 612 (4x), 765 (5x) etc' WHERE symbol = 'SMS_MAX_LENGTH';

INSERT IGNORE INTO setting
	(`rank`, heading, symbol, note, type, value)
VALUES
	(@sms_base + 150, 'URL Shortening', 'URLSHORTENER_API_KEY', 'API key for the jethro.au URL shortener, used in SMSes', 'text', '');
-- Heading on the first setting in the new order.
UPDATE setting SET heading = 'SMS Gateway' WHERE symbol = 'SMS_SENDER';

-- SMS is now a 'feature' explicitly turned on or off, rather than its enabled'ness being inferred by the SMS_HTTP_URL setting.
-- Turn it on by default, as we can't reliably tell the value of SMS_HTTP_URL / SMS_5CENTSMS_URL here, and 'on' means nothing if those aren't set anyway.
UPDATE `setting` SET 
  `type`  = REPLACE(`type`, '}', ',"SMS":"SMS"}'),
  `value` = CONCAT(`value`, ',SMS')
WHERE `symbol` = 'ENABLED_FEATURES'
AND `type` NOT LIKE '%"SMS"%';

-- In the past a major reason to 'Save as Note' was purely record-keeping. But now that SMSes are always saved (the 'Messages' tab), the only reason to "Save to Note" is if you actually intend to follow up on the SMS somehow. Thus the default note subject changes from generic 'SMS Sent' to 'SMS follow-up'.
-- Empty values are included: older Jethro substituted 'SMS Sent' for an empty subject, so '' meant the default too.
-- 2026-upgrade-to-2.40-sms.sql runs after this and reads the pre-upgrade subject
-- from the setting's value when it does not match, so keep the two in step.
UPDATE setting
SET `value` = 'SMS follow-up'
WHERE symbol = 'SMS_SAVE_TO_NOTE_SUBJECT'
  AND `value` IN ('SMS Sent', '');

-- Since 1 July 2026 the ACMA shows an unregistered alphanumeric sender ID to
-- recipients as 'Unverified', so 'Jethro' is no longer a sensible 2FA sender.
-- Blank now means: the literal SMS_SENDER, else the first ACMA-approved sender
-- ID the provider reports (see User_System::resolve2FASender()). Leave a value
-- an admin has chosen since (e.g. their own registered ID) alone.
UPDATE setting
SET `value` = ''
WHERE symbol = '2FA_SENDER_ID'
  AND `value` = 'Jethro';
UPDATE setting
SET note = 'ACMA-registered sender ID for 2FA messages; blank = literal SMS_SENDER, else the first ACMA-approved sender ID at your provider'
WHERE symbol = '2FA_SENDER_ID';

-- The new PERM_VIEWSMS permission (bitmask 524288) grants permission to see past
-- messages sent from any sender. Before this upgrade, SMSes were only recorded as
-- notes, visible to holders of 'Notes - view all' (PERM_VIEWNOTE, bitmask 48).
-- To avoid exposing those messages more widely, existing installations grant
-- PERM_VIEWSMS only where PERM_SENDSMS (2) and PERM_VIEWNOTE (48) are both held.
-- (New installations get PERM_VIEWSMS in DEFAULT_PERMISSIONS unconditionally; see
-- include/installer.class.php.)
--
-- Bit 524288 was previously unused but already set in the stock DEFAULT_PERMISSIONS
-- (7995391), so many existing accounts carry it. The bit is therefore set *or
-- cleared* to match the rule, rather than only OR'd in.
--
-- It runs once. Re-running after an admin has granted PERM_VIEWSMS by hand
-- would clear those grants again, so a marker setting records that it has run.
-- MySQL has no constants; session variables mirror include/permission_levels.php.
SET @PERM_SENDSMS = 2;
SET @PERM_VIEWNOTE = 48;
SET @PERM_VIEWSMS = 524288;
SET @PERM_SYSADMIN = 2147483647;
SET @perm_grant_pending = (SELECT COUNT(*) = 0 FROM setting WHERE symbol = 'SMS_VIEWSMS_GRANTED');

UPDATE setting
SET value = IF((CAST(value AS UNSIGNED) & @PERM_SENDSMS) = @PERM_SENDSMS AND (CAST(value AS UNSIGNED) & @PERM_VIEWNOTE) = @PERM_VIEWNOTE,
               CAST(value AS UNSIGNED) | @PERM_VIEWSMS,
               CAST(value AS UNSIGNED) & ~@PERM_VIEWSMS)
WHERE symbol = 'DEFAULT_PERMISSIONS'
  AND @perm_grant_pending;

UPDATE staff_member
SET permissions = IF((permissions & @PERM_SENDSMS) = @PERM_SENDSMS AND (permissions & @PERM_VIEWNOTE) = @PERM_VIEWNOTE,
                     permissions | @PERM_VIEWSMS,
                     permissions & ~@PERM_VIEWSMS)
WHERE permissions <> @PERM_SYSADMIN
  AND @perm_grant_pending;

INSERT IGNORE INTO setting (symbol, type, value, note, `rank`)
VALUES ('SMS_VIEWSMS_GRANTED', 'hidden', '1',
        'Internal: the one-off SMS-view-all grant for existing staff has run', 0);



-- =========================================================================
-- Remove legacy SMS_SEND_LOGFILE setting — logToFile() has been deleted;
-- the SMS send history is now in the sms + smsdelivery DB tables and the
-- Person → Messages section. To regenerate the legacy log format, use:
--   HTTP_HOST=example.org php ./scripts/sms.php export-smslog
-- Idempotent: DELETE WHERE returns 0 rows if already deleted.
DELETE FROM setting WHERE symbol = 'SMS_SEND_LOGFILE';

-- Expose the sendmail transport alternative on the System Configuration page.
-- Synced with include/installer.class.php and include/config_manager.class.php
-- (isFileOnlySetting). Idempotent.
INSERT IGNORE INTO setting (`rank`, heading, symbol, note, type, value)
SELECT `rank` + 1, '', 'SENDMAIL_COMMAND',
    'Send mail via this sendmail-compatible command instead of SMTP_SERVER. Include -bs to speak SMTP over the pipe.',
    'text', ''
FROM setting
WHERE symbol = 'SMTP_SERVER';
