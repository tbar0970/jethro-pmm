-- =============================================================================
-- Migrate legacy SMS notes to sms + _smsdelivery (+ sms_note)
--
-- Old system: each SMS sent with 'Save as Note' created a person_note +
--   _abstract_note per recipient, subject SMS_SAVE_TO_NOTE_SUBJECT (blank meant
--   'SMS Sent'), details = SMS body, creator = sender.
-- New system: sms (one per send) + _smsdelivery (one per recipient).
--
-- Runs after 2026-upgrade-to-2.40.sql, which creates the tables and aligns
-- sms.body's collation with _abstract_note.details.
--
-- Run it with the mysql/mariadb client (`mysql DB < file`): it uses DELIMITER
-- for a small stored procedure that aborts the run when the integrity check
-- fails. All changes happen in one transaction, so an abort leaves the notes
-- untouched and the script can be re-run after fixing the cause. Notes already
-- linked to a send (sms_note) are skipped, so a re-run after success is a no-op.
-- =============================================================================

-- The legacy note subject. 2026-upgrade-to-2.40.sql has already changed the
-- default ('SMS Sent' or blank) to 'SMS follow-up' and left custom subjects
-- alone, so 'SMS follow-up' here means the 2.39 subject was 'SMS Sent'.
-- 'SMS Sent' notes are migrated as well when the subject was customised: they
-- predate the customisation. A subject defined in conf.php is invisible here.
-- utf8mb4_bin: exact match, whatever the charsets of setting and _abstract_note.
SET @legacy_subject = (
	SELECT CONVERT(`value` USING utf8mb4) COLLATE utf8mb4_bin FROM setting
	WHERE symbol = 'SMS_SAVE_TO_NOTE_SUBJECT' AND `value` NOT IN ('', 'SMS follow-up', 'SMS Sent')
);
SET @legacy_subject = COALESCE(@legacy_subject, CONVERT('SMS Sent' USING utf8mb4) COLLATE utf8mb4_bin);

-- _smsdelivery.provider names the upstream API (company + version). 2.39 sent
-- via whatever generic HTTP gateway SMS_HTTP_URL pointed at, so migrated rows
-- are labelled 5CentSMS v4 only when that setting row is the v4 endpoint (the
-- URLs FiveCentSmsV4Provider is auto-detected by, jethro-sms/src/factory.php).
-- A URL set in conf.php is invisible here; such rows stay NULL (unknown).
SET @legacy_sms_provider = (SELECT IF(COUNT(*) > 0, '5centsmsv4', NULL) FROM setting
	WHERE symbol = 'SMS_HTTP_URL'
	  AND `value` IN ('https://www.5centsms.com.au/api/v4/sms', 'https://www.5centsms.com.au/api/v4'));

-- Aborts the run (the client stops at the error and the open transaction is
-- rolled back on disconnect). A procedure because SIGNAL outside a stored
-- program is MariaDB-only. Created outside the transaction: CREATE PROCEDURE
-- commits implicitly.
DROP PROCEDURE IF EXISTS _upgrade_sms_assert;
DELIMITER //
CREATE PROCEDURE _upgrade_sms_assert(IN failures INT, IN message VARCHAR(255))
BEGIN
	IF failures > 0 THEN
		SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = message;
	END IF;
END//
DELIMITER ;

START TRANSACTION;

-- =============================================================================
-- Step 1: Collect the legacy notes and group them into sends.
-- A send is a run of notes with the same sender and text, each written no more
-- than an hour after the previous one, as include/jethro_sms.php does when it
-- synchronises history from the provider. The same text sent every week is
-- therefore one send per week, not one per note or one for all weeks.
--   body_key:   SHA2 of the text (exact, unlike the column's _ci collation)
--   sms_id:     id of the sms row this note's send becomes; ids are allocated
--               above the current maximum, in send order
--   send_size:  notes in the send; >1 means a bulk send
--   keep_note:  someone interacted with the note (edited it, changed its status
--               or assignee, or commented on it), so it stays a note
-- Temporary tables may appear only once per statement on MySQL, hence the
-- nested window functions rather than UPDATE ... JOIN on itself.
-- =============================================================================
DROP TEMPORARY TABLE IF EXISTS _upgrade_sms_note;
CREATE TEMPORARY TABLE _upgrade_sms_note (
	note_id INT NOT NULL PRIMARY KEY,
	personid INT NOT NULL,
	creator INT NOT NULL,
	body_key CHAR(64) NOT NULL,
	send_created DATETIME NOT NULL,
	send_size INT NOT NULL,
	sms_id INT NOT NULL,
	keep_note TINYINT NOT NULL,
	INDEX (sms_id, personid)
);

SET @sms_id_base = (SELECT COALESCE(MAX(id), 0) FROM sms);

INSERT INTO _upgrade_sms_note (note_id, personid, creator, body_key, send_created, send_size, sms_id, keep_note)
SELECT note_id, personid, creator, body_key, send_created, send_size,
	@sms_id_base + DENSE_RANK() OVER (ORDER BY send_created, creator, body_key, send_no),
	keep_note
FROM (
	SELECT note_id, personid, creator, body_key, send_no, keep_note,
		MIN(created) OVER (PARTITION BY creator, body_key, send_no) AS send_created,
		COUNT(*) OVER (PARTITION BY creator, body_key, send_no) AS send_size
	FROM (
		SELECT note_id, personid, creator, body_key, created, keep_note,
			SUM(new_send) OVER (PARTITION BY creator, body_key ORDER BY created, note_id) AS send_no
		FROM (
			SELECT an.id AS note_id, pn.personid, an.creator, an.created,
				SHA2(an.details, 256) AS body_key,
				CASE WHEN TIMESTAMPDIFF(SECOND,
					LAG(an.created) OVER (PARTITION BY an.creator, SHA2(an.details, 256) ORDER BY an.created, an.id),
					an.created) > 3600 THEN 1 ELSE 0 END AS new_send,
				NOT (an.status = 'no_action'
					AND an.assignee_last_changed IS NULL
					AND an.editor IS NULL
					AND an.edited IS NULL
					AND COALESCE(an.creator = an.assignee, 0)
					AND NOT EXISTS (SELECT 1 FROM note_comment c WHERE c.noteid = an.id)) AS keep_note
			FROM _abstract_note an
			JOIN person_note pn ON pn.id = an.id
			WHERE CONVERT(an.subject USING utf8mb4) COLLATE utf8mb4_bin IN (@legacy_subject, 'SMS Sent')
			  AND NOT EXISTS (SELECT 1 FROM sms_note sn WHERE sn.note_id = an.id)
		) gaps
	) sends
) sized;

-- =============================================================================
-- Step 2: One sms row per send, one _smsdelivery row per recipient of it.
-- Several notes for one person in one send become one delivery.
-- (_smsdelivery, the table: the smsdelivery view shows nothing without a
-- current user, as when this script runs.)
-- =============================================================================
INSERT INTO sms (id, body, sender, created)
SELECT s.sms_id, an.details, s.creator, s.send_created
FROM (
	SELECT sms_id, MIN(note_id) AS note_id, MIN(creator) AS creator, MIN(send_created) AS send_created
	FROM _upgrade_sms_note
	GROUP BY sms_id
) s
JOIN _abstract_note an ON an.id = s.note_id;

INSERT INTO _smsdelivery (sms_id, personid, status, provider)
SELECT sms_id, personid, 'sent', @legacy_sms_provider
FROM _upgrade_sms_note
GROUP BY sms_id, personid;

-- =============================================================================
-- Step 3: Integrity check, before anything is deleted: every note's send must
-- carry exactly the note's sender and text, and must have a delivery to the
-- note's person.
-- =============================================================================
SET @lost = (
	SELECT COUNT(*)
	FROM _upgrade_sms_note n
	JOIN _abstract_note an ON an.id = n.note_id
	LEFT JOIN sms s ON s.id = n.sms_id
		AND s.sender = an.creator
		AND SHA2(s.body, 256) = n.body_key
	LEFT JOIN _smsdelivery sd ON sd.sms_id = n.sms_id AND sd.personid = n.personid
	WHERE s.id IS NULL OR sd.id IS NULL
);
SET @lost_message = CONCAT('SMS note migration aborted, nothing changed: ', @lost,
	' notes were not reproduced exactly in sms/_smsdelivery');
CALL _upgrade_sms_assert(@lost, @lost_message);

-- =============================================================================
-- Step 4: Link the notes that stay (single-recipient sends, and notes someone
-- interacted with) to their delivery.
-- =============================================================================
INSERT INTO sms_note (note_personid, note_id, smsdelivery_id)
SELECT n.personid, n.note_id, sd.id
FROM _upgrade_sms_note n
JOIN _smsdelivery sd ON sd.sms_id = n.sms_id AND sd.personid = n.personid
WHERE n.send_size = 1 OR n.keep_note;

-- =============================================================================
-- Step 5: Delete the remaining bulk-send notes. They were purely the old
-- system's record that an SMS was sent; sms + _smsdelivery now serve that
-- purpose. Older installs' person_note.id FK lacks ON DELETE CASCADE, and a
-- multi-table DELETE may delete the parent first, so FK checks are off.
-- =============================================================================
SET foreign_key_checks = 0;
DELETE an, pn
FROM _abstract_note an
JOIN person_note pn ON pn.id = an.id
JOIN _upgrade_sms_note n ON n.note_id = an.id
WHERE n.send_size > 1 AND NOT n.keep_note;
SET foreign_key_checks = 1;

COMMIT;

DROP TEMPORARY TABLE _upgrade_sms_note;
DROP PROCEDURE _upgrade_sms_assert;
