<?php

/** broken profile: returns 500 from the balance endpoint, simulating an unreachable gateway. */

use SmsMockServer\Provider\FiveCentSmsProfile;

FiveCentSmsProfile::register('sms-wrong-profile', function (FiveCentSmsProfile $p) {
    $p->profile->endpoint('GET', '/api/v1/balance')
        ->returnJSON(['error_code' => 500, 'error_msg' => 'Internal server error'])
        ->returnStatus(500);
});
