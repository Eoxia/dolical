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
 * \file    js/modules/setup.js
 * \ingroup dolical
 * \brief   Setup of the online booking : the section in sight lit in the list on the side, the settings that depend on
 *          a switch shown with it, the addresses of the pages copied in one click.
 */

'use strict';

window.dolical.setup = {};

/**
 * Setup init.
 *
 * @returns {void}
 */
window.dolical.setup.init = function() {
  const S     = window.dolical.setup;
  const $form = $('.dolical-setup');
  if (!$form.length) {
    return;
  }
  S.$form = $form;

  $form.on('change', '.dolical-setting__switch input', S.depends);
  S.depends();

  // Closing CalDAV while phones synchronize cuts them all at once : asked first, the switch back on when refused
  $form.on('submit', function(event) {
    const message = $form.attr('data-caldav-confirm');
    const caldav  = $form.find('input[name="DOLICAL_CALDAV"]').get(0);
    if (message && caldav && caldav.defaultChecked && !caldav.checked && !window.confirm(message)) {
      event.preventDefault();
      caldav.checked = true;
      S.depends();
    }
  });

  $form.on('click', '.dolical-copy', function() {
    window.dolical.util.copy($(this).attr('data-copy'));
  });

  // A section chosen in the list stays lit : a short one at the bottom of the page cannot go up to the top of the
  // window, the spy alone would light the last one
  S.chosen = '';
  $form.on('click', '.dolical-setup__nav a', function(event) {
    const anchor = $(this).attr('data-section');
    const card   = document.getElementById('dolical-setup-' + anchor);
    if (!card) {
      return;
    }
    event.preventDefault();
    card.scrollIntoView({behavior: 'smooth', block: 'start'});
    if (window.history.replaceState) {
      window.history.replaceState(null, '', '#dolical-setup-' + anchor);
    }
    S.chosen = anchor;
    S.light(anchor);
  });
  // The user scrolling himself : the spy takes over again
  $(window).on('wheel touchmove', function() {
    S.chosen = '';
  });
  $(document).on('keydown', function(event) {
    if (['ArrowUp', 'ArrowDown', 'PageUp', 'PageDown', 'Home', 'End', ' '].indexOf(event.key) !== -1) {
      S.chosen = '';
    }
  });

  $(window).on('scroll resize', S.spy);
  S.spy();
};

/**
 * Light a section in the list on the side.
 *
 * @param   {String} anchor Section
 * @returns {void}
 */
window.dolical.setup.light = function(anchor) {
  window.dolical.setup.$form.find('.dolical-setup__nav a').each(function() {
    $(this).toggleClass('is-active', $(this).attr('data-section') === anchor);
  });
};

/**
 * Settings that depend on a switch : shown when it is on, or off for a name starting with « ! ».
 *
 * @returns {void}
 */
window.dolical.setup.depends = function() {
  window.dolical.setup.$form.find('[data-depends]').each(function() {
    const rule    = $(this).attr('data-depends');
    const reverse = rule.charAt(0) === '!';
    const checked = window.dolical.setup.$form.find('input[name="' + rule.replace('!', '') + '"]').is(':checked');
    $(this).toggle(reverse ? !checked : checked);
  });
};

/**
 * The section in sight : the last one whose top went above the first third of the window.
 *
 * @returns {void}
 */
window.dolical.setup.spy = function() {
  const S = window.dolical.setup;
  if (S.chosen) {
    return;
  }
  const limit = $(window).scrollTop() + $(window).height() / 3;
  let current = '';
  S.$form.find('.dolical-setup__card').each(function() {
    if ($(this).offset().top <= limit || !current) {
      current = this.id.replace('dolical-setup-', '');
    }
  });
  // At the bottom of the page, the last section, too short to reach the third of the window
  if ($(window).scrollTop() + $(window).height() >= $(document).height() - 4) {
    current = S.$form.find('.dolical-setup__card').last().attr('id').replace('dolical-setup-', '');
  }
  S.light(current);
};
