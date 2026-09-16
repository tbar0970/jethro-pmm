<?php

/**
 * Status panel for the Mailchimp section of the system configuration page.
 * Fetched via AJAX from ?call=admin_statuspanel_mailchimp.
 */

require_once __DIR__ . '/call_admin_statuspanel.class.php';

class Call_Admin_Statuspanel_Mailchimp extends Call_Admin_Statuspanel
{
    protected function isConfigured(): \Result
    {
        if (ifdef('MAILCHIMP_API_KEY', '') !== '') {
            return \Result::success(true);
        }
        return \Result::failure([
            'message' => 'MAILCHIMP_API_KEY is not set',
            'details' => 'Create an API key in Mailchimp under Account &rarr; Extras &rarr; API keys, '
                . 'then define <code>MAILCHIMP_API_KEY</code> in conf.php.',
        ]);
    }

    protected function getHelpText(): string
    {
        return 'Mailchimp integration syncs person query results with Mailchimp audiences, and can send email campaigns.';
    }

    protected function getStatus(): array
    {
        require_once JETHRO_ROOT . '/vendor/drewm/mailchimp-api/src/MailChimp.php';

        $apiKey = ifdef('MAILCHIMP_API_KEY', '');

        try {
            $mc = new \DrewM\MailChimp\MailChimp($apiKey);
        } catch (\Exception $e) {
            return ['success' => false, 'message' => 'Invalid API key: ' . $e->getMessage(), 'details' => []];
        }

        // Ping — GET / returns account info
        $account = $mc->get('');
        if (!$mc->success()) {
            return ['success' => false, 'message' => 'API key rejected: ' . $mc->getLastError(), 'details' => []];
        }

        // Fetch audiences
        $listsResult = $mc->get('lists');
        if (!$mc->success()) {
            return [
                'success' => false,
                'message' => 'Failed to fetch audiences: ' . $mc->getLastError(),
                'details' => [],
            ];
        }

        $lists = $listsResult['lists'] ?? [];
        $details = [
            'Account' => $account['account_name'] ?? 'Unknown',
            'Audiences' => count($lists) . ' list(s)',
        ];

        foreach ($lists as $list) {
            $memberCount = $list['stats']['member_count'] ?? '?';
            $details[$list['name']] = 'ID ' . $list['id'] . ' — ' . $memberCount . ' members';
        }

        return [
            'success' => true,
            'message' => 'Connected to Mailchimp. ' . count($lists) . ' audience(s) found.',
            'details' => $details,
        ];
    }
}
