<?php
/** sms-overdraft profile: no credit, yet sends are accepted (overdraft). */
use SmsMockServer\Provider\CellcastProfile;
CellcastProfile::register('sms-overdraft', function (CellcastProfile $p) {
    // demo's own mobile, which these tests send from (_USER_MOBILE_): already
    // registered on the account, as Jethro requires (DbLoggingSmsProvider::verifySenderNumber()).
    $p->preRegisterNumbers(['61491570157']);
    $p->setBalance(0);
});
