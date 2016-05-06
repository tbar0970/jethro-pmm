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

  // Move Modal for SMS Messaging out to the body, to avoid weird z-index bugs
  $(".single-sms-modal").detach().appendTo("#body");

  // SMS Character counting
  $('.charactercount').parent().find('textarea').on('keyup propertychange paste', function(){
    var maxlength = $(this).attr("maxlength");
    var chars = maxlength - $(this).val().length;
    if (chars <= 0) {
      $(this).val( $(this).val().substring(0,maxlength) );
      chars = 0;
    }
    $('.charactercount').html(chars +' characters remaining.');
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


  /*************************** SMS AJAX ********************/
  $('.single-sms-modal .sms-submit').on('click', function(event) {
    event.preventDefault();
    var modalDiv = $(this).parent().parent(); // takes us back up to the DIV
    var sms_message = modalDiv.find("textarea").val();
    if (!sms_message) {
      alert("Please enter a message first.");
      return false;
    } else {
      var submitBtn, smsData,personid;
      $(this).prop('disabled', true);
      $(this).html("Sending");
      smsData = {
        personid: modalDiv.attr("data-personid"),
	saveasnote: (modalDiv.find('.saveasnote').attr('checked') == 'checked')?'1':'0',
        ajax: 1,
        message: sms_message
      }
      //smsData = $(this).serialize();
      $.ajax({
        type: 'POST',
        dataType: 'JSON',
        url: '?view=_send_sms_http',
        data: smsData,
        context: $(this),
        error: function (jqXHR, status, error) {
          var modalDiv = $(this).parent().parent(); // takes us back up to the DIV
          console.log('Error sending SMS', status, error);
          var statusBtn = modalDiv.find('.single-sms-status');
          statusBtn.unbind('click');
          statusBtn.on('click', function (event) { event.preventDefault(); alert(error); });
          statusBtn.toggleClass('fade');
        },
        success: function (data) {
	  console.log(data);
          var modalDiv = $(this).parent().parent(); // takes us back up to the DIV
          var successCount = 0,
          failedCount = 0,
          rawresponse = '',
          error = 'No details available. Please check the number is right.',
          statusBtn,
          submitBtn;
          if (data.success !== undefined) { successCount = data.success.count; }
          if (data.failed !== undefined) { failedCount = data.failed.count; }
          if (data.rawresponse !== undefined) { rawresponse = data.rawresponse; }
          if (data.error !== undefined) { error = data.error; }

          if (failedCount > 0) {
            statusBtn = modalDiv.find('.single-sms-status');
            statusBtn.unbind('click');
            statusBtn.on('click', function (event) { event.preventDefault(); alert(error); });
            statusBtn.toggleClass('fade');
          } else {
            modalDiv.modal('hide');
          }
          $(this).prop('disabled', false);
          $(this).html("Send");
	  if ($("#tab_notes").length) {
		$.ajax({
			type: 'POST',
			url: './?view=persons',
			data: { personid: $("#view-person .person-details-box:first-child").data("personid") },
			success: function (data) {
				var notesdata = $.parseJSON(data);
				$("#notes").html(notesdata.noteshtml);
				$("#tab_notes").html("Notes (" + notesdata.notescount + ")");
			}
		  });
	 }
        }
      });
      return false;
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
  $('#bulk-action-chooser').change(function () {
    $('.bulk-action').hide();
    $('.bulk-action input, .bulk-action select, .bulk-action textarea').attr('disabled', true);
    $('#' + this.value).show('fast', function () { try { this.scrollIntoView(); } catch (e) {} });
    var selectedInputs = $('#' + this.value + ' input, #' + this.value + ' select, #' + this.value + ' textarea');
    selectedInputs
      .attr('disabled', false)
      .filter(':visible:first').focus();
    selectedInputs.filter('[data-toggle=enable]').attr('disabled', false).change();
  });

  $('form.bulk-person-action').submit(function (event) {
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
    if ($(this).prop('action').match('send_sms_http$')) {
      event.preventDefault();
      var submitBtn = $(this).find('.bulk-sms-submit');
      submitBtn.prop('disabled', true);
      submitBtn.prop('value', 'Sending...');
      var smsData = $(this).serialize();
      $.ajax({
        type: 'POST',
        dataType: 'JSON',
        url: '?view=_send_sms_http',
        data: smsData,
        context: $(this),
        error: function (jqXHR, status, error) {
          console.log('Error sending SMS: ', status, error);
          var failDiv = $('.bulk-sms-failed');
          failDiv.append("<h4>SMS Sending error<h4>");
          failDiv.append('<span class="clickable" onclick="$(\'.bulk-sms-failed #response\').toggle()\'">Show SMS server response</span></b></p><div class="hidden standard" id="response">' + error + '</div>');
        },
        success: function (data) {
          console.log("DATA: ", data);
          var smsRequestCount = $("input[name='personid[]']:checked").length;
          var sentCount = 0;
          var failedCount = 0;
          var archivedCount = 0;
          var blankCount = 0;
          var rawresponse = '';
          var error = 'No details available. Please check the number is right.';
          var failDiv = $('.bulk-sms-failed');

          if (data.sent !== undefined) { sentCount = data.sent.count; }
          if (data.failed !== undefined) { failedCount = data.failed.count; }
          if (data.failed_archived !== undefined) { archivedCount = data.failed_archived.count; }
          if (data.failed_blank !== undefined) { blankCount = data.failed_blank.count; }
          if (data.rawresponse !== undefined) { rawresponse = data.rawresponse; }
          if (data.error !== undefined) { error = data.error; }
          if ($('#action_status').length) { // action status already exists
            $('#action_status').html('SMS');
          } else {
            $('.bulk-person-action thead tr').append("<th id='action_status'>SMS</th>");
            $('.bulk-person-action tbody tr').each(function () { $(this).append('<td></td>'); });
          }
          failDiv.html('');
          if (failedCount > 0) {
            failDiv.append("<h4>SMS Failures</h4><p style='clear:both'>SMS sending failed for " + failedCount + ' recipients.</p>');
            for (var personID in data.failed.recipients) {
               $("[data-personid=" + personID + "]").closest('tr').find("td:last").html("Failed (General)");
            }
          }
          if ((blankCount > 0) || (archivedCount >0)) {
            if (blankCount > 0) {
              failDiv.append("<h4>No Mobile</h4><p style='clear:both'>SMS sending failed for " + blankCount + ' recipients due to the lack of a mobile number.</p>');
              for (var personID in data.failed_blank.recipients) {
                $("[data-personid=" + personID + "]").closest('tr').find("td:last").html("Failed (No mobile)");
              }
            }
            if (archivedCount > 0) {
              failDiv.append("<h4>Archived Recipients</h4><p style='clear:both'>" + archivedCount + ' of the intended recipients were not sent the message because they are archived.</p>');
              for (var personID in data.failed_archived.recipients) {
                $("[data-personid=" + personID + "]").closest('tr').find("td:last").html("Failed (Archived)");
              }
            }
          }

          if (sentCount > 0) {
            for (var personID in data.sent.recipients) {
              console.log("KEY: ", personID, " DATA: ", data.sent.recipients[personID]);
              var sentStatus = "Sent";
              if (!data.sent.confirmed) {
                failDiv.appen("'<h4>Sending could not be confirmed</h4><p style='clear:both'>Unable to confirm whether SMS sending was successful. Please check your SMS settings.</p>");
                sentStatus = sentStatus + " (Assumed))";
              }
              $("[data-personid=" + personID + "]").closest('tr').find("td:last").html("Sent");
              $("[data-personid=" + personID + "]").closest('td').find("[type=checkbox]").prop("checked", false);
            }
          }
          failDiv.append(data);
          if (sentCount !== smsRequestCount) {
            if (data.error!==undefined) {
              failDiv.append("<h4>More details<h4>");
              failDiv.append('<span class="clickable" onclick="$(\'.bulk-sms-failed #response\').toggleClass(\'hidden\');">Show SMS server response</span></b></p><div class="hidden standard alert alert-warning" id="response">' + data.error + '</div>');
            }
            $('#alert').removeClass('alert-info').removeClass('alert-error').removeClass('alert-success');
            $('#alert').addClass('alert-error');
            $("#alert").html("<strong>SMS Failure:</strong> Sending failed for some (or all) recipients.");
            $("#alert").fadeIn();
          } else {
            $('#smshttp').toggle();
            $("#bulk-action-chooser").val(0);
            $('#alert').removeClass('alert-info').removeClass('alert-error').removeClass('alert-success');
            $('#alert').addClass('alert-success');
            $("#alert").html("<strong>SMS Success!</strong> All messages successfully sent.");
            $("#alert").fadeIn();
            setTimeout(function() {$("#alert").fadeOut();}, 10000);
          }

          var submitBtn = $(this).find('.bulk-sms-submit');
          submitBtn.prop('disabled', false);
          submitBtn.prop('value', 'Send');
        }
      });
      return false;
    } else {
      return true;
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

	//setTimeout( "applyNarrowColumns('body'); ", 30);

  if (document.getElementById('service-planner')) {
    JethroServicePlanner.init();
  }

	$('table.reorderable tbody').sortable(	{
			cursor: "move",
    /*containment: "parent",*/
    revert: 100,
    opacity: 1,
    axis: 'y',
		})

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
  console.log($tr.find('input'));
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
  console.log(componentTR);
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
    var tablewidth = table[0].getBoundingClientRect().right - table[0].getBoundingClientRect().left; // ie<=8 doesn't have .width
		table.css('width', tablewidth+'px');
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
    window.haveLaidOutMatchBoxes = 1;
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
