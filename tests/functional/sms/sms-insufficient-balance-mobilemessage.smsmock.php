<?php
/** sms-insufficient-balance-mobilemessage profile: an account with no credit. */
use SmsMockServer\Provider\MobileMessageProfile;
MobileMessageProfile::register('sms-insufficient-balance-mobilemessage', function (MobileMessageProfile $p) {
    // demo's own mobile, which these tests send from (_USER_MOBILE_): already
    // verified on the account, as Jethro requires (DbLoggingSmsProvider::verifySenderNumber()).
    $p->preRegisterNumbers(['61491570157']);
    $p->setBalance(0);
});
