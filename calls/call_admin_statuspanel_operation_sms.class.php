<?php

require_once __DIR__ . '/call_admin_statuspanel_operation.class.php';

/**
 * Operation handler for the SMS Gateway status panel.
 *
 * GET  responses return the morphable container div containing the operation
 *      form.  The submit button carries a Datastar @post attribute so no
 *      JavaScript form-submit handler is needed.
 *
 * POST responses return the morphable container div containing the result
 *      (next wizard step, success message, or error).  Datastar morphs both
 *      responses into the DOM by container ID.
 *
 * URL: ?call=admin_statuspanel_operation_sms[&operation=<name>]
 *
 * @see Call_Admin_Statuspanel_Sms::getOperations()
 */
class Call_Admin_Statuspanel_Operation_Sms extends Call_Admin_Statuspanel_Operation
{
    /**
     * The configured provider chain, or NULL after reporting the failure to the
     * admin. Callers just `if ($provider === null) return;`.
     */
    private function getSmsProvider(): ?\Sms\SmsProvider
    {
        require_once JETHRO_ROOT . '/include/jethro_sms.php';
        $result = \Jethro\Sms\getSmsProvider();
        if ($result->isFailure()) {
            $this->echoContainer('<p class="text-error">' . ents($result->getError()) . '</p>');
            return null;
        }
        return $result->getValue();
    }

    /**
     * Dispatch to the requested SMS operation.
     *
     * Calls {@see parent::run()} first for the sysadmin permission check.
     * Each operation method handles both GET (form) and POST (process).
     * Responses are wrapped via {@see echoContainer()} for Datastar morph.
     */
    public function run(): void
    {
        if (!parent::run()) {
            return;
        }

        require_once JETHRO_ROOT . '/include/jethro_sms.php';

        match ($this->operation()) {
            'synchronizeHistory' => $this->doSynchronizeHistory(),
            'registerSenderId'   => $this->doRegisterSenderId(),
            'deleteSenderNumber' => $this->doDeleteSenderNumber(),
            default              => $this->echoContainer('<p class="text-error">Unknown operation.</p>'),
        };
    }

    /**
     * GET  — show the sync-history form.
     * POST — execute a history synchronisation.
     */
    private function doSynchronizeHistory(): void
    {
        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
            $this->echoContainer($this->buildSyncHistoryForm());
            return;
        }

        $sinceRaw = $_POST['since'] ?? '';
        $since    = $sinceRaw !== '' ? strtotime($sinceRaw) : null;
        $since    = $since !== false ? $since : null;

        // The import can run for minutes; release the session lock so the
        // admin's other tabs/requests aren't blocked meanwhile.
        session_write_close();

        try {
            $result = \Jethro\Sms\synchronizeHistory($since);
            $html   = '<div class="alert alert-success">'
                . '<strong>Done.</strong> '
                . 'Imported ' . (int) $result['imported'] . ' deliveries '
                . 'in ' . (int) $result['batches'] . ' new batches'
                . ' (' . (int) $result['skipped'] . ' already existed).'
                . '</div>';
        } catch (\Throwable $e) {
            $html = '<div class="alert alert-error">'
                . '<strong>Error:</strong> ' . ents($e->getMessage())
                . '</div>';
        }

        $this->echoContainer($html);
    }

    /**
     * GET  — start or continue the sender-ID registration wizard.
     * POST — process a registration step submission.
     */
    private function doRegisterSenderId(): void
    {
        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
            $provider = $this->getSmsProvider();
            if ($provider === null) {
                return;
            }
            $result = $provider->registerSenderId(null);

            if ($result->isFailure()) {
                $this->echoContainer('<p class="text-error">' . ents($result->getError()) . '</p>');
                return;
            }

            $schema = $result->getValue();
            if (!$schema instanceof \Sms\RegistrationStep || $schema->isComplete()) {
                $this->echoContainer('<p>No registration steps required.</p>');
                return;
            }

            $this->echoContainer(
                \Jethro\Sms\renderRegistrationStepHtml($schema, 'registerSenderId', '?call=' . $this->callName())
            );
            return;
        }

        // POST
        $params = $_POST;
        unset($params['operation']);

        $provider = $this->getSmsProvider();
        if ($provider === null) {
            return;
        }

        $senderIdStr = $params['senderid'] ?? null;
        $senderId    = $senderIdStr !== null ? new \Sms\SenderID($senderIdStr) : null;
        unset($params['senderid']);
        $result = $provider->registerSenderId($senderId, $params);

        if ($result->isFailure()) {
            $this->echoContainer('<p class="text-error">' . ents($result->getError()) . '</p>');
            return;
        }

        $step = $result->getValue();
        $this->echoContainer(
            \Jethro\Sms\renderRegistrationStepHtml($step, 'registerSenderId', '?call=' . $this->callName())
        );
    }

    /**
     * GET  — show the delete-sendernum confirmation form.
     * POST — delete the number from the provider account.
     *
     * Reached from the "(delete)" links beside each sender number in the
     * status panel details (and from the Delete Sender Number button, which
     * shows the empty form).  On success the whole panel is re-rendered in
     * the same response so the numbers list reflects the deletion without a
     * manual reload: Datastar morphs each top-level element by ID, so the
     * panel is echoed BEFORE the confirmation container — morphing the panel
     * first replaces the (empty) operations container it contains, and the
     * confirmation morph then lands in the fresh one.
     */
    private function doDeleteSenderNumber(): void
    {
        $numberRaw = trim((string) ($_REQUEST['number'] ?? ''));

        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
            $this->echoContainer($this->buildDeleteForm($numberRaw));
            return;
        }

        if ($numberRaw === '') {
            $this->echoContainer('<p class="text-error">No number given.</p>');
            return;
        }
        try {
            $number = new \Sms\PhoneNumber($numberRaw);
        } catch (\InvalidArgumentException $e) {
            $this->echoContainer('<p class="text-error">Invalid phone number.</p>');
            return;
        }

        $provider = $this->getSmsProvider();
        if ($provider === null) {
            return;
        }

        if (!$provider->hasCapability(\Sms\SmsCapability::DELETE_SENDER_NUMBER)) {
            $this->echoContainer('<p class="text-error">This provider cannot delete sender numbers via the API.</p>');
            return;
        }

        $result = $provider->deleteSenderNumber($number);
        if ($result->isFailure()) {
            $this->echoContainer(
                '<div class="alert alert-error"><strong>Error:</strong> ' . ents($result->getError()) . '</div>'
            );
            return;
        }

        echo $this->renderSmsPanel();
        $this->echoContainer(
            '<div class="alert alert-success"><strong>Deleted.</strong> '
            . ents($numberRaw) . ' is no longer registered with the provider.</div>'
        );
    }

    /**
     * Build the delete confirmation form. With no number (the generic Delete
     * Sender Number button) the admin types one in.
     *
     * Returns a <form> fragment for {@see echoContainer()} to wrap; the
     * confirm button carries the Datastar @post attribute (see
     * {@see buildSyncHistoryForm()}).
     */
    private function buildDeleteForm(string $number): string
    {
        $postUrl = ents('?call=' . $this->callName());
        $numE = ents($number);
        if ($number === '') {
            $numberInput = '<label>Sender number <input type="tel" name="number" required></label>';
            $title = 'Delete a sender number?';
        } else {
            $numberInput = '<input type="hidden" name="number" value="' . $numE . '">';
            $title = 'Delete sender number ' . $numE . '?';
        }
        return <<<HTML
            <form onsubmit="return false" class="status-panel-op-form" data-operation="deleteSenderNumber">
                <input type="hidden" name="operation" value="deleteSenderNumber">
                {$numberInput}
                <div class="alert alert-danger">
                    <strong>{$title}</strong> This removes it from the
                    provider account and cannot be undone; to use it as a sender again it
                    must be re-registered and re-verified. If it is a stale unverified
                    entry, deleting it un-blocks fresh registration attempts.
                </div>
                <button type="button" class="btn btn-danger"
                        data-on:click="@post('{$postUrl}', {contentType: 'form'})">
                    <i class="icon-trash icon-white"></i> Confirm delete
                </button>
            </form>
            HTML;
    }

    /**
     * Render the SMS Gateway status panel (fresh, post-deletion state).
     *
     * Re-runs the panel call directly — same permission check, same output —
     * capturing its echo instead of letting it hit the response.
     */
    private function renderSmsPanel(): string
    {
        require_once __DIR__ . '/call_admin_statuspanel_sms.class.php';
        ob_start();
        (new Call_Admin_Statuspanel_Sms())->run();
        return (string) ob_get_clean();
    }

    /**
     * Build the sync-history form HTML string.
     *
     * Returns a <form> fragment for {@see echoContainer()} to wrap.
     * The submit button carries a Datastar @post attribute so no JS handler
     * is needed.
     */
    private function buildSyncHistoryForm(): string
    {
        $postUrl  = ents('?call=' . $this->callName());
        $dateVal  = date('Y-m-d\TH:i', time() - 86400);
        return <<<HTML
            <form onsubmit="return false" class="status-panel-op-form" data-operation="synchronizeHistory">
                <input type="hidden" name="operation" value="synchronizeHistory">
                <div class="alert alert-info">
                    <strong>Import SMS History:</strong> Fetches deliveries from the
                    upstream provider and imports any that don't already exist locally.
                    Safe to run multiple times.
                </div>
                <label for="sync-since">From (optional):</label>
                <input type="datetime-local" name="since" id="sync-since" step="86400"
                       value="{$dateVal}"
                       style="width: 250px; display: inline-block; margin: 0 6px;">
                <span class="help-inline">blank = last 24 hours</span>
                <br><br>
                <button type="button" class="btn btn-danger"
                        data-on:click="@post('{$postUrl}', {contentType: 'form'})">
                    <i class="icon-refresh icon-white"></i> Synchronize
                </button>
            </form>
            HTML;
    }

}
