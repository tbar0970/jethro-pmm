<?php
/** sms-status-panel profile: a known account balance for the status panel. */
use SmsMockServer\Provider\CellcastProfile;
CellcastProfile::register('sms-status-panel', function (CellcastProfile $p) {
    $p->setBalance(42);
});
