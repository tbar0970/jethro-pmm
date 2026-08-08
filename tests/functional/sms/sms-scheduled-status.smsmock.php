<?php
/**
 * sms-scheduled-status profile: default Cellcast behaviour, the scenario's
 * Sender ID listed, and a one-hour sent→delivered gap so a message that has
 * gone out stays exactly 'sent' while the spec looks at it.
 */
use SmsMockServer\Provider\CellcastProfile;
CellcastProfile::register('sms-scheduled-status', function (CellcastProfile $p) {
	$p->setBusinessNames(['Jethro']);
	$p->profile->deliveryDelay = 3600;
});
