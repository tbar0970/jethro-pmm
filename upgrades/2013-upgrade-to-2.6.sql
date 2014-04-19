alter table abstract_note add column editor int(11) default null after created;
alter table abstract_note add column edited datetime default null after editor;

