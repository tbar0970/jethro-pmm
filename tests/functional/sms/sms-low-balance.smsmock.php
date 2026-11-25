<?php
/** sms-low-balance profile: an account with 2 credits. */
use SmsMockServer\Provider\CellcastProfile;
CellcastProfile::register('sms-low-balance', function (CellcastProfile $p) {
    // demo's own mobile, which these tests send from (_USER_MOBILE_): already
    // registered on the account, as Jethro requires (DbLoggingSmsProvider::verifySenderNumber()).
    $p->preRegisterNumbers(['61491570157']);
    $p->setBalance(2);
});
