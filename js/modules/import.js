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
 * \file    js/modules/import.js
 * \ingroup dolical
 * \brief   Page of the import of an agenda : a file too big for the server refused before it is sent, the button
 *          busy while the import runs, an import undone only once confirmed in a modal.
 */

'use strict';

window.dolical.import = {};

/**
 * Import page init.
 *
 * @returns {void}
 */
window.dolical.import.init = function() {
  const $page = $('.dolical-import');
  if (!$page.length) {
    return;
  }
  const config = JSON.parse($page.attr('data-config') || '{}');

  $page.on('submit', '.dolical-import__form', function(event) {
    const input = this.querySelector('input[type=file]');
    const file  = input && input.files.length ? input.files[0] : null;
    // Over the limit, the server would get nothing at all, not even the token
    if (file && config.maxSize > 0 && file.size > config.maxSize * 1024) {
      event.preventDefault();
      window.dolical.util.toast(config.i18n.tooLarge, 'error');
      return;
    }
    const $button = $(this).find('button[type=submit]');
    $button.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i>' + window.dolical.util.esc($button.attr('data-running')));
  });

  // Undoing an import deletes its events : asked in a modal first, then everything stays busy until the page comes back
  const $modal = $('#dolical-import-undo-modal');
  $page.on('submit', '.dolical-import__undo', function(event) {
    if (this.getAttribute('data-confirmed') === '1') {
      return;
    }
    event.preventDefault();
    const form = this;
    $modal.removeClass('is-busy');
    $modal.find('.dolical-confirm__heading').text(form.getAttribute('data-heading'));
    $modal.find('.dolical-confirm__text').text(form.getAttribute('data-text'));
    $modal.find('.dolical-import__undo-label').text(form.getAttribute('data-button'));
    $modal.find('.dolical-import__undo-confirm').prop('disabled', false).find('i').attr('class', 'fas fa-trash-alt');
    $modal.find('.dolical-import__undo-confirm').off('click').on('click', function() {
      $modal.addClass('is-busy');
      $(this).prop('disabled', true).find('i').attr('class', 'fas fa-spinner fa-spin');
      $(form).find('button[type=submit]').prop('disabled', true).find('i').attr('class', 'fas fa-spinner fa-spin');
      form.setAttribute('data-confirmed', '1');
      // The native submit sends the form without passing here again
      form.submit();
    });
    $modal.addClass('modal-active');
    // Cancel is the safe choice : Enter right away keeps the import
    $modal.find('.modal-footer .modal-close').trigger('focus');
  });
  $(document).on('keydown', function(event) {
    if (event.key === 'Escape' && $modal.hasClass('modal-active') && !$modal.hasClass('is-busy')) {
      $modal.removeClass('modal-active');
    }
  });
};
