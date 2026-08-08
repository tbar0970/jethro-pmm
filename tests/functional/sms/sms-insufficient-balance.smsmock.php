<?php
/** sms-insufficient-balance profile: an account with no credit. */
use SmsMockServer\Provider\CellcastProfile;
CellcastProfile::register('sms-insufficient-balance', function (CellcastProfile $p) {
    // demo's own mobile, which these tests send from (_USER_MOBILE_): already
    // registered on the account, as Jethro now requires (DbLoggingSmsProvider::verifySenderNumber()).
    $p->preRegisterNumbers(['61491570157']);
    $p->setBalance(0);
});
