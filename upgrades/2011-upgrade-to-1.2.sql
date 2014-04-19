#INTERNATIONAL ADDRESS AND PHONE FORMATS
ALTER TABLE family
MODIFY home_tel varchar(16) collate latin1_general_ci NOT NULL default '';
ALTER TABLE family
MODIFY address_postcode varchar(12) collate latin1_general_ci NOT NULL default '';
ALTER TABLE person
MODIFY work_tel varchar(16) collate latin1_general_ci NOT NULL default '';
ALTER TABLE person
MODIFY mobile_tel varchar(16) collate latin1_general_ci NOT NULL default '';

#CONGREGATION LONG NAME AND PRINT QUANTITY
alter table congregation add column `print_quantity` int(11) NOT NULL default 0;
alter table congregation add column long_name varchar(255) NOT NULL default '';