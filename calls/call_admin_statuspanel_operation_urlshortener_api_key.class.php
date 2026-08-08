<?php

require_once __DIR__ . '/call_admin_statuspanel_operation.class.php';
require_once __DIR__ . '/../include/url_shortener.php';   // urlShortenerConfig(), shared with getUrlShortener()

/**
 * Operation handler for the SMS URL Shortener status panel.
 *
 * GET  responses return the morphable container div containing the operation
 *      form.  The submit button carries a Datastar @post attribute so no
 *      JavaScript form-submit handler is needed.
 *
 * POST responses return the morphable container div containing the result
 *      (success message, or error).  Datastar morphs both responses into the
 *      DOM by container ID.
 *
 * URL: ?call=admin_statuspanel_operation_urlshortener_api_key[&operation=<name>]
 *
 * Registration flow (see jethro-url-shortener PHP_INTEGRATION.md):
 *   1. GET register → show a form with a "Sign in with Google" link to
 *      https://jethro.au/signup?redirect=<callback-url>
 *   2. After OAuth, the worker redirects back to the callback URL with
 *      ?sh_apikey=<token>&sh_email=<email>[&sh_replaced=1]
 *   3. This handler detects sh_apikey, writes URLSHORTENER_API_KEY to
 *      the setting table, and shows a success message.
 *
 * @see Call_Admin_Statuspanel_Urlshortener_Api_Key::getOperations()
 */
class Call_Admin_Statuspanel_Operation_Urlshortener_Api_Key extends Call_Admin_Statuspanel_Operation
{
    // ------------------------------------------------------------------
    // Route
    // ------------------------------------------------------------------

    /**
     * Dispatch to the requested URL shortener operation.
     *
     * Calls {@see parent::run()} first for the sysadmin permission check.
     * Each operation method handles both GET (form) and POST (process).
     */
    public function run(): void
    {
        if (!parent::run()) {
            return;
        }

        match ($this->operation()) {
            'register'   => $this->doRegister(),
            'manual'     => $this->doManual(),
            'deregister' => $this->doDeregister(),
            default      => $this->echoContainer('<p class="text-error">Unknown operation.</p>'),
        };
    }

    /**
     * GET — show the signup link, or handle the OAuth callback if returning
     *       from jethro.au with ?sh_apikey=… in the query string.
     */
    private function doRegister(): void
    {
        // Callback from the OAuth signup flow: GET shows a confirmation form,
        // and only its POST saves the key. Saving on the GET would let a link
        // (or another site on the same domain) swap in an attacker's key.
        if (isset($_POST['sh_apikey'])) {
            $this->handleSignupCallback();
            return;
        }
        if (isset($_GET['sh_apikey'])) {
            $this->echoContainer($this->buildCallbackConfirmForm((string) $_GET['sh_apikey'], (string) ($_GET['sh_email'] ?? '')));
            return;
        }

        $this->echoContainer($this->buildRegisterForm());
    }

    /**
     * GET  — show the manual key-entry form.
     * POST — validate and save the submitted API key.
     */
    private function doManual(): void
    {
        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
            $this->echoContainer($this->buildManualForm());
            return;
        }

        $token = $this->persistApiKey(
            $_POST['api_key'] ?? '',
            '<strong>Invalid key.</strong> The API key must start with <code>cus_</code>.'
        );
        if ($token === null) {
            return;
        }

        $settingsUrl = $this->getSettingsUrl();

        $this->echoContainer(
            '<div class="alert alert-success">'
            . '<strong>API key saved.</strong> '
            . 'URL shortening is now enabled. '
            . '<a href="' . ents($settingsUrl) . '">Return to system settings</a>.'
            . '</div>'
            . '<meta http-equiv="refresh" content="2;url=' . ents($settingsUrl) . '">'
        );
    }

    // ------------------------------------------------------------------
    // OAuth signup callback
    // ------------------------------------------------------------------

    /**
     * Process the OAuth callback's confirmation POST (sh_apikey in the body).
     *
     * Validates the token, saves it, and echoes a success or error container.
     * Only reached via POST, so the request-origin check applies.
     */
    private function handleSignupCallback(): void
    {
        $email = (string) ($_POST['sh_email'] ?? 'unknown');

        $token = $this->persistApiKey(
            (string) $_POST['sh_apikey'],
            '<strong>Invalid API key received.</strong> The signup callback did not contain a valid token. '
            . '<a href="?call=' . ents($this->callName()) . '&amp;operation=register">Try again</a>.'
        );
        if ($token === null) {
            return;
        }

        $settingsUrl = $this->getSettingsUrl();
        $masked      = substr($token, 0, 8) . '…';

        $this->echoContainer(
            '<div class="alert alert-success">'
            . '<strong>API key registered for ' . ents($email) . '.</strong> '
            . 'The key <code>' . ents($masked) . '</code> '
            . 'has been saved to your configuration.'
            . '</div>'
            . '<p><a href="' . ents($settingsUrl) . '">'
            . 'Return to system settings</a></p>'
            . '<meta http-equiv="refresh" content="3;url=' . ents($settingsUrl) . '">'
        );
    }

    // ------------------------------------------------------------------
    // Forms
    // ------------------------------------------------------------------

    /**
     * Build the registration form HTML string.
     *
     * Shows a signup link to https://jethro.au/signup with the callback
     * URL pointing back to this handler, so the OAuth flow returns here.
     */
    private function buildRegisterForm(): string
    {
        $base = \urlShortenerConfig()['base'];
        if ($base === '') {
            return '<p class="text-error">No supported URL shortener is configured. '
                . 'Set <code>URLSHORTENER</code> to <code>jethroau</code> or '
                . '<code>https://jethro.au</code> in conf.php.</p>';
        }

        $callbackUrl = $this->getCallbackUrl();
        $signupUrl   = $base . '/signup?redirect=' . urlencode($callbackUrl);
        $campaign    = defined('SYSTEM_NAME') ? (string) SYSTEM_NAME : '';
        if ($campaign !== '') {
            $signupUrl .= '&campaign=' . urlencode($campaign);
        }

        return <<<HTML
            <div class="alert alert-info">
                <strong>Register with jethro.au:</strong>
                Sign in with Google or GitHub to obtain an API key
                for the jethro.au URL shortener.
            </div>
            <p>
                <a class="btn" href="{$signupUrl}" target="_blank"
                   rel="noopener noreferrer">
                   URL Shortener Signup
                </a>
            </p>
            <p class="smallprint">
                After signing in you will be redirected back to this page,
                where you can confirm and save the API key.
                If you already have a key,
                <a href="?call={$this->callName()}&amp;operation=manual">enter it manually</a>.
            </p>
            HTML;
    }

    /**
     * Build the confirmation form shown when the signup flow returns.
     *
     * The key must not be saved straight from the redirect's query string: a
     * link, or a subresource request from another site on the same domain,
     * could otherwise replace the configured key. The POST is covered by the
     * request-origin check.
     */
    private function buildCallbackConfirmForm(string $token, string $email): string
    {
        $callName = ents($this->callName());
        $masked   = ents(substr($token, 0, 8) . '…');
        $maskedRaw = ents($token);
        $emailRaw  = ents($email);
        $who      = $email !== '' ? '<strong>' . ents($email) . '</strong>' : 'your account';
        $valid    = (bool) preg_match('/^cus_[a-f0-9]{32}$/', $token);
        $disabled = $valid ? '' : 'disabled';

        $warning = $valid ? '' : '<div class="alert alert-error">That return address did not contain a valid key. '
            . '<a href="?call=' . $callName . '&amp;operation=register">Try again</a>.</div>';

        $settingsUrl = ents($this->getSettingsUrl());

        // The signup flow returns here by top-level navigation, so this page
        // is the bare container without Datastar: a plain POST form is right.
        return <<<HTML
            {$warning}
            <form method="post" action="?call={$callName}">
                <input type="hidden" name="operation" value="register">
                <input type="hidden" name="sh_apikey" value="{$maskedRaw}">
                <input type="hidden" name="sh_email" value="{$emailRaw}">
                <div class="alert alert-info">
                    jethro.au returned an API key for {$who}.
                    Save it as this site's URL shortener key?
                </div>
                <p>Key: <code>{$masked}</code></p>
                <button type="submit" class="btn btn-primary" {$disabled}>Save API key</button>
                <a class="btn" href="{$settingsUrl}">Cancel</a>
            </form>
            HTML;
    }

    /**
     * Build the manual key-entry form.
     */
    private function buildManualForm(): string
    {
        $callName = ents($this->callName());

        return <<<HTML
            <form onsubmit="return false" class="status-panel-op-form" data-operation="manual">
                <input type="hidden" name="operation" value="manual">
                <div class="control-group">
                    <label class="control-label" for="sh_apikey_manual">
                        jethro.au API key
                    </label>
                    <div class="controls">
                        <input type="text" name="api_key" id="sh_apikey_manual"
                               placeholder="cus_…"
                               pattern="cus_[a-f0-9]{32}"
                               required
                               style="width: 100%; max-width: 30em;">
                        <span class="help-inline">
                            Starts with <code>cus_</code> followed by 32 hex digits.
                        </span>
                    </div>
                </div>
                <div class="control-group">
                    <div class="controls">
                        <button type="button" class="btn btn-primary"
                                data-on:click="@post('?call={$callName}', {contentType: 'form'})">
                            Save API key
                        </button>
                    </div>
                </div>
            </form>
            HTML;
    }

    // ------------------------------------------------------------------
    // Database persistence
    // ------------------------------------------------------------------

    /**
     * Validate the token format and persist it.
     *
     * On failure, echoes the error container (using {@see $invalidFormatError}
     * for format errors, or a fixed message for persistence errors) and returns
     * null.  On success, returns the token.
     *
     * @param string $token              the API key candidate
     * @param string $invalidFormatError inner HTML for the format-error alert
     * @return ?string the token on success, null on failure (error already echoed)
     */
    private function persistApiKey(string $token, string $invalidFormatError): ?string
    {
        if (!preg_match('/^cus_[a-f0-9]{32}$/', $token)) {
            $this->echoContainer('<div class="alert alert-error">' . $invalidFormatError . '</div>');
            return null;
        }

        if (!$this->saveApiKey($token)) {
            $this->echoContainer(
                '<div class="alert alert-error">'
                . '<strong>Could not save the API key.</strong> '
                . 'Check that the database is accessible.'
                . '</div>'
            );
            return null;
        }

        return $token;
    }

    /**
     * Save the API key to the setting database table.
     *
     * Uses Config_Manager::saveSetting() which updates the existing row.
     * If the setting row does not yet exist, it is inserted first.
     * Returns true on success.
     */
    private function saveApiKey(string $token): bool
    {
        $db = $GLOBALS['db'];

        // Ensure the setting row exists.
        $existing = $db->queryOne(
            'SELECT symbol FROM setting WHERE symbol = ' . $db->quote('URLSHORTENER_API_KEY')
        );
        if (!$existing) {
            // queryOne returns a scalar (the first column value).
            $smsShortenRank = (int)$db->queryOne(
                'SELECT `rank` FROM setting WHERE symbol = ' . $db->quote('SMS_SHORTEN_URLS')
            );
            $rank = $smsShortenRank ? $smsShortenRank + 1 : 0;

            $db->exec('INSERT INTO setting
                (`rank`, heading, symbol, note, type, value)
                VALUES ('
                . (int)$rank . ', '
                . $db->quote('SMS URL Shortening') . ', '
                . $db->quote('URLSHORTENER_API_KEY') . ', '
                . $db->quote('API key for the jethro.au URL shortener (obtained via the self-service signup flow)') . ', '
                . $db->quote('text') . ', '
                . $db->quote('')
                . ')'
            );
        }

        Config_Manager::saveSetting('URLSHORTENER_API_KEY', $token);
        return true;
    }

    // ------------------------------------------------------------------
    // Deregister
    // ------------------------------------------------------------------

    /**
     * GET  — show a confirmation form before revoking the key.
     * POST — revoke the key on jethro.au, blank the local setting, show result.
     */
    private function doDeregister(): void
    {
        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
            $this->echoContainer($this->buildDeregisterForm());
            return;
        }

        $apiKey = ifdef('URLSHORTENER_API_KEY', '');
        $prefix = $apiKey !== '' ? substr($apiKey, 0, 8) . '…' : '';

        // Revoke on the jethro.au side.
        $revoked = $this->revokeKeyOnShortener($apiKey);

        // Blank the local setting regardless of remote outcome —
        // the key is useless to us now.
        $this->blankApiKey();

        $settingsUrl = $this->getSettingsUrl();

        if ($revoked) {
            $this->echoContainer(
                '<div class="alert alert-success">'
                . '<strong>Deregistered.</strong> '
                . 'The key <code>' . ents($prefix) . '</code> has been revoked '
                . 'and removed from your configuration.'
                . '</div>'
                . '<p><a href="' . ents($settingsUrl) . '">Return to system settings</a></p>'
                . '<meta http-equiv="refresh" content="2;url=' . ents($settingsUrl) . '">'
            );
        } else {
            $this->echoContainer(
                '<div class="alert alert-warning">'
                . '<strong>Partially deregistered.</strong> '
                . 'The key <code>' . ents($prefix) . '</code> has been removed '
                . 'from your configuration, but revoking it on jethro.au failed. '
                . 'It may already have been revoked.'
                . '</div>'
                . '<p><a href="' . ents($settingsUrl) . '">Return to system settings</a></p>'
            );
        }
    }

    /**
     * Build the deregistration confirmation form.
     */
    private function buildDeregisterForm(): string
    {
        $callName = ents($this->callName());
        $apiKey   = ifdef('URLSHORTENER_API_KEY', '');
        $prefix   = $apiKey !== '' ? substr($apiKey, 0, 8) . '…' : '';

        return <<<HTML
            <div class="alert alert-warning">
                <strong>Deregister from jethro.au?</strong>
                This will:
                <ul>
                    <li>Revoke the API key <code>{$prefix}</code> on jethro.au</li>
                    <li>Remove your email and organisation name from any
                        shortened URLs you created (the URLs themselves will
                        keep working)</li>
                    <li>Remove the key from this configuration —
                        URL shortening in SMS messages will be disabled</li>
                </ul>
            </div>
            <form onsubmit="return false" class="status-panel-op-form" data-operation="deregister">
                <input type="hidden" name="operation" value="deregister">
                <button type="button" class="btn btn-danger"
                        data-on:click="@post('?call={$callName}', {contentType: 'form'})">
                    Yes, deregister
                </button>
                <button type="button" class="btn" style="margin-left: 0.5em;"
                        onclick="var c = this.closest('.status-panel-op-container'); c.classList.remove('visible'); c.innerHTML = '';">
                    Cancel
                </button>
            </form>
            HTML;
    }

    /**
     * Call DELETE /api/keys/mine on jethro.au to revoke the key.
     * Returns true if the key was revoked (or was already gone).
     */
    private function revokeKeyOnShortener(string $apiKey): bool
    {
        if ($apiKey === '') return false;

        // Same resolver getUrlShortener() uses, so this can't disagree with
        // the base the client was configured against.
        $shortenerUrl = \urlShortenerConfig()['base'];
        if ($shortenerUrl === '') return false;

        $ch = curl_init(rtrim($shortenerUrl, '/') . '/api/keys/mine');
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST  => 'DELETE',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . $apiKey],
            CURLOPT_TIMEOUT        => 5,
        ]);
        curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        // 200 = revoked, 404 = already gone — both are success.
        return $httpCode === 200 || $httpCode === 404;
    }

    /**
     * Blank the URLSHORTENER_API_KEY setting (set to empty string, don't delete row).
     */
    private function blankApiKey(): void
    {
        Config_Manager::saveSetting('URLSHORTENER_API_KEY', '');
    }

    // ------------------------------------------------------------------
    // URL helpers
    // ------------------------------------------------------------------

    /**
     * The absolute URL that the OAuth flow redirects back to after signup.
     *
     * This is the call endpoint itself, so the sh_apikey parameter is
     * available in GET when the handler runs next.
     */
    private function getCallbackUrl(): string
    {
        // NB: request_scheme_and_host() applies the A5 X-Forwarded/ALLOWED_HOSTS
        // rules — this URL is registered with the URL-shortener service as an
        // OAuth callback, so a poisoned host would redirect that flow.
        $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        $query = 'call=' . $this->callName() . '&operation=register';

        return request_scheme_and_host()."{$path}?{$query}";
    }

    /**
     * The URL of the system configuration page, where the SMS URL shortener
     * status panel is displayed.  Used to redirect after a successful save.
     */
    private function getSettingsUrl(): string
    {
        $path  = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        $query = 'view=admin__system_configuration';

        return "{$path}?{$query}";
    }
}
