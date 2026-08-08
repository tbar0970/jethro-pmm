<?php
/** sms-status-panel-mobilemessage profile: a known account balance for the status panel. */
use SmsMockServer\Provider\MobileMessageProfile;
MobileMessageProfile::register('sms-status-panel-mobilemessage', function (MobileMessageProfile $p) {
    $p->setBalance(42);
});
