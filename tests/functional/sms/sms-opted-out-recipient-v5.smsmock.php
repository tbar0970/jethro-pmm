<?php
/** sms-opted-out-recipient-v5 profile: Jamison Williamson has opted out. */
use SmsMockServer\Provider\FiveCentSmsProfile;
FiveCentSmsProfile::register('sms-opted-out-recipient-v5', function (FiveCentSmsProfile $p) {
    // demo's own mobile, which this test sends from (_USER_MOBILE_): already
    // verified on the account, as Jethro requires (DbLoggingSmsProvider::verifySenderNumber()).
    $p->preRegisterNumbers(['61491570157']);
    $p->setOptOuts(['61491570159']);
});
