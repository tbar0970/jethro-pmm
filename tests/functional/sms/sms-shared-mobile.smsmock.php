<?php
/**
 * sms-shared-mobile profile: default Cellcast behaviour, with the account
 * listing the Sender ID this scenario's SMS_SENDER_OPTIONS names.
 */
use SmsMockServer\Provider\CellcastProfile;
CellcastProfile::register('sms-shared-mobile', function (CellcastProfile $p) {
	$p->setBusinessNames(['Jethro']);
});
