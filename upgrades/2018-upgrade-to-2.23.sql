/* Issue #457 - clean up zero dates just for tidyness */
UPDATE _person SET status_last_changed = NULL where CAST(status_last_changed AS CHAR(20)) = '0000-00-00 00:00:00' ;
