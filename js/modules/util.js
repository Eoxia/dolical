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
 * \file    js/modules/util.js
 * \ingroup dolical
 * \brief   Helpers shared by the calendar, the card and the form : dates, escaping, colors, clipboard, toast,
 *          and the closing of the modals.
 */

'use strict';

window.dolical.util = {};

/**
 * Util init : a modal closes from its close buttons only, a click beside it would lose what was typed.
 *
 * @returns {void}
 */
window.dolical.util.init = function() {
  $(document).on('click', '.dolical-modal .modal-close', function() {
    $(this).closest('.dolical-modal').removeClass('modal-active');
  });
};

/**
 * Configuration of the calendar page, read once from the data attribute of the frame.
 *
 * @returns {Object}
 */
window.dolical.util.config = function() {
  if (!window.dolical.util.configCache) {
    const raw = $('.dolical-app, .dolical-sync').first().attr('data-config');
    window.dolical.util.configCache = raw ? JSON.parse(raw) : {i18n: {}, urls: {}, users: [], groups: []};
  }
  return window.dolical.util.configCache;
};

/**
 * Translated text of the calendar, with %COUNT% replaced when given.
 *
 * @param   {String} key   Key of config.i18n
 * @param   {Number} count Value of %COUNT%
 * @returns {String}
 */
window.dolical.util.t = function(key, count) {
  const text = window.dolical.util.config().i18n[key] || key;
  return count === undefined ? text : text.replace('%COUNT%', count);
};

/**
 * Escape a text for HTML.
 *
 * @param   {*} value Text
 * @returns {String}
 */
window.dolical.util.esc = function(value) {
  return String(value === null || value === undefined ? '' : value)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#39;');
};

/**
 * Midnight of the day of a date.
 *
 * @param   {Date} date Date
 * @returns {Date}
 */
window.dolical.util.startOfDay = function(date) {
  return new Date(date.getFullYear(), date.getMonth(), date.getDate());
};

/**
 * Date moved by a number of days, the time of day kept even across a change of summer time.
 *
 * @param   {Date}   date Date
 * @param   {Number} days Days to add, negative to go back
 * @returns {Date}
 */
window.dolical.util.addDays = function(date, days) {
  const result = new Date(date.getTime());
  result.setDate(result.getDate() + days);
  return result;
};

/**
 * Monday of the week of a date.
 *
 * @param   {Date} date Date
 * @returns {Date}
 */
window.dolical.util.startOfWeek = function(date) {
  const day = window.dolical.util.startOfDay(date);
  return window.dolical.util.addDays(day, -((day.getDay() + 6) % 7));
};

/**
 * Whether two dates are the same day.
 *
 * @param   {Date} a First date
 * @param   {Date} b Second date
 * @returns {Boolean}
 */
window.dolical.util.sameDay = function(a, b) {
  return a.getFullYear() === b.getFullYear() && a.getMonth() === b.getMonth() && a.getDate() === b.getDate();
};

/**
 * Key of a day, YYYY-MM-DD in local time.
 *
 * @param   {Date} date Date
 * @returns {String}
 */
window.dolical.util.dayKey = function(date) {
  const pad = function(value) {
    return String(value).padStart(2, '0');
  };
  return date.getFullYear() + '-' + pad(date.getMonth() + 1) + '-' + pad(date.getDate());
};

/**
 * Date of a day key, at midnight local time.
 *
 * @param   {String} key YYYY-MM-DD
 * @returns {Date}
 */
window.dolical.util.fromDayKey = function(key) {
  const parts = key.split('-').map(Number);
  return new Date(parts[0], parts[1] - 1, parts[2]);
};

/**
 * ISO number of the week of a date.
 *
 * @param   {Date} date Date
 * @returns {Number}
 */
window.dolical.util.isoWeek = function(date) {
  const target = new Date(Date.UTC(date.getFullYear(), date.getMonth(), date.getDate()));
  const day    = target.getUTCDay() || 7;
  target.setUTCDate(target.getUTCDate() + 4 - day);
  const yearStart = new Date(Date.UTC(target.getUTCFullYear(), 0, 1));
  return Math.ceil(((target - yearStart) / 86400000 + 1) / 7);
};

/**
 * Format a date with the locale of the user.
 *
 * @param   {Date}   date    Date
 * @param   {Object} options Options of Intl.DateTimeFormat
 * @returns {String}
 */
window.dolical.util.format = function(date, options) {
  try {
    return new Intl.DateTimeFormat(window.dolical.util.config().locale || undefined, options).format(date);
  } catch (error) {
    return date.toLocaleString();
  }
};

/**
 * Hour and minutes of a date, 09:15.
 *
 * @param   {Date} date Date
 * @returns {String}
 */
window.dolical.util.time = function(date) {
  return window.dolical.util.format(date, {hour: '2-digit', minute: '2-digit'});
};

/**
 * Turn the data-color attributes of a fragment into the CSS variable the styles use.
 *
 * @param   {jQuery|HTMLElement} root Fragment, the whole document by default
 * @returns {void}
 */
window.dolical.util.applyColors = function(root) {
  $(root || document).find('[data-color]').addBack('[data-color]').each(function() {
    const color = this.getAttribute('data-color');
    if (color && /^#[0-9a-f]{3,8}$/i.test(color)) {
      this.style.setProperty('--ra-color', color);
    }
  });
};

/**
 * Copy a text to the clipboard and tell it.
 *
 * @param   {String} text Text to copy
 * @returns {void}
 */
window.dolical.util.copy = function(text) {
  const done = function() {
    window.dolical.util.toast(window.dolical.util.t('copied'));
  };
  // Plain http, or a browser refusing the clipboard API : the old way still works
  const fallback = function() {
    const area = document.createElement('textarea');
    area.value = text;
    area.setAttribute('readonly', '');
    area.className = 'dolical-offscreen';
    document.body.appendChild(area);
    area.select();
    try {
      document.execCommand('copy');
      done();
    } finally {
      document.body.removeChild(area);
    }
  };
  if (navigator.clipboard && window.isSecureContext) {
    navigator.clipboard.writeText(text).then(done, fallback);
    return;
  }
  fallback();
};

/**
 * Short message at the bottom of the screen.
 *
 * @param   {String} message Message
 * @param   {String} type    '' or 'error'
 * @returns {void}
 */
window.dolical.util.toast = function(message, type) {
  const $toast = $('.dolical-toast');
  $toast.text(message).toggleClass('is-error', type === 'error').addClass('is-visible');
  clearTimeout(window.dolical.util.toastTimer);
  window.dolical.util.toastTimer = setTimeout(function() {
    $toast.removeClass('is-visible');
  }, type === 'error' ? 5000 : 2200);
};

/**
 * POST to the event endpoint, with the CSRF token.
 *
 * @param   {Object|FormData} data Fields, action included
 * @returns {jqXHR}
 */
window.dolical.util.post = function(data) {
  const config = window.dolical.util.config();
  if (data instanceof FormData) {
    data.append('token', config.token);
    return $.ajax({url: config.urls.event, type: 'POST', data: data, processData: false, contentType: false, dataType: 'json'});
  }
  return $.ajax({url: config.urls.event, type: 'POST', data: $.extend({token: config.token}, data), dataType: 'json'});
};

/**
 * Error message of a failed request.
 *
 * @param   {jqXHR} xhr Request
 * @returns {String}
 */
window.dolical.util.xhrError = function(xhr) {
  if (xhr && xhr.responseJSON && xhr.responseJSON.error) {
    return xhr.responseJSON.error;
  }
  return window.dolical.util.t('error');
};

/**
 * Read a value saved for this browser, nothing when the storage is not available.
 *
 * @param   {String} key Key
 * @returns {*}
 */
window.dolical.util.load = function(key) {
  try {
    const value = window.localStorage.getItem('dolical.' + key);
    return value ? JSON.parse(value) : null;
  } catch (error) {
    return null;
  }
};

/**
 * Save a value for this browser, silently skipped when the storage is not available.
 *
 * @param   {String} key   Key
 * @param   {*}      value Value
 * @returns {void}
 */
window.dolical.util.save = function(key, value) {
  try {
    window.localStorage.setItem('dolical.' + key, JSON.stringify(value));
  } catch (error) {
    // Private window or blocked storage : the calendar works the same, only forgets
  }
};

/**
 * Ask which dates of a series a change or a deletion is for.
 *
 * @param   {String}   kind     'edit' or 'delete'
 * @param   {Boolean}  allowOne Whether « this event » is offered : a new rule is never for one date only
 * @param   {Function} done     Called with 'one', 'following' or 'all' once confirmed
 * @returns {void}
 */
window.dolical.util.chooseScope = function(kind, allowOne, done) {
  const $modal = $('#dolical-scope-modal');
  const $title = $modal.find('.dolical-scope__title');
  $title.text($title.attr('data-' + kind) || '');
  $modal.find('.dolical-scope__choice[data-scope="one"]').toggle(allowOne);
  $modal.find('input[name="dolical_scope"][value="' + (allowOne ? 'one' : 'following') + '"]').prop('checked', true);
  $modal.find('.dolical-scope__confirm').off('click').on('click', function() {
    $modal.removeClass('modal-active');
    done($modal.find('input[name="dolical_scope"]:checked').val());
  });
  $modal.addClass('modal-active');
};
