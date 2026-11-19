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
 * \file    js/modules/shortcuts.js
 * \ingroup dolical
 * \brief   Calendar page : the menu of a right click on an event (open, change, duplicate, confirm, done, copy the link,
 *          delete) or on an empty place (a new event there, the day), and the keyboard shortcuts, listed by « ? ». The
 *          actions with questions (confirm, delete) are the ones of the card : it opens and does them ; done and to do
 *          again are sent at once.
 */

'use strict';

window.dolical.shortcuts = {};

/**
 * Shortcuts init.
 *
 * @returns {void}
 */
window.dolical.shortcuts.init = function() {
  const S = window.dolical.shortcuts;
  if (!$('.dolical-app').length) {
    return;
  }
  S.$menu = null;

  $(document).on('contextmenu', '.dolical-calendar .dolical-event', S.eventMenu);
  $(document).on('contextmenu', '.dolical-calendar .ra-tg__col, .dolical-calendar .ra-tg__allday-col, .dolical-calendar .ra-month__cell, .dolical-calendar .ra-team__cell', S.cellMenu);
  $(document).on('click', '.dolical-contextmenu [data-do]', S.run);
  // The menu closes on a press beside it, on Escape, when the page scrolls or changes size
  $(document).on('mousedown', function(event) {
    if (S.$menu && !$(event.target).closest('.dolical-contextmenu').length) {
      S.close();
    }
  });
  $(window).on('resize blur', S.close);
  // Any scroll, the grid of the hours too : caught on its way down
  document.addEventListener('scroll', S.close, true);

  $(document).on('keydown', S.keyboard);
  $(document).on('click', '.dolical-toolbar [data-action="shortcuts"]', S.help);
  // Nothing typed in the list of the shortcuts : a click beside it closes it
  $(document).on('click', '#dolical-keys-modal', function(event) {
    if (event.target === this) {
      $(this).removeClass('modal-active');
    }
  });
};

/**
 * An event of the calendar, as it was loaded.
 *
 * @param   {Number} id Event
 * @returns {Object|null}
 */
window.dolical.shortcuts.eventOf = function(id) {
  return (window.dolical.calendar.state.events || []).find(function(event) {
    return event.id === id;
  }) || null;
};

/**
 * Right click on an event : what can be done with it.
 *
 * @param   {Event} event Context menu
 * @returns {void}
 */
window.dolical.shortcuts.eventMenu = function(event) {
  const S  = window.dolical.shortcuts;
  const U  = window.dolical.util;
  const id = parseInt($(this).attr('data-id'), 10);
  const ev = S.eventOf(id);
  if (!id || !ev) {
    return;
  }
  event.preventDefault();
  event.stopPropagation();
  S.target = {id: id, element: this};
  const items = [{action: 'open', icon: 'far fa-eye', label: U.t('menuOpen')}];
  if (ev.editable) {
    items.push({action: 'edit', icon: 'fas fa-pen', label: U.t('menuEdit'), key: 'E'});
  }
  items.push({action: 'clone', icon: 'far fa-clone', label: U.t('menuDuplicate')});
  if (ev.editable && ev.toConfirm) {
    items.push({action: 'confirm', icon: 'fas fa-check', label: U.t('confirmSlotsTitle'), key: 'C'});
  }
  if (ev.editable && ev.percent >= 0) {
    items.push(ev.percent === 100 ? {action: 'reopen', icon: 'fas fa-undo', label: U.t('menuReopen'), key: 'F'} : {action: 'done', icon: 'fas fa-check-circle', label: U.t('menuDone'), key: 'F'});
  }
  items.push({action: 'copylink', icon: 'fas fa-link', label: U.t('menuCopyLink')});
  if (ev.editable) {
    items.push({separator: true});
    items.push({action: 'delete', icon: 'far fa-trash-alt', label: U.t('menuDelete'), key: U.t('keyDelete'), danger: true});
  }
  S.open(event.clientX, event.clientY, items);
};

/**
 * Right click on an empty place : a new event there, the day.
 *
 * @param   {Event} event Context menu
 * @returns {void}
 */
window.dolical.shortcuts.cellMenu = function(event) {
  const S = window.dolical.shortcuts;
  const U = window.dolical.util;
  if ($(event.target).closest('.dolical-event, [data-goto]').length) {
    return;
  }
  event.preventDefault();
  S.target = {cell: this, event: event};
  const items = [];
  if (U.config().canCreate) {
    items.push({action: 'create', icon: 'fas fa-plus', label: U.t('menuNewHere'), key: 'N'});
  }
  if ($(this).attr('data-date')) {
    items.push({action: 'day', icon: 'far fa-calendar', label: U.t('menuShowDay')});
  }
  if (items.length) {
    S.open(event.clientX, event.clientY, items);
  }
};

/**
 * Open the menu at the pointer, kept in the window.
 *
 * @param   {Number}   x     Left, in the window
 * @param   {Number}   y     Top, in the window
 * @param   {Object[]} items [{action, icon, label, key, danger} or {separator: true}]
 * @returns {void}
 */
window.dolical.shortcuts.open = function(x, y, items) {
  const S = window.dolical.shortcuts;
  const U = window.dolical.util;
  S.close();
  let html = '<div class="dolical-contextmenu" role="menu">';
  items.forEach(function(item) {
    if (item.separator) {
      html += '<div class="dolical-contextmenu__separator" role="separator"></div>';
      return;
    }
    html += '<button type="button" role="menuitem" class="dolical-contextmenu__item' + (item.danger ? ' is-danger' : '') + '" data-do="' + U.esc(item.action) + '">'
      + '<i class="' + U.esc(item.icon) + '"></i><span>' + U.esc(item.label) + '</span>'
      + (item.key ? '<kbd>' + U.esc(item.key) + '</kbd>' : '') + '</button>';
  });
  html += '</div>';
  S.$menu = $(html).appendTo('body');
  const width  = S.$menu.outerWidth();
  const height = S.$menu.outerHeight();
  S.$menu.css({
    left: Math.max(8, Math.min(x, window.innerWidth - width - 8)),
    top: Math.max(8, Math.min(y, window.innerHeight - height - 8))
  });
  S.$menu.find('.dolical-contextmenu__item').first().trigger('focus');
};

/**
 * Close the menu.
 *
 * @returns {void}
 */
window.dolical.shortcuts.close = function() {
  const S = window.dolical.shortcuts;
  if (S.$menu) {
    S.$menu.remove();
    S.$menu = null;
  }
};

/**
 * An item of the menu chosen.
 *
 * @returns {void}
 */
window.dolical.shortcuts.run = function() {
  const S      = window.dolical.shortcuts;
  const target = S.target || {};
  const action = $(this).attr('data-do');
  S.close();
  S.act(action, target);
};

/**
 * Do an action on an event or on a place of the calendar.
 *
 * @param   {String} action Action
 * @param   {Object} target {id, element} for an event, {cell, event} for a place
 * @returns {void}
 */
window.dolical.shortcuts.act = function(action, target) {
  const S = window.dolical.shortcuts;
  const U = window.dolical.util;
  const C = window.dolical.calendar;
  const E = window.dolical.eventcard;
  switch (action) {
    case 'open':
      E.open(target.id, target.element);
      break;
    case 'edit':
      window.dolical.eventform.open({id: target.id});
      break;
    case 'clone':
      window.dolical.eventform.open({clone: target.id});
      break;
    case 'copylink':
      U.copy(window.location.origin + window.location.pathname + '?event=' + target.id);
      break;
    // The card does them, with its questions : the dates of a series, the people invited, the slots kept
    case 'confirm':
    case 'delete':
      S.cardAction(target, action);
      break;
    case 'done':
      S.setPercent(target, 100);
      break;
    case 'reopen':
      S.setPercent(target, 0);
      break;
    case 'create':
      E.close();
      C.clickEmpty.call(target.cell, target.event);
      break;
    case 'day':
      C.gotoDay.call($('<span>').attr('data-goto', $(target.cell).attr('data-date'))[0], $.Event('click'));
      break;
    default:
  }
};

/**
 * Done or to do again : nothing to ask, so the event changes without its card opening (an open card is drawn again).
 *
 * @param   {Object} target  {id, element}
 * @param   {Number} percent 100 done, 0 to do
 * @returns {void}
 */
window.dolical.shortcuts.setPercent = function(target, percent) {
  const U = window.dolical.util;
  U.post({action: 'setpercent', id: target.id, percent: percent}).done(function(response) {
    if (response.success) {
      $(document).trigger('dolical:changed', [target.id]);
    } else {
      U.toast(response.error || U.t('error'), 'error');
    }
  }).fail(function(xhr) {
    U.toast(U.xhrError(xhr), 'error');
  });
};

/**
 * Do an action of the card of an event : the card opens, the action runs once it is drawn.
 *
 * @param   {Object} target {id, element}
 * @param   {String} action data-action of the card
 * @returns {void}
 */
window.dolical.shortcuts.cardAction = function(target, action) {
  const E = window.dolical.eventcard;
  E.pendingAction = {action: action};
  if (E.isOpen() && E.currentId === target.id && $('.dolical-popover .dolical-card').length) {
    E.runPending();
    return;
  }
  E.open(target.id, target.element);
};

/**
 * Keyboard shortcuts of the calendar page. Nothing while a field is typed in or a window is open ; the arrows and « T »
 * are read by the calendar itself.
 *
 * @param   {KeyboardEvent} event Key down
 * @returns {void}
 */
window.dolical.shortcuts.keyboard = function(event) {
  const S = window.dolical.shortcuts;
  const E = window.dolical.eventcard;
  if (event.key === 'Escape' && S.$menu) {
    S.close();
    return;
  }
  // The list of the shortcuts : nothing typed in it, Escape or « ? » closes it
  if ((event.key === 'Escape' || event.key === '?') && $('#dolical-keys-modal').hasClass('modal-active')) {
    event.preventDefault();
    $('#dolical-keys-modal').removeClass('modal-active');
    return;
  }
  if (event.ctrlKey || event.metaKey || event.altKey || $(event.target).is('input, textarea, select, [contenteditable], [contenteditable] *') || $('.dolical-modal.modal-active').length) {
    return;
  }
  const key = event.key.length === 1 ? event.key.toLowerCase() : event.key;

  // The card of an event open : its actions first
  if (E.isOpen() && E.currentId) {
    const cardKeys = {e: ['edit'], c: ['confirm'], f: ['setpercent'], Delete: ['delete']};
    if (cardKeys[key]) {
      const $btn = $('.dolical-popover .dolical-card [data-action="' + cardKeys[key][0] + '"]').first();
      if ($btn.length) {
        event.preventDefault();
        $btn.trigger('click');
      }
      return;
    }
  }

  const views = {1: 'day', 2: 'week', 3: 'team', 4: 'month', 5: 'list', j: 'day', s: 'week', m: 'month', l: 'list'};
  if (views[key]) {
    $('.dolical-toolbar [data-view="' + views[key] + '"]').trigger('click');
    return;
  }
  switch (key) {
    case 'n':
      if (window.dolical.util.config().canCreate) {
        event.preventDefault();
        window.dolical.calendar.createFromButton();
      }
      break;
    case 'r':
      $('.dolical-toolbar [data-nav="refresh"]').trigger('click');
      break;
    case '/':
      event.preventDefault();
      $('.dolical-toolbar__search input').trigger('focus');
      break;
    case '?':
      event.preventDefault();
      S.help();
      break;
    default:
  }
};

/**
 * The list of the shortcuts.
 *
 * @returns {void}
 */
window.dolical.shortcuts.help = function() {
  $('#dolical-keys-modal').addClass('modal-active');
};
