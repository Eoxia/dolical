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
 * \file    js/modules/sync.js
 * \ingroup dolical
 * \brief   Page of the synchronization of the phones : the address and the login copied in one click.
 */

'use strict';

window.dolical.sync = {};

/**
 * Sync page init.
 *
 * @returns {void}
 */
window.dolical.sync.init = function() {
  if (!$('.dolical-sync').length) {
    return;
  }
  window.dolical.util.applyColors($('.dolical-sync'));
  $(document).on('click', '.dolical-sync .dolical-copy', function() {
    window.dolical.util.copy($(this).attr('data-copy'));
  });
  // A new password stops the devices already set up, removing it too : asked first
  $(document).on('submit', '.dolical-sync form[data-confirm]', function(event) {
    if (!window.confirm($(this).attr('data-confirm'))) {
      event.preventDefault();
    }
  });
};
