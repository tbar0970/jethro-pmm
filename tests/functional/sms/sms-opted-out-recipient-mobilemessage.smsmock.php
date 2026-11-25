<?php
/** sms-opted-out-recipient-mobilemessage profile: Jamison Williamson has unsubscribed. */
use SmsMockServer\Provider\MobileMessageProfile;
MobileMessageProfile::register('sms-opted-out-recipient-mobilemessage', function (MobileMessageProfile $p) {
    // demo's own mobile, which this test sends from (_USER_MOBILE_): already
    // verified on the account, as Jethro requires (DbLoggingSmsProvider::verifySenderNumber()).
    $p->preRegisterNumbers(['61491570157']);
    $p->setOptOuts(['61491570159']);
});
