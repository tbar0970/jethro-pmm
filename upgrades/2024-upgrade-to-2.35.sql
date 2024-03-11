CREATE TABLE `2fa_trust` (
  `userid` int(11) NOT NULL,
  `token` varchar(255) NOT NULL,
  `expiry` datetime NOT NULL,
  CONSTRAINT 2fatrust_person FOREIGN KEY (`userid`) REFERENCES `staff_member` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB;


SET @rankBase = (SELECT `rank` FROM setting WHERE symbol = 'SESSION_MAXLENGTH_MINS');
UPDATE setting SET `rank` = `rank` + 20 WHERE `rank` > @rankBase;

INSERT INTO setting (`rank`, heading, symbol, note, type, value)
VALUES
(@rankBase+5, '2-factor Authentication',  '2FA_REQUIRED_PERMS','Users who hold permission levels selected here will be required to complete 2-factor authentication at login.','text',''),
(@rankBase+10, '',                         '2FA_EVEN_FOR_RESTRICTED_ACCTS','Require 2-factor auth even for accounts with group/congregation restrictions?','bool','0'),
(@rankBase+15, '',                         '2FA_TRUST_DAYS','Users can tick a box to skip 2-factor auth for this many days. Set to 0 to disable.','int','30'),
(@rankBase+20, '',                         '2FA_SENDER_ID','Sender ID for 2-factor auth messages','text','Jethro');
