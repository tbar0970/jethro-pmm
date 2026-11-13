<?php

/** sms-2fa-gateway-down profile: the gateway rejects every send, including 2FA codes. */

use SmsMockServer\Provider\CellcastProfile;

CellcastProfile::register('sms-2fa-gateway-down', function (CellcastProfile $p) {
    $p->setBalance(100);
    $p->rejectAllSends('Service unavailable');
});
