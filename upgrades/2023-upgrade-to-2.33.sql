delete from setting where symbol = 'ATTENDANCE_LIST_ORDER';
delete from setting where symbol = 'MEMBER_REGO_EMAIL_CC';
insert into setting (`rank`, symbol, note, type, value)
select `rank`-1, 'MEMBER_REGO_HELP_EMAIL', 'Address that users can contact for assistance with member rego (optional)', 'text', ''
from setting where symbol = 'MEMBER_REGO_FAILURE_EMAIL'; 