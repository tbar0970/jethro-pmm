<?php
/** sms-cooloff-mobilemessage profile: default MobileMessage behaviour. */
use SmsMockServer\Provider\MobileMessageProfile;
MobileMessageProfile::register('sms-cooloff-mobilemessage', function (MobileMessageProfile $p) {
    // demo's own mobile, which this test sends from (_USER_MOBILE_): already
    // verified on the account, as Jethro requires (DbLoggingSmsProvider::verifySenderNumber()).
    $p->preRegisterNumbers(['61491570157']);
});
