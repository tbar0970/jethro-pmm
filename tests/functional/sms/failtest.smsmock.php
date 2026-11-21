<?php

/** failtest profile: fails a specific recipient. */

use SmsMockServer\Provider\CellcastProfile;

CellcastProfile::register('failtest', function (CellcastProfile $p) {
    $p->failRecipient('61491570159', 'Blocked by carrier');
});
