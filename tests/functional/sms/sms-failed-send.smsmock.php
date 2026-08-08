<?php

/** send-fail profile: rejects all sends (with credit on the account, so the
 *  request reaches the provider; zero-credit blocking is sms-insufficient-balance). */

use SmsMockServer\Provider\CellcastProfile;

CellcastProfile::register('sms-failed-send', function (CellcastProfile $p) {
    // demo's own mobile, which these tests send from (_USER_MOBILE_): already
    // registered on the account, as Jethro now requires (DbLoggingSmsProvider::verifySenderNumber()).
    $p->preRegisterNumbers(['61491570157']);
    $p->setBalance(100);
    $p->rejectAllSends('Insufficient credits');
});
