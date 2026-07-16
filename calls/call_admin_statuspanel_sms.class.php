<?php

/**
 * Status panel for the SMS Gateway section of the system configuration page.
 * Fetched via AJAX from ?call=admin_statuspanel_sms.
 */

require_once __DIR__ . '/call_admin_statuspanel.class.php';

class Call_Admin_Statuspanel_Sms extends Call_Admin_Statuspanel
{
    protected function isConfigured(): \Result
    {
        require_once JETHRO_ROOT . '/include/sms_sender.class.php';
        return SMS_Sender::canSend() ? \Result::success(true) : \Result::failure(['message' => 'SMS gateway is not configured']);
    }

    protected function getHelpText(): string
    {
        return 'SMS can be sent to individuals, families, groups, and custom person queries. Messages may optionally be saved as person notes.';
    }

    protected function getStatus(): array
    {
        $details = [];

        $url = ifdef('SMS_HTTP_URL', '');
        $details['Gateway'] = ents($url);


        return [
            'success' => true,
            'message' => 'Gateway is configured.',
            'details' => $details,
        ];
    }
}
