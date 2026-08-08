<?php
/**
 * sms-delivered-status-v5 profile: default 5CentSMS v5 behaviour (a sent
 * message is confirmed delivered 5s later) with the scenario's Sender ID approved.
 */
use SmsMockServer\Provider\FiveCentSmsProfile;
FiveCentSmsProfile::register('sms-delivered-status-v5', function (FiveCentSmsProfile $p) {
    $p->setSenderIds(['Jethro']);
});
