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
 * \file    js/modules/eventform.js
 * \ingroup dolical
 * \brief   Form of an event in a modal : people, contacts, third party, project, linked documents and
 *          attachments, saved into the native event in one go.
 */

'use strict';

window.dolical.eventform = {};

/**
 * Form init.
 *
 * @returns {void}
 */
window.dolical.eventform.init = function() {
  if (!$('#dolical-event-modal').length) {
    return;
  }
  window.dolical.eventform.event();
};

/**
 * Form events.
 *
 * @returns {void}
 */
window.dolical.eventform.event = function() {
  const F = window.dolical.eventform;

  $(document).on('click', '#dolical-event-modal .dolical-form__submit', F.submit);
  $(document).on('change', '#dolical-event-modal input[name="fullday"]', F.toggleFullday);
  $(document).on('change', '#dolical-event-modal input[name="start_at"], #dolical-event-modal input[name="start_day"]', F.keepDuration);
  $(document).on('focus', '#dolical-event-modal input[name="start_at"], #dolical-event-modal input[name="start_day"]', F.rememberStart);
  $(document).on('input', '#dolical-event-modal textarea[name="note"]', F.noteChanged);
  $(document).on('change', '#dolical-event-modal .dolical-form__files', F.filesChanged);
  $(document).on('dragover', '#dolical-event-modal .dolical-dropzone', F.dragOver);
  $(document).on('dragleave drop', '#dolical-event-modal .dolical-dropzone', F.dragLeave);
  $(document).on('drop', '#dolical-event-modal .dolical-dropzone', F.drop);
  $(document).on('click', '#dolical-event-modal .dolical-file__delete', F.deleteFile);
  $(document).on('keydown', '#dolical-event-modal', F.keyboard);
  $(document).on('change', '#dolical-event-modal #dolical-users, #dolical-event-modal #dolical-contacts', F.peopleChanged);
  $(document).on('select2:select', '#dolical-event-modal #dolical-contacts', F.contactChosen);
  $(document).on('change', '#dolical-event-modal input[name="start_at"], #dolical-event-modal input[name="end_at"], #dolical-event-modal input[name="start_day"], #dolical-event-modal input[name="end_day"], #dolical-event-modal input[name="fullday"], #dolical-event-modal input[name="location"]', F.placeChanged);
  $(document).on('change', '#dolical-event-modal input[name="visio"]', F.visioChanged);
  $(document).on('click', '#dolical-event-modal input[name="notify"]', F.notifyTouched);
  $(document).on('change', '#dolical-event-modal select[name="owner_id"]', function() {
    F.scheduleAvailability($(this).closest('.dolical-form'));
  });
  $(document).on('change', '#dolical-event-modal input[name="start_at"], #dolical-event-modal input[name="start_day"]', function() {
    F.relabelRepeat($(this).closest('.dolical-form'));
  });
  $(document).on('change', '#dolical-event-modal select[name="recurrence"]', F.recurrenceChanged);
  $(document).on('click', '#dolical-event-modal .dolical-form__maildate', F.useMailDate);
  $(document).on('click', '#dolical-event-modal .dolical-form__add-date', function() {
    const $form = $(this).closest('.dolical-form');
    F.addDate($form, '', '', '');
    F.datesChanged($form);
  });
  $(document).on('click', '#dolical-event-modal .dolical-form__extra-remove', function() {
    const $form = $(this).closest('.dolical-form');
    $(this).closest('.dolical-form__extra').remove();
    F.datesChanged($form);
  });
  // Another date typed : no more the one of the mail ; its end follows its start, the length stays
  $(document).on('change', '#dolical-event-modal .dolical-form__extra [data-extra="fullday"]', F.extraFullday);
  $(document).on('change', '#dolical-event-modal .dolical-form__extra-day', function() {
    $(this).closest('.dolical-form__extra').attr('data-chip', '');
    F.datesChanged($(this).closest('.dolical-form'));
  });
  $(document).on('focus', '#dolical-event-modal .dolical-form__extra-at[data-extra="start"]', F.rememberStart);
  $(document).on('change', '#dolical-event-modal .dolical-form__extra-at', function() {
    const $row = $(this).closest('.dolical-form__extra');
    if ($(this).attr('data-extra') === 'start') {
      const previous = new Date($(this).attr('data-previous') || '');
      const current  = new Date($(this).val());
      const end      = new Date($row.find('[data-extra="end"]').val());
      $(this).attr('data-previous', $(this).val());
      if (!isNaN(previous) && !isNaN(current) && !isNaN(end)) {
        $row.find('[data-extra="end"]').val(F.localValue(new Date(end.getTime() + (current - previous))));
      }
    }
    $row.attr('data-chip', '');
    F.datesChanged($(this).closest('.dolical-form'));
  });
  // The dates of the form typed : no more the ones of a date of the mail
  $(document).on('change', '#dolical-event-modal input[name="start_at"], #dolical-event-modal input[name="end_at"], #dolical-event-modal input[name="start_day"], #dolical-event-modal input[name="end_day"]', function() {
    const $form = $(this).closest('.dolical-form');
    $form.attr('data-dates-touched', 1).find('.dolical-form__dates').attr('data-chip', '');
    F.datesChanged($form);
  });
  $(document).on('change', '#dolical-event-modal input[name="reminder"]', F.toggleReminder);
  $(document).on('change', '#dolical-event-modal input[name="reminder_types[]"]', function() {
    F.askNotification($(this).closest('.dolical-form__reminder'));
  });
};

/**
 * Word the choices of repetition again from the day chosen : « Toutes les semaines le mardi ».
 *
 * @param   {jQuery} $form Form
 * @returns {void}
 */
window.dolical.eventform.relabelRepeat = function($form) {
  const U      = window.dolical.util;
  const period = window.dolical.eventform.period($form);
  if (!period) {
    return;
  }
  const start  = period.start;
  const day    = U.format(start, {weekday: 'long'});
  const date   = start.getDate();
  const length = new Date(start.getFullYear(), start.getMonth() + 1, 0).getDate();
  const nth    = date + 7 > length ? U.t('nthLast') : U.t('nth' + Math.ceil(date / 7));
  const labels = {
    '': U.t('repeatNone'),
    daily: U.t('repeatDaily'),
    weekdays: U.t('repeatWeekdays'),
    weekly: U.t('repeatWeekly').replace('%DAY%', day),
    biweekly: U.t('repeatBiweekly').replace('%DAY%', day),
    monthly_day: U.t('repeatMonthlyDay').replace('%DATE%', date),
    monthly_weekday: U.t('repeatMonthlyWeekday').replace('%NTH%', nth).replace('%DAY%', day),
    yearly: U.t('repeatYearly').replace('%DATE%', U.format(start, {day: 'numeric', month: 'long'}))
  };
  $form.find('select[name="recurrence"] option').each(function() {
    if (labels[this.value] !== undefined) {
      $(this).text(labels[this.value]);
    }
  });
};

/**
 * Repeating or not : the last day of the series only matters when it repeats.
 *
 * @returns {void}
 */
window.dolical.eventform.recurrenceChanged = function() {
  $(this).closest('.dolical-form__repeat').toggleClass('is-repeating', this.value !== '');
};

/**
 * Switch of the reminder : its options show when it is on.
 *
 * @returns {void}
 */
window.dolical.eventform.toggleReminder = function() {
  const $box = $(this).closest('.dolical-form__reminder');
  $box.toggleClass('is-on', this.checked);
  window.dolical.eventform.askNotification($box);
};

/**
 * A reminder in the browser needs its permission : asked when the user turns it on, blocked told plainly.
 *
 * @param   {jQuery} $box Reminder part of the form
 * @returns {void}
 */
window.dolical.eventform.askNotification = function($box) {
  const wanted = $box.find('input[name="reminder"]').is(':checked') && $box.find('input[name="reminder_types[]"][value="browser"]').is(':checked');
  if (!wanted || !('Notification' in window)) {
    $box.removeClass('is-blocked');
    return;
  }
  if (Notification.permission === 'default') {
    Notification.requestPermission().then(function(permission) {
      $box.toggleClass('is-blocked', permission === 'denied');
    });
  }
  $box.toggleClass('is-blocked', Notification.permission === 'denied');
};

/**
 * Tick "send the invitations" unless the user already chose : the form proposes, the user decides.
 *
 * @param   {jQuery} $form Form
 * @returns {void}
 */
window.dolical.eventform.proposeNotify = function($form) {
  const $notify = $form.find('input[name="notify"]');
  if (!$notify.attr('data-touched')) {
    $notify.prop('checked', true);
  }
};

/**
 * Someone was added : the invitation is proposed.
 *
 * @returns {void}
 */
window.dolical.eventform.peopleChanged = function() {
  const $form    = $(this).closest('.dolical-form');
  const initial  = ($(this).attr('data-initial') || '').split(',').filter(Boolean);
  const current  = $(this).val() || [];
  const newcomer = current.some(function(value) {
    return initial.indexOf(value) === -1;
  });
  // Someone removed from an event he was invited to must be told it is off for him
  const removed  = initial.some(function(value) {
    return current.indexOf(value) === -1;
  });
  if (newcomer || removed) {
    window.dolical.eventform.proposeNotify($form);
  }
  window.dolical.eventform.scheduleAvailability($form);
};

/**
 * A contact of a third party chosen while the event has none yet : that third party fills the field.
 * A third party already chosen is kept, the contact may come from a partner.
 *
 * @param   {Event} e select2:select event
 * @returns {void}
 */
window.dolical.eventform.contactChosen = function(e) {
  const data        = (e.params && e.params.data) || {};
  const $thirdparty = $(this).closest('.dolical-form').find('select[name="socid"]');
  const socid       = parseInt(data.socid, 10);
  if (!socid || !$thirdparty.length || $thirdparty.val()) {
    return;
  }
  if (!$thirdparty.find('option[value="' + socid + '"]').length) {
    $thirdparty.append(new Option(data.socname || '', socid, false, false));
  }
  $thirdparty.val(String(socid)).trigger('change');
};

/**
 * Start and end of the form, as dates, or null when they are not valid.
 *
 * @param   {jQuery} $form Form
 * @returns {{start: Date, end: Date, fullday: Boolean}|null}
 */
window.dolical.eventform.period = function($form) {
  const fullday = $form.find('input[name="fullday"]').is(':checked');
  let start;
  let end;
  if (fullday) {
    start = new Date($form.find('input[name="start_day"]').val() + 'T00:00:00');
    end   = new Date($form.find('input[name="end_day"]').val() + 'T23:59:59');
  } else {
    start = new Date($form.find('input[name="start_at"]').val());
    end   = new Date($form.find('input[name="end_at"]').val());
  }
  if (isNaN(start) || isNaN(end) || end < start) {
    return null;
  }
  return {start: start, end: end, fullday: fullday};
};

/**
 * Check the availability a moment after the last change : typing a date fires many changes.
 *
 * @param   {jQuery} $form Form
 * @returns {void}
 */
window.dolical.eventform.scheduleAvailability = function($form) {
  clearTimeout(window.dolical.eventform.availabilityTimer);
  window.dolical.eventform.availabilityTimer = setTimeout(function() {
    window.dolical.eventform.checkAvailability($form);
  }, 350);
};

/**
 * Warn when a colleague of the form is already taken during the slot.
 *
 * @param   {jQuery} $form Form
 * @returns {void}
 */
window.dolical.eventform.checkAvailability = function($form) {
  const U       = window.dolical.util;
  const $box    = $form.find('.dolical-form__conflicts');
  const period  = window.dolical.eventform.period($form);
  const users   = [$form.find('select[name="owner_id"]').val()].concat($form.find('#dolical-users').val() || []).filter(Boolean);
  if (!period || !users.length) {
    $box.removeClass('is-visible').empty();
    return;
  }

  $.getJSON(U.config().urls.event, {
    view: 'availability',
    id: parseInt($form.attr('data-id'), 10) || 0,
    start: Math.floor(period.start.getTime() / 1000),
    end: Math.floor(period.end.getTime() / 1000),
    users: users.join(',')
  }).done(function(response) {
    const conflicts = (response && response.conflicts) || [];
    if (!conflicts.length) {
      $box.removeClass('is-visible').empty();
      return;
    }
    let html = '<i class="fas fa-exclamation-triangle"></i><div><strong>' + U.esc(U.t('busyWarning')) + '</strong><ul>';
    conflicts.forEach(function(conflict) {
      const start = new Date(conflict.start * 1000);
      const end   = new Date(conflict.end * 1000);
      const day   = function(date) {
        return U.format(date, {day: 'numeric', month: 'short'});
      };
      let when    = conflict.allDay ? U.t('allDay') : U.time(start) + ' – ' + U.time(end);
      // Over several days : each end with its date, an hour alone would read as the same day
      if (!conflict.allDay && !U.sameDay(start, end)) {
        when = day(start) + ' ' + U.time(start) + ' – ' + day(end) + ' ' + U.time(end);
      } else if (!U.sameDay(start, period.start)) {
        when = day(start) + ' ' + when;
      }
      html += '<li><b>' + U.esc(conflict.name) + '</b> · ' + U.esc(when) + ' · ' + U.esc(conflict.label || U.t('busyOther')) + '</li>';
    });
    html += '</ul></div>';
    $box.html(html).addClass('is-visible');
  });
};

/**
 * The date or the place of an existing event changed : telling the participants is proposed.
 *
 * @returns {void}
 */
window.dolical.eventform.placeChanged = function() {
  const $form = $(this).closest('.dolical-form');
  window.dolical.eventform.scheduleAvailability($form);
  window.dolical.eventform.visioChanged.call(this);
};

/**
 * The place or the video call link of an existing event changed : telling the participants is proposed, the link
 * goes to them in the mail.
 *
 * @returns {void}
 */
window.dolical.eventform.visioChanged = function() {
  const $form  = $(this).closest('.dolical-form');
  const people = ($form.find('#dolical-users').val() || []).length + ($form.find('#dolical-contacts').val() || []).length;
  if (parseInt($form.attr('data-id'), 10) > 0 && people > 0) {
    window.dolical.eventform.proposeNotify($form);
  }
};

/**
 * The user ticked or unticked the box himself : the form stops proposing.
 *
 * @returns {void}
 */
window.dolical.eventform.notifyTouched = function() {
  $(this).attr('data-touched', '1');
};

/**
 * Open the form : a new event, an event to change, or the copy of an event.
 *
 * @param   {Object} params {id} to edit, {clone} to duplicate, else {start, end, allday, user, socid, projectid, elements}
 * @returns {void}
 */
window.dolical.eventform.open = function(params) {
  const F      = window.dolical.eventform;
  const U      = window.dolical.util;
  const config = U.config();
  const $modal = $('#dolical-event-modal');
  const $box   = $modal.find('.modal-container');

  if (params && params.id) {
    window.dolical.eventcard.close();
  }

  $box.html('<div class="dolical-form__loading"><i class="fas fa-circle-notch fa-spin"></i></div>');
  $modal.addClass('modal-active');

  // What comes from a mail (title, addresses, Message-ID) goes by POST, the token in the body : never in an
  // address, so never in a log. Else a read in GET without token, as the other views
  const fromMail = !!(params && (params.label || params.emails || params.email_msgid));
  $.ajax({
    url: config.urls.event,
    method: fromMail ? 'POST' : 'GET',
    data: $.extend({view: 'form'}, fromMail ? {token: config.token} : {}, params || {}),
    dataType: 'html'
  }).done(function(html) {
    $box.html(html);
    F.setup($box);
    // The other modules bring their fields to life (hook formObjectOptions)
    $(document).trigger('dolical:formopen', [$box, params || {}]);
  }).fail(function(xhr) {
    $modal.removeClass('modal-active');
    U.toast(U.xhrError(xhr), 'error');
  });
};

/**
 * Bring the fields of a freshly loaded form to life.
 *
 * @param   {jQuery} $box Content of the modal
 * @returns {void}
 */
window.dolical.eventform.setup = function($box) {
  const F      = window.dolical.eventform;
  const U      = window.dolical.util;
  const config = U.config();
  const $form  = $box.find('.dolical-form');

  F.toggleFullday.call($form.find('input[name="fullday"]')[0]);

  // The people already there : only a newcomer makes the form propose an invitation
  $form.find('#dolical-users, #dolical-contacts').each(function() {
    $(this).attr('data-initial', ($(this).val() || []).join(','));
  });

  if ($.fn.select2) {
    $form.find('.dolical-select2').each(function() {
      const $select = $(this);
      const type    = $select.attr('data-ajax-type');
      const options = {
        width: '100%',
        dropdownParent: $('#dolical-event-modal'),
        placeholder: $select.attr('data-placeholder') || '',
        allowClear: $select.attr('data-allow-clear') === '1',
        language: {
          searching: function() {
            return U.t('searching');
          },
          noResults: function() {
            return U.t('noResults');
          },
          inputTooShort: function() {
            return U.t('inputTooShort');
          }
        }
      };
      // A person outside the contacts is typed as an email : it becomes a contact when saved
      if ($select.attr('data-tags') === 'email') {
        const newLabel = $select.attr('data-new-label') || '%EMAIL%';
        options.tags = true;
        options.createTag = function(params) {
          const term  = $.trim(params.term || '');
          const email = (term.match(/[^\s<>]+@[^\s<>]+\.[^\s<>]+/) || [''])[0];
          if (!email) {
            return null;
          }
          return {id: 'new:' + encodeURIComponent(term), text: newLabel.replace('%EMAIL%', term), newTag: true};
        };
        options.insertTag = function(data, tag) {
          data.unshift(tag);
        };
      }
      if (type) {
        options.minimumInputLength = type === 'thirdparty' ? 2 : 0;
        options.ajax = {
          url: config.urls.search,
          dataType: 'json',
          delay: 250,
          data: function(params) {
            // The third party chosen in the form brings its contacts, projects and documents first
            return {type: type, q: params.term || '', socid: $form.find('select[name="socid"]').val() || 0};
          },
          processResults: function(data) {
            return {results: data.results || []};
          },
          cache: false
        };
      }
      $select.select2(options);
    });
  }

  setTimeout(function() {
    $form.find('input[name="label"]').trigger('focus');
  }, 50);

  // Notifications already refused : said at once, the switch alone would look like it works
  const $reminder = $form.find('.dolical-form__reminder');
  if ($reminder.length && 'Notification' in window && Notification.permission === 'denied') {
    $reminder.toggleClass('is-blocked', $reminder.find('input[name="reminder"]').is(':checked') && $reminder.find('input[name="reminder_types[]"][value="browser"]').is(':checked'));
  }

  F.datesChanged($form);
  F.checkAvailability($form);
};

/**
 * All day : dates without hours.
 *
 * @returns {void}
 */
window.dolical.eventform.toggleFullday = function() {
  if (!this) {
    return;
  }
  $(this).closest('.dolical-form').toggleClass('is-fullday', this.checked);
};

/**
 * A date written as the inputs of the form take it : « 2026-11-24T14:30 ».
 *
 * @param   {Date} date Date
 * @returns {String}
 */
window.dolical.eventform.localValue = function(date) {
  const pad = function(value) {
    return String(value).padStart(2, '0');
  };
  return date.getFullYear() + '-' + pad(date.getMonth() + 1) + '-' + pad(date.getDate()) + 'T' + pad(date.getHours()) + ':' + pad(date.getMinutes());
};

/**
 * Dates of a chip of the mail : a day without hours takes the hours and the length of the dates of the form.
 *
 * @param   {jQuery} $form Form
 * @param   {jQuery} $chip Chip
 * @returns {Object}       {start, end} as the inputs take them
 */
window.dolical.eventform.chipDates = function($form, $chip) {
  const F   = window.dolical.eventform;
  let start = $chip.attr('data-start');
  let end   = $chip.attr('data-end');
  if ($chip.attr('data-has-time') !== '1') {
    const from   = new Date($form.find('input[name="start_at"]').val());
    const to     = new Date($form.find('input[name="end_at"]').val());
    const length = !isNaN(from) && !isNaN(to) && to > from ? to - from : 3600000;
    start = start.slice(0, 10) + 'T' + (!isNaN(from) ? F.localValue(from).slice(11) : '09:00');
    end   = F.localValue(new Date(new Date(start).getTime() + length));
  }
  return {start: start, end: end};
};

/**
 * Put dates in the dates of the form.
 *
 * @param   {jQuery}  $form    Form
 * @param   {String}  start    Start, as the inputs take it
 * @param   {String}  end      End
 * @param   {String}  chip     Chip of the mail they come from, '' when typed
 * @param   {Boolean} withTime Whether they have hours : the event is no more a whole day
 * @returns {void}
 */
window.dolical.eventform.setMainDates = function($form, start, end, chip, withTime) {
  const F = window.dolical.eventform;
  $form.find('input[name="start_at"]').val(start).attr('data-previous', start);
  $form.find('input[name="end_at"]').val(end);
  $form.find('input[name="start_day"]').val(start.slice(0, 10)).attr('data-previous', start.slice(0, 10));
  $form.find('input[name="end_day"]').val(end.slice(0, 10));
  $form.find('.dolical-form__dates').attr('data-chip', chip);
  if (withTime) {
    $form.find('input[name="fullday"]').prop('checked', false);
  }
  $form.find('input[name="fullday"]').trigger('change');
  F.relabelRepeat($form);
};

/**
 * A date of the mail clicked : chosen, it is one more date of the event ; chosen again, it goes (the next date takes
 * the place of the first one, the only date stays).
 *
 * @returns {void}
 */
window.dolical.eventform.useMailDate = function() {
  const F     = window.dolical.eventform;
  const $chip = $(this);
  const $form = $chip.closest('.dolical-form');
  const index = $chip.attr('data-index');
  const $main = $form.find('.dolical-form__dates');

  if ($chip.hasClass('is-active')) {
    const $row = $form.find('.dolical-form__extra[data-chip="' + index + '"]');
    if ($row.length) {
      $row.remove();
    } else if ($main.attr('data-chip') === index) {
      const $next = $form.find('.dolical-form__extra').first();
      if (!$next.length) {
        return;
      }
      F.setMainDates($form, $next.find('[data-extra="start"]').val(), $next.find('[data-extra="end"]').val(), $next.attr('data-chip') || '', true);
      $next.remove();
    }
  } else if ($main.attr('data-chip') === '' && !$form.find('.dolical-form__extra').length && !$form.attr('data-dates-touched')) {
    // The dates of the form were not chosen yet : the first date of the mail takes their place
    const dates = F.chipDates($form, $chip);
    F.setMainDates($form, dates.start, dates.end, index, $chip.attr('data-has-time') === '1');
  } else {
    const dates = F.chipDates($form, $chip);
    F.addDate($form, dates.start, dates.end, index);
  }
  F.datesChanged($form);
};

/**
 * One more date for the event : given (a date of the mail), or the day after the last one, at the same hours.
 *
 * @param   {jQuery} $form Form
 * @param   {String} start Start, as the inputs take it ('' for the day after the last date)
 * @param   {String} end   End
 * @param   {String} chip  Chip of the mail it comes from, '' when typed
 * @returns {void}
 */
window.dolical.eventform.addDate = function($form, start, end, chip) {
  const F = window.dolical.eventform;
  const U = window.dolical.util;
  if (!start) {
    const $last = $form.find('.dolical-form__extra').last();
    const from  = new Date($last.length ? $last.find('[data-extra="start"]').val() : $form.find('input[name="start_at"]').val());
    const to    = new Date($last.length ? $last.find('[data-extra="end"]').val() : $form.find('input[name="end_at"]').val());
    if (isNaN(from) || isNaN(to)) {
      return;
    }
    start = F.localValue(new Date(from.getTime() + 86400000));
    end   = F.localValue(new Date(to.getTime() + 86400000));
  }
  // The line of the dates of the event, for one more date : its own « all day » and « to confirm »
  const hasTentative = $form.find('input[name="tentative"]').length > 0;
  const $row = $('<div class="dolical-form__extra">'
    + '<input type="datetime-local" class="dolical-form__extra-at" data-extra="start">'
    + '<input type="date" class="dolical-form__extra-day" data-extra="start-day">'
    + '<span class="dolical-form__arrow"><i class="fas fa-long-arrow-alt-right"></i></span>'
    + '<input type="datetime-local" class="dolical-form__extra-at" data-extra="end">'
    + '<input type="date" class="dolical-form__extra-day" data-extra="end-day">'
    + '<label class="dolical-check"><input type="checkbox" data-extra="fullday"><span>' + U.esc(U.t('allDay')) + '</span></label>'
    + (hasTentative ? '<label class="dolical-check dolical-form__tentative"><input type="checkbox" data-extra="tentative" checked><span>' + U.esc(U.t('toConfirm')) + '</span></label>' : '')
    + '<button type="button" class="dolical-icon-btn dolical-form__extra-remove" title="' + U.esc(U.t('removeDate')) + '" aria-label="' + U.esc(U.t('removeDate')) + '"><i class="fas fa-times"></i></button>'
    + '</div>');
  $row.attr('data-chip', chip || '');
  $row.find('[data-extra="start"]').val(start);
  $row.find('[data-extra="end"]').val(end);
  $row.find('[data-extra="start-day"]').val(start.slice(0, 10));
  $row.find('[data-extra="end-day"]').val(end.slice(0, 10));
  // A whole day as the first date, unless the date comes with its hours (a date of the mail)
  if (!chip && $form.find('input[name="fullday"]').is(':checked')) {
    $row.addClass('is-fullday').find('[data-extra="fullday"]').prop('checked', true);
  }
  $row.insertBefore($form.find('.dolical-form__add-date'));
};

/**
 * « All day » of another date : its days shown instead of its hours, the day kept from one to the other.
 *
 * @returns {void}
 */
window.dolical.eventform.extraFullday = function() {
  const $row = $(this).closest('.dolical-form__extra');
  const on   = this.checked;
  ['start', 'end'].forEach(function(edge) {
    const $at  = $row.find('[data-extra="' + edge + '"]');
    const $day = $row.find('[data-extra="' + edge + '-day"]');
    if (on) {
      $day.val(($at.val() || '').slice(0, 10));
    } else if ($day.val()) {
      $at.val($day.val() + 'T' + (($at.val() || '').slice(11) || '09:00'));
    }
  });
  $row.toggleClass('is-fullday', on).attr('data-chip', '');
  window.dolical.eventform.datesChanged($row.closest('.dolical-form'));
};

/**
 * The dates changed : the chips of the mail lit as chosen ; several dates are offered to confirm, never repeated.
 *
 * @param   {jQuery} $form Form
 * @returns {void}
 */
window.dolical.eventform.datesChanged = function($form) {
  const $rows   = $form.find('.dolical-form__extra');
  const chosen  = {};
  const mainKey = $form.find('.dolical-form__dates').attr('data-chip') || '';
  if (mainKey !== '') {
    chosen[mainKey] = true;
  }
  $rows.each(function() {
    if ($(this).attr('data-chip')) {
      chosen[$(this).attr('data-chip')] = true;
    }
  });
  $form.find('.dolical-form__maildate').each(function() {
    $(this).toggleClass('is-active', chosen[$(this).attr('data-index')] === true);
  });

  const several = $rows.length > 0;
  $form.toggleClass('has-extra-dates', several);
  // Turned to several dates : offered to confirm (the box stays as the user leaves it afterwards)
  if (several && !$form.data('several')) {
    $form.find('input[name="tentative"]').prop('checked', true);
  }
  $form.data('several', several);
  const $recurrence = $form.find('select[name="recurrence"]');
  if (several && $recurrence.val() !== '') {
    $recurrence.val('').trigger('change');
  }
  $recurrence.prop('disabled', several);
};

/**
 * Remember the start before it changes, to move the end with it.
 *
 * @returns {void}
 */
window.dolical.eventform.rememberStart = function() {
  $(this).attr('data-previous', $(this).val());
};

/**
 * Moving the start moves the end too : the duration stays.
 *
 * @returns {void}
 */
window.dolical.eventform.keepDuration = function() {
  const $input   = $(this);
  const $form    = $input.closest('.dolical-form');
  const isDay    = $input.attr('name') === 'start_day';
  const $end     = $form.find(isDay ? 'input[name="end_day"]' : 'input[name="end_at"]');
  const previous = new Date($input.attr('data-previous') || '');
  const current  = new Date($input.val());
  const end      = new Date($end.val());

  $input.attr('data-previous', $input.val());
  if (isNaN(previous) || isNaN(current) || isNaN(end)) {
    return;
  }
  const moved = new Date(end.getTime() + (current - previous));
  const pad   = function(value) {
    return String(value).padStart(2, '0');
  };
  let value = moved.getFullYear() + '-' + pad(moved.getMonth() + 1) + '-' + pad(moved.getDate());
  if (!isDay) {
    value += 'T' + pad(moved.getHours()) + ':' + pad(moved.getMinutes());
  }
  $end.val(value);
};

/**
 * The description was typed in : it will be saved.
 *
 * @returns {void}
 */
window.dolical.eventform.noteChanged = function() {
  $(this).closest('.dolical-form').find('input[name="note_changed"]').val('1');
};

/**
 * Tell how many files are waiting to be sent.
 *
 * @returns {void}
 */
window.dolical.eventform.filesChanged = function() {
  const $text = $(this).closest('.dolical-dropzone').find('.dolical-dropzone__text');
  $text.text(this.files.length ? window.dolical.util.t('filesSelected', this.files.length) : $text.attr('data-empty'));
};

/**
 * Files dragged over the drop zone.
 *
 * @param   {Event} event Drag
 * @returns {void}
 */
window.dolical.eventform.dragOver = function(event) {
  event.preventDefault();
  $(this).addClass('is-drop-target');
};

/**
 * Files no longer over the drop zone.
 *
 * @returns {void}
 */
window.dolical.eventform.dragLeave = function() {
  $(this).removeClass('is-drop-target');
};

/**
 * Files dropped on the drop zone : they join the field, sent with the form.
 *
 * @param   {Event} event Drop
 * @returns {void}
 */
window.dolical.eventform.drop = function(event) {
  const transfer = event.originalEvent && event.originalEvent.dataTransfer;
  if (!transfer || !transfer.files || !transfer.files.length) {
    return;
  }
  event.preventDefault();
  const input = $(this).find('.dolical-form__files')[0];
  try {
    const merged = new DataTransfer();
    Array.prototype.forEach.call(input.files, function(file) {
      merged.items.add(file);
    });
    Array.prototype.forEach.call(transfer.files, function(file) {
      merged.items.add(file);
    });
    input.files = merged.files;
  } catch (error) {
    input.files = transfer.files;
  }
  $(input).trigger('change');
};

/**
 * Delete an attachment already saved, from the form.
 *
 * @returns {void}
 */
window.dolical.eventform.deleteFile = function() {
  const U     = window.dolical.util;
  const $item = $(this).closest('.dolical-file');
  const id    = parseInt($(this).closest('.dolical-files').attr('data-id'), 10);
  const file  = $(this).attr('data-file');
  if (!id) {
    return;
  }
  U.ask({title: U.t('deleteFileTitle'), text: U.t('confirmFile'), confirm: U.t('deleteFile'), danger: true}, function() {
    U.post({action: 'deletefile', id: id, file: file}).done(function(response) {
      if (response.success) {
        $item.remove();
        $(document).trigger('dolical:changed', [id]);
      }
    });
  });
};

/**
 * Enter in the title, or Ctrl+Enter anywhere, saves.
 *
 * @param   {KeyboardEvent} event Key
 * @returns {void}
 */
window.dolical.eventform.keyboard = function(event) {
  if (event.key !== 'Enter') {
    return;
  }
  if ((event.ctrlKey || event.metaKey) || $(event.target).is('input[name="label"]')) {
    event.preventDefault();
    window.dolical.eventform.submit();
  }
};

/**
 * Show an error under the form.
 *
 * @param   {String} message Message
 * @returns {void}
 */
window.dolical.eventform.error = function(message) {
  $('#dolical-event-modal .dolical-form__error').text(message);
};

/**
 * Save the event.
 *
 * @returns {void}
 */
window.dolical.eventform.submit = function() {
  const F       = window.dolical.eventform;
  const U       = window.dolical.util;
  const $modal  = $('#dolical-event-modal');
  const $form   = $modal.find('.dolical-form');
  const $submit = $modal.find('.dolical-form__submit');
  const form    = $form[0];

  if (!form || $submit.prop('disabled')) {
    return;
  }

  const label = $form.find('input[name="label"]').val().trim();
  if (label === '') {
    F.error(U.t('labelRequired'));
    $form.find('input[name="label"]').trigger('focus');
    return;
  }

  // The inputs give local dates, the server stores timestamps
  const period = F.period($form);
  if (!period) {
    F.error(U.t('datesInvalid'));
    return;
  }
  const fullday = period.fullday;
  const start   = period.start;
  const end     = period.end;

  // The other dates, each with its own « all day » and « to confirm »
  const extras = [];
  let badExtra = false;
  $form.find('.dolical-form__extra').each(function() {
    const allDay = $(this).find('[data-extra="fullday"]').is(':checked');
    const from   = new Date(allDay ? $(this).find('[data-extra="start-day"]').val() + 'T00:00:00' : $(this).find('[data-extra="start"]').val());
    const to     = new Date(allDay ? $(this).find('[data-extra="end-day"]').val() + 'T23:59:59' : $(this).find('[data-extra="end"]').val());
    if (isNaN(from) || isNaN(to) || to < from) {
      badExtra = true;
      return false;
    }
    extras.push({from: from, to: to, fullday: allDay, tentative: $(this).find('[data-extra="tentative"]').is(':checked')});
  });
  if (badExtra) {
    F.error(U.t('datesInvalid'));
    return;
  }

  const id   = parseInt($form.attr('data-id'), 10) || 0;
  const data = new FormData(form);
  ['start_at', 'end_at', 'start_day', 'end_day', 'fullday', 'busy', 'recur_until_day'].forEach(function(name) {
    data.delete(name);
  });
  data.append('action', 'save');
  data.append('id', id);
  data.append('start', Math.floor(start.getTime() / 1000));
  data.append('end', Math.floor(end.getTime() / 1000));
  data.append('fullday', fullday ? 1 : 0);
  data.append('busy', $form.find('input[name="busy"]').is(':checked') ? 1 : 0);
  extras.forEach(function(extra) {
    data.append('extra_start[]', Math.floor(extra.from.getTime() / 1000));
    data.append('extra_end[]', Math.floor(extra.to.getTime() / 1000));
    data.append('extra_fullday[]', extra.fullday ? 1 : 0);
    data.append('extra_tentative[]', extra.tentative ? 1 : 0);
  });

  // The last day of a series, at noon : the same day in every time zone close to the one of the user
  const recurrence = $form.find('select[name="recurrence"]').val() || '';
  const untilDay   = $form.find('input[name="recur_until_day"]').val() || '';
  if (recurrence !== '') {
    const until = new Date(untilDay + 'T12:00:00');
    if (isNaN(until) || until < U.startOfDay(start)) {
      F.error(U.t('untilInvalid'));
      return;
    }
    data.append('recur_until', Math.floor(until.getTime() / 1000));
  }

  F.error('');

  // A date of a series : this one, the following ones or all of them. A new rule is never for one date only
  const series = parseInt($form.attr('data-series'), 10) || 0;
  if (series > 1) {
    const ruleChanged = recurrence !== ($form.attr('data-recurrence') || '') || (recurrence !== '' && untilDay !== $form.attr('data-until'));
    U.chooseScope('edit', !ruleChanged, function(scope) {
      data.append('recur_scope', scope);
      F.send(data, id);
    });
    return;
  }
  F.send(data, id);
};

/**
 * Send the form and tell what was done.
 *
 * @param   {FormData} data Fields of the form
 * @param   {Number}   id   Event, 0 when new
 * @returns {void}
 */
window.dolical.eventform.send = function(data, id) {
  const F       = window.dolical.eventform;
  const U       = window.dolical.util;
  const $modal  = $('#dolical-event-modal');
  const $submit = $modal.find('.dolical-form__submit');

  $submit.prop('disabled', true);

  U.post(data).done(function(response) {
    if (!response || !response.success) {
      F.error((response && response.error) || U.t('error'));
      return;
    }
    $modal.removeClass('modal-active');
    $modal.find('.modal-container').empty();
    let message = '';
    if (response.warnings && response.warnings.length) {
      message = response.warnings.join(' ');
      U.toast(message, 'error');
    } else {
      message = U.t('saved');
      if (response.created > 0) {
        message += ' · ' + U.t(response.truncated ? 'occurrencesTruncated' : 'occurrencesCreated', response.created + 1);
      }
      if (response.slots > 0) {
        message += ' · ' + U.t('slotsCreated', response.slots + 1);
      }
      if (response.sent > 0) {
        message += ' · ' + U.t('invitationsSent', response.sent);
      }
      if (response.cancelled > 0) {
        message += ' · ' + U.t('cancelledSent', response.cancelled);
      }
      U.toast(message);
    }
    // The message goes with the change : the page of one event gives it to the window that embeds it
    $(document).trigger('dolical:changed', [response.id, message]);
    // A new event shows its card at once : the people and documents can be checked there
    if (!id && window.dolical.calendar.state) {
      window.dolical.eventcard.open(response.id, null);
    }
  }).fail(function(xhr) {
    F.error(U.xhrError(xhr));
  }).always(function() {
    $submit.prop('disabled', false);
  });
};
