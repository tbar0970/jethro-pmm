<?php
/** sms-cooloff profile: default Cellcast behaviour (no overrides). */
use SmsMockServer\Provider\CellcastProfile;
CellcastProfile::register('sms-cooloff', function (CellcastProfile $p) { });
