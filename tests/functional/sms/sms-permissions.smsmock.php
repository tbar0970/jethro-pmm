<?php

/** sms-permissions profile: would accept sends; the specs assert none arrive. */

use SmsMockServer\Provider\CellcastProfile;

CellcastProfile::register('sms-permissions', function (CellcastProfile $p) {
    $p->setBalance(100);
});
