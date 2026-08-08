<?php
/** sms-2fa profile: default Cellcast behaviour (no overrides). */
use SmsMockServer\Provider\CellcastProfile;
CellcastProfile::register('sms-2fa', function (CellcastProfile $p) { });
