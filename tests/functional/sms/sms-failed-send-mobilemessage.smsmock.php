<?php
/** sms-failed-send-mobilemessage profile: every send rejected (with credit, so
 *  the request reaches the provider; zero-credit blocking is
 *  sms-insufficient-balance-mobilemessage). */
use SmsMockServer\Provider\MobileMessageProfile;
MobileMessageProfile::register('sms-failed-send-mobilemessage', function (MobileMessageProfile $p) {
    // demo's own mobile, which this test sends from (_USER_MOBILE_): already
    // verified on the account, as Jethro requires (DbLoggingSmsProvider::verifySenderNumber()).
    $p->preRegisterNumbers(['61491570157']);
    $p->setBalance(100);
    $p->rejectAllSends('Insufficient credits');
});
