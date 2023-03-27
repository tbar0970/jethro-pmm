
	    CREATE TABLE `comm_reply` (
              `id` int(11) NOT NULL auto_increment,
              `commid` int(11) NOT NULL default '0',
              `creator` int(11) NOT NULL default '0',
              `created` timestamp NOT NULL default CURRENT_TIMESTAMP,
              `contents` text NOT NULL,
              PRIMARY KEY  (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

            CREATE TABLE `person_comm` (
              `personid` int(11) NOT NULL default '0',
              `id` int(11) NOT NULL default '0',
	      `person_comm` INT NOT NULL DEFAULT '1',
              PRIMARY KEY  (`personid`,`id`)
		   ) ENGINE=InnoDB;
/* Not sure what to do with the constraints...
              CONSTRAINT `pc_personid` FOREIGN KEY (personid) REFERENCES _person(id) ON DELETE CASCADE,
              CONSTRAINT pc_id FOREIGN KEY (id) REFERENCES abstract_note(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
	
*/
/* Move SMS Messages from person_note to person_comm */

	INSERT INTO person_comm(personid,id)
	SELECT person_note.personid as personid,person_note.id as id
	FROM `person_note` JOIN abstract_note ON person_note.id = abstract_note.id
	WHERE abstract_note.subject = "SMS";


	DELETE FROM person_note WHERE id IN (
		SELECT * FROM (
			SELECT person_note.id as id
			FROM `person_note` JOIN abstract_note ON person_note.id = abstract_note.id
			WHERE abstract_note.subject = "SMS"
	    	) AS p
	)
