-- #1213 - update the installer-specified default SMS length of 140 to 160
update setting set value=160 where symbol='SMS_MAX_LENGTH' and value=140;
