<?php

require_once __DIR__ . '/../include/general.php';

/**
 * Abstract base for admin status panel operation handlers.
 *
 * These handlers are the AJAX targets for operation buttons rendered by
 * {@see Call_Admin_Statuspanel}.  Each feature panel (SMS, SMTP, etc.)
 * has a corresponding operation handler at a predictable URL:
 *
 *   Status panel:  ?call=admin_statuspanel_sms
 *   Operations:    ?call=admin_statuspanel_operation_sms
 *
 * Responses are text/html wrapped via {@see echoContainer()} so the Datastar
 * morph target is consistent.
 *
 * Subclasses override {@see run()} to dispatch by operation name (extracted
 * from GET or POST via {@see operation()}), then call operation-specific
 * {@code do*()} methods that each handle both GET (form) and POST (process).
 * They should call {@see parent::run()} first for the permission check and
 * guard on its return value.
 *
 * This class provides the permission check, the operation-name helper, and
 * the ID helpers that keep the container ID in sync with the static
 * placeholder rendered by {@see Call_Admin_Statuspanel::run()}.
 */
abstract class Call_Admin_Statuspanel_Operation extends Call
{
    /**
     * Check the sysadmin permission.
     *
     * If denied, echoes the error container and returns false.
     * Subclasses override this, call {@see parent::run()}, and guard on the
     * return value before proceeding with their own dispatch logic.
     *
     * @return bool true if permitted, false if denied (error already echoed).
     */
    public function run()
    {
        if (!$GLOBALS['user_system']->havePerm(PERM_SYSADMIN)) {
            $this->echoContainer('<p class="text-error">Permission denied.</p>');
            return false;
        }
        return true;
    }

    /**
     * The requested operation name, extracted from GET or POST params.
     *
     * @return string e.g. 'synchronizeHistory', 'registerSenderId', 'manual'.
     */
    protected function operation(): string
    {
        return $_SERVER['REQUEST_METHOD'] === 'POST'
            ? ($_POST['operation'] ?? '')
            : ($_GET['operation'] ?? '');
    }

    // -----------------------------------------------------------------------
    // ID helpers — keep GET/POST responses in sync with the static placeholder
    // rendered by Call_Admin_Statuspanel::run().
    // -----------------------------------------------------------------------

    /**
     * Lowercase feature suffix derived from the class name.
     *
     * Call_Admin_Statuspanel_Operation_Sms → 'sms'
     */
    protected function featureSuffix(): string
    {
        return strtolower(substr(static::class, strlen('Call_Admin_Statuspanel_Operation_')));
    }

    /**
     * URL call parameter for this operation handler.
     *
     * e.g. 'admin_statuspanel_operation_sms'
     */
    protected function callName(): string
    {
        return 'admin_statuspanel_operation_' . $this->featureSuffix();
    }

    /**
     * Echo $innerHtml wrapped in the morphable container div.
     *
     * The container ID matches the static placeholder rendered by
     * {@see Call_Admin_Statuspanel::run()}; Datastar morphs the response
     * into the existing DOM element by ID.  Pass $visible = false to hide
     * the container (e.g. a close/cancel response).
     */
    protected function echoContainer(string $innerHtml, bool $visible = true): void
    {
        $suffix = $this->featureSuffix();
        $id     = 'status-panel-ops-' . $suffix . '-container';
        $cls    = 'status-panel-op-container' . ($visible ? ' visible' : '');
        echo '<div id="' . ents($id) . '" class="' . $cls . '">' . $innerHtml . '</div>';
    }
}
