<?php
/**
 * sms-delivered-status profile: default Cellcast behaviour (a sent message is
 * confirmed delivered 5s later) with the scenario's Sender ID listed.
 */
use SmsMockServer\Provider\CellcastProfile;
CellcastProfile::register('sms-delivered-status', function (CellcastProfile $p) {
	$p->setBusinessNames(['Jethro']);
});
