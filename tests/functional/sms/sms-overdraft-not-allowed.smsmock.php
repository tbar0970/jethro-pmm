<?php
/** sms-overdraft-not-allowed profile: no credit. */
use SmsMockServer\Provider\CellcastProfile;
CellcastProfile::register('sms-overdraft-not-allowed', function (CellcastProfile $p) {
    // demo's own mobile, which these tests send from (_USER_MOBILE_): already
    // registered on the account, as Jethro requires (DbLoggingSmsProvider::verifySenderNumber()).
    $p->preRegisterNumbers(['61491570157']);
    $p->setBalance(0);
});
