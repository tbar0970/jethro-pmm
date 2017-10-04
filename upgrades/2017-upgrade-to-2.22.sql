/* Fix #389 */
CREATE TABLE `frontpage_person_query` (
  `queryid` int(11) NOT NULL auto_increment,
  `noperms` bool NOT NULL default false,
  PRIMARY KEY  (`queryid`)
) ENGINE=InnoDB ;
