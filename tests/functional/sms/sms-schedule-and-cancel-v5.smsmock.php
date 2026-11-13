<?php
/** sms-schedule-and-cancel-v5 profile: default 5CentSMS v5 behaviour. */
use SmsMockServer\Provider\FiveCentSmsProfile;
FiveCentSmsProfile::register('sms-schedule-and-cancel-v5', function (FiveCentSmsProfile $p) {
    // demo's own mobile, which these tests send from (_USER_MOBILE_): already
    // verified on the account, as Jethro requires (DbLoggingSmsProvider::verifySenderNumber()).
    $p->preRegisterNumbers(['61491570157']);
});
