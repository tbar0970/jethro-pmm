<?php

/**
 * Status panel for the SMTP Email section of the system configuration page.
 * Fetched via AJAX from ?call=admin_statuspanel_smtp.
 */

require_once __DIR__ . '/call_admin_statuspanel.class.php';

class Call_Admin_Statuspanel_Smtp extends Call_Admin_Statuspanel
{
    protected function isConfigured(): \Result
    {
        if (ifdef('SMTP_SERVER', '') !== '') {
            return \Result::success(true);
        }
        return \Result::failure([
            'message' => 'SMTP_SERVER is not set',
            'details' => 'Define <code>SMTP_SERVER</code> in conf.php (and <code>SMTP_USERNAME</code> / '
                . '<code>SMTP_PASSWORD</code> if your mail server requires authentication). '
                . 'Without it, Jethro falls back to PHP&rsquo;s local <code>mail()</code>.',
        ]);
    }

    protected function getHelpText(): string
    {
        return 'SMTP settings are used to send member registration emails, error alerts to system administrators, and other system notifications.';
    }

    protected function getStatus(): array
    {
        require_once JETHRO_ROOT . '/include/emailer.class.php';
        $result = Emailer::testConnection();

        $details = [];
        if ($result['greeting'] !== '') {
            $details['Greeting'] = $result['greeting'];
        }
        if ($result['ehlo'] !== '') {
            $details['EHLO'] = $result['ehlo'];
        }

        return [
            'success' => $result['success'],
            'message' => $result['success'] ? 'SMTP server is reachable and responding.' : $result['error'],
            'details' => $details,
        ];
    }
}
