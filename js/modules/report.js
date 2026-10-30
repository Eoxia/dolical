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
 * \file    js/modules/report.js
 * \ingroup dolical
 * \brief   Report of a meeting : written, or dictated with the microphone of the media block of Saturne and written
 *          up by the AI module ; proposed once the meeting is over.
 */

'use strict';

window.dolical.report = {};

/**
 * Id of the event whose report is open, 0 when closed.
 *
 * @type {Number}
 */
window.dolical.report.currentId = 0;

/**
 * Event proposed by the nudge.
 *
 * @type {Number}
 */
window.dolical.report.nudgeId = 0;

/**
 * Report init.
 *
 * @returns {void}
 */
window.dolical.report.init = function() {
  if (!$('#dolical-report-modal').length) {
    return;
  }
  window.dolical.report.event();
};

/**
 * Report events.
 *
 * @returns {void}
 */
window.dolical.report.event = function() {
  const R = window.dolical.report;

  $(document).on('click', '#dolical-report-modal .dolical-report__submit', R.submit);
  $(document).on('click', '.dolical-nudge [data-nudge]', R.nudgeAction);
  $(document).on('click', '#dolical-report-modal .modal-close', function() {
    R.currentId = 0;
    $('body > .dolical-report-detached').remove();
  });
  // Photos and documents dropped next to the block, several at once
  $(document).on('change', '#dolical-report-modal .dolical-report__files', function() {
    R.upload(this.files);
  });
  $(document).on('dragenter dragover', '#dolical-report-modal .dolical-report__drop', function(event) {
    event.preventDefault();
    $(this).addClass('is-drop-target');
  });
  $(document).on('dragleave drop', '#dolical-report-modal .dolical-report__drop', function() {
    $(this).removeClass('is-drop-target');
  });
  $(document).on('drop', '#dolical-report-modal .dolical-report__drop', function(event) {
    const transfer = event.originalEvent && event.originalEvent.dataTransfer;
    if (transfer && transfer.files && transfer.files.length) {
      event.preventDefault();
      R.upload(transfer.files);
    }
  });
  // The voice note is sent by the media block of Saturne : once it is stored, it is written up
  $(document).on('ajaxComplete', function(event, xhr, settings) {
    if (R.currentId && settings && /[?&]action=add_audio(&|$)/.test(settings.url || '') && xhr.status === 200) {
      R.dictate();
    }
  });
  // A meeting that ends while the calendar is open is proposed too
  $(document).on('dolical:loaded', R.proposeDue);
  setInterval(R.proposeDue, 60000);
};

/**
 * Open the report of an event.
 *
 * @param   {Number}  id     Event
 * @param   {Boolean} record Start recording a voice note at once
 * @returns {void}
 */
window.dolical.report.open = function(id, record) {
  const R      = window.dolical.report;
  const U      = window.dolical.util;
  const $modal = $('#dolical-report-modal');
  const $box   = $modal.find('.modal-container');

  R.currentId = id;
  R.hideNudge();
  $box.html('<div class="dolical-form__loading"><i class="fas fa-circle-notch fa-spin"></i></div>');
  $modal.addClass('modal-active');

  $.get(U.config().urls.event, {action: 'report', id: id}, null, 'html').done(function(html) {
    // The libraries of the media block are modals of their own : out of this one, or they would show inside it
    $('body > .dolical-report-detached').remove();
    $box.html(html);
    $box.find('.wpeo-modal').addClass('dolical-report-detached').appendTo('body');
    if (record) {
      $box.find('.saturne-start-recording').first().trigger('click');
    }
    setTimeout(function() {
      if (!record) {
        $box.find('textarea[name="report"]').trigger('focus');
      }
    }, 50);
  }).fail(function(xhr) {
    $modal.removeClass('modal-active');
    R.currentId = 0;
    U.toast(U.xhrError(xhr), 'error');
  });
};

/**
 * Send dropped files to the documents of the event, then draw the media block again.
 *
 * @param   {FileList} files Files
 * @returns {void}
 */
window.dolical.report.upload = function(files) {
  const R  = window.dolical.report;
  const U  = window.dolical.util;
  const id = R.currentId;
  if (!id || !files || !files.length) {
    return;
  }

  const data = new FormData();
  data.append('action', 'upload');
  data.append('id', id);
  Array.prototype.forEach.call(files, function(file) {
    data.append('files[]', file);
  });

  const $drop = $('#dolical-report-modal .dolical-report__drop').addClass('is-busy');
  U.post(data).done(function(response) {
    if (!response.success && response.error) {
      U.toast(response.error, 'error');
    }
    $.get(U.config().urls.event, {action: 'mediablock', id: id}, null, 'html').done(function(html) {
      if (R.currentId !== id) {
        return;
      }
      $('body > .dolical-report-detached').remove();
      const $block = $('#dolical-report-modal .dolical-report__media-block').html(html);
      $block.find('.wpeo-modal').addClass('dolical-report-detached').appendTo('body');
    });
  }).fail(function(xhr) {
    U.toast(U.xhrError(xhr), 'error');
  }).always(function() {
    $drop.removeClass('is-busy');
  });
};

/**
 * Status line under the text.
 *
 * @param   {String}  message Message, empty to clear
 * @param   {Boolean} busy    Whether something is running
 * @returns {void}
 */
window.dolical.report.status = function(message, busy) {
  const $status = $('#dolical-report-modal .dolical-report__status');
  $status.toggleClass('is-busy', !!busy).html(message ? (busy ? '<i class="fas fa-circle-notch fa-spin"></i> ' : '') + window.dolical.util.esc(message) : '');
};

/**
 * Write up the voice note just recorded, added after what is already typed.
 *
 * @returns {void}
 */
window.dolical.report.dictate = function() {
  const R     = window.dolical.report;
  const U     = window.dolical.util;
  const $form = $('#dolical-report-modal .dolical-report');
  const id    = R.currentId;

  if (!$form.length || $form.attr('data-dictate') !== '1') {
    R.status(U.t('reportVoiceKept'));
    return;
  }

  R.status(U.t('reportWriting'), true);
  U.post({action: 'dictate', id: id}).done(function(response) {
    if (R.currentId !== id) {
      return;
    }
    if (!response.success) {
      R.status(U.t('reportVoiceKept') + ' ' + U.t('reportAiFailed').replace('%ERROR%', response.error || U.t('error')));
      return;
    }
    if (!response.report) {
      R.status(U.t('reportEmpty'));
      return;
    }
    const $text   = $form.find('textarea[name="report"]');
    const current = $.trim($text.val());
    $text.val(current ? current + '\n\n' + response.report : response.report);
    R.status(U.t('reportWritten'));
  }).fail(function(xhr) {
    R.status(U.xhrError(xhr));
  });
};

/**
 * Save the report.
 *
 * @returns {void}
 */
window.dolical.report.submit = function() {
  const R       = window.dolical.report;
  const U       = window.dolical.util;
  const $modal  = $('#dolical-report-modal');
  const $form   = $modal.find('.dolical-report');
  const $submit = $modal.find('.dolical-report__submit');
  const id      = parseInt($form.attr('data-id'), 10) || 0;

  if (!id || $submit.prop('disabled')) {
    return;
  }
  $submit.prop('disabled', true);
  U.post({
    action: 'savereport',
    id: id,
    report: $form.find('textarea[name="report"]').val(),
    done: $form.find('input[name="done"]').is(':checked') ? 1 : 0
  }).done(function(response) {
    if (!response.success) {
      $modal.find('.dolical-form__error').text(response.error || U.t('error'));
      return;
    }
    $modal.removeClass('modal-active');
    $modal.find('.modal-container').empty();
    $('body > .dolical-report-detached').remove();
    R.currentId = 0;
    U.toast(U.t('reportSaved'));
    $(document).trigger('dolical:changed', [id]);
  }).fail(function(xhr) {
    $modal.find('.dolical-form__error').text(U.xhrError(xhr));
  }).always(function() {
    $submit.prop('disabled', false);
  });
};

/**
 * Propose the report of the last meeting of the user that ended today and has none : once per meeting.
 *
 * @returns {void}
 */
window.dolical.report.proposeDue = function() {
  const R     = window.dolical.report;
  const U     = window.dolical.util;
  const C     = window.dolical.calendar;
  const me    = U.config().currentUser;
  const now   = new Date();
  const since = new Date(now.getTime() - 12 * 3600000);
  if (!C.state || !C.state.events || $('.wpeo-modal.modal-active').length || R.nudgeId) {
    return;
  }

  const seen = U.load('reportsProposed') || [];
  let due    = null;
  C.state.events.forEach(function(event) {
    const mine = event.ownerId === me || event.userIds.indexOf(me) !== -1;
    if (!mine || event.report || event.auto || event.allDay || event.percent < 0 || event.e > now || event.e < since || seen.indexOf(event.id) !== -1) {
      return;
    }
    if (!due || event.e > due.e) {
      due = event;
    }
  });
  if (!due) {
    return;
  }

  R.nudgeId = due.id;
  const $nudge = $('.dolical-nudge');
  $nudge.find('.dolical-nudge__text').text(U.t('reportNudgeText').replace('%LABEL%', due.label || U.t('untitled')));
  $nudge.addClass('is-visible');
};

/**
 * Hide the nudge, and remember the meeting was proposed.
 *
 * @returns {void}
 */
window.dolical.report.hideNudge = function() {
  const R = window.dolical.report;
  const U = window.dolical.util;
  if (R.nudgeId) {
    const seen = (U.load('reportsProposed') || []).slice(-200);
    seen.push(R.nudgeId);
    U.save('reportsProposed', seen);
  }
  R.nudgeId = 0;
  $('.dolical-nudge').removeClass('is-visible');
};

/**
 * Buttons of the nudge.
 *
 * @returns {void}
 */
window.dolical.report.nudgeAction = function() {
  const R      = window.dolical.report;
  const id     = R.nudgeId;
  const choice = $(this).attr('data-nudge');
  R.hideNudge();
  if (id && choice !== 'later') {
    R.open(id, choice === 'dictate');
  }
};
