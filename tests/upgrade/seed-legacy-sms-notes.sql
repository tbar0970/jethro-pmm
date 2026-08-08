-- =============================================================================
-- Seed legacy 'SMS Sent' notes for the 2.40 SMS-note migration test.
--
-- Run after loading a 2.39 dump and tests/upgrade/rewind-to-2.39-sms.sql, and
-- before the upgrade scripts. The rewind sets SMS_SAVE_TO_NOTE_SUBJECT to
-- 'Text message log' (a custom subject), so the 'SMS Sent' notes below predate
-- the customisation and both subjects have to be migrated.
--
-- In 2.39 a 'Save as Note' send wrote one note per recipient: subject = the
-- setting, details = the SMS body, creator = assignee = the sending staff
-- member, and person_note linking it to the recipient.
--
-- Expected result after upgrades/2026-upgrade-to-2.40-sms.sql — see
-- run-upgrade-test.sh, which asserts it:
--   weekly reminder: 2 people x 3 weeks -> 3 sends of 2 notes, 6 deliveries,
--     6 notes deleted;
--   bulk AGM notice: 10 notes in one second -> 1 send, 10 deliveries, all
--     deleted (a repeating text is not matched across weeks: no N^2 rows);
--   single recipient: 1 note -> 1 send, 1 delivery, note kept and linked;
--   commented bulk: 2 notes -> 1 send, 2 deliveries, the commented note kept
--     (with its comment) and linked, the other deleted;
--   custom subject: 1 note -> 1 send, 1 delivery, note kept and linked;
--   handled bulk: 2 notes, one whose status changed -> 1 send, 2 deliveries,
--     the handled note kept and linked, the other deleted.
-- Total: 8 sends, 22 deliveries, 4 notes kept and 4 sms_note links.
-- Idempotent: deletes its own notes first; note IDs are assigned explicitly so
-- the person_note rows can be created without guessing AUTO_INCREMENT values.
-- =============================================================================

SET @sender = (SELECT id FROM staff_member WHERE active = 1 ORDER BY id LIMIT 1);
-- Ten distinct recipients that are not the sender.
SET @p1  = (SELECT id FROM _person WHERE id <> @sender ORDER BY id LIMIT 1);
SET @p2  = (SELECT id FROM _person WHERE id <> @sender ORDER BY id LIMIT 1 OFFSET 1);
SET @p3  = (SELECT id FROM _person WHERE id <> @sender ORDER BY id LIMIT 1 OFFSET 2);
SET @p4  = (SELECT id FROM _person WHERE id <> @sender ORDER BY id LIMIT 1 OFFSET 3);
SET @p5  = (SELECT id FROM _person WHERE id <> @sender ORDER BY id LIMIT 1 OFFSET 4);
SET @p6  = (SELECT id FROM _person WHERE id <> @sender ORDER BY id LIMIT 1 OFFSET 5);
SET @p7  = (SELECT id FROM _person WHERE id <> @sender ORDER BY id LIMIT 1 OFFSET 6);
SET @p8  = (SELECT id FROM _person WHERE id <> @sender ORDER BY id LIMIT 1 OFFSET 7);
SET @p9  = (SELECT id FROM _person WHERE id <> @sender ORDER BY id LIMIT 1 OFFSET 8);
SET @p10 = (SELECT id FROM _person WHERE id <> @sender ORDER BY id LIMIT 1 OFFSET 9);

DELETE FROM note_comment WHERE contents = 'Upgrade test: comment on the bulk message';
DELETE pn FROM person_note pn JOIN _abstract_note an ON an.id = pn.id WHERE an.details LIKE 'Upgrade test:%';
DELETE FROM _abstract_note WHERE details LIKE 'Upgrade test:%';

DROP TEMPORARY TABLE IF EXISTS _seed_note;
CREATE TEMPORARY TABLE _seed_note (
	n INT NOT NULL PRIMARY KEY,
	personid INT NOT NULL,
	subject VARCHAR(255) NOT NULL,
	details TEXT NOT NULL,
	status VARCHAR(255) NOT NULL,
	created DATETIME NOT NULL
);

-- 1. The same text every week for two people: three sends, not one.
INSERT INTO _seed_note VALUES
	(1,  @p1, 'SMS Sent', 'Upgrade test: weekly roster reminder', 'no_action', '2026-01-05 09:00:00'),
	(2,  @p2, 'SMS Sent', 'Upgrade test: weekly roster reminder', 'no_action', '2026-01-05 09:00:00'),
	(3,  @p1, 'SMS Sent', 'Upgrade test: weekly roster reminder', 'no_action', '2026-01-12 09:00:00'),
	(4,  @p2, 'SMS Sent', 'Upgrade test: weekly roster reminder', 'no_action', '2026-01-12 09:00:00'),
	(5,  @p1, 'SMS Sent', 'Upgrade test: weekly roster reminder', 'no_action', '2026-01-19 09:00:00'),
	(6,  @p2, 'SMS Sent', 'Upgrade test: weekly roster reminder', 'no_action', '2026-01-19 09:00:00'),
-- 2. A ten-recipient bulk send, all notes written in the same second.
	(7,  @p1,  'SMS Sent', 'Upgrade test: bulk AGM notice', 'no_action', '2026-01-20 10:00:00'),
	(8,  @p2,  'SMS Sent', 'Upgrade test: bulk AGM notice', 'no_action', '2026-01-20 10:00:00'),
	(9,  @p3,  'SMS Sent', 'Upgrade test: bulk AGM notice', 'no_action', '2026-01-20 10:00:00'),
	(10, @p4,  'SMS Sent', 'Upgrade test: bulk AGM notice', 'no_action', '2026-01-20 10:00:00'),
	(11, @p5,  'SMS Sent', 'Upgrade test: bulk AGM notice', 'no_action', '2026-01-20 10:00:00'),
	(12, @p6,  'SMS Sent', 'Upgrade test: bulk AGM notice', 'no_action', '2026-01-20 10:00:00'),
	(13, @p7,  'SMS Sent', 'Upgrade test: bulk AGM notice', 'no_action', '2026-01-20 10:00:00'),
	(14, @p8,  'SMS Sent', 'Upgrade test: bulk AGM notice', 'no_action', '2026-01-20 10:00:00'),
	(15, @p9,  'SMS Sent', 'Upgrade test: bulk AGM notice', 'no_action', '2026-01-20 10:00:00'),
	(16, @p10, 'SMS Sent', 'Upgrade test: bulk AGM notice', 'no_action', '2026-01-20 10:00:00'),
-- 3. A single-recipient send: the note stays, linked to the delivery.
	(17, @p3, 'SMS Sent', 'Upgrade test: single recipient message', 'no_action', '2026-01-21 11:00:00'),
-- 4. A bulk send where one note was commented on: that note stays.
	(18, @p4, 'SMS Sent', 'Upgrade test: commented bulk message', 'no_action', '2026-01-22 12:00:00'),
	(19, @p5, 'SMS Sent', 'Upgrade test: commented bulk message', 'no_action', '2026-01-22 12:00:00'),
-- 5. The custom subject (SMS_SAVE_TO_NOTE_SUBJECT) is migrated too.
	(20, @p6, 'Text message log', 'Upgrade test: custom subject message', 'no_action', '2026-01-23 13:00:00'),
-- 6. A bulk send where one note's status was changed: that note stays.
	(21, @p7, 'SMS Sent', 'Upgrade test: handled bulk message', 'actioned',  '2026-01-24 14:00:00'),
	(22, @p8, 'SMS Sent', 'Upgrade test: handled bulk message', 'no_action', '2026-01-24 14:00:00');

SET @note_base = (SELECT COALESCE(MAX(id), 0) FROM _abstract_note);
INSERT INTO _abstract_note (id, subject, details, status, creator, assignee, created, history)
SELECT @note_base + n, subject, details, status, @sender, @sender, created, ''
FROM _seed_note ORDER BY n;
INSERT INTO person_note (personid, id)
SELECT personid, @note_base + n FROM _seed_note;

INSERT INTO note_comment (noteid, creator, created, contents)
SELECT @note_base + 18, @sender, '2026-01-22 12:30:00', 'Upgrade test: comment on the bulk message';
