<?php
/**
 * sms-bulk-interactive profile: default Cellcast behaviour, with the account listing the
 * business names (Sender IDs) this scenario's SMS_SENDER_OPTIONS names.
 * Cellcast declares GET_SENDER_IDS, so OverridingSmsProvider only offers
 * configured IDs the account reports.
 */
use SmsMockServer\Provider\CellcastProfile;
CellcastProfile::register('sms-bulk-interactive', function (CellcastProfile $p) {
	$p->setBusinessNames(['Jethro']);
});
