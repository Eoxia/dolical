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
 * \file    js/modules/eventcard.js
 * \ingroup dolical
 * \brief   Card of an event, next to the clicked event (a sheet at the bottom of a phone) : people, documents,
 *          billing, attachments dropped right on it, and the quick actions.
 */

'use strict';

window.dolical.eventcard = {};

/**
 * Id of the event whose card is open, 0 when closed.
 *
 * @type {Number}
 */
window.dolical.eventcard.currentId = 0;

/**
 * Element the card is attached to.
 *
 * @type {HTMLElement|null}
 */
window.dolical.eventcard.anchor = null;

/**
 * Card init.
 *
 * @returns {void}
 */
window.dolical.eventcard.init = function() {
  if (!$('.dolical-popover').length) {
    return;
  }
  window.dolical.eventcard.event();
};

/**
 * Card events.
 *
 * @returns {void}
 */
window.dolical.eventcard.event = function() {
  const E = window.dolical.eventcard;

  $(document).on('click', '.dolical-card__close', E.close);
  $(document).on('click', '.dolical-card__menu-toggle', E.toggleMenu);
  $(document).on('click', '.dolical-card [data-action]', E.action);
  $(document).on('click', '.dolical-card .dolical-copy', E.copy);
  $(document).on('click', '.dolical-card__note-toggle', E.toggleNote);
  $(document).on('click', '.dolical-card .dolical-file__delete', E.deleteFile);
  $(document).on('change', '.dolical-card .dolical-dropzone__input', E.pickFiles);
  $(document).on('dragenter dragover', '.dolical-popover', E.dragOver);
  $(document).on('dragleave drop', '.dolical-popover', E.dragLeave);
  $(document).on('drop', '.dolical-popover', E.drop);

  $(document).on('mousedown', E.clickOutside);
  $(document).on('keydown', function(event) {
    if (event.key === 'Escape' && E.isOpen() && !$('.wpeo-modal.modal-active').length) {
      E.close();
    }
  });
  $(window).on('resize', function() {
    if (E.isOpen()) {
      E.position();
    }
  });
  $(document).on('dolical:changed', function(event, id) {
    if (E.isOpen() && (!id || id === E.currentId)) {
      E.reload();
    }
  });
};

/**
 * Whether a card is open.
 *
 * @returns {Boolean}
 */
window.dolical.eventcard.isOpen = function() {
  return $('.dolical-popover').hasClass('is-open');
};

/**
 * Open the card of an event.
 *
 * @param   {Number}           id     Id of the event
 * @param   {HTMLElement|null} anchor Clicked event, null to center the card
 * @returns {void}
 */
window.dolical.eventcard.open = function(id, anchor) {
  const E = window.dolical.eventcard;
  E.currentId = id;
  E.anchor    = anchor;

  $('.dolical-calendar .dolical-event.is-active').removeClass('is-active');
  if (anchor) {
    $(anchor).addClass('is-active');
  }

  $('.dolical-popover').html('<div class="dolical-card dolical-card--loading"><i class="fas fa-circle-notch fa-spin"></i></div>').addClass('is-open');
  E.position();
  E.reload();
};

/**
 * Load the card of the current event again.
 *
 * @returns {void}
 */
window.dolical.eventcard.reload = function() {
  const E      = window.dolical.eventcard;
  const U      = window.dolical.util;
  const config = U.config();
  const id     = E.currentId;

  $.get(config.urls.event, {action: 'card', id: id}, null, 'html').done(function(html) {
    if (E.currentId !== id || !E.isOpen()) {
      return;
    }
    const $popover = $('.dolical-popover');
    $popover.html(html);
    U.applyColors($popover);
    // A short description needs no "show more"
    $popover.find('.dolical-card__note').each(function() {
      if (this.scrollHeight <= this.clientHeight + 4) {
        $(this).closest('.dolical-card__section').find('.dolical-card__note-toggle').remove();
      }
    });
    E.position();
  }).fail(function(xhr) {
    E.close();
    U.toast(U.xhrError(xhr), 'error');
  });
};

/**
 * Close the card.
 *
 * @returns {void}
 */
window.dolical.eventcard.close = function() {
  const E = window.dolical.eventcard;
  E.currentId = 0;
  E.anchor    = null;
  $('.dolical-popover').removeClass('is-open is-sheet').empty();
  $('.dolical-calendar .dolical-event.is-active').removeClass('is-active');
};

/**
 * Place the card beside its event, on the side where it fits, inside the window.
 *
 * @returns {void}
 */
window.dolical.eventcard.position = function() {
  const E        = window.dolical.eventcard;
  const $popover = $('.dolical-popover');
  const popover  = $popover[0];
  const margin   = 12;

  // On a phone, a sheet at the bottom of the screen
  if (window.innerWidth < 768) {
    $popover.addClass('is-sheet');
    popover.style.removeProperty('--ra-left');
    popover.style.removeProperty('--ra-top');
    return;
  }
  $popover.removeClass('is-sheet');

  // The top bar of Dolibarr stays above everything : the card starts below it
  const header = document.getElementById('id-top');
  const minTop = Math.max(margin, (header ? header.getBoundingClientRect().bottom : 0) + 8);
  popover.style.setProperty('--ra-max-height', (window.innerHeight - minTop - margin) + 'px');

  const width  = popover.offsetWidth;
  const height = popover.offsetHeight;
  let left     = (window.innerWidth - width) / 2;
  let top      = Math.max(minTop, (window.innerHeight - height) / 3);

  // The anchor may have been redrawn by a refresh : find it again by its id
  let anchor = E.anchor;
  if (anchor && !document.body.contains(anchor)) {
    anchor   = $('.dolical-calendar .dolical-event[data-id="' + E.currentId + '"]').get(0) || null;
    E.anchor = anchor;
  }
  if (anchor) {
    const rect = anchor.getBoundingClientRect();
    left = rect.right + margin;
    if (left + width > window.innerWidth - margin) {
      left = rect.left - width - margin;
    }
    if (left < margin) {
      left = Math.min(window.innerWidth - width - margin, Math.max(margin, rect.left));
    }
    top = Math.min(Math.max(minTop, rect.top), window.innerHeight - height - margin);
  }

  popover.style.setProperty('--ra-left', Math.max(margin, left) + 'px');
  popover.style.setProperty('--ra-top', Math.max(minTop, top) + 'px');
};

/**
 * Close the card on a click elsewhere, but not on an event (it opens its own card), nor in the form.
 *
 * @param   {MouseEvent} event Click
 * @returns {void}
 */
window.dolical.eventcard.clickOutside = function(event) {
  const E = window.dolical.eventcard;
  if (!E.isOpen()) {
    return;
  }
  const $target = $(event.target);
  if ($target.closest('.dolical-popover, .dolical-calendar .dolical-event, .wpeo-modal, #saturne-photo-editor-modal, .select2-container, .dolical-toast, .dolical-nudge').length) {
    return;
  }
  E.close();
};

/**
 * Menu "...".
 *
 * @param   {Event} event Click
 * @returns {void}
 */
window.dolical.eventcard.toggleMenu = function(event) {
  event.stopPropagation();
  $(this).closest('.dolical-card__menu-wrap').toggleClass('is-open');
};

/**
 * Show the whole description.
 *
 * @returns {void}
 */
window.dolical.eventcard.toggleNote = function() {
  $(this).closest('.dolical-card__section').find('.dolical-card__note').addClass('is-expanded');
  $(this).remove();
};

/**
 * Copy a text of the card : emails, address, link.
 *
 * @returns {void}
 */
window.dolical.eventcard.copy = function() {
  window.dolical.util.copy($(this).attr('data-copy'));
};

/**
 * Buttons of the card.
 *
 * @param   {Event} event Click
 * @returns {void}
 */
window.dolical.eventcard.action = function(event) {
  const E      = window.dolical.eventcard;
  const U      = window.dolical.util;
  const $btn   = $(this);
  const id     = E.currentId;
  const action = $btn.attr('data-action');

  $btn.closest('.dolical-card__menu-wrap').removeClass('is-open');

  switch (action) {
    case 'edit':
      window.dolical.eventform.open({id: id});
      break;
    case 'clone':
      E.close();
      window.dolical.eventform.open({clone: id});
      break;
    case 'copylink':
      U.copy($btn.attr('data-copy'));
      break;
    case 'delete': {
      // A date of a series : this one, the following ones or all of them ; the people invited by email are told
      const $card   = $btn.closest('.dolical-card');
      const invited = parseInt($card.attr('data-invited'), 10) || 0;
      const remove  = function(scope) {
        U.post({action: 'delete', id: id, scope: scope}).done(function(response) {
          if (response.success) {
            E.close();
            const parts = [];
            if (response.deleted > 1) {
              parts.push(U.t('deletedOccurrences', response.deleted));
            }
            if (response.cancelled > 0) {
              parts.push(U.t('cancelledSent', response.cancelled));
            }
            if (parts.length) {
              U.toast(parts.join(' · '));
            }
            if (response.error) {
              U.toast(response.error, 'error');
            }
            $(document).trigger('dolical:changed');
          } else {
            U.toast(response.error || U.t('error'), 'error');
          }
        }).fail(function(xhr) {
          U.toast(U.xhrError(xhr), 'error');
        });
      };
      if ((parseInt($card.attr('data-series'), 10) || 0) > 1) {
        U.chooseScope('delete', true, function(scope) {
          if (invited === 0 || window.confirm(U.t('confirmDeleteInvited', invited))) {
            remove(scope);
          }
        });
        break;
      }
      if (!window.confirm(invited > 0 ? U.t('confirmDeleteInvited', invited) : U.t('confirmDelete'))) {
        return;
      }
      remove('one');
      break;
    }
    case 'report':
    case 'dictate':
      window.dolical.report.open(id, action === 'dictate');
      break;
    case 'invite':
    case 'remind':
      $btn.prop('disabled', true);
      U.post({action: action, id: id, kind: $btn.attr('data-kind') || '', person: $btn.attr('data-person') || 0}).done(function(response) {
        if (response.sent > 0) {
          U.toast(U.t(action === 'remind' ? 'remindersSent' : 'invitationsSent', response.sent) + (response.error ? ' · ' + response.error : ''), response.error ? 'error' : '');
        } else {
          U.toast(response.error || U.t('error'), 'error');
        }
        E.reload();
      }).fail(function(xhr) {
        U.toast(U.xhrError(xhr), 'error');
        $btn.prop('disabled', false);
      });
      break;
    case 'setpercent':
    case 'answer':
      U.post({action: action, id: id, percent: $btn.attr('data-percent'), status: $btn.attr('data-status')}).done(function(response) {
        if (response.success) {
          $(document).trigger('dolical:changed', [id]);
        } else {
          U.toast(response.error || U.t('error'), 'error');
        }
      }).fail(function(xhr) {
        U.toast(U.xhrError(xhr), 'error');
      });
      break;
    default:
      return;
  }
  event.preventDefault();
};

/**
 * Send files to the documents of the event.
 *
 * @param   {FileList|File[]} files Files
 * @returns {void}
 */
window.dolical.eventcard.upload = function(files) {
  const E = window.dolical.eventcard;
  const U = window.dolical.util;
  if (!files || !files.length || !E.currentId) {
    return;
  }

  const data = new FormData();
  data.append('action', 'upload');
  data.append('id', E.currentId);
  Array.prototype.forEach.call(files, function(file) {
    data.append('files[]', file);
  });

  $('.dolical-popover .dolical-dropzone').addClass('is-busy');
  U.post(data).done(function(response) {
    if (!response.success && response.error) {
      U.toast(response.error, 'error');
    }
    E.reload();
  }).fail(function(xhr) {
    U.toast(U.xhrError(xhr), 'error');
    E.reload();
  });
};

/**
 * Files chosen with the button of the drop zone.
 *
 * @returns {void}
 */
window.dolical.eventcard.pickFiles = function() {
  window.dolical.eventcard.upload(this.files);
};

/**
 * Files dragged over the card : show where they will land.
 *
 * @param   {Event} event Drag
 * @returns {void}
 */
window.dolical.eventcard.dragOver = function(event) {
  if (!$(this).find('.dolical-dropzone').length) {
    return;
  }
  event.preventDefault();
  $(this).addClass('is-drop-target');
};

/**
 * Files no longer over the card.
 *
 * @param   {Event} event Drag
 * @returns {void}
 */
window.dolical.eventcard.dragLeave = function(event) {
  if (event.type === 'dragleave' && this.contains(event.relatedTarget)) {
    return;
  }
  $(this).removeClass('is-drop-target');
};

/**
 * Files dropped on the card.
 *
 * @param   {Event} event Drop
 * @returns {void}
 */
window.dolical.eventcard.drop = function(event) {
  const transfer = event.originalEvent && event.originalEvent.dataTransfer;
  if (!transfer || !transfer.files || !transfer.files.length || !$(this).find('.dolical-dropzone').length) {
    return;
  }
  event.preventDefault();
  window.dolical.eventcard.upload(transfer.files);
};

/**
 * Delete an attachment from the card.
 *
 * @returns {void}
 */
window.dolical.eventcard.deleteFile = function() {
  const E = window.dolical.eventcard;
  const U = window.dolical.util;
  if (!window.confirm(U.t('confirmFile'))) {
    return;
  }
  U.post({action: 'deletefile', id: E.currentId, file: $(this).attr('data-file')}).always(function() {
    E.reload();
  });
};
