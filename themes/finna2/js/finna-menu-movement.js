/* global finna */

/**
 * Creates an arrow key movement to given menu element, typically an ul.
 * 
 * @param {jQuery} element 
 */
function FinnaMovement (element) {
  var _ = this;
  _.menuRootElement = $(element);
  _.menuElements = [];
  _.setChildData();
  _.keys = {
    up: 38,
    down: 40,
    left: 37,
    right: 39,
    space: 32
  };
  _.indexCache = -1;
  _.setEvents();
}

/**
 * Set events related to the movement component
 */
FinnaMovement.prototype.setEvents = function setEvents() {
  var _ = this;
  _.menuRootElement.on('reindex.finna', function reIndex() {
    _.setChildData();
    _.setFocusTo();
  });
  _.menuRootElement.on('keydown', function detectKeyPress(e) {
    _.checkKey(e);
  });
};

/**
 * Reset the internal pointers of movement handler
 */
FinnaMovement.prototype.reset = function reset() {
  var _ = this;
  _.indexCache = -1;
};

/**
 * Function to refocus to cached element
 */
FinnaMovement.prototype.setFocusTo = function setFocusTo() {
  var _ = this;
  if (_.indexCache !== -1) {
    var element = _.getMenuItem(_.menuElements, 0, _.indexCache);
    element.input.focus();
  }
};

/** 
 * Finds all menu elements and their children if the menu is horizontal
 */
FinnaMovement.prototype.setChildData = function setChildData() {
  var _ = this;
  var i = 0;
  _.menuElements = [];

  var FOCUSABLE_ELEMENTS = ['a[href]', 'area[href]', 'input[type=radio]:checked', 'input:not([disabled]):not([type="hidden"]):not([aria-hidden]):not([type=radio])', 'select:not([disabled]):not([aria-hidden])', 'textarea:not([disabled]):not([aria-hidden])', 'button:not([disabled]):not([aria-hidden])', 'iframe', 'object', 'embed', '[contenteditable]', '[tabindex]:not([tabindex^="-"])'];

  var nodes = _.menuRootElement[0].querySelectorAll(FOCUSABLE_ELEMENTS);
  var children = [].slice.apply(nodes);
  var formedObjects = [];
  children.forEach(function testEach(element) {
    var obj = {input: $(element), parent: undefined};
    obj.input.attr('tabindex', (i === 0) ? '0' : '-1');
    obj.input.data('index', i++);
    formedObjects.push(obj);
  });
  _.menuElements = formedObjects;
};

/**
 * Check the input key given by the user
 */
FinnaMovement.prototype.checkKey = function checkKey(e) {
  var _ = this;
  var code = (e.keyCode ? e.keyCode : e.which);
  switch (code) {
  case _.keys.up:
    _.moveMainmenu(-1);
    e.preventDefault();
    break;
  case _.keys.left:
  case _.keys.right:
  case _.keys.space:
    var element = _.getMenuItem(_.menuElements, 0);
    if (!element.input.is('input')) {
      element.input.trigger('togglesubmenu');
      e.preventDefault();
    }
    break;
  case _.keys.down:
    _.moveMainmenu(1);
    e.preventDefault();
    break;
  }
};

/**
 * Move the cursor in the level 1 menu elements, adjusted by direction
 * 
 * @param {int} dir
 *
 */
FinnaMovement.prototype.moveMainmenu = function moveMainmenu(dir) {
  var _ = this;
  var current = _.getMenuItem(_.menuElements, dir);
  if (current.input.is(':hidden')) {
    _.moveMainmenu(dir);
  } else {
    current.input.focus();
  }
};

/**
 * Function to calculate desired index, array of elements, dir. Optionally you can use cacheIndex to 
 * get certain element
 * 
 * @param {Array} elements
 * @param {int} direction
 * @param {int} cacheIndex
 */
FinnaMovement.prototype.getMenuItem = function getMenuItem(elements, direction, cacheIndex) {
  var _ = this;
  var currentIndex = typeof cacheIndex === 'undefined' ? $(':focus').data('index') : cacheIndex;
  var tryToFind = +currentIndex + direction;

  if (tryToFind > elements.length - 1) {
    tryToFind = 0;
  } else if (tryToFind < 0) {
    tryToFind = elements.length - 1;
  }
  _.indexCache = tryToFind;
  return elements[tryToFind];
};

finna.finnaMovement = (function finnaMovement() {
  var my = {
    init: function init() {
      $('.finna-movement').each(function initKeyboardMovement() {
        new FinnaMovement(this);
      });
    }
  };

  return my;
})();
