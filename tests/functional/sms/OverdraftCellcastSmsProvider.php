<?php

declare(strict_types=1);

namespace FuncTest;

use Sms\SmsCapability;

/**
 * Functional-test provider: Cellcast whose balance may go negative
 * (SmsCapability::OVERDRAFT), as a host-kept credit ledger's may. Whether a
 * scenario is afforded the overdraft is its SMS_OVERDRAFT_ALLOWED.
 *
 * Loaded by overdraft-provider.inc.php.
 * @see docs/docs/developer/reference/sms-balance.md
 */
class OverdraftCellcastSmsProvider extends \Sms\Providers\CellcastSmsProvider
{
    public function hasCapability(SmsCapability $cap): bool
    {
        return $cap === SmsCapability::OVERDRAFT || parent::hasCapability($cap);
    }
}
