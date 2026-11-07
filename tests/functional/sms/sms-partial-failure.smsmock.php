<?php

/** partial-failure profile: accepts the send but fails one recipient. */

use SmsMockServer\Provider\CellcastProfile;

CellcastProfile::register('sms-partial-failure', function (CellcastProfile $p) {
    // demo's own mobile, which these tests send from (_USER_MOBILE_).
    $p->preRegisterNumbers(['61491570157']);
    $p->setBalance(100);
    // Jamison Williamson (family 14): rejected at send time, while Bridget
    // (0491570156) is accepted. Cellcast is sent the stored local numbers.
    $p->failRecipientAtSend('0491570159', 'Blocked by carrier');
});
