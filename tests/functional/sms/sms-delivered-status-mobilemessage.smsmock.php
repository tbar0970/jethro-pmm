<?php
/**
 * sms-delivered-status-mobilemessage profile: default MobileMessage behaviour
 * (a sent message is confirmed delivered 5s later) with the scenario's Sender
 * ID on the account.
 */
use SmsMockServer\Provider\MobileMessageProfile;
MobileMessageProfile::register('sms-delivered-status-mobilemessage', function (MobileMessageProfile $p) {
    $p->setSenderIds(['Jethro']);
});
