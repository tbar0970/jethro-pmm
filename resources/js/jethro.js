// TODO: more sniffing of the relevant bits (like attendance does) to speed up page load

$(document).ready(function() {

	if ($('.stop-js').length) return; /* Classname flag for big pages that don't want JS to run */

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


	/*************************** REPORTS *********************/
	$('input.select-rule-toggle').click(function() {
		$($(this).parents('tr')[0]).find('div.select-rule-options').css('display', (this.checked ? '' : 'none'));
	});

	if ($('#datefield-rules')) {
		$('.datefield-rule-period').hide();
		$('.datefield-rule-criteria').change(function() {
			if ((this.value == 'exact') || (this.value == 'anniversary')) {
				$(this).siblings('.datefield-rule-period').show();
			} else {
				$(this).siblings('.datefield-rule-period').hide();
			}
		}).change();
	}
		

	
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

	$('form.bulk-person-action').submit(function() {
		var checkboxes = document.getElementsByName('personid[]');
		for (var i=0; i < checkboxes.length; i++) {
			if (checkboxes[i].checked) return true;
		}
		if (confirm('You have not selected any persons. Would you like to perform this action on every person listed?')) {
			for (var i=0; i < checkboxes.length; i++) {
				checkboxes[i].checked = true;
			}
			return true;
		} else {
			TBLib.cancelValidation();
			return false;
		}
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

	
	$('.radio-button-group div')
		.on('touchstart', function(event) {
			$(this).addClass('active');
			var t = $(this);
			t.attr('touched', 1);
			onRadioButtonActivated.apply(t);
			event.stopPropagation();
			event.cancelDefault();
		})
		.on('click', function() {
			var t = $(this);
			if (!t.attr('touched')) onRadioButtonActivated.apply(t);
			t.attr('touched', 0);

		});
	
	function onRadioButtonActivated(event) {
		this.siblings('div').removeClass('active');
		this.addClass('active');
		this.parents('.radio-button-group').find('input').val(this.attr('data-val'));
		//this.fadeOut(100).fadeIn(100);
	}
	
	
	$('.attendance .radio-button-group div').click(function(e) {
		var thisRow = $(this).parents('tr:first');
		thisRow.removeClass('hovered');
		thisRow.next('tr').find('.radio-button-group').focus();
	});
	
	$('.radio-button-group').keypress(function(e) {
		var char = String.fromCharCode(e.which).toUpperCase();
		$(this).find('div').each(function() {
			if ($(this).text().trim() == char) {
				this.click();
			}
		});
	});

	$('.attendance .radio-button-group').keyup(function(e) {
		if (e.which == 40) $(this).parents('tr:first').next('tr').find('.radio-button-group').focus();
		if (e.which == 38) $(this).parents('tr:first').prev('tr').find('.radio-button-group').focus();
	});


	$('.attendance .radio-button-group').focus(function() {
		$('tr.hovered').removeClass('hovered');
		$(this).parents('tr:first').addClass('hovered');
	});
	
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

	if (document.getElementById('service-planner')) {
		JethroServicePlanner.init();
	}

	if (document.getElementById('service-program-editor')) {
		JethroServiceProgram.init();
	}


});

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

JethroServicePlanner._getTRDragHelper = function(event, tr) {
	var helper = tr.clone();
	var originals = tr.children();
	helper.children().each(function(index) {
		$(this).width(originals.eq(index).width())
	});
	return helper;
}

JethroServicePlanner.init = function() {

	// COMPONENTS TABLES:

	TBLib.anchorBottom('#service-comps, #service-plan-container');

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

	$("#component-search input").keypress(function() {
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

	JethroServicePlanner.refreshNumbersAndTimes();

	// WARN UNSAVED

	window.onbeforeunload = JethroServicePlanner.onBeforeUnload;

	$('#service-plan-container').submit(function() {
			
	});


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
}

JethroServicePlanner.Item.viewCompDetail = function($tr) {
	var href="?call=service_comp_detail&head=1&id="+($tr.find('input.componentid').val());
	TBLib.handleMedPopupLinkClick({'href' : href});
}

JethroServicePlanner.onItemDrop = function(event, ui) {
	if (JethroServicePlanner.draggedComp) {
		JethroServicePlanner.addFromComponent(JethroServicePlanner.draggedComp, this);
		JethroServicePlanner.draggedComp = null;
	}
}

JethroServicePlanner.addFromComponent = function(componentTR, beforeItem) {
	JethroServicePlanner.isChanged = true;

	var newTR = $('#service-item-template').clone().attr('id', '');
	newTR.css('display', '').addClass('service-item');
	var attrs = ['componentid', 'is_numbered', 'length_mins'];
	var runsheetTitle = componentTR.attr('data-runsheet_title');
	var newTitle = runsheetTitle ? runsheetTitle: componentTR.find('.title').html();
	newTR.find('td.item span').html(newTitle);
	for (var i=0; i < attrs.length; i++) {
		newTR.find('td.item').append('<input type="hidden" class="'+attrs[i]+'" name="'+attrs[i]+'[]" value="'+componentTR.attr('data-'+attrs[i])+'" />');
	}
	if (!beforeItem || $(beforeItem).parents('tfoot').length) {
		beforeItem = "#service-plan tbody tr:last";
	}
	$(beforeItem).before(newTR);
	$('#service-plan-placeholder').remove();

	JethroServicePlanner.refreshNumbersAndTimes();
	newTR.droppable({
		drop: JethroServicePlanner.onItemDrop,
		hoverClass: 'drop-hover',
	});
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
		if ($(this).find('input.is_numbered').val() == 1) {
			$(this).find('td.number').html(currentNumber++);
		}
	});
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
	var parents = cells.parents('table:visible');
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
* Otherwise, give them 100% of the width (unless they need even more than that).
*/
function layOutMatchBoxes() {

	// Only run it once, because applyNarrowColumns will have messed with the table widths after the initial one
	if (window.haveLaidOutMatchBoxes) return;
	var matchBoxes = $('.person-details-box:visible');
	// Remove prior formatting
	matchBoxes.css('width', 'auto').css('height', 'auto').css('clear', 'none'); //.css('margin-right', 0);
	if (matchBoxes.length) {
		window.haveLaidOutMatchBoxes =  1;
		var first = matchBoxes.first();
		var second = matchBoxes.last();
		if (first.position().top == second.position().top) {
			// make the heights the same and remove margin bottom
			matchBoxes.height(Math.max(first.height(), second.height())+20).css('margin-bottom', 0);
		} else {
			// make the widths equal
			matchBoxes.css('min-width', '97%');
		}
	}
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
	$('select.person-status').not('.bulk-action *').change(handlePersonStatusChange).change();
	$('form#add-family .person-status select').change(handleNewPersonStatusChange);
	$('form#add-family .congregation select').change(handleNewPersonCongregationChange);

	$('.note-status select')
		.keypress(function() { handleNoteStatusChange(this); })
		.on('touchstart', function() { handleNoteStatusChange(this); })
		.click(function() { handleNoteStatusChange(this); })
		.change(function() { handleNoteStatusChange(this); })
		.change();
});

function handleNoteStatusChange(elt) {
	var prefix = elt.name.replace('status', '');
	var newDisplay = (elt.value == 'no_action') ? 'none' : '';
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