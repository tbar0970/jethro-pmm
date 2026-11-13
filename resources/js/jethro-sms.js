/************* SMS ****************/

// =============================================================================
// JethroSMS — the few SMS behaviours that belong in the browser
// =============================================================================
// Sending is server-driven through Datastar: both Send buttons post their
// form to ?call=sms, which renders the result alerts itself and answers with
// SSE (Call_SMS::_respondDatastar()). The statusline and preview are likewise
// rendered by ?call=sms_statusline. This file keeps only what has to happen
// in the browser:
//   - opening the send modal for the clicked person(s);
//   - asking, when no person is ticked, whether to send to everyone listed;
//   - applying a send's outcome to the page: row classes, closing the modal,
//     reloading a visible Messages tab (JethroSMS.applySendOutcome);
//   - defaulting the scheduled-send time;
//   - the Persons → Messages cost total and row layout (updateSmsCostSum).
// See docs/docs/developer/sms/send-pipeline.mdx and SMS_DATASTAR.md.
// =============================================================================

var JethroSMS = {
};

/**
 * Initialise the SMS module: called once on page load to attach the modal
 * trigger handler.
 */
JethroSMS.init = function() {
	// -----------------------------------------------------------------------
	// Modal SMS dialog trigger.
	// When a [data-toggle="sms-modal"] element is clicked, populate the modal
	// with recipient info and show it.
	// -----------------------------------------------------------------------
	$(document).on('click', '[data-toggle="sms-modal"]', function(e) {
		var $this = $(this)
				, href = $this.attr('href')
				, $target = $($this.attr('data-target') || (href && href.replace(/.*(?=#[^\s]+$)/, ''))) //strip for ie7
				, option = $target.data('modal') ? 'toggle' : $.extend({remote: !/#/.test(href) && href}, $target.data(), $this.data());

		var recipients = $this.attr('data-name');
		var personid = $this.attr('data-personid');
		var singleRecipient = (personid || '').indexOf(',') === -1;

		// Hide token hint and message preview for single-person sends
		$("#sms-token-hint").toggle(!singleRecipient);
		$(".sms-preview-toggle").toggle(!singleRecipient);

		// Populate the modal with recipient names and clear previous state.
		// .text(), not .html(): the attribute is entity-decoded by jQuery, so
		// a name containing markup would otherwise be parsed as HTML.
		$("#send-sms-modal .sms_recipients").text(recipients);
		if (!singleRecipient) {
			$("#send-sms-modal .sms_recipients").append(
			' <small class="soft">(' + personid.split(',').length + ' recipients)</small>'
			);
		}
		var $modalTa = $target.find('.sms-message');
		$modalTa.val(""); // Empty the textarea in case of reuse
		// Clear the previous send's result and error (Call_SMS patches them in).
		$("#sms-results").empty();
		$("#sms-send-response").hide().empty();

		if (personid) {
			$("#send-sms-modal").attr("data-personid", personid);
			e.preventDefault();
			$target.modal(option).one('hide', function() {
				$this.focus()
			})
			$target.on('shown', function() { $target.find('.sms-message').select(); })
		} else {
			alert('No SMS recipients found');
		}
		// Mirror the recipient into the modal's hidden personid field so the
		// Datastar statusline post (?call=sms_statusline) resolves the right
		// recipient. The statusline itself is server-rendered on first input.
		$('#send-sms-modal input[name="personid"]').val(personid || '');
	});
}

/**
 * Before a bulk send: with no person ticked, ask whether to send to everyone
 * listed (and tick them all), as the other bulk actions do (jethro.js,
 * form.bulk-person-action). Called from the bulk Send button's data-on:click
 * ahead of its @post.
 *
 * @param {HTMLElement} button  The Send button, inside the bulk-action form.
 * @returns {boolean}  TRUE to go ahead with the send.
 */
JethroSMS.confirmBulkRecipients = function(button) {
	var boxes = $(button.form).find("input[name='personid[]']");
	if (boxes.filter(':checked').length > 0) return true;
	if (!confirm('You have not selected any persons. Would you like to perform this action on every person listed?')) {
		return false;
	}
	boxes.prop('checked', true);
	return true;
};

/**
 * Apply a send's outcome, as decided by the server (Call_SMS::_respondDatastar(),
 * Jethro\Sms\presentSendResult()), to the page.
 *
 * @param {Object}  outcome
 * @param {Object}  outcome.rows            person ID → CSS classes for that person's list rows
 *                                          (sms-success / sms-failure / note-success)
 * @param {boolean} outcome.closeModal      Close the send modal (one person sent to, nobody left out)
 * @param {boolean} outcome.reloadMessages  Reload if the page shows a Messages tab, so the new message appears
 * @param {?number} outcome.messageId       Message to scroll to after that reload
 */
JethroSMS.applySendOutcome = function(outcome) {
	Object.keys(outcome.rows).forEach(function(personId) {
		$('tr[data-personid="' + personId + '"]').addClass(outcome.rows[personId].join(' '));
	});
	if (outcome.closeModal) {
		$('#send-sms-modal').modal('hide');
	}
	if (outcome.reloadMessages && $('.messages-history-container').length) {
		if (outcome.messageId) location.hash = 'message_' + outcome.messageId;
		location.reload();
	}
};

// -----------------------------------------------------------------------
// SMS scheduled-send — datetime default on check (visibility via Datastar)
// -----------------------------------------------------------------------
$(document).on('change', '.sms-schedule-toggle', function() {
	var $input = $(this).closest('.control-group').find('.sms-schedule-datetime');
	if (this.checked && !$input.val()) {
		var d = new Date();
		$input.val(d.getFullYear() + '-' + String(d.getMonth()+1).padStart(2,'0') + '-' + String(d.getDate()).padStart(2,'0') + 'T' + String(d.getHours()).padStart(2,'0') + ':' + String(d.getMinutes()).padStart(2,'0'));
	}
	if (!this.checked) {
		$input.val('');
	}
});

$(document).ready(function() {
	JethroSMS.init();
	updateSmsCostSum();
});
$(window).on('resize', updateSmsCostSum);
/**
 * Sum the costs of all currently-visible rows and sync recipient-scroll
 * heights.  Single pass over the rows to avoid double DOM walk.
 * Called by filter input data-on:input handlers (Datastar-driven) and on page load.
 */
function updateSmsCostSum() {
	requestAnimationFrame(function() {
		var sum = 0;
		var rows = document.querySelectorAll('.sms-history-table tbody tr');
		for (var i = 0; i < rows.length; i++) {
			var row = rows[i];
			if (row.style.display === 'none') continue;
			// cost sum
			sum += parseFloat(row.dataset.cost || '0');
			// recipient height sync (reads offsetHeight — only correct
			// for laid-out rows; off-screen rows skipped by h > 0 guard)
			var bodyCell = row.querySelector('td:nth-child(4)');
			var scroll = row.querySelector('.sms-history-recipients-scroll');
			if (bodyCell && scroll) {
				var h = bodyCell.offsetHeight;
				if (h > 0) {
					scroll.style.maxHeight = h + 'px';
				}
			}
		}
		var el = document.getElementById('sms-cost-total');
		if (el) {
			el.textContent = 'Σ $' + sum.toFixed(2);
		}
	});
}
