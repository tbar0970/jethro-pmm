/* Front page reports */
ALTER table person_query
ADD COLUMN `show_on_homepage` varchar(16) not null default '';

/* Issue #457 - clean up zero dates just for tidyness */
UPDATE _person SET status_last_changed = NULL where CAST(status_last_changed AS CHAR(20)) = '0000-00-00 00:00:00' ;

/* issue #506 - is_adult col sometimes wrong definition */
UPDATE age_bracket set is_adult = 0 where is_adult <> 1;
ALTER TABLE age_bracket MODIFY COLUMN is_adult TINYINT(1) UNSIGNED NOT NULL DEFAULT 0;

/* issue #492 - correct the wording for the RESTRICTED_USERS_CAN_ADD setting */
UPDATE setting SET note = 'Can users with congregation restrictions add new persons and families?' where symbol = 'RESTRICTED_USERS_CAN_ADD';
