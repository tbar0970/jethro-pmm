INSERT INTO setting(rank, heading, symbol, note, type, value) VALUES (98,"Attendance Recording","EXTRA_ATTENDANCE_CATEGORIES","","multitext_cm","Extras");

CREATE TABLE `congregation_headcount_categorised` (
  `date` date NOT NULL,
  `congregationid` int(11) NOT NULL,
  `category` varchar(30) NOT NULL,
  `number` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
