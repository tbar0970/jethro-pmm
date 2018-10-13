/* Issue #481 - fix FK so that I don't get notified about notes I created for myself */
ALTER TABLE abstract_note
MODIFY COLUMN editor int(11) default null;
update abstract_note
set editor = NULL where editor = 0;
ALTER TABLE abstract_note
ADD CONSTRAINT abstract_note_editor FOREIGN KEY (`editor`) REFERENCES _person(`id`);