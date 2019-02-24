$(document).ready(function() {

	if ($('.stop-js').length) return; /* Classname flag for big pages that don't want JS to run */

	// Make standalone safari stay standalone
	if (("standalone" in window.navigator) && window.navigator.standalone) {
		// http://www.andymercer.net/blog/2016/02/full-screen-web-apps-on-ios/
		var insideApp = sessionStorage.getItem('insideApp'), location = window.location.href, stop = /^(a|html)$/i;
		if (insideApp) {
			localStorage.setItem('returnToPage', location);
		} else {
			var returnToPage = localStorage.getItem('returnToPage');
			if (returnToPage && (returnToPage != location) && ($('.login-box').length == 0)) {
				window.location.href = returnToPage;
			}
			sessionStorage.setItem('insideApp', true);
		}

		// add a back button
		$('a.brand').parent().prepend('<ei class="icon-white icon-chevron-left" onclick="history.go(-1); "></i>')

		// stay inside the app, avoid linking out to mobile safari
		$("a").click(function (event) {
			if ((!$(this).attr('target'))
					&& (!$(this).attr('data-toggle'))
					&& this.href != ''
					&& this.href.indexOf('#') != 0
					&& this.href.indexOf('javascript:') != 0
					&& !((this.innerHTML == 'Search') && $(this).parents('.nav').length)
			) {
				event.preventDefault();
				window.location = $(this).attr("href");
				return false;
			}
		});
	}
	if (("standalone" in window.navigator) && !window.navigator.standalone) {
		// Opportunity to tell them to save to home screen
	}

	// This needs to be first!
	// https://github.com/twitter/bootstrap/issues/3217
	$('#jethro-overall-width').append($('.modal').not('form .modal').remove());
	$('.modal').on('shown', function() {
		$(this).find('input:first, select:first').select();
	});

	$('.modal.autosize').on('shown', function() {
		$(this).css({
			width: 'auto',
			'margin-left': function () {
				return -($(this).width() / 2);
			}
		});
	});

  	// Attach the quick-search handlers
	$('.nav a').each(function() {
		if (this.innerHTML && (this.innerHTML.toLowerCase() == 'search')) {
			$(this).click(handleSearchLinkClick);
			this.accessKey = $(this).parents('ul').parents('li').find('a.dropdown-toggle').html().toLowerCase()[0];
		}
	});


	// Popups etc
	var envelopeWindow = null;
	$('a.envelope-popup').click(function() {
		envelopeWindow = window.open(this.href, 'envelopes', 'height=320,width=500,location=no,menubar=no,titlebar=no,toolbar=no,resizable=yes,statusbar=no');
		if (envelopeWindow) {
			setTimeout('envelopeWindow.print()', 750);
		} else {
			alert('Jethro tried but could not open a popup window - you probably have a popup blocker enabled.  Please disable your popup blocker for this site, reload the page and try again.');
		}
		return false;
	});

	$('a.postcode-lookup').click(function() {
		var suburb = this.parentNode.getElementsByTagName('INPUT')[0].value;
		var state = $('select[name=address_state]');
		if ((-1 != this.href.indexOf('__SUBURB__')) && (suburb == '')) {
			alert('You must enter a suburb first, then click the link to find its postcode');
			this.parentNode.getElementsByTagName('INPUT')[0].focus();
			return false;
		}
		var url = this.href.replace('__SUBURB__', suburb);
		if (state.length) url = url.replace('__STATE__', state.get(0).value);
		var postcodeWindow = window.open(url, 'postcode', 'height=320,width=650,location=no,menubar=no,titlebar=no,toolbar=no,resizable=yes,statusbar=no,scrollbars=yes');
		if (!postcodeWindow) {
			alert('Jethro tried but could not open a popup window - you probably have a popup blocker enabled.  Please disable your popup blocker for this site, reload the page and try again.');
		}
		return false;
	});

	$('a.ccli-lookup').click(function() {
		var title = $('[name=title]').val();
		if (title == '') return false;
		var url = this.href.replace('__TITLE__', title);
		var ccliWindow = window.open(url, 'ccli', 'height=320,width=800,location=no,menubar=no,titlebar=no,toolbar=no,resizable=yes,statusbar=no,scrollbars=yes');
		if (!ccliWindow) {
			alert('Jethro tried but could not open a popup window - you probably have a popup blocker enabled.  Please disable your popup blocker for this site, reload the page and try again.');
		}
		return false;
	});

	$('a.map').click(function() {
		var mapWindow = window.open(this.href, 'map', 'height='+parseInt($(window).height()*0.9, 10)+',width='+parseInt($(window).width()*0.9, 10)+',location=no,menubar=no,titlebar=no,toolbar=no,resizable=yes,statusbar=no');
		if (!mapWindow) {
			alert('Jethro tried but could not open a popup window - you probably have a popup blocker enabled.  Please disable your popup blocker for this site, reload the page and try again.');
		}
		return false;
	});

	$('input.cancel, a.cancel').click(function() {
		if (window.opener) {
			try {
				// If we are a popup, close ourselves if possible
				if ((window.opener.location.hostname+window.opener.location.pathname) == (window.location.hostname+window.location.pathname)) {
					window.close();
					return false;
				}
			} catch (e) {}
		}
		if ($.browser.msie) {
			this.parentNode.click();
		}
	});

	/*********************** LOCK EXPIRY WARNING ****************/
	initLockExpiry();


	/*************************** REPORTS *********************/
	$('input.select-rule-toggle').click(function() {
		$($(this).parents('tr')[0]).find('div.select-rule-options').css('display', (this.checked ? '' : 'none'));
	});

	/************************ SEARCH CHOOSERS ************************/

	$('input.person-search-multiple').each(function() {
		var stem = this.id.substr(0, this.id.length-6);
		var options = {
			script: "?call=find_person_json&",
			varname: "search",
			json: true,
			maxresults: 10,
			delay: 300,
			cache: false,
			timeout: -1,
			callback: new Function("item",
							"$(document.getElementById('"+stem+"-list')).append('<li><div class=\"delete-list-item\" title=\"Remove this item\" onclick=\"deletePersonChooserListItem(this);\" />'+item.value+'<input type=\"hidden\" name=\""+stem+"[]\" value=\"'+item.id+'\" /></li>');" +
							"with (document.getElementById('"+stem+"-input')) {"+
								"if (typeof onchange == 'function') onchange(); " +
								"value = '';" +
								"focus();" +
							"}"
					  )
		};
		var as = new bsn.AutoSuggest(this.id, options);
	});

	$('input.person-search-single, input.family-search-single').each(function() {
		var stem = this.id.substr(0, this.id.length-6);
		var options = {
			varname: "search",
			json: true,
			maxresults: 10,
			delay: 300,
			cache: false,
			timeout: -1,
			callback: new Function("item",
							"document.getElementsByName('"+stem+"')[0].value = item.id;" +
							"with (document.getElementById('"+stem+"-input')) {"+
								"if (typeof onchange == 'function') onchange(); " +
								"value = item.value+' (#'+item.id+')';" +
								"select();" +
								"oldValue = value;" +
							"}"
					  )
		};
		options.script = $(this).hasClass('person-search-single') ? "?call=find_person_json&" : "?call=find_family_json&";
		var as = new bsn.AutoSuggest(this.id, options);
	}).focus(function() {
		this.select();
	}).blur(function() {
		if (this.value == '') {
			document.getElementsByName(this.id.substr(0, this.id.length-6))[0].value = 0;
		} else if ((this.value != this.oldValue) && (this.oldValue)) {
			this.value = this.oldValue;
		}
	});


	/******************* DOCUMENT REPOSITORY ************************/

	if ($('.document-icons').length) {
		$('.document-message').hide().fadeIn('medium');
		$('.rename-file').click(function() {
			var filename = $(this).parents('tr:first').find('td.filename').text();
			$('#rename-file-modal')
				.modal('show')
				.on('shown', function() {
					$(this).find('input#rename-file')
								.attr('name', 'renamefile['+filename+']')
								.attr('value', filename);
					TBLib.selectBasename.apply($(this).find('input#rename-file').get(0));
			});
		});
		$('.replace-file').click(function() {
			var filename = $(this).parents('tr:first').find('td.filename').text();
			$('#replace-file-modal')
				.modal('show')
				.find('input#replace-file')
					.attr('name', 'replacefile['+filename+']')
				.end()
				.find('span#replaced-filename')
					.html(filename)
				.end()
				.find('form')
					.submit(function() {
						var origname = $('span#replaced-filename').text().toLowerCase();
						var newname = $('input#replace-file').val().replace(/.+[\\\/]/, '').toLowerCase();
						if (newname != origname) {
							if (!confirm('You are uploading a file called "'+newname+'" but it will be saved as "'+origname+'"')) {
								$('#replace-file-modal').hide();
								return false;
							}
						}
						return true;
					});
		});
		$('.move-file').click(function() {
			var filename = $(this).parents('tr:first').find('td.filename').text();
			$('#move-file-modal')
				.find('span#moving-filename')
					.html(filename)
					.end()
				.modal('show')
				.on('shown', function() {
							$(this).find('select#move-file')
								.attr('name', 'movefile['+filename+']')
								.focus();
			});
		});


		$('#upload-file-modal input[type=file], #replace-file-modal input[type=file]').change(function() {
			$(this.form)
				.submit()
				.find('.upload-progress').show()
				.end()
				.find('input[type=button]').attr('disabled', true);
		});
	}


	/*************************** BULK ACTIONS ********************/
	$('#bulk-action-chooser').change(function() {
		$('.bulk-action').hide();
		$('.bulk-action input, .bulk-action select, .bulk-action textarea').attr('disabled', true);
		$('#'+this.value).show('fast', function() { try { this.scrollIntoView() } catch(e) {} });
		var selectedInputs = $('#'+this.value+' input, #'+this.value+' select, #'+this.value+' textarea');
		selectedInputs
				.attr('disabled', false)
				.filter(':visible:first').focus();
		selectedInputs.filter('[data-toggle=enable]').attr('disabled', false).change();
	});

	$('form.bulk-person-action').submit(function(event) {
		var checkboxes = document.getElementsByName('personid[]');
		if ($("input[name='personid[]']:checked").length === 0) {
			if (confirm('You have not selected any persons. Would you like to perform this action on every person listed?')) {
			  for (var i = 0; i < checkboxes.length; i++) {
				checkboxes[i].checked = true;
			  }
			} else {
			  TBLib.cancelValidation();
			  return false;
			}
		}
		return true;
    });

	/********************** TAGGING ******************/

	$('select.tag-chooser').change(function() {
		if (this.value == '_new_') {
			$(this).next('input').show().select();
		} else {
			$(this).next('input').show().hide();

		}
	});

	/************* HIGHLIGHT NOTE *****************/
	if (document.location.hash) {
		$(document.location.hash).filter('.notes-history-entry').addClass('highlight');
	}


	/***************** LAYOUT FIXES *******************/

	layOutMatchBoxes();
	$('a[data-toggle="tab"]').on('shown', layOutMatchBoxes);
	$(window).resize(layOutMatchBoxes);

	// Make sure the width doesn't bounce around when we change tabs
	var tabPanes = $('.tab-pane');
	if (tabPanes.length) {
		/*
		// This caused problems with the half-width tabs in service comps page
		var maxWidth = 0;
		tabPanes.each(function() {
			var w = $(this).width();
			if (w > maxWidth) maxWidth = w;
		});
		$('.tab-content').width(maxWidth);
		*/

		if (document.location.hash) {
			var targetTab = $('a[name='+document.location.hash.substr(1)+']').parents('.tab-pane');
			if (targetTab.length) {
				$('a[href=#'+targetTab.attr('id')+']').tab('show');
			}
			$(".nav-tabs li a[href='#" + window.location.hash.substr(1) + "']").click()
		}
	}

	/****** Radio buttons *****/

	var attendanceUseKeyboard = ($(window).width() > 640);

	$('.radio-button-group div')
		.on('touchstart', function(event) {
			var t = $(this);
			onRadioButtonActivated.apply(t);
			event.preventDefault();
			event.stopPropagation();
			t.off('click');
			return false;
		})
		.on('click', function() {
			onRadioButtonActivated.apply($(this));
		});

	function onRadioButtonActivated(event) {
		this.addClass('active');
		this.siblings('div').removeClass('active');
		this.parents('.radio-button-group').find('input').val(this.attr('data-val'));

		if (attendanceUseKeyboard) {
			var thisCell = $(this).parents('td');
			thisCell.closest('tr').removeClass('hovered');
			var nextCell = thisCell;
			var wentToNextRow = false;
			do {
				nextCell = nextCell.next('td');
				if (!nextCell.length && !wentToNextRow) {
					wentToNextRow = true;
					nextCell = thisCell.parents('tr').next('tr').find('td').first();
				}

			} while (nextCell.length && !nextCell.find('.radio-button-group').length);

			nextCell.find('.radio-button-group').focus();
		}
	}

	if (attendanceUseKeyboard) {
		/* when a key is pressed while a button group is hovered, click the applicable button */
		$('.radio-button-group').keypress(function(e) {
			var theChar = String.fromCharCode(e.which).toUpperCase();
			$(this).find('div').each(function() {
				if ($(this).text().trim() == theChar) {
					this.click();
				}
			});
		});

		/* support up/down buttons for row navigation */
		$('.attendance .radio-button-group').keyup(function(e) {
			if (e.which == 40) $(this).parents('tr:first').next('tr').find('.radio-button-group').focus();
			if (e.which == 38) $(this).parents('tr:first').prev('tr').find('.radio-button-group').focus();
		});

		/* mark a row as hovered when the focus shifts to it */
		$('.attendance .radio-button-group').focus(function() {
			$('tr.hovered').removeClass('hovered');
			$(this).parents('tr:first').addClass('hovered');
		});
	}

	// MULTI-SELECT

	$('div.multi-select label input').change(function() {
		if (this.checked) {
			$(this.parentNode).addClass('active');
		} else {
			$(this.parentNode).removeClass('active');
		}
	}).change();

	// FAMILY PHOTOS

	handleFamilyPhotosLayout();

	// NARROW COLUMNS

	setTimeout( "applyNarrowColumns('body'); ", 30);

	JethroServicePlanner.init();

	JethroRoster.init();


	$('table.reorderable tbody').sortable(	{
			cursor: "move",
			/*containment: "parent",*/
			revert: 100,
			opacity: 1,
			axis: 'y',
		});

	$('input.select-other').prevAll('select').change(function() {
		var otherBox = $(this).nextAll('input.select-other');
		if (this.value == 'other') {
			otherBox.show().focus();
		} else {
			otherBox.val('').hide();
		}
	}).change();

	if (document.getElementById('custom-fields-editor')) {
		$("#custom-fields-editor>tbody").sortable(	{
			cursor: "move",
			/*containment: "parent",*/
			revert: 100,
			opacity: 1,
			axis: 'y',
			start: function(event, ui) { ui.helper.find('table').hide(); },
			stop: function(event, ui) { ui.item.find('table').show('medium'); },

		})

		$('#custom-fields-editor').parents('form').submit(function() {
			var optionsMsg = fieldsMsg = '';
			$(this).find('input[type=checkbox]').each(function() {
				if (this.checked){
					if (this.name.match(/fields_[0-9]+_delete/)) fieldsMsg = "\nDeleting a field will delete all values for that field from all persons.";
					if (this.name.match(/fields_[0-9]+_options_delete/)) optionsMsg = "\nDeleting a select option will remove that value from all persons currently using it.";
				}
			})
			if (optionsMsg || fieldsMsg) return confirm("WARNING: "+fieldsMsg+optionsMsg+"\nAre you sure you want to continue?");
		})

		$('#custom-fields-editor .toggle-divider input').click(function() {
			$(this).parents('tr')[this.checked ? 'addClass' : 'removeClass']('divider-before');
		});
		var handleTooltipClick = function() {
			var tt = $(this).parents('tr').find('.tooltip-text');
			tt[this.checked ? 'show' : 'hide']();
			if (this.checked) {
				tt.focus();
			} else {
				tt.val('');
			}
		}
		$('#custom-fields-editor .toggle-tooltip input')
				.each(handleTooltipClick)
				.click(handleTooltipClick);

		var handleToggleHeading = function() {
			var tr = $(this).parents('tr')
			var headingBox = tr.find('.heading');
			if (this.checked) {
				tr.addClass('with-heading');
				headingBox.focus();
			} else {
				tr.removeClass('with-heading');
				headingBox.val('');
			}
		}
		$('#custom-fields-editor .toggle-heading input')
			.click(handleToggleHeading)
			.each(handleToggleHeading);
	}

	if (document.getElementById('service-program-editor')) {
		JethroServiceProgram.init();
	}

	JethroSMS.init();
});

var JethroSMS = {};

JethroSMS.init = function() {

	// SMS Character counting
	$('.smscharactercount').parent().find('textarea, div.sms_editor').on('keyup propertychange paste', function() {
		var maxlength = (this.tagName == 'DIV') ? $(this).attr("data-maxlength") : $(this).attr('maxlength');
		var currentLength = (this.tagName == 'DIV') ? $(this).text().length : this.value.length;
		var chars = maxlength - currentLength;
		if (chars <= 0 && this.tagName == 'DIV') {
			$(this).val($(this).text().substring(0, maxlength));
			chars = 0;
		}
		$(this).parent().find('.smscharactercount').html(chars + ' characters remaining.');
	});

	$(document).on('click', '[data-toggle="sms-modal"]', function(e) {
		var $this = $(this)
				, href = $this.attr('href')
				, $target = $($this.attr('data-target') || (href && href.replace(/.*(?=#[^\s]+$)/, ''))) //strip for ie7
				, option = $target.data('modal') ? 'toggle' : $.extend({remote: !/#/.test(href) && href}, $target.data(), $this.data());

		var $recipients = $this.attr('data-name');
		var $personid = $this.attr('data-personid');

		$("#send-sms-modal .sms_recipients").html($recipients);
		$("#sms_message").text(""); // Empty the textarea in case of reuse
		$("#send-sms-modal .results").html(""); // Empty in case of reuse
		$('#send-sms-modal .smscharactercount').html($("#sms_message").attr("data-maxlength") + ' characters remaining.'); // reset character count

		if (!!$personid) {
			$("#send-sms-modal").attr("data-sms_type", "person");
			$("#send-sms-modal").attr("data-personid", $personid);
			$("#send-sms-modal .sms-modal-option").show();
			e.preventDefault();
			$target.modal(option).one('hide', function() {
				$this.focus()
			})
		} else {
			alert('No SMS recipients found');
		}
	});

	$('.bulk-sms-submit').click(function(event) {
		event.preventDefault();
		var submitBtn = $("#smshttp .bulk-sms-submit");
		submitBtn.prop('disabled', true);
		submitBtn.prop('value', 'Sending...');
		submitBtn.css('cursor', 'wait');

		var smsData = $(this.form).serialize();

		$.ajax({
			type: 'POST',
			dataType: 'JSON',
			url: '?call=sms',
			data: smsData,
			context: $(this),
			error: function(jqXHR, status, error) {
				alert('AJAX error sending SMS: ' + error);
			},
			success: function(data) {
				var smsRequestCount = $("input[name='personid[]']:checked").length;
				var resultsDiv = $('#bulk-sms-results');

				JethroSMS.onAJAXSuccess(data, resultsDiv);

			},
			complete: function() {
				var submitBtn = $("#smshttp .bulk-sms-submit");
				submitBtn.prop('disabled', false);
				submitBtn.prop('value', 'Send');
				submitBtn.css('cursor', '');
			}
		});
	});

	$('#send-sms-modal .sms-submit').on('click', function(event) {
		event.preventDefault();
		var resultsDiv = $("#send-sms-modal .results");
		resultsDiv.hide();

		var modalDiv = $("#send-sms-modal");
		var sms_message = $("#sms_message").text();
		if (!sms_message) {
			alert("Please enter a message first.");
			return false;
		} else {
			var smsData, personid;
			$(this).prop('disabled', true);
			$(this).html("Sending...");
			$("#send-sms-modal .results").hide();
			var sendButton = $(this);
			smsData = {
				personid: modalDiv.attr("data-personid"),
				saveasnote: ($("#send-sms-modal .saveasnote").attr("checked") === "checked") ? '1' : '0',
				ajax: 1,
				message: sms_message
			}
			$.ajax({
				type: 'POST',
				dataType: 'JSON',
				url: '?call=sms',
				data: smsData,
				context: $(this),
				error: function(jqXHR, status, error) {
					alert('Server error while sending SMS');
					console.log(jqXHR);
					console.log(status);
					console.log(error);
					sendButton.html("Send");
				},
				success: function(data) {
					var modalDiv = $("#send-sms-modal");
					var showResults = JethroSMS.onAJAXSuccess(data, resultsDiv);
					if (showResults) {
						resultsDiv.show();
						sendButton.html("Send");
						sendButton.removeClass('sms-success');
					} else { // Success!
						sendButton.html('<i class="icon-ok"></i> Sent').addClass('sms-success');
						setTimeout(function() {
							modalDiv.modal('hide');
							sendButton.html("Send");
							sendButton.removeClass('sms-success');
						}, 1000);
					}
					sendButton.prop('disabled', false);
				}
			});
			return false;
		}
	});
}

/**
 *
 * @param object data
 * @param object resultsDiv
 * @returns {Boolean} TRUE if the modal window should be kept open to display errors.
 */
JethroSMS.onAJAXSuccess = function (data, resultsDiv) {
	var sentCount = 0,
	failedCount = 0,
	archivedCount = 0,
	blankCount = 0,
	rawresponse = '',
	statusBtn;

	if (data.sent !== undefined) { sentCount = data.sent.count; }
	if (data.failed !== undefined) { failedCount = data.failed.count; }
	if (data.failed_archived !== undefined) { archivedCount = data.failed_archived.count; }
	if (data.failed_blank !== undefined) { blankCount = data.failed_blank.count; }
	if (data.rawresponse !== undefined) { rawresponse = data.rawresponse; }

	resultsDiv.html(""); // Reset results in case there's something there
	var message = '';
	if (data.error!==undefined) {
		alert('Server error sending SMS\n'+data.error);
		return true;
	}
	if (sentCount > 0) {
		if (sentCount == 1) {
			var recip = data.sent.recipients[Object.keys(data.sent.recipients)[0]];
			message = 'Message successfully sent to '+recip.first_name+' '+recip.last_name;
		} else {
			message = 'Message successfully sent to '+sentCount+' recipients';
		}
		JethroSMS.appendAlert(resultsDiv, 'alert-success', message, sentCount == 1 ? null : data.sent.recipients);
		JethroSMS.markRecipientStatuses('Sent', data.sent.recipients, 'sms-success', 'SMS sent', true);

		if (!data.sent.confirmed) {
			JethroSMS.appendAlert(resultsDiv, '', 'Unable to confirm whether SMS sending was successful. Please check your system SMS configuration.');
		}
	}

	if (blankCount > 0) {
		if (blankCount == 1) {
			var recip = data.failed_blank.recipients[Object.keys(data.failed_blank.recipients)[0]];
			message = recip.first_name+' '+recip.last_name+' was not sent the message because they have no mobile number';
		} else {
			message = blankCount+' recipients were not sent the message because they have no mobile number';
		}
		JethroSMS.appendAlert(resultsDiv, '', message, blankCount == 1 ? null : data.failed_blank.recipients);
		JethroSMS.markRecipientStatuses('Failed (No Mobile)', data.failed_blank.recipients, 'sms-failure', null, false);
	}

	if (archivedCount > 0) {
		if (archivedCount == 1) {
			var recip = data.failed_archived.recipients[Object.keys(data.failed_archived.recipients)[0]];
			message = recip.first_name+' '+recip.last_name+' was not sent the message because they are archived';
		} else {
			message = archivedCount+' archived persons were not sent the message';
		}
		JethroSMS.appendAlert(resultsDiv, '', message, archivedCount == 1 ? null : data.failed_archived.recipients);
		JethroSMS.markRecipientStatuses('Failed (Archived)', data.failed_archived.recipients, 'sms-failure', 'SMS not sent - person is archived', false);
	}

	if (failedCount > 0) {
		if (failedCount == 1) {
			var recip = data.failed.recipients[Object.keys(data.failed.recipients)[0]];
			message = 'SMS sending failed for '+recip.first_name+' '+recip.last_name;
		} else {
			message = 'SMS sending failed for '+failedCount+' recipients';
		}
		JethroSMS.appendAlert(resultsDiv, 'alert-error', message, failedCount == 1 ? null : data.failed.recipients);
		JethroSMS.markRecipientStatuses('Failed', data.failed.recipients, 'sms-failure', 'SMS failed', false);
	}

	return ((failedCount > 0) || (archivedCount > 0) || ( blankCount > 0) || ( sentCount == 0) || (data.error !== undefined));
}

JethroSMS.appendAlert = function(parent, className, content, recipients)
{
	if (recipients) {
		content += '<p>';
		var count = 0, personid = 0;
		for (personID in recipients) {
			if (recipients.hasOwnProperty(personID)) {
				if (count > 0) {
					content += ", ";
				} else {
					count = count + 1;
				}
				content += recipients[personID]['first_name'] + " " + recipients[personID]['last_name'];
			}
		}
		content += '</p>';
	}
	parent.append('<div class="alert ' + className + '">' + content + '</div>');

}

JethroSMS.markRecipientStatuses = function(notice, recipients, rowClass, buttonMessage, untick)
{
	var fail_list = '<p class="namelist">';
	// Silly long version to support IE < 9
	var personID;
	for (personID in recipients) {
		if (recipients.hasOwnProperty(personID)) {
			if (rowClass) $('tr[data-personid=' + personID + ']').addClass(rowClass);
			if (untick)
				$('tr[data-personid=' + personID + '] input[type=checkbox]').attr('checked', false);
			if (buttonMessage) $('tr[data-personid=' + personID + '] .btn-sms').attr('title', buttonMessage);
		}
	}
}

var JethroServiceProgram = {};

JethroServiceProgram.init = function() {

		$('.confirm-shift').click(function() {
			$('#'+this.name).val(this.value);
			$('#shift-confirm-popup').modal('show');
			return false;
		});
		$('.confirm-delete').click(function() {
			return confirm("Really delete service?");
		});

		$('.notes-icon').click(function() {
			$(this).parents('tr:first').next('tr:first').toggle();
		});
		$('.copy-left').click(function() {
			var targetCell = $(this).parents('td:first').prev('td:first').prev('td:first');
			var sourceCell = $(this).parents('td:first').next('td:first');
			JethroServiceProgram.copyServiceDetails(sourceCell, targetCell);
		});
		$('.copy-right').click(function() {
			var targetCell = $(this).parents('td:first').next('td:first').next('td:first');
			var sourceCell = $(this).parents('td:first').prev('td:first');
			JethroServiceProgram.copyServiceDetails(sourceCell, targetCell);
		});
		$('#populate-services').click(function() {
			var placeholder = prompt('Enter a topic to apply to all empty services:');
			if (placeholder) $('[name^="topic_title"][value=]').val(placeholder);
		})
};
	/*
	function cancelShiftConfirmPopup()
	{
		$('#delete_all_date').val('');
	}
*/
JethroServiceProgram.copyServiceDetails = function(sourceCell, targetCell) {
	// copy by transplanting the whole table and re-naming the inputs
	var topicTitlePrefix = 'topic_title';
	var targetCellFieldnameSuffix = targetCell.find('input[name^='+topicTitlePrefix+']:first').attr('name').substr(topicTitlePrefix.length);
	var sourceCellFieldnameSuffix = sourceCell.find('input[name^='+topicTitlePrefix+']:first').attr('name').substr(topicTitlePrefix.length);
	var targetTable = targetCell.find('table.service-details');
	var replacementTable = sourceCell.find('table.service-details').clone(true);
	replacementTable.find('input, textarea').each(function() {
		if (this.name) {
			this.name = this.name.replace(sourceCellFieldnameSuffix, targetCellFieldnameSuffix);
		}
	});

	targetTable.after(replacementTable);
	targetTable.remove();
}



var JethroServicePlanner = {};

JethroServicePlanner.draggedComp = null;

JethroServicePlanner.newComponentInsertPoint = null;
JethroServicePlanner.itemBeingEdited = null;

JethroServicePlanner._getTRDragHelper = function(event, tr) {
	var helper = tr.clone();
	var originals = tr.children();
	helper.children().each(function(index) {
		$(this).width(originals.eq(index).width())
	});
	return helper;
}

JethroServicePlanner.init = function() {

	if (!document.getElementById('service-planner')) return;

	// COMPONENTS TABLES:
	// We have to start off with these hidden so we can set their width explicitly
	// to their parent width.  Otherwise they always push stuff out.
	$('#service-comps table').width(
		$('#service-comps .tab-pane.active').first().width() + 'px'
	).show();

    $("#service-comps tbody tr").draggable({
		containment: "#service-planner",
		helper: "clone",
		cursor: "move",
		start: function(event, ui) {
			$('#service-plan').addClass('comp-dragging');
			ui.helper.addClass('component-in-transit');
			JethroServicePlanner.draggedComp = $(this);
		},
		stop: function(event, ui) {
			$('#service-plan').removeClass('comp-dragging');
			ui.helper.removeClass('component-in-transit');
		}
    });

	$("#component-search input").keypress(function(event) {
		if (event.charCode == 13) JethroServicePlanner.beginComponentFiltering();
	})
	$("#component-search button[data-action=search]").click(JethroServicePlanner.beginComponentFiltering)
	$("#component-search button[data-action=clear]").click(JethroServicePlanner.endComponentFiltering);

    $("#service-comps tbody tr").on('dblclick', function() {
		JethroServicePlanner.addFromComponent($(this));
	})

	$("#service-comps td, #service-plan td").css('cursor', 'default').disableSelection();


	$('#service-comps table').stupidtable().bind('aftertablesort', function(event, data) {
		$(this).find('th .icon-arrow-up, th .icon-arrow-down').remove();
		var cn = (data.direction === "asc") ? 'up' : 'down';
		$(this).find('th').eq(data.column).append('<i class="icon-arrow-'+cn+'"></i>');
	})

	// SERVICE PLAN TABLE:

	$("#service-plan tbody tr").droppable({
        drop: JethroServicePlanner.onItemDrop,
		hoverClass: 'drop-hover',
    });

	$("#service-plan tfoot tr").droppable({
        drop: JethroServicePlanner.onItemDrop,
		hoverClass: 'drop-hover',
    });

    $("#service-plan tbody").sortable(	{
		cursor: "move",
		stop: JethroServicePlanner.onItemReorder,
		helper: JethroServicePlanner._getTRDragHelper,
		appendTo: "#service-plan",
		containment: "parent",
		revert: 100,
		opacity: 1,
		axis: 'y'
    })

	$('#service-plan').on('focus', 'textarea, input', function() {
		$(this).removeClass('unfocused');
	})

	$('#service-plan').on('blur', 'textarea, input', function() {
		JethroServicePlanner.isChanged = true;
		$(this).addClass('unfocused');
	})

	$('#service-plan').on('keypress', 'textarea', function(event) {
		JethroServicePlanner.isChanged = true;
		if (event.charCode == 13) this.rows += 1;
	})
	$('#service-plan').on('keypress', 'input.service-heading', function(event) {
		if (event.charCode == 13) {
			this.blur();
			return false;
		}
	})

	$('#service-plan button[type=submit]').click(JethroServicePlanner.onSubmit)

	$('#service-plan').on('click', '.tools a[data-action]', function() {
		var action = $(this).attr('data-action');
		JethroServicePlanner.Item[action]($(this).parents('tr:first'));
	})
	$('#ad-hoc-modal input[data-action]').click(function() {
		var action = $(this).attr('data-action');
		JethroServicePlanner.Item[action]();
	})
	$('#ad-hoc-modal input').keypress(function(event) {
		if (event.charCode == 13) JethroServicePlanner.Item.saveItemDetails();
	})

	JethroServicePlanner.refreshNumbersAndTimes();

	// WARN UNSAVED
	window.onbeforeunload = JethroServicePlanner.onBeforeUnload;

}

JethroServicePlanner.isChanged = false;

JethroServicePlanner.onBeforeUnload = function() {
	if (JethroServicePlanner.isChanged) return 'You have unsaved changes which will be lost if you don\'t save first';
}

JethroServicePlanner.beginComponentFiltering = function() {
	var url = document.location.href.substr(0, document.location.href.indexOf('?'));
	url += '?call=search_service_components_json';
	url += '&tagid='+$("#component-search select").val();
	url += '&search='+$("#component-search input").val();
	$.ajax(url, {
		dataType: 'json',
		success: JethroServicePlanner.filterComponents
	});
}

JethroServicePlanner.filterComponents = function(resultIDs) {
	$('#service-comps tbody tr').each(function() {
		this.style.display = resultIDs.contains($(this).attr('data-componentid')) ? '' : 'none';
	})
}
JethroServicePlanner.endComponentFiltering = function() {
	$('#service-comps tbody tr').css('display', '');
	$('#component-search input, #component-search select').val('');
}

JethroServicePlanner.onSubmit = function() {
	// Disable the templates
	$('#service-item-template *, #service-heading-template *').attr('disabled', 'disabled');

	// Add a heading_text field to each item and populate it accordingly
	var lastHeading = '';
	$('#service-plan tr').each(function() {
		var headingBox = $(this).find('input.service-heading')
		if (headingBox.length) {
			lastHeading = headingBox.val();
		} else if ($(this).hasClass('service-item')) {
			$(this).find('td:first').append('<input type="hidden" name="heading_text[]" value="'+lastHeading+'" />');
			lastHeading = '';
		}
	})

	JethroServicePlanner.isChanged = false;
}

JethroServicePlanner.Item = {};

JethroServicePlanner.Item.addHeading = function($tr) {
	var newRow = $('#service-heading-template').clone().attr('id', '');
	$tr.before(newRow);
	newRow.find('input.service-heading').focus();
}

JethroServicePlanner.Item.addNote = function($tr) {
	$tr.find('textarea').show().focus();
}

JethroServicePlanner.Item.remove = function($tr) {
	$tr.remove();
	JethroServicePlanner.refreshNumbersAndTimes();
	JethroServicePlanner.isChanged = true;
}

JethroServicePlanner.Item.viewCompDetail = function($tr) {
	var href="?call=service_comp_detail&head=1&id="+($tr.find('input.componentid').val());
	TBLib.handleMedPopupLinkClick({'href' : href});
}

JethroServicePlanner.Item.addAdHoc = function ($tr) {
	JethroServicePlanner.itemBeingEdited = null
	JethroServicePlanner.newComponentInsertPoint = $tr.next('tr');
	$modal = $('#ad-hoc-modal');
	$modal.find('input[name=title]').val('');

	$modal.find('select[name=show_in_handout] option[value=full]')
			.css('display', 'none')
			.attr('disabled');
	$modal.find('select[name=show_in_handout] option[value=title]')
			.html('Yes');

	$modal.find('.modal-header h4').html('Add ad-hoc service item');
	$modal.modal('show');
}

JethroServicePlanner.Item.saveItemDetails = function () {

	var attrs = {};
	$('#ad-hoc-modal input[name], #ad-hoc-modal select').each(function() {
		attrs[this.name] = this.value;
	});
	if (attrs['title'] == '') {
		alert('You must specifiy a title');
		return;
	}
	if (JethroServicePlanner.itemBeingEdited) {
		for (k in attrs) {
			JethroServicePlanner.itemBeingEdited.find('input[name="'+k+'[]"]').val(attrs[k]);
			JethroServicePlanner.itemBeingEdited.find('td.item span').html(attrs['title']);
		}
		JethroServicePlanner.itemBeingEdited = null;
		JethroServicePlanner.refreshNumbersAndTimes();
		JethroServicePlanner.isChanged = true;

	} else {
		attrs.componentid = '';
		JethroServicePlanner.addItem(attrs['title'], attrs, JethroServicePlanner.newComponentInsertPoint);
	}
	$('#ad-hoc-modal').modal('hide').find('input[name=title]').val('');
}

JethroServicePlanner.Item.editDetails = function ($tr) {
	JethroServicePlanner.itemBeingEdited = $tr;
	$modal = $('#ad-hoc-modal');
	var attrs = ['title', 'length_mins', 'show_in_handout'];
	for (var i=0; i < attrs.length; i++) {
		$modal.find('[name='+attrs[i]+']').val($tr.find('input[name="'+attrs[i]+'[]"]').val());
	}
	// Show 'show in handout = full' only for non-ad-hoc items
	var componentID = $tr.find('input[name="componentid[]"]').val();
	$modal.find('select[name=show_in_handout] option[value=full]')
			.prop('disabled', componentID ? false : true)
			.css('display', componentID ? '' : 'none');
	$modal.find('select[name=show_in_handout] option[value=title]')
			.html(componentID ? 'Title only' : 'Yes');

	// Show the 'title' box only for non-ad-hoc items
	var titleRow = $modal.find('input[name=title]').parents('.control-group');
	titleRow[componentID ? 'hide' : 'show']();

	$modal.find('.modal-header h4').html('Edit service item');
	$modal.modal('show');


}

JethroServicePlanner.onItemDrop = function(event, ui) {
	if (JethroServicePlanner.draggedComp) {
		JethroServicePlanner.addFromComponent(JethroServicePlanner.draggedComp, this);
		JethroServicePlanner.draggedComp = null;
	}
}

JethroServicePlanner.addFromComponent = function(componentTR, beforeItem) {
	var attrVals = {};
	var runsheetTitle = componentTR.attr('data-runsheet_title');
	var newTitle = runsheetTitle ? runsheetTitle : componentTR.find('.title').html();
	var attrs = ['componentid', 'show_in_handout', 'length_mins', 'personnel'];
	for (var i=0; i < attrs.length; i++) {
		attrVals[attrs[i]] = componentTR.attr('data-'+attrs[i]);
	}
	JethroServicePlanner.addItem(newTitle, attrVals, beforeItem);
}

JethroServicePlanner.addItem = function(title, attrVals, beforeItem) {
	var newTR = $('#service-item-template').clone().attr('id', '');
	newTR.css('display', '').addClass('service-item');
	if (!attrVals['componentid']) newTR.addClass('ad-hoc');
	newTR.find('td.item span').html(title);
	newTR.find('input[name="personnel[]"]').val(attrVals['personnel']);
	delete attrVals['personnel'];
	attrVals['title'] = title;
	for (k in attrVals) {
		newTR.find('td.item').append('<input type="hidden" class="'+k+'" name="'+k+'[]" value="'+attrVals[k]+'" />');
	}
	if (!beforeItem || $(beforeItem).parents('tfoot').length) {
		beforeItem = "#service-plan tbody tr:last";
	}
	$(beforeItem).before(newTR);
	$('#service-plan-placeholder').remove();

	newTR.droppable({
		drop: JethroServicePlanner.onItemDrop,
		hoverClass: 'drop-hover',
	});
	JethroServicePlanner.refreshNumbersAndTimes();
	JethroServicePlanner.isChanged = true;
}

JethroServicePlanner.onItemReorder = function() {
	JethroServicePlanner.isChanged = true;
	JethroServicePlanner.refreshNumbersAndTimes();
}

JethroServicePlanner.refreshNumbersAndTimes = function() {
	var sp = $('#service-plan');
	sp.find('td.number, td.start').html('');
	var currentNumber = 1;
	var currentTime = sp.attr('data-starttime');
	sp.find('tr.service-item').each(function() {
		$(this).find('td.start').html(currentTime);
		currentTime = JethroServicePlanner._addTime(currentTime, $(this).find("input.length_mins").val());
		if ($(this).find('input.show_in_handout').val() != 0) {
			$(this).find('td.number').html(currentNumber++);
		}
	});
	// Adjust the spacer so the min height is 5 items equivalent
	var spacer = $('#service-plan-spacer');
	spacer.remove();
	$('#service-plan tbody').append(spacer); // make sure it's at the end
	var spacerHeight = Math.max(0, (5 - $('tr.service-item').length)*30);
	$('#service-plan-spacer td').height(spacerHeight);


}


JethroServicePlanner._addTime = function(clockTime, addMins) {
	var hours = parseInt(clockTime.substr(0, 2), 10);
	var mins = parseInt(clockTime.substr(2, 2), 10);
	addMins = parseInt(addMins, 10);
	if (!isNaN(addMins)) {
		mins += parseInt(addMins, 10);
		if (mins > 60) {
			mins = mins % 60;
			hours++;
		}
		if (hours < 10) hours = "0"+hours;
		if (mins < 10) mins = "0"+mins;
		return ""+hours+mins;
	}
}


var JethroRoster = {}

JethroRoster.CUSTOM_ASSIGNEE_TARGET = null;

JethroRoster.init = function() {
	if (!$('form#roster').length) return;

	$('table.roster select').keypress(function() { JethroRoster.onAssignmentChange(this); }).change(function() { JethroRoster.onAssignmentChange(this); });
	$('table.roster input.person-search-single, table.roster input.person-search-multiple').each(function() {
		this.onchange = function() { JethroRoster.onAssignmentChange(this); };
	});
	$('table.roster > tbody > tr').each(function() { JethroRoster.updateClashesForRow($(this)); });

	$('.roster select').change(function() {
			$opt = $(this.options[this.selectedIndex]);
			if ($opt.hasClass('other')) {
				JethroRoster.CUSTOM_ASSIGNEE_TARGET = this;
				$('#choose-assignee-modal').modal({});
			}
	});

	$('#choose-assignee-save').click(function() {
		$target = $(JethroRoster.CUSTOM_ASSIGNEE_TARGET)
		$target.find('.unlisted-allocee').remove();
		var newID = $('#choose-assignee-modal input[name=personid]').val();
		var newName = $('#personid-input').val();
		if (!newID || !newName) {
			alert("Please choose an assignee");
			return false;
		}
		$newOption = $('<option selected="selected" class="unlisted-allocee" value="'+newID+'">'+newName+'</option>')
		$target.find('.other').before($newOption);
		$('#choose-assignee-modal input').val('');
		$target.change(); //bubbles the props up so it looks orange
		setTimeout(function() { $target.effect("pulsate", {times: 2}, 700) }, 600);

		if ($('#choose-assignee-modal input[name=add-to-group]').attr('checked')) {
			var matches = JethroRoster.CUSTOM_ASSIGNEE_TARGET.name.match(/assignees\[([0-9]+)\]/);
			var roleID = matches[1];
			$(JethroRoster.CUSTOM_ASSIGNEE_TARGET.form).append('<input type="hidden" name="new_volunteers['+roleID+'][]" value="'+newID+'" />');
		}

	});
	$('#choose-assignee-cancel').click(function() {
		$(JethroRoster.CUSTOM_ASSIGNEE_TARGET).val('');
	});
}

JethroRoster.onAssignmentChange = function(inputField) {
	var row = null;
	if ($(inputField).hasClass('person-search-single') || $(inputField).hasClass('person-search-multiple')) {
		row = $(inputField).parents('tr:first');
	} else if (inputField.tagName == 'SELECT' || inputField.type == 'hidden') {
		var expandableParent = $(inputField).parents('table.expandable');
		if (expandableParent.length) {
			var row = $(inputField).parents('table:first').parents('tr:first');
		} else {
			var row = $(inputField).parents('tr:first');
		}
	}
	if (row) {
		JethroRoster.updateClashesForRow(row);
	}
}

JethroRoster.updateClashesForRow = function(row) {
	var uses = new Object();
	// Deal with the single person choosers and select boxes first
	var sameRowInputs = row.find('input.person-search-single, select');
	sameRowInputs.removeClass('clash');
	sameRowInputs.each(function() {
		var thisElt = this;
		var thisVal = 0;
		if (this.className == 'person-search-single') {
			var hiddenInput = document.getElementsByName(this.id.substr(0, this.id.length-6))[0];
			thisVal = hiddenInput.value;
		} else if (this.tagName == 'SELECT') {
			thisVal = this.value;
		}
		if (thisVal != 0) {
			if (!uses[thisVal]) {
				uses[thisVal] = new Array();
			}
			uses[thisVal].push(thisElt);
		}
	});
	// Now add the multi person choosers
	row.find('ul.multi-person-finder li').removeClass('clash').each(function() {
		var thisVal = $(this).find('input')[0].value;
		if (thisVal != 0) {
			if (!uses[thisVal]) {
				uses[thisVal] = new Array();
			}
			uses[thisVal].push(this);
		}
	});
	for (i in uses) {
		if (uses[i].length > 1) {
			for (j in uses[i]) {
				if (typeof uses[i][j] == 'function') continue;
				$(uses[i][j]).addClass('clash');
			}
		}
	}
}

function handleFamilyPhotosLayout() {
	var photoContainer = $('#family-photos-container');
	if (photoContainer.length) {
		// either a strip of photos down the right, or a strip across the bottom.
		photoContainer.css('width', Math.max(52, ($('#family-members-container').width() - $('#member-details-container').outerWidth() - 10))+'px');
		if (photoContainer.offset().top != $('#member-details-container').offset().top) {
			photoContainer.css('width', '100%');
		} else {
			photoContainer.css('margin-left', '1ex');
		}
	}
}

var applyNarrowColumns = function(root) {
	// All of this is because in Chrome, if you set a width on a TD,
	// there is no way to stop the overall table from being width 100% OF THE WINDOW
	// (even if its parent is less than 100% width).
	// We want the whole table to be as wide as it needs to be but no wider.
	var expr = 'td.narrow, th.narrow, table.object-summary th'
	var cells = $(root).find(expr); 
	var parents = cells.parents('table:visible').not('.no-narrow-magic');
	parents.each(function() {
		var table = $(this);
		table.css('width', table.width()+'px');
		table.removeClass('table-auto-width').removeClass('table-min-width'); // because this class has an 'important' width we need to override
	});
	cells.css('white-space', 'nowrap');
	parents.each(function() {
		if ($(this).hasClass('object-summary')) {
			$(this).find('tr:visible:first th').css('width', '1%');
		} else {
			$(this).find('tr:visible:first').find('.narrow').css('width', '1%');
			$(this).find('tbody tr:visible:first').find('.narrow').css('width', '1%');
		}
	});
}


/**
* Lay out a pair of matching boxes.
* If they can fit next to each other, make them the same height
*/
function layOutMatchBoxes()
{
	var maxHeight = 0;
	var lastTop = -1;
	var sameTop = true;
	$('.match-height').each(function() {
		$(this).css('height', '');
		if ($(this).height() > maxHeight) maxHeight = $(this).height();
		if (lastTop == -1) lastTop = $(this).position().top;
		sameTop = (lastTop == $(this).position().top);
	});
	if (sameTop) $('.match-height').height(maxHeight);
}

/* handle clicks on 'search' links in the top nav by building a modal */
function handleSearchLinkClick()
{
	$(this).parents('ul').parents('li').find('a.dropdown-toggle').dropdown('toggle');
	var heading = $(this).parents('ul').parents('li').find('a.dropdown-toggle').text().toLowerCase();
	if ($('#search-modal').length == 0) {
		$('#jethro-overall-width').append(
			'<div id="search-modal" class="modal hide fade" role="dialog" aria-hidden="true">'+
			'	<form method="get">'+
			'		<div class="modal-header"><h4>Search <span></span></h4></div>'+
			'		<div class="modal-body">Search <span></span> for: <input id="search-name" type="text" name="name" /></div>'+
			'		<div class="modal-footer">'+
			'			<button type="button" class="btn" data-dismiss="modal" aria-hidden="true">Cancel</button>'+
			'			<button type="submit" class="btn" accesskey="s">Go</button>'+
			'		</div>'+
			'	</form>'+
			'</div>'
		);
	}
	$('#search-modal').find('span').html(heading);

	// Convert query string to hidden vars, since query strings in a GET form's action are ignored
	$('#search-modal form').find('input[type=hidden]').remove();
	var queryVars = parseQueryString(this.href.substr(this.href.indexOf('?')+1));
	for (varName in queryVars) {
		$('#search-modal form').prepend('<input type="hidden" name="'+varName+'" value="'+queryVars[varName]+'" />');
	}
	$('#search-modal').modal('show').on('shown', function() { $('#search-modal input:visible:first').select(); });
	return false;
}




/************************** LOCKING ********************************/

function initLockExpiry()
{
	$('form[data-lock-length]').each(function() {
		var length = $(this).attr('data-lock-length');
		var bits = length.split(' ');
		var units = parseInt(bits[0],10) * 1000;
		var warningTime = 60000; // 1 minute
		switch (bits[1].toLowerCase()) {
			case 'minute':
			case 'minutes':
				units = units * 60;
				break;
			case 'hour':
			case 'hours':
				units = units * 60 * 60;
				break;
		}
		setTimeout('showLockExpiryWarning()', units - warningTime);
		setTimeout('showLockExpiredWarning()', units);
	})
}

function showLockExpiryWarning()
{
	var modal = $('<div id="lock-warning-modal" class="modal hide fade" role="dialog" aria-hidden="true">'
				+'		<div class="modal-header">'
				+'			<h4>Lock warning</h4>'
				+'		</div>'
				+'		<div class="modal-body">'
				+'			<p><b>Your lock on this object will soon expire.</b></p><p>To make sure your changes get saved, you should submit the form now.<p>'
				+'		</div>'
				+'		<div class="modal-footer">'
				+'			<button class="btn" data-dismiss="modal" aria-hidden="true">OK</button>'
				+'		</div>'
				+'		</form>'
				+'	</div>');
	$('#jethro-overall-width').append(modal)
	$('#lock-warning-modal').modal('show');
}

function showLockExpiredWarning()
{
	$('#lock-warning-modal')
		.find('.modal-body')
			.html('<p><b>Your lock on this object has now expired.  You cannot save the changes you have made.  Would you like to reload the form and try again?</b></p>')
			.end()
		.find('.modal-footer')
			.html('<input type="button" value="Yes" class="btn reload" />'
					+'<input type="button" value="No" data-dismiss="modal" class="btn disable-form" />'
			).end()
		.modal('show');
	$('.disable-form').click(function() {
		$('form[method=post] input, form[method=post] select, form[method=post] button').attr('disabled', true)
	});
	$('.reload').click(function() {
		document.location.href = document.location;
	});
	window.DATA_CHANGED = false; // see setupUnsavedWarnings() in tb_lib.js
}

// Allow certain submit buttons to target their form to an envelope-sized popup or hidden frame.
// Used in envelopes bulk action
$(document).ready(function() {
	$('input[data-set-form-target], button[data-set-form-target]').click(function() {
		switch ($(this).attr('data-set-form-target')) {
			case 'envelope':
				envelopeWindow = window.open('', 'envelopes', 'height=320,width=500,location=no,menubar=no,titlebar=no,toolbar=no,resizable=yes,statusbar=no');
				if (envelopeWindow) {
					this.form.target = 'envelopes';
				} else {
					alert('Jethro tried but could not open a popup window - you probably have a popup blocker enabled.  Please disable your popup blocker for this site, reload the page and try again.');
				}
				break;
			case 'hidden':
				if (!$('iframe#hidden').length) {
					document.body.appendChild($('<iframe name="hidden" id="hidden" style="width: 0px; height: 0px; border: 0px"></iframe>').get(0));
				}
				this.form.target = 'hidden';
				break;
			default:
				alert('Unknown data-set-form-target value: '+$(this).attr('data-set-form-target'));
		}
	}).each(function() {
		// For every other submit button in the form, reset the target to blank.
		$(this.form).find('input[type=submit], button[type=submit]').not('[data-set-form-target]').click(function() {
			this.form.target = '';
		});
	});
});

/************************* PERSON AND FAMILY FORMS AND NOTES ************************/
$(document).ready(function() {
	$('form#edit-family, form#add-family').submit(handleFamilyFormSubmit);
	$('form#add-family').submit(handleNewFamilySubmit);
	$('form#add-family input.family-name').blur(handleFamilyNameBlur);
	$('select.person-status').not('.bulk-action *, .action-plan *').change(handlePersonStatusChange).change();
	$('form#add-family .person-status select').change(handleNewPersonStatusChange);
	$('form#add-family .congregation select').change(handleNewPersonCongregationChange);

	$('.note-status select')
		.keypress(function() { handleNoteStatusChange(this); })
		.on('touchstart', function() { handleNoteStatusChange(this); })
		.click(function() { handleNoteStatusChange(this); })
		.change(function() { handleNoteStatusChange(this); })
		.change();

	$('#note_template_chooser').change(function() {
		$('#note-field-widgets').load('?call=note_template_widgets', { templateid: this.value });
	})
});

function handleNoteStatusChange(elt) {
	var prefix = elt.name.replace('status', '');
	var newDisplay = (elt.value != 'pending') ? 'none' : '';
	$('input[name='+prefix+'action_date_d]').parents('.control-group:first').css('display', newDisplay);
	$('select[name='+prefix+'assignee]').parents('.control-group:first').css('display', newDisplay);
	// the 'none' assignee should be removed when action is required
	if (elt.value == 'no_action') {
		if ($('select[name='+prefix+'assignee] option[value=""]').length == 0) {
			$('select[name='+prefix+'assignee]').prepend('<option selected="selected" value="">(None)</option>');
		}
	} else {
		$('select[name='+prefix+'assignee] option[value=""]').remove();
	}
}

function handlePersonStatusChange()
{
	var congChooserName = this.name.replace('status', 'congregationid');
	var congChoosers = document.getElementsByName(congChooserName);
	if (congChoosers.length != 0) {
		var chooser = congChoosers[0];
		for (var i=0; i < chooser.options.length; i++) {
			if (chooser.options[i].value == '') {
				if ((this.value == 'contact') || (this.value == 'archived')) {
					// blank value allowed
					return;
				} else {
					chooser.remove(i);
					return;
				}
			}
		}
		if ($(chooser).attr('data-allow-empty') != 0) {
			// if we got to here, there is no blank option
			if ((this.value == 'contact') || (this.value == 'archived')) {
				// we need a blank option
				var newOption = new Option('(None)', '');
				try {
					chooser.add(newOption, chooser.options[0]); // standards compliant; doesn't work in IE
				} catch(ex) {
					chooser.add(newOption, 0); // IE only
				}
			}
		}
	}
	return true;
}

function deletePersonChooserListItem(elt)
{
	var li = $(elt).parents('li:first');
	var input = li.find('input')[0];
	var textInput = document.getElementById(input.name.substr(0, input.name.length-2)+'-input');
	li.remove();
	if (typeof textInput.onchange == 'function') {
		textInput.onchange();
	}
}

var personStatusCascaded = false;
function handleNewPersonStatusChange()
{
	if (!personStatusCascaded && this.name == 'members_0_status') {
		$('form#add-family .person-status select').attr('value', this.value);
		personStatusCascaded = true;
		$('select.person-status').change();
	}
}

var congregationCascaded = false;
function handleNewPersonCongregationChange()
{
	if (!congregationCascaded && this.name == 'members_0_congregationid') {
		$('form#add-family .congregation select').attr('value', this.value);
		congregationCascaded = true;
	}
}

function handleNewFamilySubmit()
{
	var i = 0;
	var haveMember = false;
	while (document.getElementsByName('members_'+i+'_first_name').length != 0) {
		var memberFirstNameField = document.getElementsByName('members_'+i+'_first_name')[0];
		var memberLastNameField = document.getElementsByName('members_'+i+'_last_name')[0];
		if (memberFirstNameField.value != '') {
			if (memberLastNameField.value == '') {
				alert('You must specify a last name for each family member');
				memberLastNameField.focus();
				TBLib.cancelValidation();
				return false;
			}
			haveMember = true;
		}
		i++;
	}

	if (!haveMember) {
		document.getElementsByName('members_0_first_name')[0].focus();
		alert('New family must have at least one member');
		TBLib.markErroredInput(document.getElementsByName('members_0_first_name')[0]);
		document.getElementsByName('members_0_first_name')[0].focus();
		TBLib.cancelValidation();
		return false;
	}
	return true;
}

function handleFamilyNameBlur()
{
	$('form#add-family .last_name input').each(new Function("if (this.value == '') this.value = '"+this.value.replace("'", "\\'")+"';"));
}

function handleFamilyFormSubmit()
{
	if ((document.getElementsByName('address_postcode')[0].value == '') && (document.getElementsByName('address_suburb')[0].value != '')) {
		alert('If a suburb is supplied, a postcode must also be supplied');
		document.getElementsByName('address_postcode')[0].focus();
		TBLib.cancelValidation();
		return false;
	}
	if ((document.getElementsByName('address_postcode')[0].value != '') && (document.getElementsByName('address_suburb')[0].value == '')) {
		alert('If a postcode is supplied, a suburb must also be supplied');
		document.getElementsByName('address_suburb')[0].focus();
		TBLib.cancelValidation();
		return false;
	}
	return true;
}
