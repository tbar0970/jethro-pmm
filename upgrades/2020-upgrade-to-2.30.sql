CREATE TABLE `checkin` (
  `id` int(11) NOT NULL auto_increment,
  `venueid` int(11) NOT NULL DEFAULT 0,
  `timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `name` varchar(255) NOT NULL,
  `tel` varchar(255) NULL,
  `email` varchar(255) NULL,
  `pax` int(11) NOT NULL DEFAULT 0,
PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `venue` (
 `id` int(11) NOT NULL AUTO_INCREMENT,
 `name` varchar(255) NOT NULL,
 `set_attendance` varchar(255) NOT NULL,
 `is_archived` varchar(255) NOT NULL DEFAULT '0',
 PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

ALTER TABLE attendance_record
ADD COLUMN checkinid INT(11) DEFAULT NULL;

ALTER TABLE attendance_record ADD CONSTRAINT FOREIGN KEY (`checkinid`) REFERENCES `checkin`(`id`) ON DELETE SET NULL;

INSERT INTO setting
(rank, symbol, note, type, value)
SELECT rank+1, 'QR_CODE_GENERATOR_URL', 'URL template for generating QR codes, containing the placeholder __URL__', 'text', 'https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=__URL__'
FROM setting
WHERE symbol = 'MAP_LOOKUP_URL';
