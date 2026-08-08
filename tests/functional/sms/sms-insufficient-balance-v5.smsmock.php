<?php
/** sms-insufficient-balance-v5 profile: an account with no credit. */
use SmsMockServer\Provider\FiveCentSmsProfile;
FiveCentSmsProfile::register('sms-insufficient-balance-v5', function (FiveCentSmsProfile $p) {
    // demo's own mobile, which these tests send from (_USER_MOBILE_): already
    // verified on the account, as Jethro requires (DbLoggingSmsProvider::verifySenderNumber()).
    $p->preRegisterNumbers(['61491570157']);
    $p->setBalance(0);
});
