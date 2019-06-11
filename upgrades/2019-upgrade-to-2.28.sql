            CREATE TABLE `comm_reply` (
              `id` int(11) NOT NULL auto_increment,
              `commid` int(11) NOT NULL default '0',
              `creator` int(11) NOT NULL default '0',
              `created` timestamp NOT NULL default CURRENT_TIMESTAMP,
              `contents` text NOT NULL,
              PRIMARY KEY  (`id`)
            ) ENGINE=InnoDB ;


            CREATE TABLE `person_comm` (
              `personid` int(11) NOT NULL default '0',
              `id` int(11) NOT NULL default '0',
              PRIMARY KEY  (`personid`,`id`),
              CONSTRAINT `pc_personid` FOREIGN KEY (personid) REFERENCES _person(id) ON DELETE CASCADE,
              CONSTRAINT pc_id FOREIGN KEY (id) REFERENCES abstract_note(id) ON DELETE CASCADE
            ) ENGINE=InnoDB ;

