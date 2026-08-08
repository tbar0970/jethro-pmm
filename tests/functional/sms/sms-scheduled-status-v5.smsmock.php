<?php
/**
 * sms-scheduled-status-v5 profile: default 5CentSMS v5 behaviour, the
 * scenario's Sender ID approved, and a one-hour sent→delivered gap so a
 * message that has gone out stays exactly 'sent' while the spec looks at it.
 */
use SmsMockServer\Provider\FiveCentSmsProfile;
FiveCentSmsProfile::register('sms-scheduled-status-v5', function (FiveCentSmsProfile $p) {
    $p->setSenderIds(['Jethro']);
    $p->profile->deliveryDelay = 3600;
});
