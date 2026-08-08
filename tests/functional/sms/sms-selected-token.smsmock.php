<?php
/** sms-selected-token profile: an ordinary account with credit. */
use SmsMockServer\Provider\CellcastProfile;
CellcastProfile::register('sms-selected-token', function (CellcastProfile $p) {
    // demo's own mobile, which these tests send from (_USER_MOBILE_): already
    // registered on the account, as Jethro requires (DbLoggingSmsProvider::verifySenderNumber()).
    $p->preRegisterNumbers(['61491570157']);
});
