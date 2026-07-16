<?php

require_once __DIR__ . '/../include/general.php';

/**
 * Abstract base for admin status panel calls.
 *
 * An admin 'status panel' is a HTML snippet reporting the status of some configurable Jethro subsystem, such as SMS, SMTP or Mailchimp. The status panels rendered by subclasses are displayed on the System Configuration page just below the heading ('SMS Gateway', etc), invoked via AJAX.
 *
 * The status panel consists of:
 *
 *  - a 'Status:' summary line
 *  - Enabled/Disabled
 *  - Configured
 *  - Status message
 *  - Operations
 *
 * Where:
 *
 * "Status: …" is a plain-English summary ({@see self::summaryText()}), e.g.
 * "Status: enabled but not configured".
 *
 *   1. Enabled/Disabled (if {@see linkedFeature()} returns a feature flag name) —
 *      is the feature toggled on? Links to the ENABLED_FEATURES setting: "(link)" when
 *      enabled, "(see: Enabled Features)" when disabled, naming the fix for the reader.
 *
 *   2. Configured — are the required credentials/constants in place? This is a
 *      cheap check ({@see self::isConfigured()}) that does not touch external services.
 *
 *   3. (If configured) Status message with optional collapsible details — is the feature actually
 *      working right now? This is a live operational check ({@see getStatus()})
 *      that may contact external providers, fetch balances, test API calls, etc.
 *      The 'success' field is about operational health, not config existence.
 *
 *   4. Operations are configuration-related actions that might be taken for this subsystem, e.g. 'Register Sender ID' for SMS.
 *
 * Full writeup: docs/docs/developer/reference/status-panels.mdx
 */
abstract class Call_Admin_Statuspanel extends Call
{
    /**
     * Feature flag name for the Enabled/Disabled line.
     *
     * @return string|null null means "not applicable" (no Enabled line shown);
     *                     override to return a feature flag name e.g. 'SMS'.
     */
    public function linkedFeature(): ?string
    {
        return null;
    }

    /**
     * Whether the required credentials/constants are in place.
     *
     * This is a cheap check — do not contact external services here.
     *
     * @return \Result<bool, array{message: string, details?: string}> Success, or failure
     *     carrying a one-line 'message' saying what is missing, plus an optional 'details'
     *     block of HTML (shown behind a "(details)" toggle) telling the admin how to fix it.
     */
    abstract protected function isConfigured(): \Result;

    /** Help text (HTML) displayed above the status. */
    abstract protected function getHelpText(): string;

    /**
     * Live operational check — is the feature actually working right now?
     *
     * The 'success' field is about operational health, not config existence. E.g. a SMS provider might be configured correctly (isConfigured succeeds), but its API might be offline (getStatus returns false).
     * Subclasses should use a message like "Connected" / "Not available" rather
     * than "Configured" / "Not configured" to avoid redundancy with the Configured line.
     * 'message' is an overall summary. 'details' is key:value pairs displayed in a table.
     *
     * Every string here is PLAIN TEXT and is escaped by the renderer — do not
     * call ents() on it and do not pass markup. Detail values routinely carry
     * third-party bytes (SMTP banners, API error text, scripture), so escaping
     * is centralised rather than left to each panel to remember. This differs
     * from {@see isConfigured()}, whose 'details' is a block of HTML prose.
     *
     * @return array{success: bool, message: string, details?: array<string, string>}
     */
    abstract protected function getStatus(): array;

    /**
     * Operations the admin can initiate from this status panel.
     *
     * Each entry maps an operation name to a human-readable button label.
     * When non-empty, the status panel renders a row of buttons; clicking a
     * button fires a Datastar @get to the operation handler which returns the
     * form HTML; submitting the form triggers a Datastar @post to the same
     * handler which returns the result HTML — both morphed into the container
     * by ID.
     *
     * The operation handler URL is derived from the status panel class name:
     *
     *   Call_Admin_Statuspanel_Sms →
     *   ?call=admin_statuspanel_operation_sms
     *
     * @return array<string, string>  operation name => label
     */
    protected function getOperations(): array
    {
        return [];
    }

    public function run(): void
    {
        if (!$GLOBALS['user_system']->havePerm(PERM_SYSADMIN)) {
            return;
        }

        $suffix = strtolower(substr(static::class, strlen('Call_Admin_Statuspanel_')));

        // Resolve every check before rendering: the summary is printed above the
        // lines it summarises, so it needs their outcomes up front.
        $feature = $this->linkedFeature();
        $enabled = $feature === null ? null : $GLOBALS['system']->featureEnabled($feature);

        $configuredResult = $this->isConfigured();
        $configured = $configuredResult->isSuccess();

        // getStatus() may contact external services — only worth asking once configured.
        $status = $configured ? $this->getStatus() : null;
        $working = $status === null ? null : $status['success'];
        ?>
        <div id="status-panel-<?php echo ents($suffix); ?>" class="status-panel">
        <p class="status-panel-help"><?php echo $this->getHelpText(); ?></p>
        <p class="status-panel-summary">Status:
            <?php echo ents($this->summaryText($enabled, $configured, $working)); ?>:</p>
        <div class="status-panel-lines">
        <?php
        if ($enabled !== null) {
            $this->printStatusLine(
                $enabled,
                ($enabled ? 'Enabled' : 'Disabled')
                    . ' <a href="' . baseurl_relative() . '/?view=admin__system_configuration#ENABLED_FEATURES"><i>'
                    . ($enabled ? '(link)' : '(see: Enabled Features)') . '</i></a>'
            );
        }

        if ($status === null) {
            $error = $configuredResult->getError();
            $this->printStatusLine(
                false,
                'Not configured &mdash; ' . ents($error['message']),
                $error['details'] ?? '',
                'config-panel-details-' . $suffix
            );
        } else {
            $this->printStatusLine(true, 'Configured');
            $this->printStatusLine(
                $status['success'],
                ents($status['message']),
                $this->detailsTable($status['details'] ?? []),
                'status-panel-details-' . $suffix,
                'form-horizontal'
            );
        }
        ?>
        </div>
        <?php

        // Operation buttons
        $operations = $configured ? $this->getOperations() : [];
        if ($operations !== []) {
            $opCall = 'admin_statuspanel_operation_' . $suffix;
            $opId = 'status-panel-ops-' . $suffix;
            ?>
            <div class="status-panel-operations" id="<?php echo ents($opId); ?>">
                <?php foreach ($operations as $method => $label): ?>
                    <a href="javascript:void()"
                        class="status-panel-op-btn"
                        data-on:click="@get('?call=<?php echo ents($opCall); ?>&operation=<?php echo ents($method); ?>')">
                        <i class="icon-plus-sign"></i><?php echo ents($label); ?>
                    </a>
                <?php endforeach; ?>
                <div class="status-panel-op-container" id="<?php echo ents($opId); ?>-container"></div>
            </div>
            <?php
        }
        ?>
        </div>
        <?php
    }

    /**
     * One check line: tick/cross icon, text, and an optional collapsible block.
     *
     * The collapsible block is emitted after the closing </p>, never inside it:
     * a <div> nested in a paragraph is invalid HTML, and browsers close the
     * paragraph early when they meet one — so it ended up a sibling regardless.
     *
     * @param bool   $ok           TRUE → green tick, FALSE → red cross.
     * @param string $text         Line text, already escaped/marked-up by the caller.
     * @param string $detailsHtml  Collapsible content; '' for no details link.
     * @param string $detailsId    Element id tying the toggle to the block.
     * @param string $detailsClass Extra classes for the collapsible block.
     */
    private function printStatusLine(
        bool $ok,
        string $text,
        string $detailsHtml = '',
        string $detailsId = '',
        string $detailsClass = ''
    ): void {
        ?>
        <p class="status-icon-<?php echo $ok ? 'yes' : 'no'; ?>"><?php echo $text; ?>
        <?php if ($detailsHtml !== ''): ?>
            <a href="#" data-toggle="collapse" data-target="#<?php echo ents($detailsId); ?>" onclick="return false"><i>(details)</i></a>
        <?php endif; ?>
        </p>
        <?php if ($detailsHtml !== ''): ?>
        <div id="<?php echo ents($detailsId); ?>" class="collapse status-panel-details <?php echo ents($detailsClass); ?>">
            <?php echo $detailsHtml; ?>
        </div>
        <?php endif;
    }

    /**
     * Render {@see getStatus()}'s detail pairs as a definition-style table.
     *
     * Both label and value are plain text and are escaped here — see the
     * getStatus() contract. Callers must not pre-escape.
     *
     * @param array<string, string> $details
     * @return string HTML, or '' when there is nothing to show.
     */
    protected function detailsTable(array $details): string
    {
        if ($details === []) {
            return '';
        }
        $html = '';
        foreach ($details as $label => $value) {
            $html .= '<div class="control-group">'
                . '<label class="control-label">' . ents($label) . '</label>'
                . '<div class="controls">' . ents($value) . '</div>'
                . '</div>';
        }
        return $html;
    }

    /**
     * Plain-English one-liner combining the enabled / configured / working lines.
     *
     * Returns the bare phrase — {@see run()} wraps it as "Status: <phrase>:", the
     * trailing colon introducing the indented check lines below. E.g. "enabled and
     * working", "enabled but not configured", "configured and working, but not enabled".
     *
     * @param bool|null $enabled    Whether the linked feature flag is on;
     *                              null when the feature has no flag ({@see linkedFeature()}).
     * @param bool      $configured Whether the required settings are in place.
     * @param bool|null $working    Live operational health ({@see getStatus()});
     *                              null when not checked, which is always the case
     *                              when $configured is false.
     */
    protected function summaryText(?bool $enabled, bool $configured, ?bool $working): string
    {
        // 'unconfigured' | 'working' | 'broken' — operational health is irrelevant when unconfigured.
        $state = !$configured ? 'unconfigured' : ($working ? 'working' : 'broken');

        return match (true) {
            // No feature flag: enablement is not a concept for this feature.
            $enabled === null && $state === 'working' => 'configured and working',
            $enabled === null && $state === 'broken'  => 'configured but not working',
            $enabled === null                         => 'not configured',

            $enabled === true && $state === 'working' => 'enabled and working',
            $enabled === true && $state === 'broken'  => 'enabled, configured but not working',
            $enabled === true                         => 'enabled but not configured',

            $state === 'working' => 'configured and working, but not enabled',
            $state === 'broken'  => 'configured but not working, and not enabled',
            default              => 'not configured and not enabled',
        };
    }
}
