<?php

/** williamson-optout profile: sets up opt-out entries for Williamson family. */

use SmsMockServer\Provider\CellcastProfile;

CellcastProfile::register('sms-opted-out-recipient', function (CellcastProfile $p) {
    // demo's own mobile, which these tests send from (_USER_MOBILE_): already
    // registered on the account, as Jethro now requires (DbLoggingSmsProvider::verifySenderNumber()).
    $p->preRegisterNumbers(['61491570157']);
    $p->setOptOuts([
        [
            'number' => '61491570159',
            'first_name' => 'Jamison',
            'last_name' => 'Williamson',
            'full_name' => 'Jamison Williamson',
        ],
    ]);
});
