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
 * \brief   Report of a meeting : written once the meeting is over, and proposed then.
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
  });
  // A meeting that ends while the calendar is open is proposed too
  $(document).on('dolical:loaded', R.proposeDue);
  setInterval(R.proposeDue, 60000);
};

/**
 * Open the report of an event.
 *
 * @param   {Number} id Event
 * @returns {void}
 */
window.dolical.report.open = function(id) {
  const R      = window.dolical.report;
  const U      = window.dolical.util;
  const $modal = $('#dolical-report-modal');
  const $box   = $modal.find('.modal-container');

  R.currentId = id;
  R.hideNudge();
  $box.html('<div class="dolical-form__loading"><i class="fas fa-circle-notch fa-spin"></i></div>');
  $modal.addClass('modal-active');

  $.get(U.config().urls.event, {action: 'report', id: id, token: U.config().token}, null, 'html').done(function(html) {
    $box.html(html);
    setTimeout(function() {
      $box.find('textarea[name="report"]').trigger('focus');
    }, 50);
  }).fail(function(xhr) {
    $modal.removeClass('modal-active');
    R.currentId = 0;
    U.toast(U.xhrError(xhr), 'error');
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
  if (!C.state || !C.state.events || $('.dolical-modal.modal-active').length || R.nudgeId) {
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
    R.open(id);
  }
};
