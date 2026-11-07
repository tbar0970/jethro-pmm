<?php
/** sms-status-panel-v5 profile: a known account balance for the status panel. */
use SmsMockServer\Provider\FiveCentSmsProfile;
FiveCentSmsProfile::register('sms-status-panel-v5', function (FiveCentSmsProfile $p) {
    $p->setBalance(42);
});
