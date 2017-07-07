/*global VuFind,checkSaveStatuses*/
finna.record = (function() {
    var initAuthorityInfo = function() {
        $('.authority').each(function(ind, el) {
            var element = $(el);
            var content = null;
            var openBtn = element.find('.show-info');
            var closeBtn = element.find('.hide-info');
            var info = element.nextAll('.authority-info').first();

            openBtn.click(function() {
                if (!element.hasClass('loaded')) {
                    var data = element.data();
                    var url = VuFind.path + '/AJAX/JSON?method=getAuthorityInfo';
                    url += '&type=' + data.type + '&source=' + data.source;
                    url += '&id=' + data.authority;
 
                    var callback = function(response) {
                        info.html(
                            response.data.length ? response.data : VuFind.translate('error_occurred')
                        );

                        closeBtn.click(function() {
                            element.toggleClass('open', false);
                            openBtn.show();
                            closeBtn.hide();
                            info.toggleClass('hidden', true);
                            return false;
                        });
                        closeBtn.show();
                        element.addClass('loaded');
                    };
                    element.toggleClass('open', true);

                    $.getJSON(url, callback).fail(function() {
                        element.hide();
                    });
                }
                element.toggleClass('open', true);
                info.toggleClass('hidden', false);

                openBtn.hide();
                closeBtn.show();
                
                return false;
            });   
        });
    }

    var initDescription = function() {
        var description = $('#description_text');
        if (description.length) {
            var id = description.data('id');
            var url = VuFind.path + '/AJAX/JSON?method=getDescription&id=' + id;
            $.getJSON(url)
            .done(function(response) {
                if (response.data.length > 0) {
                    description.html(response.data);

                    // Make sure any links open in a new window
                    description.find('a').attr('target', '_blank');

                    description.wrapInner('<div class="truncate-field wide"><p class="summary"></p></div>');
                    finna.layout.initTruncate(description);
                    if (!$('.hide-details-button').hasClass('hidden')) {
                        $('.record .description').addClass('too-long');
                        $('.record .description .more-link.wide').click();
                    }
                } else {
                    description.hide();
                }
            })
            .fail(function() {
                description.hide();
            });
        }
    }

    var initHideDetails = function() {
      $('.show-details-button').click (function() {
        $('.record-information .record-details-more').removeClass('hidden');
        $(this).addClass('hidden');
        $('.hide-details-button').removeClass('hidden');
        $('.record .description .more-link.wide').click();
        sessionStorage.setItem('finna_record_details', '1');
      });
      $('.hide-details-button').click (function() {
        $('.record-information .record-details-more').addClass('hidden');
        $(this).addClass('hidden');
        $('.show-details-button').removeClass('hidden');
        $('.record .description .less-link.wide').click();
        sessionStorage.removeItem('finna_record_details');
      });
      if ($('.record-information').height() > 350 && $('.show-details-button')[0]) {
        $('.record .description').addClass('too-long');
        if (sessionStorage.getItem('finna_record_details')) {
          $('.show-details-button').click();
        } else {
          $('.hide-details-button').click();
        }
      }
    };

    getRequestLinkData = function(element, recordId) {
      var vars = {}, hash;
      var hashes = element.href.slice(element.href.indexOf('?') + 1).split('&');

      for (var i = 0; i < hashes.length; i++) {
        hash = hashes[i].split('=');
        var x = hash[0];
        var y = hash[1];
        vars[x] = y;
      }
      vars['id'] = recordId;
      return vars;
    }

    checkRequestsAreValid = function(elements, requestType) {
      if (!elements[0]) {
        return;
      }
      var recordId = elements[0].href.match(/\/Record\/([^\/]+)\//)[1];

      var vars = [];
      $.each(elements, function(idx, element) {
        vars.push(getRequestLinkData(element, recordId));
      });


      var url = VuFind.path + '/AJAX/JSON?method=checkRequestsAreValid';
      $.ajax({
        dataType: 'json',
        data: {id: recordId, requestType: requestType, data: vars},
        method: 'POST',
        cache: false,
        url: url
      })
      .done(function(responses) {
        $.each(responses.data, function(idx, response) {
          var element = elements[idx];
          if (response.status) {
            $(element).removeClass('disabled')
              .html(response.msg);
            } else {
              $(element).remove();
            }
        });
      })
      .fail(function(response, textStatus) {
        console.log(response, textStatus);
      });
    }

    var setUpCheckRequest = function() {
      checkRequestsAreValid($('.expandedCheckRequest').removeClass('expandedCheckRequest'), 'Hold');
      checkRequestsAreValid($('.expandedCheckStorageRetrievalRequest').removeClass('expandedCheckStorageRetrievalRequest'), 'StorageRetrievalRequest');
      checkRequestsAreValid($('.expandedCheckILLRequest').removeClass('expandedCheckILLRequest'), 'ILLRequest');
    }

    var initHoldingsControls = function() {
        $('.holdings-container-heading').click(function (e) {
            if ($(e.target).hasClass('location-service') || $(e.target).parents().hasClass('location-service')) {
                return;
            }
            $(this).nextUntil('.holdings-container-heading').toggleClass('collapsed');
            if ($('.location .fa', this).hasClass('fa-arrow-down')) {
                $('.location .fa', this).removeClass('fa-arrow-down');
                $('.location .fa', this).addClass('fa-arrow-right');
            }
            else {
                $('.location .fa', this).removeClass('fa-arrow-right');
                $('.location .fa', this).addClass('fa-arrow-down');
                var rows = $(this).nextUntil('.holdings-container-heading');
                checkRequestsAreValid(rows.find('.collapsedCheckRequest').removeClass('collapsedCheckRequest'), 'Hold', 'holdBlocked');
                checkRequestsAreValid(rows.find('.collapsedCheckStorageRetrievalRequest').removeClass('collapsedCheckStorageRetrievalRequest'), 'StorageRetrievalRequest', 'StorageRetrievalRequestBlocked');
                checkRequestsAreValid(rows.find('.collapsedCheckILLRequest').removeClass('collapsedCheckILLRequest'), 'ILLRequest', 'ILLRequestBlocked');
            }
        });
    };

    var setupHoldingsTab = function() {
        initHoldingsControls();
        setUpCheckRequest();
        finna.layout.initLocationService();
        finna.layout.initJumpMenus($('.holdings-tab'));
        VuFind.lightbox.bind($('.holdings-tab'));
    };

    var initRecordNaviHashUpdate = function() {
        $(window).on('hashchange', function() {
            $('.record-view-header .pager a').each(function(i, a) {
                a.hash = window.location.hash;
            });
        });
        $(window).trigger('hashchange');
    }

    var init = function() {
        initHideDetails();
        initAuthorityInfo();
        initDescription();
        initRecordNaviHashUpdate();
    };

    var my = {
        checkRequestsAreValid: checkRequestsAreValid,
        init: init,
        setupHoldingsTab: setupHoldingsTab
    };

    return my;
})(finna);
