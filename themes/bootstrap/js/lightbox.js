/*global checkSaveStatuses, console, deparam, extractSource, hexEncode, htmlEncode, path, rc4Encrypt, refreshCommentList, vufindString */

var Lightbox = {
  /**
   * We save the URL and POST data every time we call getByUrl.
   * If we don't have a target a form submission, we use these variables
   * to replicate empty target behaviour by submitting to the current "page".
   */
  lastURL: false,
  lastPOST: false,
  shown: false,      // Is the lightbox deployed?
  XHR: false,        // Used for current in-progress XHR lightbox request
  openStack: [],     // Array of functions to be called after changeContent or the lightbox event 'shown'
  closeStack: [],    // Array of functions to be called after the lightbox event 'hidden'
  formHandlers: [],  // Full custom handlers for forms; by name
  formCallbacks: [], // Custom functions for forms, called after .submit(); by name

  /**********************************/
  /* ======    INTERFACE     ====== */
  /**********************************/
  /**
   * Register custom open event handlers
   *
   * Think of this as the $(document).ready() of the Lightbox
   * There's actually an alias right below it.
   *
   * If your template has inline JS, $(document).ready() will not fire in the lightbox
   * because it already fired before you opened the lightbox and won't fire again.
   *
   * You can use $.isReady to determine if ready() has already been called
   * so you can trigger your function immediately in the inline JS.
   */
  addOpenAction: function(func) {
    this.openStack.push(func);
  },
  // Alias for addOpenAction
  ready: function(func) {
    this.openStack.push(func);
  },
  /**
   * Register custom close event handlers
   */
  addCloseAction: function(func) {
    this.closeStack.push(func);
  },
  /**
   * For when you want to handle that form all by yourself
   *
   * We recommend using the getLightbox action in the AJAX Controller
   * to ensure you get a lightbox formatted page.
   *
   * If your handler doesn't return false, the form will submit the normal way,
   * with all normal behavior that goes with: redirection, etc.
   */
  addFormHandler: function(formName, func) {
    this.formHandlers[formName] = func;
  },
  /**
   * Register a function to be called when a form submission succeeds
   *
   * We add error checking by default, you never know when error blocks will strike.
   * Passing false to expectsError turns this off. Errors are inserted above *current* content.
   */
  addFormCallback: function(formName, func, expectsError) {
    if(typeof expectsError === "undefined" || expectsError) {
      this.formCallbacks[formName] = function(html) {
        Lightbox.checkForError(html, func);
      };
    } else {
      this.formCallbacks[formName] = func;
    }
  },
  /**
   * We store all the ajax calls in case we need to cancel.
   * This function cancels the previous call and creates a new one.
   */
  ajax: function(obj) {
    this.XHR.abort();
    this.XHR = $.ajax(obj);
  },
  /**********************************/
  /* ====== LIGHTBOX ACTIONS ====== */
  /**********************************/
  /**
   * Change the content of the lightbox.
   *
   * Hide the header if it's empty to make more
   * room for content and avoid double headers.
   */
  changeContent: function(html, headline) {
    var header = $('#modal .modal-header');
    if(typeof headline !== "undefined") {
      header.html(headline);
    }
    if(header.find('h3').html().length == 0) {
      header.css('border-bottom-width', '0');
    } else {
      header.css('border-bottom-width', '1px');
    }
    $('#modal .modal-body').html(html).modal({'show':true,'backdrop':false});
    Lightbox.openActions();
  },

  /**
   * This is the function you call to manually close the lightbox
   */
  close: function(evt) {
    $('#modal').modal('hide'); // This event calls closeActions
  },
  /**
   * This function is attached to the lightbox close event,
   * so it always runs when the lightbox is closed.
   */
  closeActions: function() {
    Lightbox.shown = false;
    // Clean out stack
    while(Lightbox.closeStack.length > 0) {
      var f = Lightbox.closeStack.pop();
      f();
    }
    // Abort requests triggered by the lightbox
    if(this.XHR) { this.XHR.abort(); }
    // Reset content so we start fresh when we open a lightbox
    $('#modal').removeData('modal');
    $('#modal').find('.modal-header h3').html('');
    $('#modal').find('.modal-body').html(vufindString.loading + "...");
    
    /**
     * Below here, we're doing content updates (sample events that affect content)
     */ 
    var recordId = $('#record_id').val();
    var recordSource = $('.hiddenSource').val();
     
    // Update the "Saved In" lists (add favorite, login)
    if(typeof checkSaveStatuses === 'function') {
      checkSaveStatuses();
    }
    
    // Update the comment list (add comment, login)
    if(typeof refreshCommentList === 'function') {
      refreshCommentList(recordId, recordSource);
    }
  },
  /**
   * Call all the functions we need for when the modal loads
   *
   * Called by the 'shown' event and at the end of changeContent
   */
  openActions: function() {
    for(var i=0;i<Lightbox.openStack.length;i++) {
      Lightbox.openStack[i]();
    }
  },
  /**
   * This function changes the content of the lightbox to a message with a close button
   */
  confirm: function(message) {
    this.changeContent('<div class="alert alert-info">'+message+'</div><button class="btn" onClick="Lightbox.close()">'+vufindString['close']+'</button>');
  },
  /**
   * Regexes a piece of html to find an error alert
   * If one is found, display it
   *
   * If one is not found, return html to a success callback function
   */
  checkForError: function(html, success) {
    var fi = html.indexOf('<div class="alert alert-error">');
    if(fi > -1) {
      var li = html.indexOf('</div>', fi+31);
      Lightbox.displayError(html.substring(fi+31, li));
    } else {
      success(html);
    }
  },
  /**
   * Insert an error alert element at the top of the lightbox
   */
  displayError: function(message) {
    var alert = $('#modal .modal-body .alert');
    if(alert.length > 0) {
      $(alert).html(message);
    } else if($('#modal .modal-body').html() == vufindString.loading+"...") {
      $('#modal .modal-body').html('<div class="alert alert-error">'+message+'</div><button class="btn" onClick="Lightbox.close()">'+vufindString['close']+'</button>');
    } else {
      $('#modal .modal-body').prepend('<div class="alert alert-error">'+message+'</div>');
    }
    $('.icon-spinner').remove();
  },

  /***********************************/
  /* ====== LIGHTBOX REQUESTS ====== */
  /***********************************/
  /**
   * This function creates an XHR request to the URL
   * and handles the response according to the callback.
   *
   * Default callback is changeContent
   */
  getByUrl: function(url, post, callback) {
    if(typeof callback == "undefined") {
      // No custom handler: display return in lightbox
      callback = this.changeContent;
    }
    // If the lightbox isn't visible, fix that
    if(this.shown == false) {
      $('#modal').modal('show');
      this.shown = true;
    }
    // Create our AJAX request, store it in case we need to cancel later
    this.XHR = $.ajax({
      type:'POST',
      url:url,
      data:post,
      success:function(html) { // Success!
        callback(html);
      },
      error:function(d,e) {
        if (d.status == 200) {
          try {
            var data = JSON.parse(d.responseText);
            Lightbox.changeContent('<p class="alert alert-error">'+data.data+'</p>');
          } catch(e) {
            Lightbox.changeContent('<p class="alert alert-error">'+d.responseText+'</p>');
          }
        } else {
          Lightbox.changeContent('<p class="alert alert-error">'+d.statusText+' ('+d.status+')</p>');
        }
        console.log(e,d); // Error reporting
        console.log(url,post);
      }
    });
    // Store current "page" context for empty targets
    this.lastURL = url;
    this.lastPOST = post;
    //this.openActions();
    return false;
  },
  /**
   * This is the friendly face to the function above.
   * It converts a Controller and Action into a URL through the AJAX handler
   * with GET and pushes the data and callback to the getByUrl
   */
  get: function(controller, action, get, post, callback) {
    // Build URL
    var url = path+'/AJAX/JSON?method=getLightbox&submodule='+controller+'&subaction='+action;
    if(typeof get !== "undefined" && get !== {}) {
      url += '&'+$.param(get);
    }
    if(typeof post == "undefined") {
      post = {};
    }
    return this.getByUrl(url, post, callback);
  },

  /**********************************/
  /* ====== FORM SUBMISSIONS ====== */
  /**********************************/
  /**
   * Returns all the input values from a form as an associated array
   *
   * This function takes a jQuery wrapped form
   * $(event.target) for example
   */
  getFormData: function($form) {
    // Gather all the data
    var inputs = $form.find('*[name]');
    var data = {};
    for(var i=0;i<inputs.length;i++) {
      var currentName = inputs[i].name;
      var array = currentName.substring(currentName.length-2) == '[]';
      if(array && !data[currentName.substring(0,currentName.length-2)]) {
        data[currentName.substring(0,currentName.length-2)] = [];
      }
      // Submit buttons
      if(inputs[i].type == 'submit') {
        if($(inputs[i]).attr('clicked') == 'true') {
          data[currentName] = inputs[i].value;
        }
      // Radio buttons
      } else if(inputs[i].type == 'radio') {
        if(inputs[i].checked) {
          if(array) {
            var n = currentName.substring(0,currentName.length-2);
            data[n].push(inputs[i].value);
          } else {
            data[currentName] = inputs[i].value;
          }
        }
      // Checkboxes
      } else if($(inputs[i]).attr('type') != 'checkbox' || inputs[i].checked) {
        if(array) {
          var f = currentName.substring(0,currentName.length-2);
          data[f].push(inputs[i].value);
        } else {
          data[currentName] = inputs[i].value;
        }
      }
    }
    return data;
  },
  /**
   * The default, automatic form submission
   *
   * This function gleans all the information in a form from the function above
   * Then it uses the action="" attribute of the form to figure out where to send the data
   * and the method="" attribute to send it the proper way
   *
   * In the wild, forms without an action="" are submitted to the current URL.
   * In the case where we have a form with no action in the lightbox, 
   * we emulate that behaviour by submitting the last URL loaded through
   * .getByUrl, stored in lastURL in the Lightbox object.
   */
  submit: function($form, callback) {
    // Default callback is to close
    if(typeof callback == "undefined") {
      callback = this.close;
    }
    var data = this.getFormData($form);
    // If we have an action: parse
    var POST = $form.attr('method') && $form.attr('method').toUpperCase() == 'POST';
    if($form.attr('action')) {
      // Parse action location
      var action = $form.attr('action').substring($form.attr('action').indexOf(path)+path.length+1);
      var params = action.split('?');
      action = action.split('/');
      var get = params.length > 1 ? deparam(params[1]) : data['id'] ? {id:data['id']} : {};
      if(POST) {
        this.get(action[0], action[action.length-1], get, data, callback);
      } else {
        this.get(action[0], action[action.length-1], data, {}, callback);
      }
    // If not: fake context by using the previous action
    } else if(POST) {
      this.getByUrl(this.lastURL, data, callback);
    } else {
      this.getByUrl(this.lastURL, {}, callback);
    }
    $(this).find('.modal-body').html(vufindString.loading + "...");
  }
};

/**
 * This is where you add click events to open the lightbox.
 * We do it here so that non-JS users still have a good time.
 */
$(document).ready(function() {
  /**
   * Hook into the Bootstrap close event
   *
   * Yes, the secret's out, our beloved Lightbox is a modal
   */
  $('#modal').on('hidden', Lightbox.closeActions);
  /**
   * If a link with the class .modal-link triggers the lightbox,
   * look for a title="" to use as our lightbox title.
   */
  $('.modal-link,.help-link').click(function() {
    var title = $(this).attr('title');
    if(typeof title === "undefined") {
      title = $(this).html();
    }
    $('#modal .modal-header h3').html(title);
  });
});