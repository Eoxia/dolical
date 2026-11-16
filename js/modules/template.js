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
 * \file    js/modules/template.js
 * \ingroup dolical
 * \brief   Weekly template of the online booking, as the one of the appointments of Nextcloud, drawn as the week of
 *          the calendar : a slot drawn by sliding in a day, moved or stretched, changed or removed from its menu (click
 *          or right click), and the menu of a day to add a series of slots, copy the day to others or empty it. The
 *          slots go to the hidden field of the form, saved with it.
 */

'use strict';

window.dolical.template = {};

/**
 * Pixels for an hour : the height of an hour of the calendar.
 *
 * @type {Number}
 */
window.dolical.template.HOUR_HEIGHT = 48;

/**
 * Template init.
 *
 * @returns {void}
 */
window.dolical.template.init = function() {
  const T     = window.dolical.template;
  const $root = $('.dolical-template');
  if (!$root.length) {
    return;
  }
  T.$root  = $root;
  T.config = JSON.parse($root.attr('data-config'));
  T.state  = {};
  for (let day = 1; day <= 7; day++) {
    T.state[day] = (T.config.slots[day] || []).map(function(slot) {
      return [parseInt(slot[0], 10), parseInt(slot[1], 10)];
    });
  }
  T.dirty = false;
  T.drag  = null;
  T.$pop  = null;
  T.build();
  T.render();

  if (!T.config.canWrite) {
    return;
  }
  $root.on('mousedown', '.dolical-template__col', T.colDown);
  $root.on('mousedown', '.dolical-template__slot', T.slotDown);
  $root.on('contextmenu', '.dolical-template__slot', T.slotMenu);
  $root.on('click', '.dolical-template__daymenu', T.dayMenu);
  $(document).on('mousemove', T.move);
  $(document).on('mouseup', T.up);
  $(document).on('keydown', function(event) {
    if (event.key === 'Escape') {
      T.closePop();
    }
  });
  $(document).on('mousedown', function(event) {
    if (T.$pop && !$(event.target).closest('.dolical-template__pop').length) {
      T.closePop();
    }
  });
  $('.dolical-template-form').on('submit', function() {
    T.sync();
    T.dirty = false;
  });
  $('.dolical-template-cancel').on('click', function() {
    T.dirty = false;
  });
  // Changes not saved : the browser asks before leaving
  $(window).on('beforeunload', function(event) {
    if (T.dirty) {
      event.preventDefault();
      return T.config.i18n.unsaved;
    }
  });
};

/**
 * Minutes written as a time : 630 → « 10:30 ».
 *
 * @param   {Number} minutes Minutes from midnight
 * @returns {String}
 */
window.dolical.template.hhmm = function(minutes) {
  const hours = Math.floor(minutes / 60);
  const rest  = minutes % 60;
  return (hours < 10 ? '0' : '') + hours + ':' + (rest < 10 ? '0' : '') + rest;
};

/**
 * A time read back : « 10:30 » → 630.
 *
 * @param   {String} text Time
 * @returns {Number|null}
 */
window.dolical.template.minutes = function(text) {
  const match = /^(\d{1,2}):(\d{2})$/.exec(String(text || '').trim());
  if (!match || parseInt(match[1], 10) > 24 || parseInt(match[2], 10) > 59) {
    return null;
  }
  return parseInt(match[1], 10) * 60 + parseInt(match[2], 10);
};

/**
 * Pixels of a number of minutes.
 *
 * @param   {Number} minutes Minutes
 * @returns {Number}
 */
window.dolical.template.px = function(minutes) {
  return minutes * window.dolical.template.HOUR_HEIGHT / 60;
};

/**
 * Place a slot, or the slot being drawn, in its day.
 *
 * @param   {jQuery} $el    Slot
 * @param   {Number} start  Start, in minutes
 * @param   {Number} length Length, in minutes
 * @returns {void}
 */
window.dolical.template.place = function($el, start, length) {
  const T = window.dolical.template;
  $el[0].style.setProperty('--ra-top', T.px(start) + 'px');
  $el[0].style.setProperty('--ra-height', Math.max(T.px(length), 12) + 'px');
  $el.toggleClass('is-short', T.px(length) < 40);
  $el.find('.ra-block__time, .ra-select__time').text(T.hhmm(start) + ' – ' + T.hhmm(start + length));
};

/**
 * Draw the week as the one of the calendar : the days with their menu, the hours, the hours out of the day of work
 * greyed, a column for each day.
 *
 * @returns {void}
 */
window.dolical.template.build = function() {
  const T    = window.dolical.template;
  const esc  = window.dolical.util.esc;
  const H    = T.HOUR_HEIGHT;
  let head   = '<div class="ra-tg__head"><div class="ra-tg__gutter"></div>';
  let body   = '<div class="ra-tg__hours">';
  for (let hour = 0; hour < 24; hour++) {
    body += '<span class="ra-tg__hour">' + (hour ? T.hhmm(hour * 60) : '') + '</span>';
  }
  body += '</div>';

  for (let day = 1; day <= 7; day++) {
    head += '<div class="ra-tg__colhead"><div class="dolical-template__dayhead" data-day="' + day + '">';
    head += '<span class="ra-dayhead__dow">' + esc(T.config.days[day]) + '</span>';
    head += '<span class="dolical-template__daycount"></span>';
    if (T.config.canWrite) {
      head += '<button type="button" class="dolical-icon-btn dolical-template__daymenu" data-day="' + day + '" title="' + esc(T.config.i18n.menu) + '" aria-label="' + esc(T.config.i18n.menu + ' : ' + T.config.days[day]) + '"><i class="fas fa-ellipsis-h"></i></button>';
    }
    head += '</div></div>';

    body += '<div class="ra-tg__col dolical-template__col' + (day >= 6 ? ' is-weekend' : '') + '" data-day="' + day + '">';
    body += '<div class="ra-tg__off" style="--ra-top:0px;--ra-height:' + (T.config.dayStart * H) + 'px"></div>';
    body += '<div class="ra-tg__off" style="--ra-top:' + (T.config.dayEnd * H) + 'px;--ra-height:' + ((24 - T.config.dayEnd) * H) + 'px"></div>';
    body += '</div>';
  }
  head += '</div>';

  T.$root.html('<div class="ra-tg ra-tg--week" style="--ra-cols:7"><div class="ra-tg__scroll">'
    + '<div class="ra-tg__sticky">' + head + '</div><div class="ra-tg__body">' + body + '</div></div></div>');

  // Open on the first slot of the week, or on the day of work
  let first = T.config.dayStart * 60;
  for (let day = 1; day <= 7; day++) {
    if (T.state[day].length) {
      first = Math.min(first, T.state[day][0][0]);
    }
  }
  // A few pixels above the hour : its label sits on the line and must not hide under the sticky heads
  T.$root.find('.ra-tg__scroll').scrollTop(Math.max(0, T.px(Math.floor(first / 60) * 60) - 12));
};

/**
 * Draw the slots of every day, write them to the field of the form.
 *
 * @returns {void}
 */
window.dolical.template.render = function() {
  const T    = window.dolical.template;
  const esc  = window.dolical.util.esc;
  const i18n = T.config.i18n;
  let total  = 0;

  for (let day = 1; day <= 7; day++) {
    const $col = T.$root.find('.dolical-template__col[data-day="' + day + '"]');
    $col.find('.dolical-template__slot').remove();
    for (let index = 0; index < T.state[day].length; index++) {
      const slot  = T.state[day][index];
      const $slot = $('<div class="ra-block dolical-template__slot' + (T.config.canWrite ? ' is-editable' : '') + '">'
        + '<div class="ra-block__head"><i class="far fa-calendar-check"></i><span class="ra-block__time"></span></div>'
        + '<div class="ra-block__label">' + esc(T.config.label) + '</div>'
        + (T.config.canWrite ? '<div class="ra-block__resize"></div>' : '') + '</div>');
      $slot.attr({'data-day': day, 'data-index': index});
      T.place($slot, slot[0], slot[1]);
      $col.append($slot);
    }
    const count = T.state[day].length;
    total      += count;
    T.$root.find('.dolical-template__dayhead[data-day="' + day + '"] .dolical-template__daycount')
      .text(count ? i18n.slots.replace('%s', count) : i18n.none)
      .toggleClass('is-empty', !count);
  }
  $('.dolical-template__count').text(i18n.slots.replace('%s', total));
  T.sync();
};

/**
 * Write the slots to the hidden field of the form.
 *
 * @returns {void}
 */
window.dolical.template.sync = function() {
  $('.dolical-template-form input[name="DOLICAL_BOOKING_SLOTS"]').val(JSON.stringify(window.dolical.template.state));
};

/**
 * Something changed : drawn again, and kept from being lost.
 *
 * @returns {void}
 */
window.dolical.template.changed = function() {
  const T = window.dolical.template;
  for (let day = 1; day <= 7; day++) {
    T.state[day].sort(function(a, b) {
      return a[0] - b[0];
    });
  }
  T.dirty = true;
  T.render();
};

/**
 * Whether a slot would overlap another one of its day.
 *
 * @param   {Number} day    ISO day
 * @param   {Number} start  Start, in minutes
 * @param   {Number} length Length, in minutes
 * @param   {Number} except Index of the slot itself, -1 for a new one
 * @returns {Boolean}
 */
window.dolical.template.overlaps = function(day, start, length, except) {
  return window.dolical.template.state[day].some(function(slot, index) {
    return index !== except && slot[0] < start + length && start < slot[0] + slot[1];
  });
};

/**
 * Add a slot to a day, unless it overlaps another one.
 *
 * @param   {Number} day    ISO day
 * @param   {Number} start  Start, in minutes
 * @param   {Number} length Length, in minutes
 * @returns {Boolean}       Whether it was added
 */
window.dolical.template.add = function(day, start, length) {
  const T = window.dolical.template;
  length  = Math.min(length, 1440 - start);
  if (start < 0 || length < 5 || T.overlaps(day, start, length, -1)) {
    return false;
  }
  T.state[day].push([start, length]);
  return true;
};

/**
 * Minute of the day under the pointer, in a column, to the quarter of an hour.
 *
 * @param   {jQuery} $col  Column of a day
 * @param   {Number} pageY Pointer
 * @returns {Number}
 */
window.dolical.template.minuteAt = function($col, pageY) {
  const T      = window.dolical.template;
  const minute = Math.floor((pageY - $col.offset().top) * 60 / T.HOUR_HEIGHT / 15) * 15;
  return Math.max(0, Math.min(1425, minute));
};

/**
 * Column of the day under the pointer.
 *
 * @param   {Number} pageX Pointer
 * @returns {Number}       ISO day, 0 out of the week
 */
window.dolical.template.dayAt = function(pageX) {
  let found = 0;
  window.dolical.template.$root.find('.dolical-template__col').each(function() {
    const left = $(this).offset().left;
    if (pageX >= left && pageX < left + $(this).outerWidth()) {
      found = parseInt($(this).attr('data-day'), 10);
    }
  });
  return found;
};

/**
 * Press in a day, out of a slot : a slot is drawn while sliding, or one of the default length on a click.
 *
 * @param   {Event} event Mouse down
 * @returns {void}
 */
window.dolical.template.colDown = function(event) {
  const T = window.dolical.template;
  if (event.button !== 0 || $(event.target).closest('.dolical-template__slot').length) {
    return;
  }
  if (T.$pop) {
    T.closePop();
    return;
  }
  const $col   = $(this);
  const minute = T.minuteAt($col, event.pageY);
  T.drag       = {mode: 'create', day: parseInt($col.attr('data-day'), 10), $col: $col, anchor: minute, current: minute, startX: event.pageX, startY: event.pageY, moved: false};
  T.drag.$ghost = $('<div class="ra-select"><span class="ra-select__time"></span></div>').appendTo($col);
  $('body').addClass('dolical-is-selecting');
  T.ghost();
  event.preventDefault();
};

/**
 * Draw the slot being created, as the selection of the calendar.
 *
 * @returns {void}
 */
window.dolical.template.ghost = function() {
  const T      = window.dolical.template;
  const d      = T.drag;
  const start  = d.moved ? Math.min(d.anchor, d.current) : d.anchor;
  const length = d.moved ? Math.abs(d.current - d.anchor) + 15 : Math.min(T.config.length, 1440 - d.anchor);
  T.place(d.$ghost, start, length);
};

/**
 * Press on a slot : moved or stretched while sliding, its menu on a click.
 *
 * @param   {Event} event Mouse down
 * @returns {void}
 */
window.dolical.template.slotDown = function(event) {
  const T = window.dolical.template;
  if (event.button !== 0) {
    return;
  }
  if (T.$pop) {
    T.closePop();
  }
  const $slot = $(this);
  const day   = parseInt($slot.attr('data-day'), 10);
  const index = parseInt($slot.attr('data-index'), 10);
  T.drag = {
    mode: $(event.target).closest('.ra-block__resize').length ? 'resize' : 'move',
    day: day,
    index: index,
    $el: $slot,
    orig: T.state[day][index].slice(),
    target: null,
    startX: event.pageX,
    startY: event.pageY,
    moved: false
  };
  event.preventDefault();
  event.stopPropagation();
};

/**
 * Pointer moving : the slot being drawn, moved or stretched follows it.
 *
 * @param   {Event} event Mouse move
 * @returns {void}
 */
window.dolical.template.move = function(event) {
  const T = window.dolical.template;
  const d = T.drag;
  if (!d) {
    return;
  }
  if (!d.moved && (Math.abs(event.pageY - d.startY) > 4 || Math.abs(event.pageX - d.startX) > 4)) {
    d.moved = true;
    if (d.$el) {
      d.$el.addClass('is-dragging');
      $('body').addClass('dolical-is-dragging');
    }
  }
  if (!d.moved) {
    return;
  }
  if (d.mode === 'create') {
    d.current = T.minuteAt(d.$col, event.pageY);
    T.ghost();
    return;
  }
  const delta = Math.round((event.pageY - d.startY) * 60 / T.HOUR_HEIGHT / 15) * 15;
  if (d.mode === 'move') {
    const day   = T.dayAt(event.pageX) || d.day;
    const start = Math.max(0, Math.min(1440 - d.orig[1], d.orig[0] + delta));
    d.target    = {day: day, start: start, length: d.orig[1]};
    d.$el.appendTo(T.$root.find('.dolical-template__col[data-day="' + day + '"]'));
    T.place(d.$el, start, d.orig[1]);
  } else {
    const length = Math.max(15, Math.min(480, 1440 - d.orig[0], d.orig[1] + delta));
    d.target     = {day: d.day, start: d.orig[0], length: length};
    T.place(d.$el, d.orig[0], length);
  }
};

/**
 * Pointer released : the slot is created, moved or stretched, unless it would overlap another one ; a click on a slot
 * opens its menu.
 *
 * @param   {Event} event Mouse up
 * @returns {void}
 */
window.dolical.template.up = function(event) {
  const T = window.dolical.template;
  const d = T.drag;
  if (!d) {
    return;
  }
  T.drag = null;
  $('body').removeClass('dolical-is-selecting dolical-is-dragging');

  if (d.mode === 'create') {
    d.$ghost.remove();
    const start  = d.moved ? Math.min(d.anchor, d.current) : d.anchor;
    const length = d.moved ? Math.abs(d.current - d.anchor) + 15 : T.config.length;
    if (T.add(d.day, start, length)) {
      T.changed();
    } else {
      window.dolical.util.toast(T.config.i18n.overlap, 'error');
    }
    return;
  }

  d.$el.removeClass('is-dragging');
  if (!d.moved || !d.target) {
    T.render();
    if (!d.moved && d.mode === 'move') {
      T.editSlot(d.day, d.index, event);
    }
    return;
  }
  const slots = T.state[d.day];
  slots.splice(d.index, 1);
  if (T.overlaps(d.target.day, d.target.start, d.target.length, -1)) {
    slots.splice(d.index, 0, d.orig);
    window.dolical.util.toast(T.config.i18n.overlap, 'error');
    T.render();
    return;
  }
  T.state[d.target.day].push([d.target.start, d.target.length]);
  T.changed();
};

/**
 * Right click on a slot : its menu, as in Nextcloud.
 *
 * @param   {Event} event Context menu
 * @returns {void}
 */
window.dolical.template.slotMenu = function(event) {
  event.preventDefault();
  const $slot = $(this);
  window.dolical.template.editSlot(parseInt($slot.attr('data-day'), 10), parseInt($slot.attr('data-index'), 10), event);
};

/**
 * Field of a small menu : its name above it.
 *
 * @param   {String} label Name
 * @param   {String} input Field, HTML
 * @returns {String}
 */
window.dolical.template.field = function(label, input) {
  return '<label class="dolical-template__popfield"><span>' + window.dolical.util.esc(label) + '</span>' + input + '</label>';
};

/**
 * Buttons at the bottom of a small menu.
 *
 * @param   {String} left Button on the left, HTML
 * @returns {String}
 */
window.dolical.template.actions = function(left) {
  const esc  = window.dolical.util.esc;
  const i18n = window.dolical.template.config.i18n;
  return '<div class="dolical-template__popactions">' + left
    + '<button type="button" class="dolical-btn dolical-btn--primary dolical-template__ok">' + esc(i18n.ok) + '</button></div>';
};

/**
 * Menu of a slot : its start and its end, remove it.
 *
 * @param   {Number} day   ISO day
 * @param   {Number} index Index of the slot in its day
 * @param   {Event}  event Event that opened it, for its position
 * @returns {void}
 */
window.dolical.template.editSlot = function(day, index, event) {
  const T    = window.dolical.template;
  const esc  = window.dolical.util.esc;
  const i18n = T.config.i18n;
  const slot = T.state[day][index];
  if (!slot) {
    return;
  }
  const $pop = T.openPop(event.clientX, event.clientY,
    '<div class="dolical-template__poptitle">' + esc(i18n.edit) + ' – ' + esc(T.config.days[day]) + '</div>'
    + '<div class="dolical-template__poprow">'
    + T.field(i18n.start, '<input type="time" name="start" step="300" value="' + T.hhmm(slot[0]) + '">')
    + T.field(i18n.end, '<input type="time" name="end" step="300" value="' + T.hhmm(slot[0] + slot[1]) + '">')
    + '</div>'
    + T.actions('<button type="button" class="dolical-btn dolical-template__delete"><i class="far fa-trash-alt"></i>' + esc(i18n.delete) + '</button>'));
  $pop.find('.dolical-template__delete').on('click', function() {
    T.state[day].splice(index, 1);
    T.closePop();
    T.changed();
  });
  $pop.find('.dolical-template__ok').on('click', function() {
    const start = T.minutes($pop.find('input[name="start"]').val());
    const end   = T.minutes($pop.find('input[name="end"]').val());
    if (start === null || end === null || end - start < 5 || end - start > 480 || end > 1440) {
      window.dolical.util.toast(i18n.badTime, 'error');
      return;
    }
    if (T.overlaps(day, start, end - start, index)) {
      window.dolical.util.toast(i18n.overlap, 'error');
      return;
    }
    T.state[day][index] = [start, end - start];
    T.closePop();
    T.changed();
  });
};

/**
 * Menu of a day : a series of slots, a copy to other days, the day emptied.
 *
 * @param   {Event} event Click on « … »
 * @returns {void}
 */
window.dolical.template.dayMenu = function(event) {
  const T    = window.dolical.template;
  const esc  = window.dolical.util.esc;
  const i18n = T.config.i18n;
  const day  = parseInt($(this).attr('data-day'), 10);
  event.stopPropagation();
  const rect = this.getBoundingClientRect();
  const at   = {x: rect.left, y: rect.bottom};
  const $pop = T.openPop(at.x, at.y,
    '<button type="button" class="dolical-template__menuitem" data-do="series"><i class="fas fa-plus"></i>' + esc(i18n.addSeries) + '</button>'
    + '<button type="button" class="dolical-template__menuitem" data-do="copy"><i class="far fa-copy"></i>' + esc(i18n.copyTo) + '</button>'
    + '<button type="button" class="dolical-template__menuitem is-danger" data-do="clear"><i class="fas fa-eraser"></i>' + esc(i18n.clearDay) + '</button>', true);
  $pop.find('[data-do="series"]').on('click', function() {
    T.seriesForm(day, at);
  });
  $pop.find('[data-do="copy"]').on('click', function() {
    T.copyForm(day, at);
  });
  $pop.find('[data-do="clear"]').on('click', function() {
    T.closePop();
    if (T.state[day].length && window.confirm(i18n.clearAsk.replace('%s', T.config.days[day]))) {
      T.state[day] = [];
      T.changed();
    }
  });
};

/**
 * Form of a series of slots in a day : the start of the first one, their length, how many, one after the other.
 *
 * @param   {Number} day ISO day
 * @param   {Object} at  Where the menu of the day is, in the window
 * @returns {void}
 */
window.dolical.template.seriesForm = function(day, at) {
  const T     = window.dolical.template;
  const esc   = window.dolical.util.esc;
  const i18n  = T.config.i18n;
  const slots = T.state[day];
  // After the last slot of the day, else at nine
  const first = slots.length ? slots[slots.length - 1][0] + slots[slots.length - 1][1] : 540;
  const $pop  = T.openPop(at.x, at.y,
    '<div class="dolical-template__poptitle">' + esc(i18n.addSeries) + ' – ' + esc(T.config.days[day]) + '</div>'
    + T.field(i18n.start, '<input type="time" name="start" step="300" value="' + T.hhmm(Math.min(first, 1380)) + '">')
    + '<div class="dolical-template__poprow">'
    + T.field(i18n.length, '<input type="number" name="length" min="5" max="480" step="5" value="' + T.config.length + '">')
    + T.field(i18n.count, '<input type="number" name="count" min="1" max="48" value="1">')
    + '</div>'
    + T.actions('<button type="button" class="dolical-btn dolical-template__close">' + esc(i18n.cancel) + '</button>'));
  $pop.find('.dolical-template__close').on('click', T.closePop);
  $pop.find('.dolical-template__ok').on('click', function() {
    const start  = T.minutes($pop.find('input[name="start"]').val());
    const length = parseInt($pop.find('input[name="length"]').val(), 10);
    const count  = Math.min(48, Math.max(1, parseInt($pop.find('input[name="count"]').val(), 10) || 1));
    if (start === null || !(length >= 5 && length <= 480)) {
      window.dolical.util.toast(i18n.badTime, 'error');
      return;
    }
    let skipped = 0;
    for (let i = 0; i < count; i++) {
      const slotStart = start + i * length;
      if (slotStart + length > 1440 || !T.add(day, slotStart, length)) {
        skipped++;
      }
    }
    T.closePop();
    T.changed();
    if (skipped) {
      window.dolical.util.toast(i18n.skipped.replace('%s', skipped), 'error');
    }
  });
};

/**
 * Form of the copy of a day : the days that take its slots, theirs replaced.
 *
 * @param   {Number} day ISO day copied
 * @param   {Object} at  Where the menu of the day is, in the window
 * @returns {void}
 */
window.dolical.template.copyForm = function(day, at) {
  const T    = window.dolical.template;
  const esc  = window.dolical.util.esc;
  const i18n = T.config.i18n;
  let boxes  = '';
  for (let other = 1; other <= 7; other++) {
    if (other !== day) {
      boxes += '<label class="dolical-check"><input type="checkbox" value="' + other + '"><span>' + esc(T.config.days[other]) + '</span></label>';
    }
  }
  const $pop = T.openPop(at.x, at.y,
    '<div class="dolical-template__poptitle">' + esc(i18n.copyTo) + '</div>'
    + '<div class="dolical-template__days">' + boxes + '</div>'
    + T.actions('<button type="button" class="dolical-btn dolical-template__close">' + esc(i18n.cancel) + '</button>'));
  $pop.find('.dolical-template__close').on('click', T.closePop);
  $pop.find('.dolical-template__ok').on('click', function() {
    $pop.find('input:checked').each(function() {
      T.state[parseInt($(this).val(), 10)] = T.state[day].map(function(slot) {
        return slot.slice();
      });
    });
    T.closePop();
    T.changed();
  });
};

/**
 * Open a small menu at a place of the window, kept in it.
 *
 * @param   {Number}  x      Left, in the window
 * @param   {Number}  y      Top, in the window
 * @param   {String}  html   Content
 * @param   {Boolean} isMenu Whether it is a list of actions rather than a form
 * @returns {jQuery}
 */
window.dolical.template.openPop = function(x, y, html, isMenu) {
  const T = window.dolical.template;
  T.closePop();
  T.$pop = $('<div class="dolical-template__pop' + (isMenu ? ' is-menu' : '') + '"></div>').html(html).appendTo('body');
  const width  = T.$pop.outerWidth();
  const height = T.$pop.outerHeight();
  T.$pop.css({
    left: Math.max(8, Math.min(x, window.innerWidth - width - 8)),
    top: Math.max(8, Math.min(y + 4, window.innerHeight - height - 8))
  });
  T.$pop.find('input').first().trigger('focus');
  // Enter validates, as the button would
  T.$pop.on('keydown', 'input', function(event) {
    if (event.key === 'Enter') {
      event.preventDefault();
      T.$pop.find('.dolical-template__ok').trigger('click');
    }
  });
  return T.$pop;
};

/**
 * Close the open menu.
 *
 * @returns {void}
 */
window.dolical.template.closePop = function() {
  const T = window.dolical.template;
  if (T.$pop) {
    T.$pop.remove();
    T.$pop = null;
  }
};
