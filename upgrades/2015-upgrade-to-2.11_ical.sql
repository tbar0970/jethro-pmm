/* iCal subscriptions */
CREATE TABLE person_uuid (
    personid INT PRIMARY KEY,
    uuid VARCHAR(64) NOT NULL,
    CONSTRAINT UNIQUE INDEX (uuid),
    constraint person_uuid_fk foreign key (personid) references _person (id) on delete cascade
) ENGINE=InnoDB;
