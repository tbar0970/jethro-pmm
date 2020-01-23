update _person_group
set share_member_details = '0' where share_member_details = '';

alter table _person_group
modify column share_member_details varchar(255) not null default '0';