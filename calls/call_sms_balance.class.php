<?php

/**
 * AJAX handler returning the SMS account balance.
 *
 * Called via ?call=sms_balance
 * Returns JSON: {"balance": 1234} or {"balance": null}
 */
class Call_SMS_Balance extends Call
{
    function run(): void
    {
        if (!$GLOBALS['user_system']->havePerm(PERM_SENDSMS)) {
            echo json_encode(['balance' => null]);
            return;
        }

        include 'include/jethro_sms.php';
        $balance = \Jethro\Sms\getSmsBalance();
        echo json_encode(['balance' => $balance]);
    }
}
