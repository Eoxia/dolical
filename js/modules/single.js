/* Copyright (C) 2026 EVARISK <technique@evarisk.com>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

/**
 * \file    js/modules/single.js
 * \ingroup dolical
 * \brief   Page of one event (view/dolical_event.php) : its form or its card opened at once, without the calendar.
 *
 * Embedded in the window of another application (iframe of the same site), the page tells it what happens with
 * postMessage, the protocol of the windows of a mail application : {source: 'inbox-panel', action} where action is
 * 'title' (title of the window), 'dirty' (work not saved : closing asks first), 'done' (saved : the window closes and
 * shows the message) or 'close'. Alone, the page comes back to the calendar.
 */

'use strict';

/**
 * Page of one event.
 *
 * @type {Object}
 */
window.dolical.single = {};

/**
 * Last card shown : a page opened on a card comes back to it after its form (edit, duplicate).
 *
 * @type {Number}
 */
window.dolical.single.lastCard = 0;

/**
 * Whether the parent was told of work not saved.
 *
 * @type {Boolean}
 */
window.dolical.single.dirty = false;

/**
 * Init : only on the page of one event.
 *
 * @returns {void}
 */
window.dolical.single.init = function() {
  const S      = window.dolical.single;
  const config = window.dolical.util.config();
  if (!config.single) {
    return;
  }
  S.event();
  // Embedded, the window has its own close : the link back to the calendar is for the page alone
  $('body').toggleClass('dolical-embedded', S.embedded());

  if (config.single.missing) {
    S.tell('title', {title: document.title});
    return;
  }
  if (config.single.mode === 'card') {
    window.dolical.eventcard.open(config.single.id, null);
  } else if (config.single.mode === 'edit') {
    window.dolical.eventform.open({id: config.single.id});
  } else {
    window.dolical.eventform.open(S.createParams());
  }
};

/**
 * Events of the page.
 *
 * @returns {void}
 */
window.dolical.single.event = function() {
  const S = window.dolical.single;
  const E = window.dolical.eventcard;

  $(document).on('click', '.dolical-single__leave', S.leave);

  // The form or the card is drawn : its title becomes the one of the window
  $(document).ajaxComplete(function() {
    if (E.isOpen() && E.currentId) {
      S.lastCard = E.currentId;
    }
    const $title = $('#dolical-event-modal.modal-active .modal-title').first();
    const $card  = $('.dolical-popover.is-open .dolical-card__title').first();
    const text   = ($title.length ? $title.text() : $card.text()).trim();
    if (text !== '' && text !== S.title) {
      S.title = text;
      S.tell('title', {title: text});
    }
  });

  // Work not saved : the window asks before closing
  $(document).on('input change', '#dolical-event-modal .dolical-form', function() {
    S.setDirty(true);
  });

  // Cancel of the form : a page opened on a card comes back to it, else the page is left
  $(document).on('click', '#dolical-event-modal .modal-close', function() {
    S.setDirty(false);
    if (S.onCard()) {
      E.open(S.lastCard, null);
      return;
    }
    S.leave();
  });

  // Close of the card : the page is left
  $(document).on('click', '.dolical-card__close', S.leave);
  $(document).on('keydown', function(event) {
    // The card closes itself on Escape (eventcard.js) ; a form open keeps what was typed
    if (event.key === 'Escape' && !$('.dolical-modal.modal-active').length) {
      S.leave();
    }
  });

  $(document).on('dolical:changed', S.changed);
};

/**
 * Something was saved or deleted.
 *
 * @param   {Event}  event   Event of jQuery
 * @param   {Number} id      Event saved, nothing after a deletion
 * @param   {String} message Message of the form after a save
 * @returns {void}
 */
window.dolical.single.changed = function(event, id, message) {
  const S = window.dolical.single;
  const E = window.dolical.eventcard;
  const U = window.dolical.util;

  if (typeof message === 'string') {
    // Saved from the form
    S.setDirty(false);
    if (S.onCard()) {
      E.open(id, null);
      return;
    }
    S.done(message, id);
    return;
  }
  if (!id && !E.isOpen() && U.config().single.mode === 'card') {
    // The card was deleted
    S.done(U.t('deleted'), 0);
  }
};

/**
 * Whether the page was opened on a card that its form (edit, duplicate) comes back to.
 *
 * @returns {Boolean}
 */
window.dolical.single.onCard = function() {
  return window.dolical.util.config().single.mode === 'card' && window.dolical.single.lastCard > 0;
};

/**
 * Values of a new event given in the address of the page, as the calendar takes them.
 *
 * @returns {Object}
 */
window.dolical.single.createParams = function() {
  const query  = new URLSearchParams(window.location.search);
  const params = {};
  ['socid', 'projectid', 'contactids', 'elements', 'start', 'end', 'allday', 'user', 'clone'].forEach(function(name) {
    const value = query.get(name);
    if (value !== null && value !== '') {
      params[name] = value;
    }
  });
  return params;
};

/**
 * Whether the page is embedded in a window of another page of this site.
 *
 * @returns {Boolean}
 */
window.dolical.single.embedded = function() {
  try {
    return window.parent !== window && window.parent.location.origin === window.location.origin;
  } catch (error) {
    return false;
  }
};

/**
 * Tell the window that embeds the page.
 *
 * @param   {String} action title, dirty, done or close
 * @param   {Object} data   Values of the message
 * @returns {void}
 */
window.dolical.single.tell = function(action, data) {
  if (!window.dolical.single.embedded()) {
    return;
  }
  window.parent.postMessage($.extend({source: 'inbox-panel', action: action}, data || {}), window.location.origin);
};

/**
 * Work not saved, or no more.
 *
 * @param   {Boolean} on Not saved
 * @returns {void}
 */
window.dolical.single.setDirty = function(on) {
  const S = window.dolical.single;
  if (S.dirty === on) {
    return;
  }
  S.dirty = on;
  S.tell('dirty', {on: on, text: on ? window.dolical.util.t('unsavedChanges') : ''});
};

/**
 * Saved or deleted : the window closes with the message, or the calendar shows the event.
 *
 * @param   {String} message Message
 * @param   {Number} id      Event, 0 when deleted
 * @returns {void}
 */
window.dolical.single.done = function(message, id) {
  const S = window.dolical.single;
  if (S.embedded()) {
    S.tell('done', {message: message});
    return;
  }
  S.goCalendar(id);
};

/**
 * Leave the page without saving : the window closes, or back to the calendar.
 *
 * @returns {void}
 */
window.dolical.single.leave = function() {
  const S = window.dolical.single;
  if (S.embedded()) {
    S.tell('close');
    return;
  }
  S.goCalendar(window.dolical.util.config().single.id);
};

/**
 * The calendar, on an event when there is one.
 *
 * @param   {Number} id Event
 * @returns {void}
 */
window.dolical.single.goCalendar = function(id) {
  const url = window.dolical.util.config().single.calendar;
  window.location.href = url + (id ? '?event=' + encodeURIComponent(id) : '');
};
