<?php
/** sms-schedule-and-cancel-mobilemessage profile: default MobileMessage behaviour. */
use SmsMockServer\Provider\MobileMessageProfile;
MobileMessageProfile::register('sms-schedule-and-cancel-mobilemessage', function (MobileMessageProfile $p) {
    // demo's own mobile, which these tests send from (_USER_MOBILE_): already
    // verified on the account, as Jethro requires (DbLoggingSmsProvider::verifySenderNumber()).
    $p->preRegisterNumbers(['61491570157']);
});
