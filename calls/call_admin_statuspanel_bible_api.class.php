<?php

/**
 * Status panel for the Bible API section of the system configuration page.
 * Fetched via AJAX from ?call=admin_statuspanel_bible_api.
 *
 * Verifies the BIBLE_API_APIKEY by fetching available translations from
 * api.bible, and fetches John 3:16 as a live test.
 */

require_once __DIR__ . '/call_admin_statuspanel.class.php';

class Call_Admin_Statuspanel_Bible_Api extends Call_Admin_Statuspanel
{
    public function linkedFeature(): ?string
    {
        return null;
    }

    protected function isConfigured(): \Result
    {
        if (ifdef('BIBLE_API_APIKEY', '') !== '') {
            return \Result::success(true);
        }
        return \Result::failure([
            'message' => 'BIBLE_API_APIKEY is not set',
            'details' => 'Register for a free API key at <a href="https://scripture.api.bible" target="_blank">scripture.api.bible</a> '
                . 'and define <code>BIBLE_API_APIKEY</code> in conf.php.',
        ]);
    }

    protected function getHelpText(): string
    {
        return 'When configured, Jethro can fetch Bible passage text from api.bible and display it inline in service handouts and run sheets.';
    }

    protected function getStatus(): array
    {
        require_once JETHRO_ROOT . '/include/bibleapi.php';

        $translations = getBibleTranslations();
        $count = count($translations);

        if ($count === 0) {
            return [
                'success' => false,
                'message' => 'API key is set but no translations could be fetched — check your key and network.',
                'details' => [],
            ];
        }

        // Fetch a well-known passage to verify the API works end-to-end
        $sampleBibleId = array_key_first($translations);
        $sampleResult = fetchBiblePassage('John 3:16', $sampleBibleId);

        $details = [
            'API URL' => BIBLE_API_URL,
            'Translations available' => (string) $count,
        ];

        if ($sampleResult !== null) {
            $sampleText = strip_tags($sampleResult[0]);
            $sampleText = preg_replace('/\s+/', ' ', trim($sampleText));
            if (strlen($sampleText) > 120) {
                $sampleText = substr($sampleText, 0, 117) . '…';
            }
            $details['Example (John 3:16)'] = $sampleText;
        } else {
            $details['Example (John 3:16)'] = 'Failed to fetch';
        }

        $preferred = defined('BIBLE_TRANSLATION_PREFERRED') ? BIBLE_TRANSLATION_PREFERRED : null;
        if ($preferred !== null && $preferred !== '') {
            $info = $translations[$preferred] ?? null;
            if ($info !== null) {
                $details['Preferred translation'] = $info['abbreviation'] . ' — ' . $info['name'];
            } else {
                $details['Preferred translation (not found)'] = $preferred;
            }
        }

        return [
            'success' => true,
            'message' => 'Connected',
            'details' => $details,
        ];
    }
}
