<?php

/**
 * Status panel for the SMTP Email section of the system configuration page.
 * Fetched via AJAX from ?call=admin_statuspanel_smtp.
 *
 * Emailer::_transport() sends through SMTP_SERVER when set, else through
 * SENDMAIL_COMMAND, else PHP's native mail(); the panel reports whichever of
 * the first two is configured.
 */

require_once __DIR__ . '/call_admin_statuspanel.class.php';

class Call_Admin_Statuspanel_Smtp extends Call_Admin_Statuspanel
{
    protected function isConfigured(): \Result
    {
        if (ifdef('SMTP_SERVER', '') || ifdef('SENDMAIL_COMMAND', '')) {
            return \Result::success(true);
        }
        return \Result::failure([
            'message' => 'Neither SMTP_SERVER nor SENDMAIL_COMMAND is set',
            'details' => 'Define <code>SMTP_SERVER</code> in conf.php (and <code>SMTP_USERNAME</code> / '
                . '<code>SMTP_PASSWORD</code> if your mail server requires authentication), or '
                . '<code>SENDMAIL_COMMAND</code> to send through a sendmail-compatible command. '
                . 'Without either, Jethro falls back to PHP&rsquo;s local <code>mail()</code>.',
        ]);
    }

    protected function getHelpText(): string
    {
        return 'Mail settings are used to send member registration emails, password reset links, error alerts to system administrators, and other system notifications.';
    }

    protected function getStatus(): array
    {
        require_once JETHRO_ROOT . '/include/emailer.class.php';

        if (!ifdef('SMTP_SERVER', '')) {
            // isConfigured() has established SENDMAIL_COMMAND is set: there is no
            // SMTP server to handshake with, and probing the command would mean
            // sending a message, so there is nothing to test here.
            return [
                'success' => true,
                'message' => 'Mail is sent by SENDMAIL_COMMAND, so there is no SMTP connection to test.',
                'details' => [],
            ];
        }

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
