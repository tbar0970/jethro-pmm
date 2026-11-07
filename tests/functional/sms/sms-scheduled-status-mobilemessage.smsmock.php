<?php
/**
 * sms-scheduled-status-mobilemessage profile: default MobileMessage
 * behaviour, the scenario's Sender ID on the account, and a one-hour
 * sent→delivered gap so a message that has gone out stays exactly 'sent'
 * while the spec looks at it.
 */
use SmsMockServer\Provider\MobileMessageProfile;
MobileMessageProfile::register('sms-scheduled-status-mobilemessage', function (MobileMessageProfile $p) {
    $p->setSenderIds(['Jethro']);
    $p->profile->deliveryDelay = 3600;
});
