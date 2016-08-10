CREATE TABLE family_photo (
   familyid INT NOT NULL,
   photodata MEDIUMBLOB NOT NULL,
   CONSTRAINT `famliyphotofamilyid` FOREIGN KEY (`familyid`) REFERENCES `family` (`id`),
   PRIMARY KEY (familyid)
) ENGINE=InnoDB;
