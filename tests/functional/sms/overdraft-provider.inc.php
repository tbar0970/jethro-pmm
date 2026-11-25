<?php
// Shared by the sms-overdraft* scenarios: SMS_PROVIDER is a Cellcast whose
// balance may go negative (FuncTest\OverdraftCellcastSmsProvider). Autoloaded,
// as conf.php is read before the provider package is.
spl_autoload_register(static function (string $class): void {
    if ($class === 'FuncTest\\OverdraftCellcastSmsProvider') {
        require __DIR__ . '/OverdraftCellcastSmsProvider.php';
    }
});
define('SMS_PROVIDER', 'FuncTest\\OverdraftCellcastSmsProvider');
