<?php

/** cancel-permissions profile: accepts sends/cancels; no special failures. */

use SmsMockServer\Provider\CellcastProfile;

CellcastProfile::register('sms-cancel-permissions', function (CellcastProfile $p) {
    // demo's own mobile, which these tests send from (_USER_MOBILE_).
    $p->preRegisterNumbers(['61491570157']);
    $p->setBalance(100);
});
