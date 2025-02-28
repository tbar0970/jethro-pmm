var TBLib = {};


/***********************************
 * Event handlers
 ************************************/

$(document).ready(function() {

	if ($('.stop-js').length) return; /* Classname flag for big pages that don't want JS to run */

	$('body').on('click', 'a[href^="mailto:"], a[href*="mail.google.com"]', TBLib.handleMailtoClick);
	
	var i = document.createElement('input');
	if (!('autofocus' in i) || $('[autofocus]').length == 0) {
		// native autofocus is not supported, or no element is using it
		if ($('.initial-focus, .autofocus, [autofocus]').length) {
			setTimeout("$('.initial-focus, .autofocus, [autofocus]').get(0).focus()", 200);
		} else if (document.location.hash.length == 0) {
			// Focus the first visible input
			setTimeout("try { $('body input[type!=checkbox]:visible, select:visible').not('.btn-link, [type=checkbox], [type=radio], [type=submit]').not('.no-autofocus *, .no-autofocus').get(0).focus(); } catch (e) {}", 200);
		}
	}
	$('input[autoselect]').each(function() {
		this.select();
	});

	//// VALIDATION ////
	$('input.bible-ref').change(TBLib.handleBibleRefBlur);
	$('input.valid-email').change(TBLib.handleEmailBlur);
	$('input.day-box').change(TBLib.handleDayBoxBlur);
	$('input.year-box').change(TBLib.handleYearBoxBlur);
	$('textarea[maxlength]').keypress(function() {
		var m = parseInt($(this).attr('maxlength'), 10);
		return ((m < 1) || (this.value.length <= m));
	});
	$('input[regex]').change(TBLib.handleRegexInputBlur);
	$('.optional .compulsory').removeClass('compulsory');
	$('form').submit(TBLib.handleFormSubmit);
	$('form.disable-submit-buttons input[type=submit]').click(function() {
		// The submit button itself will be disabled on submit, so we
		// create a hidden element to preserve its value in the request
		var h = '<input type="hidden" name="'+this.name+'" value="'+this.value+'" />';
		$(this).addClass('submit-in-progress').after(h);

	})

	//// POPUPS ETC ////

	$('a.med-popup').click(TBLib.handleMedPopupLinkClick).each(function() {
		if (!this.title) this.title = '(Opens in a new window)';
	});
	$('a.med-newwin').click(TBLib.handleMedNewWinLinkClick).attr('title', '(Opens in a new window)');

	//// CLICKABLE THINGS ETC ////

	// Ability for any element to submit the form it's part of
	$('.submit').click(function() {
		if ($(this).hasClass('confirm-title')) {
			if (!confirm("Are you sure you want to "+this.title[0].toLowerCase()+this.title.substr(1)+"?")) {
				return false;
			}
		}
		if (this.target) {
			var form = $('#'+this.target);
		} else {
			var form = $(this).parents('form');
		}
		if (form.length) {
			if (!form[0].onsubmit || form[0].onsubmit()) $(form[0]).submit();
		}
		return false;
	});

	$('.back').click(function() {
		history.back();
	});

	// Ability to click anywhere on a table row to activate the link within it
	$('table.clickable-rows td').click(function(e) {
		var t = $(this);
		var myLinks = t.find('a, input');
		if (!myLinks.length) {
			childLinks = $(this).parent('tr').find('a');
			self.location = childLinks[0].href;
		} else if (myLinks.filter('a').length == 1) {
			self.location = myLinks[0].href;
		}
	});

	$('a.take-parent-click').parent()
		.css('cursor', 'pointer')
		.click(function() {
			self.location.href = $(this).find('a.take-parent-click').attr('href');
		});

	// A table that expands as you fill in the input boxes
	$('table.expandable').each(function() { TBLib.setupExpandableTable(this) });

	// Ability to enable/disable the children of a related element
	// using data-toggle="enable" and data-target="selector of what to enable/disable"
	$('[data-toggle=enable], [data-toggle=disable]').change(function() {
		var newDisabledVal = ($(this).attr('data-toggle') == 'disable') ? this.checked : !this.checked;
		if (this.type == 'radio') {
			$('input[name='+this.name+']').each(function() {
				if ($(this).attr('data-target')) {
					newDisabledVal = $(this).attr('data-toggle') == 'disable' ? this.checked : !this.checked;
					$($(this).attr('data-target')).attr('disabled', newDisabledVal);
				}
			});
		} else if (this.type == 'checkbox') {
			$($(this).attr('data-target')).attr('disabled', newDisabledVal);
		} else {
			var newDisabledVal = ($(this).attr('data-toggle') == 'disable') ? this.value : !this.value;
			// eg select box
			$($(this).attr('data-target')).attr('disabled', newDisabledVal);
		}

	}).change();

	$('[data-toggle=strikethrough]').change(function() {
		var target;
		if ($(this).attr('data-target') == 'row') {
			target = $(this).parents('tr:first').find('*');
		} else {
			target = $($(this).attr('data-target'));
		}
		target.css('text-decoration', this.checked ? 'line-through' : 'none');
	});

	/**
	 * <select data-toggle="visible" data-target="row .option" data-match-attr="data-mytype"> ...
	 * <div class="option" data-mytype="x"></div>
	 * <div class="option" data-mytype="y"></div>
	 */
	// needs to attach to document so that dynamically-generated buttons can work
	$( document ).on('change', 'input[data-toggle=visible][type!=checkbox], select[data-toggle=visible]', function(event) {
		var base = $(document);
		var targetExp = $(this).attr('data-target');
		if (/^row /.test(targetExp)) {
			base = $(this).parents('tr:first');
			targetExp = targetExp.substr(4);
		}
		target = base.find(targetExp);
		target.hide();
		var attrName = $(this).attr('data-match-attr');
		var targetValue = this.value;
		target.each(function() {
			if (-1 != $.inArray(targetValue, $(this).attr(attrName).split(' '))) {
				$(this).show();
			}
		})
	})
	$('input[data-toggle=visible][type!=checkbox][type!=radio], select[data-toggle=visible]').change();

	// needs to attach to document so that dynamically-generated buttons can work
	$( document ).on('click', '[data-toggle=visible]', function(event) {
		if ($(this).is('input[type!=checkbox], select')) return;

		var targetExp = $(this).attr('data-target');
		var target = null;
		if (targetExp == 'next') {
			target = $(this).next();
		} else {
			var base;
			if (/^row /.test(targetExp)) {
				base = $(this).parents('tr:first');
				targetExp = targetExp.substr(4);
			} else {
				base = $(document.body);
			}
			target = base.find(targetExp);
		}
		target.toggle();
		event.stopPropagation();
	});


	// Ability to change the form action when a button is clicked.  Allows one form to submit to several different places.
	$('input[data-set-form-action], button[data-set-form-action]').click(function() {
		this.form.action = $(this).attr('data-set-form-action');
	});

	// Ability to have input fields that become compulsory only when certain submit buttons are clicked
	$('input[data-require-fields]').click(function() {
		var ok = true;

		$($(this).attr('data-require-fields')).each(function() {
			if (!this.value) {
				alert('A mandatory field has been left blank');
				TBLib.markErroredInput(this);
				ok = false;
				return;
			}
		})
		return ok;
	})

	var selectChooserRadios = $('input.select-chooser-radio');
	if (selectChooserRadios.length) {
		selectChooserRadios.change(function() {
			$('input[name='+this.name+']').each(function() {
				$('select[name='+this.value+']').attr('disabled', !this.checked);
				$('select[name='+this.value+'[]]').attr('disabled', !this.checked);
			});
		});
		$(selectChooserRadios.get(0)).change();
	}


	/**
	 * Radio list - Enables and disables inputs according to a set of radio buttons.  Eg.
	 * <span class="radio-list"><input type="radio" name="xyz" value="fixed" /><input type="text" name="fixedvalue" /></span><br />
	 * <span class="radio-list"><input type="radio" name="xyz" value="relative" /><input type="text" name="relativevalue" /></span>
	 * will disable the relativevalue box when you click the "fixed" radio button, and vice versa.
	 */
	$('.radio-list input[type!=radio]').each(function() {
		$(this).focus(function() {
			$(this).parents('span.radio-list').find('input[type=radio]').attr('checked', true);
		});
	});

	// Buttons to modify rows in a table
	$('.delete-row').click(function() { $(this).parents('tr:first').remove();});
	$('.move-row-up').click(function() { $(this).parents('tr:first').prev('tr:first').before($(this).parents('tr:first')); });
	$('.move-row-down').click(function() { $(this).parents('tr:first').next('tr:first').after($(this).parents('tr:first')); });
	$('.insert-row-below').click(function() {
		var myClone = $(this).parents('tr:first').clone(true);
		$(this).parents('tr:first').after(myClone);
		myClone.find('input, select, textarea').val('');
	});

	// Add a confirmation based on the title of the clicked element
	$('.confirm-title').click(function(event) {
		if (!$(this).hasClass('submit')) {
			if (!confirm("Are you sure you want to "+this.title[0].toLowerCase()+this.title.substr(1)+"?")) {
				event.preventDefault();
				event.stopImmediatePropagation();
				return false
			}
		}
	});

	$('[data-confirm]').click(function(event) {
		if (!confirm($(this).attr('data-confirm'))) {
			event.preventDefault();
			event.stopImmediatePropagation();
			return false
		}
	});

	$('.double-confirm-title').click(function(event) {
		if (!( confirm("Are you sure you want to "+this.title[0].toLowerCase()+this.title.substr(1)+"?")
				&& confirm("This action cannot be undone.  Are you sure?"))) {
			event.preventDefault();
			event.stopImmediatePropagation();
			return false;
		}
	});


	// A checkbox that selects all the other checkboxes in the same table column
	$('input.select-all').click(TBLib.handleSelectAllClick);

	// Control a hidden input using a checkbox (since the checkbox won't get submitted if unchecked)
	$('.toggle-next-hidden').each(function() {
		var hidden = $(this).next('input[type="hidden"]').get(0);
		if (hidden == null) {
			alert('TBLIB ERROR: Could not find next hidden input');
		} else {
			this.checked = (hidden.value == 1);
		}
	}).change(function() {
		var hidden = $(this).next('input[type="hidden"]').get(0);
		hidden.value = this.checked ? "1" : "0";
	});

	// A set of checkboxes that check/uncheck each other based on bitmask relationships between their values
	$('.bitmask-boxes input[type=checkbox]').click(TBLib.handleBitmaskBoxClick);

	// Change the classname and title of a select box based on the classname and title of the selected option
	// Used in rostering
	$('.bubble-option-props select').bind('change', function(e) {
		this.className = this.options[this.selectedIndex].className;
		this.title = this.options[this.selectedIndex].title;
	}).bind('keypress', function(e) {
		this.className = this.options[this.selectedIndex].className;
		this.title = this.options[this.selectedIndex].title;
	}).change();

	// When a textbox is focused, select just the basename of a filename value (eg excluding the .txt in somefile.txt)
	$('input.select-basename').focus(TBLib.selectBasename);



	setTimeout('setupUnsavedWarnings()', 400);

	/** used to show/hide service notes */
	$('.toggle-next-tr').click(function() {
		$(this).parents('tr:first').next('tr').toggle();
		if ($(this).hasClass('icon-chevron-down')) {
			$(this).removeClass('icon-chevron-down').addClass('icon-chevron-up');
		} else if ($(this).hasClass('icon-chevron-up')) {
			$(this).removeClass('icon-chevron-up').addClass('icon-chevron-down');
		}
	});

	$('.accordion-heading a').click(function() {
		// Adjust the chevrons on click
		var subs;
		if ((subs = $(this).find('.icon-chevron-up')).length) {
			// collapsing this one
			subs.removeClass('icon-chevron-up').addClass('icon-chevron-down');
		} else if ((subs = $(this).find('.icon-chevron-down')).length) {
			// expanding this one
			$(this).parents('.accordion').find('.accordion-heading .icon-chevron-up').removeClass('icon-chevron-up').addClass('icon-chevron-down');
			subs.removeClass('icon-chevron-down').addClass('icon-chevron-up');
		}
	});

	$('a[data-method=post]').each(function() {
		var p = $(this).attr('href').split('?');
		var action = p[0];
		var params = p[1].split('&');
		var pform = $(document.createElement('form'));
		$('body').append(pform);
		pform.attr('action', action);
		pform.attr('method', 'post');
		pform.css('display', 'none');
		for (var i = 0; i < params.length; i++) {
			var tmp = ("" + params[i]).split('=');
			var key = tmp[0], value = tmp[1];
			pform.append('<input type="hidden" name="' + key + '" value="' + value + '" />');
		}
		if (!window.postFormID) window.postFormID = 0;
		pform.attr('id', 'postform'+(window.postFormID++));
		$(document.body).append(pform);
		this.href="javascript:document.getElementById('"+pform.attr('id')+"').submit()";
	});

	// This is used only by the component library page.
	// That page could be reworked and this feature removed
	TBLib.anchorBottom('.anchor-bottom');

	$('a[target="_append"]').click(function() {
		 $.get(this.href, function(data) {
            $('#body').append(data);
        });
		return false;
	});
	
	$("[data-action=copy-tsv]").click(function() {
		var link = this;
		var oldLabel = this.innerHTML;
		TBLib.copyTSV($(this).parents('table:first').get(0)).then(function() {
			link.innerHTML = '✔ Copied';
			setTimeout(function() {
				link.innerHTML = oldLabel;
			}, 2000);
		});
	});
	$("[data-action=copy-table]").click(function() {
		var link = this;
		var oldLabel = this.innerHTML;
		TBLib.copyTable($(this).parents('table:first').get(0)).then(function() {
			link.innerHTML = '✔ Copied';
			setTimeout(function() {
				link.innerHTML = oldLabel;
			}, 2000);
		});
	});

	// Need to apply on document level so that the handler runs on buttons added after pageload
	$( document ).on('click', '[data-action=copy]', function(event) {
		var target = $($(this).attr('data-target'));
		var t = '';
		if (target.is('input, textarea')) {
			t = target.attr('value');
			target.get(0).select();
		} else {
			t = target.get(0).innerText;
		}
		try {
			if (navigator.clipboard.writeText(t)) {
				var oldLink = this;
				var oldLabel = this.value || this.innerHTML;
				if ($(this).is('input')) {
					this.value = '✔ Copied';
					setTimeout(function() {
						oldLink.value = oldLabel;
					}, 2000);				
				} else {
					this.innerHTML = '✔ Copied';
					setTimeout(function() {
						oldLink.innerHTML = oldLabel;
					}, 2000);
				}
			} else {
				alert("Sorry, browser settings do not allow copying");
			}
		} catch (e) {
				alert("Sorry, browser settings do not allow copying");
		}	
		return false;
	});

	$('input[type=file][max-bytes').change(function() {
		if (this.files[0].size > $(this).attr('max-bytes')) {
			alert("Sorry, that file is too big - please choose a smaller one");
			this.value = "";
		}
	});

	$('input[type=email]').on('paste', function(event) {
		var text = event.originalEvent.clipboardData.getData('text/plain');
		if (text.indexOf('mailto:') == 0) {
			this.value = text.substr(7); // chop off 'mailto';
			event.preventDefault();
			return false;
		}
	})
});



window.DATA_CHANGED = false;
function setupUnsavedWarnings()
{
	var warnForms = $('form.warn-unsaved');
	if (warnForms.length) {
		warnForms.submit(function() {
			window.DATA_CHANGED = false;
		}).find('input, select, textarea').keypress(function() {
			window.DATA_CHANGED = true;
		}).change(function() {
			window.DATA_CHANGED = true;
		})
		window.onbeforeunload = function() {
			if (window.DATA_CHANGED) return 'You have unsaved changes which will be lost if you don\'t save first';
		}
	}
}

TBLib.selectBasename = function() {
	var end = this.value.lastIndexOf('.');
	if ("selectionStart" in this) {
		this.setSelectionRange(0, end);
	} else if ("createTextRange" in this) {
		var t = this.createTextRange();
		//end -= start + o.value.slice(start + 1, end).split("\n").length - 1;
		//start -= o.value.slice(0, start).split("\n").length - 1;
		t.move("character", 0), t.moveEnd("character", end), t.select();
	}
}

TBLib.invalidRegexInput = null;
TBLib.handleRegexInputBlur = function()
{
	if (r = this.getAttribute('regex')) {
		$(this).parents('.control-group').removeClass('error');
		var ro = new RegExp(r, 'i');
		if ((this.value != '') && !ro.test(this.value)) {
			this.focus();
			$(this).parents('.control-group').addClass('error');
			alert('The value of the highlighted field is not valid');
			TBLib.invalidRegexInput = this;
			setTimeout('TBLib.invalidRegexInput.select()', 100);
			return false;
		}
	}
	return true;
}

TBLib.invalidBibleBox = null;
TBLib.handleBibleRefBlur = function()
{
	var re=/^(genesis|gen|genes|exodus|exod|ex|leviticus|levit|lev|numbers|nums|num|deuteronomy|deut|joshua|josh|judges|judg|ruth|1samuel|1sam|2samuel|2sam|1kings|1ki|2kings|2ki|1chronicles|1chron|1chr|2chronicles|2chron|2chr|ezra|nehemiah|nehem|neh|esther|esth|est|job|psalms|psalm|pss|ps|proverbs|prov|pr|ecclesiastes|eccles|eccl|ecc|song|sng|sg|songsol|songofsolomon|songofsongs|songofsong|sos|songofsol|isaiah|isa|jeremiah|jerem|jer|lamentations|lam|ezekiel|ezek|daniel|dan|hosea|hos|joel|jl|jo|amos|am|obadiah|obad|obd|ob|jonah|jon|micah|mic|nahum|nah|habakkuk|hab|zephaniah|zeph|haggai|hag|zechariah|zech|zec|malachi|mal|matthew|mathew|matt|mat|mark|mk|luke|lk|john|jn|actsoftheapostles|acts|ac|romans|rom|1corinthians|1cor|2corinthians|2cor|galatians|gal|ephesians|eph|philippians|phil|colossians|col|1thessalonians|1thess|1thes|2thessalonians|2thess|2thes|1timothy|1tim|2timothy|2tim|titus|tit|ti|philemon|hebrews|heb|james|jam|1peter|1pet|2peter|2pet|1john|1jn|2john|2jn|3john|3jn|jude|revelation|rev)(([0-9]+)([\-\:.]))?(([0-9]+)([\-\:.]))?(([0-9]+)([\-\:.]))?([0-9]+)(,[0-9]+(-[0-9]+)?)?$/gi;
	this.value = this.value.trim();
	if (this.value == '') return true;
	if (!this.value.replace(/ /g, '').match(re)) {
		this.focus();
		alert("Invalid bible reference - "+this.value.replace(/ /g, ''));
		TBLib.invalidBibleBox = this;
		setTimeout('TBLib.invalidBibleBox.select()', 100);
		return false;
	}
	return true;
}

TBLib.invalidDayBox = null;
TBLib.handleDayBoxBlur = function()
{
	if (this.value == '') return true;
	var intVal = parseInt(this.value, 10);
	if (isNaN(intVal) || (intVal < 1) || (intVal > 31)) {
		$(this).parents('.control-group').addClass('error');
		this.focus();
		alert('Day of month must be between 1 and 31');
		TBLib.invalidDayBox = this;
		setTimeout('TBLib.invalidDayBox.select()', 100);
		return false;
	}
	$(this).parents('.control-group').removeClass('error');
	return true;
}

TBLib.invalidYearBox = null;
TBLib.handleYearBoxBlur = function()
{
	if (this.value == '') return true;
	var intVal = parseInt(this.value, 10);
	if (isNaN(intVal) || (intVal < 1900) || (intVal > 3000)) {
		$(this).parents('.control-group').addClass('error');
		this.focus();
		alert('Year must be between 1900 and 3000');
		TBLib.invalidYearBox = this;
		setTimeout('TBLib.invalidYearBox.select()', 100);
		return false;
	}
	$(this).parents('.control-group').removeClass('error');
	return true;
}


TBLib.medLinkPopupWindow = null;
TBLib.handleMedPopupLinkClick = function(elt)
{
	if (!elt.href) elt = this;
	var myWidth = Math.max(500, Math.min(700, (window.innerWidth/2)));
	var myHeight = Math.max(500, (window.innerHeight * 0.8));
	TBLib.medLinkPopupWindow = window.open(elt.href, elt.target ? elt.target : 'medpopup', 'height='+myHeight+',width='+myWidth+',top=15,resizable=yes,scrollbars=yes');
	if (TBLib.medLinkPopupWindow) {
		TBLib.medLinkPopupWindow.focus();
	} else {
		alert('Jethro tried but could not open a popup window - you probably have a popup blocker enabled.  Please disable the popup blocker for this site, reload the page and try again.');
	}
	return false;
}

TBLib.medLinkNewWindow = null;
TBLib.handleMedNewWinLinkClick = function()
{
	TBLib.medLinkNewWindow = window.open(this.href, this.target ? this.target : 'medpopup', 'height=480,width=750,resizable=yes,scrollbars=yes,toolbar=yes,menubar=yes');
	if (TBLib.medLinkNewWindow) {
		TBLib.medLinkNewWindow.focus();
	} else {
		alert('Jethro tried but could not open a popup window - you probably have a popup blocker enabled.  Please disable your popup blocker for this site, reload the page and try again.');
	}
	return false;
}


TBLib.setupExpandableTable = function(table)
{
	TBLib.expandTable(table);
	$(table).find('.compulsory').removeClass('compulsory');
}

TBLib.expandTable = function(table)
{
	var rows = $(table).find('tbody:first > tr');
	var index = rows.length - 1;
	var originalRow = rows[index];
	var newRow = $(originalRow).clone(true,true);
	var newRowInputs = newRow.find('input, textarea, select');
	var incrementNames = !$(table).hasClass('no-name-increment');
	newRowInputs.each(function() {
		if (!this.name) return;
		// clear fields in the new row, except those inside a 'preserve-value' container
		if ($(this).parents('.preserve-value').length == 0 && !$(this).hasClass('preserve-value')) {
			if (this.type == 'checkbox') {
				this.checked = false;
			} else if (this.type != 'radio') {
				this.value = '';
			}
		} else {
			var correspondingElt = document.getElementsByName(this.name)[0];
			if (correspondingElt) {
				this.value = correspondingElt.value;
			}
		}
		if ($(this).hasClass('bubble-option-classes')) this.change();
		if (incrementNames) {
			this.name = this.name.replace('_'+index+'_', '_'+rows.length+'_');
		}
		if (this.name == 'index[]') this.value = rows.length; // so that after re-ordering, the order can be detected server-side
		if (((this.type == 'radio') || (this.type == 'checkbox')) && (this.value == index)) this.value = rows.length;
	});
	$(table).find('tbody:first').append(newRow);
	$(table).find('input, select, textarea').click(TBLib.handleTableExpansion).focus(TBLib.handleTableExpansion);
}

TBLib.handleTableExpansion = function()
{
	var table = $(this).parents('table.expandable:first');
	var lastRow = table.find('tbody:first > tr:last');
	if ((this.tagName == 'INPUT') || (this.tagName == 'SELECT')) {
		var inLastRow = false;
		var t = this;
		lastRow.find('input, select').each(function() {
			if (this == t) {
				inLastRow = true;
				return;
			}
		});
		if (inLastRow) {
			// we are in the last row.  Expand now if we are the only empty row.
			if (!TBLib.allInputsEmpty(lastRow.prev())) {
				TBLib.expandTable(table);
			}
		}
	}
	if (!TBLib.allInputsEmpty(lastRow)) TBLib.expandTable(table);
}


TBLib.allInputsEmpty = function(JQElt)
{
	var res = true;
	var x = JQElt.find('input, textarea');
	x.each(function() {
		if (0 == $(this).parents('.preserve-value').length) {
			if ((this.type != 'checkbox') && (this.type != 'radio') && (this.type != 'hidden') && (this.value != '')) {
				res = false;
				return;
			}
		}
	}).end();
	var y = JQElt.find('select');
	y.each(function() {
		if ((this.value != '') && (0 == $(this).parents('td.preserve-value').length)) {
			for (var j=0; j < this.options.length; j++) {
				if (this.options[j].value == '') {
					// it's not blank but it could have been blank
					res = false;
					return;
				}
			}
		}
	});
	if (x.length + y.length == 0) return false; // there are no empty inputs at all - don't expand
	return res;
}


TBLib.doTBLibValidation = true;
TBLib.cancelValidation = function()
{
	TBLib.doTBLibValidation = false;
}

TBLib.markErroredInput = function(input) {
	if ($(input).parents('.control-group').length) {
		$(input).parents('.control-group').addClass('error');
	} else {
		$(input).parents(':first').addClass('control-group').addClass('error');
	}
}

TBLib.handleFormSubmit = function()
{
	if (!TBLib.doTBLibValidation) {
		TBLib.doTBLibValidation = true;
		return false;
	}
	$('.control-group.error').removeClass('error');
	// Process compulsory inputs
	var compulsoryInputs = ($(this).find('input.compulsory, select.compulsory, textarea.compulsory'));
	for (var i=0; i < compulsoryInputs.size(); i++) {
		if ((compulsoryInputs.get(i).value == '') && (!compulsoryInputs.get(i).disabled)) {
			TBLib.markErroredInput(compulsoryInputs.get(i));
			compulsoryInputs.get(i).focus();
			alert('A mandatory field has been left blank');
			compulsoryInputs.get(i).focus();
			return false;
		}
	}
	var multiOK = true;
	$('.compulsory.multi-select').each(function() {
		if ($(this).find('input:checked').length==0) {
			TBLib.markErroredInput(this);
			alert('A mandatory field has been left blank');
			this.focus();
			multiOK = false;
			return false;
		}
	})
	if (!multiOK) return false;

	// Check phone numbers are OK
	var phoneInputs = ($(this).find('input.phone-number'));
	for (var i=0; i < phoneInputs.size(); i++) {
		with (phoneInputs.get(i)) {
			if (value == '') continue;
			if (value.match(/[A-Za-z]/)) {
				TBLib.markErroredInput(phoneInputs.get(i));
				alert('Phone numbers cannot contain letters');
				phoneInputs.get(i).focus();
				return false;
			}
			var numVal = value.replace(/[^0-9]/g, '');
			var validLengths = getAttribute('validlengths').split(',');
			var lengthOK = false;
			var digitOptions = '';
			for (var j=0; j < validLengths.length; j++) {
				if (numVal.length == validLengths[j]) lengthOK = true;
				if (j == 0) {
					digitOptions = validLengths[0];
				} else if (j < (validLengths.length - 1)) {
					digitOptions += ', '+validLengths[j];
				} else {
					digitOptions += ' or '+validLengths[j];
				}
			}
			if (!lengthOK) {
				TBLib.markErroredInput(phoneInputs.get(i));
				alert('The highlighted phone number must have '+digitOptions+' digits');
				phoneInputs.get(i).focus();
				return false;
			}
		}
	}
	// Check passwords
	var passwordsOK = true;
	$(this).find('input[type=password]').each(function() {
		if (this.name.substr(-1) == '1' && this.value != '') {
			var other = $('input[name='+this.name.substring(0,this.name.length-1)+'2]');
			if (other.length == 1) {
				if (other.get(0).value != this.value) {
					TBLib.markErroredInput(this);
					alert('The passwords you have entered don\'t match - please re-enter');
					this.select();
					passwordsOK = false;
					return;
				}
			}
			var minLength = $(this).attr('data-minlength');
			if (minLength && (this.value.length < minLength)) {
				TBLib.markErroredInput(this);
				alert('The password you entered is too short - it must be at least '+minLength+' characters');
				this.select();
				passwordsOK = false;
				return;
			}
			var num_nums = 0;
			var num_letters = 0;
			for (var i=0; i < this.value.length; i++) {
				if (this.value[i].match(/[0-9]/)) num_nums++;
				if (this.value[i].match(/[A-Za-z]/)) num_letters++;
			}
			if (num_nums < 2 || num_letters < 2) {
				TBLib.markErroredInput(this);
				this.select();
				alert('The password you entered is too simple - passwords must contain at least 2 letters and 2 numbers');
				passwordsOK = false;
			}
		}
	});
	if (!passwordsOK) return false;

	// Check regexps
	var regexesOK = true;
	$(this).find('input').each(function() {
		if (r = this.getAttribute('regex')) {
			var ro = new RegExp(r, 'i');
			if ((this.value != '') && !ro.test(this.value)) {
				TBLib.markErroredInput(this);
				alert('The value of the highlighted field is not valid');
				this.focus();
				regexesOK = false;
				return false;
			}
		}
	});
	if (!regexesOK) return false;

	var ok = true;

	$(this).find('input.bible-ref').each(function() {
		if (!TBLib.handleBibleRefBlur.apply(this)) {
			ok = false;
			return false;
		}
	});
	if (!ok) return false;

	$(this).find('input.day-box').each(function() {
		if (!TBLib.handleDayBoxBlur.apply(this)) {
			ok = false;
			return false;
		}
		if ((this.value == '') && $(this).siblings('select').val() != '') {
			var req = $(this).siblings('.optional-year').length ? 'day and month' : 'day, month and year';
			$(this).parents('.control-group').addClass('error');
			this.select();
			alert('The highlighted date field is incomplete - you must enter '+req+', or leave it completely blank');
			TBLib.invalidDateField = this;
			setTimeout('TBLib.invalidDateField.select()', 100);
			ok = false;
			return false;
		}
	});
	if (!ok) return false;

	$(this).find('input.year-box').each(function() {
		if (!TBLib.handleYearBoxBlur.apply(this)) {
			ok = false;
			return false;
		}
		var myDayBox = $(this).siblings('.day-box');
		if ((this.value == '') && !$(this).hasClass('optional-year') && (myDayBox.val() != '')) {
			$(this).parents('.control-group').addClass('error');
			this.select();
			alert('The highlighted date field is incomplete - you must enter day, month and year or leave it completely blank');
			TBLib.invalidDateField = this;
			setTimeout('TBLib.invalidDateField.select()', 100);
			ok = false;
			return false;
		}
	});
	if (!ok) return false;

	// For situations where there's a table column containing checkboxes and the user must tick at least one of them.
	// Mark up each cell as follows.
	// <td class="required-checkbox-col" data-error-message="You must select at least one XYZ">
	var rcc = $(this).find('td.required-checkbox-col');
	if (rcc.length) {
		var checkedBoxes = rcc.find('input[type=checkbox]:checked');
		if (!checkedBoxes.length) {
			rcc.find('input[type=checkbox]').get(0).scrollIntoView();
			var msg = "You must select one checkbox in this column";
			if (rcc.attr('data-error-message')) msg = rcc.attr('data-error-message');
			alert(msg);
			rcc.find('input[type=checkbox]').get(0).focus();
			ok = false;
			return false;
		}
	}

	if ($(this).hasClass('disable-submit-buttons')) {
		$(this).find('input[type=submit]').attr('disabled', 'disabled');
	}

	return true;
}

TBLib.invalidEmailField = null;
TBLib.handleEmailBlur = function()
{
	var rx = /^[^@]+@\w+([\.-]\w+)+$/
	this.value = this.value.trim();
	if (this.value != '' && !this.value.match(rx)) {
		this.focus();
		alert('This field must contain a valid email address');
		TBLib.invalidEmailField = this;
		setTimeout('TBLib.invalidEmailField.select();', 100);
		return false;
	}
	return true;
}

TBLib.handleSelectAllClick = function()
{
	$(this).parents('table:first').find('input[type=checkbox]').attr('checked', this.checked);
}

TBLib.handleBitmaskBoxClick = function()
{
	this.value = parseInt(this.value, 10);
	var boxes = document.getElementsByName(this.name);
	for (i=0; i < boxes.length; i++) {
		ov = parseInt(boxes[i].value, 10);
		if (this.checked && (ov < this.value) && ((ov & this.value) != 0)) {
			// check a parent
			boxes[i].checked = true;
		} else if ((!this.checked) && (ov > this.value) && ((ov & this.value) != 0)) {
			// uncheck a child
			boxes[i].checked = false;
		}
	}
}

/******************************************/

function getKeyCode(e)
{
	if (!e) e = window.event;
	return e.which ? e.which : e.keyCode;
}
function bam(x)
{
	var msg = '';
	for (i in x) {
		try {
			if (typeof x[i] != 'function') {
				msg += i + ' => ' + x[i] + "\n";
			}
		} catch (e) {}
	}
	alert(msg);
}

function tblog(x)
{
	var logBox = document.getElementById('tblib-log-box');
	if (logBox == null) {
		logBox = document.createElement('textarea');
		logBox.rows = 5;
		logBox.cols = 50;
		logBox.id = 'tblib-log-box';
		document.body.appendChild(logBox);
	}
	logBox.value += "\n"+x;
}

/************************************
 *  Extensions to built in types
 *************************************/

String.prototype.trim = function() {
	return this.replace(/^\s+|\s+$/g,"");
}
String.prototype.ltrim = function() {
	return this.replace(/^\s+/,"");
}
String.prototype.rtrim = function() {
	return this.replace(/\s+$/,"");
}

// Thanks to http://www.go4expert.com/forums/showthread.php?t=606
Array.prototype.contains = function(element)
{
	for (var i = 0; i < this.length; i++)
	{
		if (this[i] == element) {
			return true;
		}
	}
	return false;
};

TBLib.anchorBottom = function(exp, isOnResize) {
	var elts = $(exp);
	elts.css('overflow-y', 'auto');
	elts.height(1);
	var totalBodyHeight = $('body').height();
	var margin = 20;
	elts.each(function() {
		var $t = $(this);
		var padding = parseInt($t.css('padding-top'), 10) +  parseInt($t.css('padding-bottom'), 10);
		var newHeight = totalBodyHeight - $t.position().top - margin - padding;
		newHeight = Math.max(newHeight, 100);
		$t.height(newHeight);
	});
	if (!isOnResize) {
		$(window).resize(function() { TBLib.anchorBottom(exp, true); });
	}
}

TBLib.downloadText = function(content, filename) {
	content = btoa(encodeURIComponent(content).replace(/%([0-9A-F]{2})/g, function(match, p1) {
        return String.fromCharCode('0x' + p1);
    }));
	var pom = document.createElement('a');
	pom.setAttribute('href', 'data:text/html;charset=utf-8;base64,' + content);
	pom.setAttribute('download', filename);
	pom.style.display = 'none';
	document.body.appendChild(pom);
	pom.click();
}

// open mailto links in a new window (eg for gmail), but close the new window if it's unused (eg outlook desktop)
TBLib.handleMailtoClick = function() {
	var windowRef = window.open(this.href, '_email');
	windowRef.focus();
	setTimeout(function(){
		try {
			if(!windowRef.document.hasFocus()) {
				windowRef.close();
			}
		} catch (e) {} // if it's naviated to gmail.com we don't have permission to close it, and that's ok
	}, 500);
	return false;
}

TBLib.copyTSV = function(table) {
	var h = '';
	for (let r of table.rows) {
		$(r).find('>td,>th').not('.no-print, .no-tsv').each(function() {
			h += this.innerText.replace("\n", " ").replace("\t", " ") + '\t';
		});
		h += "\n";
	}
	return navigator.clipboard.writeText(h);
}

TBLib.copyTable = function(table) {
	var h = '<table border="1" cellspacing="0" cellpadding="3">';
	for (let r of table.rows) {
		h += '<tr>';
		$(r).find('>td,>th').not('.no-print, .no-tsv').each(function() {
			h += "<td>"+this.innerText.replace("\n", " ").replace("\t", " ") + '</td>';
		});
		h += "</tr>";
	}
	h += '</table>';
	var type = "text/html";
    var blob = new Blob([h], { type });
    var data = [new ClipboardItem({ [type]: blob })];
    return navigator.clipboard.write(data);
}

TBLib.selectElementText = function(el) {
	win = window;
	var doc = win.document, sel, range;
	if (win.getSelection && doc.createRange) {
		sel = win.getSelection();
		range = doc.createRange();
		range.selectNodeContents(el);
		sel.removeAllRanges();
		sel.addRange(range);
	} else if (doc.body.createTextRange) {
		range = doc.body.createTextRange();
		range.moveToElementText(el);
		range.select();
	}
}
