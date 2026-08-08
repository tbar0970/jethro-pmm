<?php

/** senderid-cache-test profile: verifies sender ID caching behavior. */

use SmsMockServer\Provider\FiveCentSmsProfile;

FiveCentSmsProfile::register('5centsms-senderid-cache-test', function (FiveCentSmsProfile $p) {
    $p->approveInstantly();
});
