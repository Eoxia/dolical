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
 * \file    js/modules/calendar.js
 * \ingroup dolical
 * \brief   Calendar of DoliCal : the native events of the selected people drawn by day (one column per
 *          person), week, team (one row per person), month and list, with drag and drop to move them in time
 *          or from a person to another.
 */

'use strict';

window.dolical.calendar = {};

/**
 * Height of an hour in the day and week grids, in pixels.
 *
 * @type {Number}
 */
window.dolical.calendar.HOUR_HEIGHT = 48;

/**
 * Step of the drag and drop in the grids, in minutes.
 *
 * @type {Number}
 */
window.dolical.calendar.SNAP = 15;

/**
 * State of the calendar : view, displayed day, people, filters and loaded events.
 *
 * @type {Object|null}
 */
window.dolical.calendar.state = null;

/**
 * Calendar init : only on the page of the calendar.
 *
 * @returns {void}
 */
window.dolical.calendar.init = function() {
  if (!$('.dolical-app').length) {
    return;
  }

  const U      = window.dolical.util;
  const C      = window.dolical.calendar;
  const config = U.config();
  const saved  = U.load('state') || {};
  const known  = config.users.map(function(person) {
    return person.id;
  });

  let users = (saved.users || []).filter(function(id) {
    return known.indexOf(id) !== -1;
  });
  if (!users.length || !config.canReadAll) {
    users = [config.currentUser];
  }

  let view = saved.view || config.defaultView;
  if (['day', 'week', 'team', 'month', 'list'].indexOf(view) === -1) {
    view = 'week';
  }
  // A phone has no room for seven columns
  if (window.innerWidth < 768 && (view === 'week' || view === 'team')) {
    view = 'list';
  }

  // A shared link chooses the people and the view : ?users=1,95&view=team
  const query  = new URLSearchParams(window.location.search);
  const wanted = (query.get('users') || '').split(',').map(Number).filter(function(id) {
    return known.indexOf(id) !== -1;
  });
  if (wanted.length && config.canReadAll) {
    users = wanted;
  }
  if (['day', 'week', 'team', 'month', 'list'].indexOf(query.get('view')) !== -1) {
    view = query.get('view');
  }

  const savedFilters = saved.filters || {};
  C.state = {
    view: view,
    anchor: U.startOfDay(new Date()),
    minicalMonth: null,
    users: users,
    filters: {
      hideauto: savedFilters.hideauto !== undefined ? savedFilters.hideauto : config.hideAuto,
      status: savedFilters.status || 'all',
      types: savedFilters.types || '',
      search: ''
    },
    events: [],
    xhr: null,
    scrollTop: null
  };
  C.usersById = {};
  config.users.forEach(function(person) {
    C.usersById[person.id] = person;
  });

  C.syncSidebar();
  U.applyColors($('.dolical-sidebar'));
  C.event();
  C.render();
  C.load();

  if (config.openEvent > 0) {
    window.dolical.eventcard.open(config.openEvent, null);
  }
  if (config.openCreate && config.canCreate) {
    window.dolical.eventform.open(config.createParams);
  }

  // The agenda of a team changes while it is open : refresh it quietly
  setInterval(function() {
    if (document.visibilityState === 'visible' && !C.drag && !$('#dolical-event-modal').hasClass('modal-active')) {
      C.load(true);
    }
  }, 120000);
};

/**
 * Calendar events.
 *
 * @returns {void}
 */
window.dolical.calendar.event = function() {
  const C = window.dolical.calendar;

  $(document).on('click', '.dolical-toolbar [data-nav]', C.navigate);
  $(document).on('click', '.dolical-toolbar [data-view]', C.changeView);
  $(document).on('click', '.dolical-sidebar-toggle', C.toggleSidebar);
  $(document).on('click', '.dolical-app [data-action="create"]', C.createFromButton);

  $(document).on('change', '.dolical-picker__check', C.pickFromList);
  $(document).on('input', '.dolical-picker__search', C.searchPeople);
  $(document).on('click', '.dolical-picker [data-pick]', C.pickShortcut);
  $(document).on('change', '.dolical-picker__group', C.pickGroup);
  $(document).on('change', '.dolical-filter', C.changeFilter);
  $(document).on('input', '.dolical-filter[data-filter="search"]', C.changeSearch);

  $(document).on('click', '.dolical-minical [data-minical-day]', C.minicalPick);
  $(document).on('click', '.dolical-minical [data-minical-nav]', C.minicalNavigate);

  $(document).on('click', '.dolical-calendar [data-goto]', C.gotoDay);
  $(document).on('click', '.dolical-calendar .dolical-event', C.clickEvent);
  $(document).on('click', '.dolical-calendar .ra-tg__col, .dolical-calendar .ra-tg__allday-col, .dolical-calendar .ra-month__cell, .dolical-calendar .ra-team__cell', C.clickEmpty);
  $(document).on('pointerdown', '.dolical-calendar .dolical-event.is-editable', C.dragStart);

  $(document).on('keydown', C.keyboard);
  $(document).on('dolical:changed', function() {
    C.load();
  });

  let resizeTimer = null;
  $(window).on('resize', function() {
    clearTimeout(resizeTimer);
    resizeTimer = setTimeout(C.render, 150);
  });
};

/*
 * State and loading
 */

/**
 * Remember the view, the people and the filters for the next visit.
 *
 * @returns {void}
 */
window.dolical.calendar.persist = function() {
  const state = window.dolical.calendar.state;
  window.dolical.util.save('state', {
    view: state.view,
    users: state.users,
    filters: {hideauto: state.filters.hideauto, status: state.filters.status, types: state.filters.types}
  });
};

/**
 * Period displayed by the current view.
 *
 * @returns {{start: Date, end: Date, days: Date[]}}
 */
window.dolical.calendar.range = function() {
  const U      = window.dolical.util;
  const state  = window.dolical.calendar.state;
  const config = U.config();
  let start;
  let length;

  switch (state.view) {
    case 'day':
      start  = U.startOfDay(state.anchor);
      length = 1;
      break;
    case 'month':
      start  = U.startOfWeek(new Date(state.anchor.getFullYear(), state.anchor.getMonth(), 1));
      length = 42;
      break;
    case 'list':
      start  = U.startOfWeek(state.anchor);
      length = 14;
      break;
    default:
      start  = U.startOfWeek(state.anchor);
      length = 7;
  }

  const days = [];
  for (let i = 0; i < length; i++) {
    const day = U.addDays(start, i);
    // Weekends hidden in the grids, never in the day view nor in the list
    if (!config.showWeekends && ['week', 'team', 'month'].indexOf(state.view) !== -1 && (day.getDay() === 0 || day.getDay() === 6)) {
      continue;
    }
    days.push(day);
  }

  return {start: start, end: U.addDays(start, length), days: days};
};

/**
 * Load the events of the period for the selected people, then draw them.
 *
 * @param   {Boolean} quiet Without the loader, for the periodic refresh
 * @returns {void}
 */
window.dolical.calendar.load = function(quiet) {
  const U      = window.dolical.util;
  const C      = window.dolical.calendar;
  const state  = C.state;
  const config = U.config();

  if (state.xhr) {
    state.xhr.abort();
  }
  if (!state.users.length) {
    state.events = [];
    C.render();
    return;
  }

  const range = C.range();
  if (!quiet) {
    $('.dolical-app').addClass('is-loading');
  }

  state.xhr = $.getJSON(config.urls.events, {
    start: Math.floor(range.start.getTime() / 1000),
    end: Math.floor(range.end.getTime() / 1000),
    users: state.users.join(','),
    hideauto: state.filters.hideauto ? 1 : 0,
    status: state.filters.status,
    types: state.filters.types,
    search: state.filters.search
  }).done(function(response) {
    if (!response || !response.success) {
      U.toast(U.t('loadError'), 'error');
      return;
    }
    state.events = response.events.map(C.normalize);
    C.render();
    $(document).trigger('dolical:loaded');
  }).fail(function(xhr, status) {
    if (status !== 'abort') {
      U.toast(U.t('loadError'), 'error');
    }
  }).always(function() {
    $('.dolical-app').removeClass('is-loading');
  });
};

/**
 * Event of the endpoint turned into dates the calendar can work with.
 *
 * @param   {Object} event Event of ajax/events.php
 * @returns {Object}
 */
window.dolical.calendar.normalize = function(event) {
  event.s = new Date(event.start * 1000);
  event.e = new Date(Math.max(event.end, event.start) * 1000);
  // Several days, all day or not, read better as a band than as a column cut in pieces
  event.band = event.allDay || (event.e - event.s) >= 86400000;
  return event;
};

/**
 * Loaded event by its id.
 *
 * @param   {Number} id Id of the event
 * @returns {Object|undefined}
 */
window.dolical.calendar.findEvent = function(id) {
  return window.dolical.calendar.state.events.find(function(event) {
    return event.id === id;
  });
};

/**
 * Whether an event is on a day.
 *
 * @param   {Object} event Event
 * @param   {Date}   day   Midnight of the day
 * @returns {Boolean}
 */
window.dolical.calendar.onDay = function(event, day) {
  const next = window.dolical.util.addDays(day, 1);
  if (event.s.getTime() === event.e.getTime()) {
    return event.s >= day && event.s < next;
  }
  return event.s < next && event.e > day;
};

/**
 * Events of a day, for one person or for all the selected people, the bands first then by hour.
 *
 * @param   {Date}   day    Midnight of the day
 * @param   {Number} userId Person, 0 for everyone selected
 * @returns {Object[]}
 */
window.dolical.calendar.eventsOf = function(day, userId) {
  const C = window.dolical.calendar;
  return C.state.events.filter(function(event) {
    return C.onDay(event, day) && (!userId || event.userIds.indexOf(userId) !== -1);
  }).sort(function(a, b) {
    return (b.band - a.band) || (a.s - b.s) || (a.id - b.id);
  });
};

/**
 * Color of an event : the person of the column, else the first selected person taking part, the owner first.
 *
 * @param   {Object} event  Event
 * @param   {Number} userId Person of the column, 0 when the column is not one person
 * @returns {String}
 */
window.dolical.calendar.colorOf = function(event, userId) {
  const C = window.dolical.calendar;
  if (userId && C.usersById[userId]) {
    return C.usersById[userId].color;
  }
  const people = [event.ownerId].concat(event.userIds);
  for (let i = 0; i < people.length; i++) {
    if (C.state.users.indexOf(people[i]) !== -1 && C.usersById[people[i]]) {
      return C.usersById[people[i]].color;
    }
  }
  return '#63acc9';
};

/*
 * Rendering
 */

/**
 * Draw the toolbar, the mini calendar and the current view.
 *
 * @returns {void}
 */
window.dolical.calendar.render = function() {
  const C = window.dolical.calendar;
  if (!C.state) {
    return;
  }

  const $calendar = $('.dolical-calendar');
  const $scroll   = $calendar.find('.ra-tg__scroll');
  if ($scroll.length) {
    C.state.scrollTop = $scroll.scrollTop();
  }

  C.renderToolbar();
  C.renderMinical();

  if (!C.state.users.length) {
    $calendar.html('<div class="ra-empty"><i class="far fa-user-circle"></i><span>' + window.dolical.util.esc(window.dolical.util.t('noPeople')) + '</span></div>');
    return;
  }

  switch (C.state.view) {
    case 'day':
      C.renderDay($calendar);
      break;
    case 'team':
      C.renderTeam($calendar);
      break;
    case 'month':
      C.renderMonth($calendar);
      break;
    case 'list':
      C.renderList($calendar);
      break;
    default:
      C.renderWeek($calendar);
  }
};

/**
 * Title of the period and selected view.
 *
 * @returns {void}
 */
window.dolical.calendar.renderToolbar = function() {
  const U     = window.dolical.util;
  const C     = window.dolical.calendar;
  const state = C.state;
  const range = C.range();
  let title;

  if (state.view === 'day') {
    title = U.format(state.anchor, {weekday: 'long', day: 'numeric', month: 'long', year: 'numeric'});
  } else if (state.view === 'month') {
    title = U.format(state.anchor, {month: 'long', year: 'numeric'});
  } else {
    const last = U.addDays(range.end, -1);
    title = U.format(range.start, {day: 'numeric', month: 'short'}) + ' – ' + U.format(last, {day: 'numeric', month: 'short', year: 'numeric'});
    if (state.view !== 'list') {
      title += ' · ' + U.t('week') + ' ' + U.isoWeek(range.start);
    }
  }

  // Capital on the first letter only : "Octobre 2026", but "26 oct. – 1 nov."
  $('.dolical-toolbar__period').text(title.charAt(0).toUpperCase() + title.slice(1));
  $('.dolical-toolbar [data-view]').each(function() {
    $(this).toggleClass('dolical-btn--on', $(this).attr('data-view') === state.view);
  });
  $('.dolical-picker__count').text(state.users.length);
};

/**
 * Mini calendar of the side bar, the displayed period highlighted.
 *
 * @returns {void}
 */
window.dolical.calendar.renderMinical = function() {
  const U     = window.dolical.util;
  const C     = window.dolical.calendar;
  const state = C.state;
  const month = state.minicalMonth || new Date(state.anchor.getFullYear(), state.anchor.getMonth(), 1);
  const first = U.startOfWeek(month);
  const range = C.range();
  const today = U.startOfDay(new Date());

  let html = '<div class="ra-minical__head">';
  html += '<button type="button" class="dolical-icon-btn" data-minical-nav="-1"><i class="fas fa-chevron-left"></i></button>';
  html += '<span class="ra-minical__title">' + U.esc(U.format(month, {month: 'long', year: 'numeric'})) + '</span>';
  html += '<button type="button" class="dolical-icon-btn" data-minical-nav="1"><i class="fas fa-chevron-right"></i></button>';
  html += '</div><div class="ra-minical__grid">';
  for (let i = 0; i < 7; i++) {
    html += '<span class="ra-minical__dow">' + U.esc(U.format(U.addDays(first, i), {weekday: 'narrow'})) + '</span>';
  }
  for (let i = 0; i < 42; i++) {
    const day     = U.addDays(first, i);
    const classes = ['ra-minical__day'];
    if (day.getMonth() !== month.getMonth()) {
      classes.push('is-outside');
    }
    if (U.sameDay(day, today)) {
      classes.push('is-today');
    }
    if (day >= range.start && day < range.end && state.view !== 'month') {
      classes.push('is-selected');
    }
    html += '<button type="button" class="' + classes.join(' ') + '" data-minical-day="' + U.dayKey(day) + '">' + day.getDate() + '</button>';
  }
  html += '</div>';

  $('.dolical-minical').html(html);
};

/**
 * Head of a column of the grids : day of the week and number, a link to the day.
 *
 * @param   {Date} day Day
 * @returns {String}
 */
window.dolical.calendar.dayHead = function(day) {
  const U = window.dolical.util;
  return '<button type="button" class="ra-dayhead" data-goto="' + U.dayKey(day) + '">'
    + '<span class="ra-dayhead__dow">' + U.esc(U.format(day, {weekday: 'short'})) + '</span>'
    + '<span class="ra-dayhead__num">' + day.getDate() + '</span></button>';
};

/**
 * Head of a column of a person.
 *
 * @param   {Number} userId Person
 * @returns {String}
 */
window.dolical.calendar.personHead = function(userId) {
  const U      = window.dolical.util;
  const person = window.dolical.calendar.usersById[userId] || {name: '#' + userId, initials: '?', color: '#94a3b8'};
  return '<span class="ra-personhead"><span class="dolical-avatar dolical-avatar--small" style="--ra-color:' + person.color + '">' + U.esc(person.initials) + '</span>'
    + '<span class="ra-personhead__name">' + U.esc(person.name) + '</span></span>';
};

/**
 * Week view : the seven days side by side, every selected person in the same grid.
 *
 * @param   {jQuery} $calendar Container
 * @returns {void}
 */
window.dolical.calendar.renderWeek = function($calendar) {
  const C = window.dolical.calendar;
  const U = window.dolical.util;
  const columns = C.range().days.map(function(day) {
    return {day: day, user: 0, head: C.dayHead(day), today: U.sameDay(day, new Date())};
  });
  C.renderGrid($calendar, columns, 'week');
};

/**
 * Day view : one column per selected person, the agendas side by side.
 *
 * @param   {jQuery} $calendar Container
 * @returns {void}
 */
window.dolical.calendar.renderDay = function($calendar) {
  const C     = window.dolical.calendar;
  const U     = window.dolical.util;
  const day   = U.startOfDay(C.state.anchor);
  const today = U.sameDay(day, new Date());
  let columns;

  if (C.state.users.length > 1) {
    columns = C.state.users.map(function(userId) {
      return {day: day, user: userId, head: C.personHead(userId), today: today};
    });
  } else {
    columns = [{day: day, user: C.state.users[0], head: C.dayHead(day), today: today}];
  }
  C.renderGrid($calendar, columns, 'day');
};

/**
 * Grid of hours with a column per day or per person.
 *
 * @param   {jQuery}   $calendar Container
 * @param   {Object[]} columns   [{day, user, head, today}]
 * @param   {String}   mode      'week' or 'day'
 * @returns {void}
 */
window.dolical.calendar.renderGrid = function($calendar, columns, mode) {
  const C      = window.dolical.calendar;
  const U      = window.dolical.util;
  const config = U.config();
  const H      = C.HOUR_HEIGHT;
  const now    = new Date();

  let head   = '<div class="ra-tg__head"><div class="ra-tg__gutter"></div>';
  let allday = '<div class="ra-tg__allday"><div class="ra-tg__gutter">' + U.esc(U.t('allDay')) + '</div>';
  let body   = '<div class="ra-tg__hours">';
  for (let hour = 0; hour < 24; hour++) {
    body += '<span class="ra-tg__hour">' + (hour ? U.time(new Date(2000, 0, 1, hour)) : '') + '</span>';
  }
  body += '</div>';

  columns.forEach(function(column) {
    const userId  = mode === 'day' ? column.user : 0;
    const events  = C.eventsOf(column.day, userId);
    const weekend = column.day.getDay() === 0 || column.day.getDay() === 6;
    const key     = U.dayKey(column.day);

    head += '<div class="ra-tg__colhead' + (column.today ? ' is-today' : '') + '">' + column.head + '</div>';

    allday += '<div class="ra-tg__allday-col ra-droppable" data-date="' + key + '" data-user="' + userId + '">';
    events.filter(function(event) {
      return event.band;
    }).forEach(function(event) {
      allday += C.chipHtml(event, userId, false);
    });
    allday += '</div>';

    body += '<div class="ra-tg__col' + (column.today ? ' is-today' : '') + (weekend ? ' is-weekend' : '') + '" data-date="' + key + '" data-user="' + userId + '">';
    body += '<div class="ra-tg__off" style="--ra-top:0px;--ra-height:' + (config.dayStart * H) + 'px"></div>';
    body += '<div class="ra-tg__off" style="--ra-top:' + (config.dayEnd * H) + 'px;--ra-height:' + ((24 - config.dayEnd) * H) + 'px"></div>';
    C.layout(events.filter(function(event) {
      return !event.band;
    }), column.day).forEach(function(item) {
      body += C.blockHtml(item, userId);
    });
    if (column.today) {
      body += '<div class="ra-tg__now" style="--ra-top:' + ((now.getHours() * 60 + now.getMinutes()) * H / 60) + 'px"></div>';
    }
    body += '</div>';
  });

  head   += '</div>';
  allday += '</div>';

  // Heads in the scrolling box, sticky : they stay aligned with the columns, scroll bar or not
  const html = '<div class="ra-tg ra-tg--' + mode + '" style="--ra-cols:' + columns.length + '"><div class="ra-tg__scroll">'
    + '<div class="ra-tg__sticky">' + head + allday + '</div><div class="ra-tg__body">' + body + '</div></div></div>';
  $calendar.html(html);

  // Open on the working hours, or on the current hour when it is later, and keep the place on a refresh
  const $scroll = $calendar.find('.ra-tg__scroll');
  if (C.state.scrollTop !== null) {
    $scroll.scrollTop(C.state.scrollTop);
  } else {
    const showsToday = columns.some(function(column) {
      return column.today;
    });
    const hour = showsToday ? Math.max(config.dayStart, Math.min(now.getHours() - 1, config.dayEnd - 4)) : config.dayStart;
    // A few pixels above the hour : its label sits on the line and must not hide under the sticky heads
    $scroll.scrollTop(Math.max(0, hour * H - 12));
    C.state.scrollTop = $scroll.scrollTop();
  }
};

/**
 * Minute of the day of a date, from the start of a day : 0 before it, 1440 after it.
 *
 * @param   {Date} date Date
 * @param   {Date} day  Midnight of the day
 * @returns {Number}
 */
window.dolical.calendar.minuteOf = function(date, day) {
  const U = window.dolical.util;
  if (date < day) {
    return 0;
  }
  if (!U.sameDay(date, day)) {
    return 1440;
  }
  // From the clock, not from a difference of timestamps : a change of summer time would shift the grid by an hour
  return date.getHours() * 60 + date.getMinutes();
};

/**
 * Place the events of a day column : overlapping events share the width in lanes.
 *
 * @param   {Object[]} events Events of the column, not bands
 * @param   {Date}     day    Midnight of the day
 * @returns {Object[]}        [{event, top, bottom, lane, lanes}] in minutes
 */
window.dolical.calendar.layout = function(events, day) {
  const C     = window.dolical.calendar;
  const items = events.map(function(event) {
    const top    = C.minuteOf(event.s, day);
    const bottom = Math.max(C.minuteOf(event.e, day), top + 20);
    return {event: event, top: top, bottom: Math.min(bottom, 1440)};
  }).sort(function(a, b) {
    return (a.top - b.top) || (b.bottom - a.bottom);
  });

  const placed  = [];
  let cluster   = [];
  let clusterEnd = -1;
  const flush = function() {
    const lanes = [];
    cluster.forEach(function(item) {
      let lane = lanes.findIndex(function(end) {
        return end <= item.top;
      });
      if (lane === -1) {
        lane = lanes.length;
        lanes.push(item.bottom);
      } else {
        lanes[lane] = item.bottom;
      }
      item.lane = lane;
    });
    cluster.forEach(function(item) {
      item.lanes = lanes.length;
      placed.push(item);
    });
    cluster = [];
  };

  items.forEach(function(item) {
    if (cluster.length && item.top >= clusterEnd) {
      flush();
      clusterEnd = -1;
    }
    cluster.push(item);
    clusterEnd = Math.max(clusterEnd, item.bottom);
  });
  if (cluster.length) {
    flush();
  }

  return placed;
};

/**
 * Dots of the other selected people taking part in an event.
 *
 * @param   {Object} event  Event
 * @param   {Number} userId Person already shown by the color
 * @returns {String}
 */
window.dolical.calendar.peopleDots = function(event, userId) {
  const C = window.dolical.calendar;
  const U = window.dolical.util;
  let html = '';
  event.userIds.forEach(function(id) {
    if (id !== userId && C.state.users.indexOf(id) !== -1 && C.usersById[id]) {
      html += '<span class="ra-dot" style="--ra-color:' + C.usersById[id].color + '" title="' + U.esc(C.usersById[id].name) + '"></span>';
    }
  });
  if (event.contacts > 0) {
    html += '<span class="ra-contacts" title="' + U.esc(U.t('contacts', event.contacts)) + '"><i class="far fa-address-book"></i>' + event.contacts + '</span>';
  }
  html += window.dolical.calendar.tallyHtml(event);
  return html ? '<span class="ra-people">' + html + '</span>' : '';
};

/**
 * Count of the answers of the guests : accepted, maybe, declined, waiting.
 *
 * @param   {Object} event Event
 * @returns {String}
 */
window.dolical.calendar.tallyHtml = function(event) {
  const U     = window.dolical.util;
  const tally = event.tally || {};
  const parts = [
    ['yes', 'fas fa-check', 'answerYes'],
    ['maybe', 'fas fa-question', 'answerMaybe'],
    ['no', 'fas fa-times', 'answerNo'],
    ['pending', 'far fa-clock', 'answerPending']
  ];
  let html = '';
  parts.forEach(function(part) {
    if (tally[part[0]] > 0) {
      html += '<span class="ra-tally__' + part[0] + '" title="' + U.esc(U.t(part[2])) + '"><i class="' + part[1] + '"></i>' + tally[part[0]] + '</span>';
    }
  });
  return html ? '<span class="ra-tally">' + html + '</span>' : '';
};

/**
 * Answer of a person to an event : 1 accepted, 2 maybe, -1 declined, 0 none. The organizer goes.
 *
 * @param   {Object} event  Event
 * @param   {Number} userId Person
 * @returns {Number|null}   Null when no person is given
 */
window.dolical.calendar.answerOf = function(event, userId) {
  if (!userId) {
    return null;
  }
  if (userId === event.ownerId) {
    return 1;
  }
  const answer = (event.answers || {})[userId];
  return answer === undefined ? 0 : answer;
};

/**
 * Classes shared by every drawing of an event, the answer of the person of the column included.
 *
 * @param   {Object} event  Event
 * @param   {Number} userId Person whose agenda shows the event, the first selected one when 0
 * @returns {String}
 */
window.dolical.calendar.stateClasses = function(event, userId) {
  const answer = window.dolical.calendar.answerOf(event, userId || window.dolical.calendar.firstSelectedOf(event));
  let classes  = '';
  if (answer === -1) {
    classes += ' is-declined';
  } else if (answer === 2) {
    classes += ' is-tentative';
  }
  if (event.percent === 100) {
    classes += ' is-done';
  } else if (event.percent >= 0 && event.e < new Date()) {
    classes += ' is-late';
  }
  if (!event.busy) {
    classes += ' is-free';
  }
  if (event.auto) {
    classes += ' is-auto';
  }
  if (event.editable) {
    classes += ' is-editable';
  }
  return classes;
};

/**
 * Event placed in a column of hours.
 *
 * @param   {Object} item   Placed event, from layout()
 * @param   {Number} userId Person of the column, 0 when the column is a day
 * @returns {String}
 */
window.dolical.calendar.blockHtml = function(item, userId) {
  const C      = window.dolical.calendar;
  const U      = window.dolical.util;
  const event  = item.event;
  const H      = C.HOUR_HEIGHT;
  const top    = item.top * H / 60;
  const height = Math.max((item.bottom - item.top) * H / 60, 18);
  const width  = 100 / item.lanes;
  const color  = C.colorOf(event, userId);
  const short  = height < 40;
  const sub    = [event.socName, event.project, event.location].filter(Boolean).join(' · ');

  let html = '<div class="dolical-event ra-block' + C.stateClasses(event, userId) + (short ? ' is-short' : '') + '" data-id="' + event.id + '" data-user="' + userId + '" data-kind="timed"'
    + ' style="--ra-top:' + top + 'px;--ra-height:' + height + 'px;--ra-left:' + (item.lane * width) + '%;--ra-width:' + width + '%;--ra-color:' + color + '"'
    + ' title="' + U.esc(U.time(event.s) + ' – ' + U.time(event.e) + ' ' + event.label) + '">';
  html += '<div class="ra-block__head"><i class="' + U.esc(event.typeIcon) + '"></i><span class="ra-block__time">' + U.esc(U.time(event.s) + ' – ' + U.time(event.e)) + '</span>' + C.flagsHtml(event) + '</div>';
  html += '<div class="ra-block__label">' + U.esc(event.label || U.t('untitled')) + '</div>';
  if (!short && sub) {
    html += '<div class="ra-block__sub">' + U.esc(sub) + '</div>';
  }
  if (!short) {
    html += C.peopleDots(event, userId || C.firstSelectedOf(event));
  }
  if (event.editable) {
    html += '<div class="ra-block__resize"></div>';
  }
  html += '</div>';
  return html;
};

/**
 * First selected person taking part in an event, the one whose color it wears.
 *
 * @param   {Object} event Event
 * @returns {Number}
 */
window.dolical.calendar.firstSelectedOf = function(event) {
  const state  = window.dolical.calendar.state;
  const people = [event.ownerId].concat(event.userIds);
  for (let i = 0; i < people.length; i++) {
    if (state.users.indexOf(people[i]) !== -1) {
      return people[i];
    }
  }
  return 0;
};

/**
 * Event as a line : bands of the grids, cells of the month and of the team.
 *
 * @param   {Object}  event    Event
 * @param   {Number}  userId   Person of the cell, 0 when the cell is not one person
 * @param   {Boolean} withTime Whether to show the hour of a timed event
 * @returns {String}
 */
window.dolical.calendar.chipHtml = function(event, userId, withTime) {
  const C = window.dolical.calendar;
  const U = window.dolical.util;
  let html = '<div class="dolical-event ra-chip' + (event.band ? ' is-band' : '') + C.stateClasses(event, userId) + '" data-id="' + event.id + '" data-user="' + userId + '" data-kind="chip"'
    + ' style="--ra-color:' + C.colorOf(event, userId) + '" title="' + U.esc(event.label) + '">';
  if (withTime && !event.allDay) {
    html += '<span class="ra-chip__time">' + U.esc(U.time(event.s)) + '</span>';
  }
  html += '<span class="ra-chip__label">' + U.esc(event.label || U.t('untitled')) + '</span>' + C.flagsHtml(event) + '</div>';
  return html;
};

/**
 * Small signs of an event : part of a series, written up.
 *
 * @param   {Object} event Event
 * @returns {String}
 */
window.dolical.calendar.flagsHtml = function(event) {
  const U  = window.dolical.util;
  let html = '';
  if (event.recurring) {
    html += '<i class="fas fa-redo-alt ra-flag" title="' + U.esc(U.t('repeating')) + '"></i>';
  }
  if (event.report) {
    html += '<i class="far fa-file-alt ra-flag" title="' + U.esc(U.t('hasReport')) + '"></i>';
  }
  return html ? '<span class="ra-flags">' + html + '</span>' : '';
};

/**
 * Team view : one row per selected person, the seven days in columns.
 *
 * @param   {jQuery} $calendar Container
 * @returns {void}
 */
window.dolical.calendar.renderTeam = function($calendar) {
  const C     = window.dolical.calendar;
  const U     = window.dolical.util;
  const days  = C.range().days;
  const today = new Date();

  let html = '<div class="ra-team" style="--ra-cols:' + days.length + '"><div class="ra-team__corner"></div>';
  days.forEach(function(day) {
    html += '<div class="ra-team__head' + (U.sameDay(day, today) ? ' is-today' : '') + '">' + C.dayHead(day) + '</div>';
  });
  C.state.users.forEach(function(userId) {
    html += '<div class="ra-team__person">' + C.personHead(userId) + '</div>';
    days.forEach(function(day) {
      html += '<div class="ra-team__cell ra-droppable' + (U.sameDay(day, today) ? ' is-today' : '') + '" data-date="' + U.dayKey(day) + '" data-user="' + userId + '">';
      C.eventsOf(day, userId).forEach(function(event) {
        html += C.chipHtml(event, userId, true);
      });
      html += '</div>';
    });
  });
  html += '</div>';

  $calendar.html(html);
};

/**
 * Month view : six weeks, a few events per day and a link to the rest.
 *
 * @param   {jQuery} $calendar Container
 * @returns {void}
 */
window.dolical.calendar.renderMonth = function($calendar) {
  const C     = window.dolical.calendar;
  const U     = window.dolical.util;
  const days  = C.range().days;
  const month = C.state.anchor.getMonth();
  const today = new Date();
  const max   = 3;
  const cols  = days.length / 6;

  let html = '<div class="ra-month" style="--ra-cols:' + cols + '">';
  days.slice(0, cols).forEach(function(day) {
    html += '<div class="ra-month__dow">' + U.esc(U.format(day, {weekday: 'long'})) + '</div>';
  });
  days.forEach(function(day) {
    const events  = C.eventsOf(day, 0);
    const classes = ['ra-month__cell', 'ra-droppable'];
    if (day.getMonth() !== month) {
      classes.push('is-outside');
    }
    if (U.sameDay(day, today)) {
      classes.push('is-today');
    }
    html += '<div class="' + classes.join(' ') + '" data-date="' + U.dayKey(day) + '" data-user="0">';
    html += '<button type="button" class="ra-month__day" data-goto="' + U.dayKey(day) + '">' + day.getDate() + '</button>';
    events.slice(0, max).forEach(function(event) {
      html += C.chipHtml(event, 0, true);
    });
    if (events.length > max) {
      html += '<button type="button" class="ra-month__more" data-goto="' + U.dayKey(day) + '">' + U.esc(U.t('more', events.length - max)) + '</button>';
    }
    html += '</div>';
  });
  html += '</div>';

  $calendar.html(html);
};

/**
 * List view : the events of two weeks, day by day.
 *
 * @param   {jQuery} $calendar Container
 * @returns {void}
 */
window.dolical.calendar.renderList = function($calendar) {
  const C     = window.dolical.calendar;
  const U     = window.dolical.util;
  const today = new Date();
  let html    = '<div class="ra-list">';
  let empty   = true;

  C.range().days.forEach(function(day) {
    const events = C.eventsOf(day, 0);
    if (!events.length) {
      return;
    }
    empty = false;
    html += '<div class="ra-list__day' + (U.sameDay(day, today) ? ' is-today' : '') + '">';
    html += '<button type="button" class="ra-list__date" data-goto="' + U.dayKey(day) + '"><span class="ra-list__num">' + day.getDate() + '</span>'
      + '<span class="ra-list__dateinfo"><span class="ra-list__dow">' + U.esc(U.format(day, {weekday: 'long'})) + '</span>'
      + '<span class="ra-list__month">' + U.esc(U.format(day, {month: 'short', year: 'numeric'})) + '</span></span></button>';
    html += '<div class="ra-list__rows">';
    events.forEach(function(event) {
      const starts = U.sameDay(event.s, day);
      const ends   = U.sameDay(event.e, day);
      // Over several days : the hour where it starts, the hour where it ends, all day in between
      let when = U.t('allDay');
      if (!event.allDay && starts && ends) {
        when = U.time(event.s) + ' – ' + U.time(event.e);
      } else if (!event.allDay && starts) {
        when = U.time(event.s) + ' →';
      } else if (!event.allDay && ends) {
        when = '→ ' + U.time(event.e);
      }
      const sub  = [event.socName, event.project, event.location].filter(Boolean).join(' · ');
      html += '<div class="dolical-event ra-list__row' + C.stateClasses(event) + '" data-id="' + event.id + '" data-user="0" data-kind="list" style="--ra-color:' + C.colorOf(event, 0) + '">';
      html += '<span class="ra-list__time">' + U.esc(when) + '</span><span class="ra-list__bar"></span>';
      html += '<span class="ra-list__main"><span class="ra-list__label"><i class="' + U.esc(event.typeIcon) + '"></i>' + U.esc(event.label || U.t('untitled')) + '</span>';
      if (sub) {
        html += '<span class="ra-list__sub">' + U.esc(sub) + '</span>';
      }
      html += '</span>' + C.peopleDots(event, 0) + '</div>';
    });
    html += '</div></div>';
  });

  if (empty) {
    html += '<div class="ra-empty"><i class="far fa-calendar"></i><span>' + U.esc(U.t('noEvent')) + '</span></div>';
  }
  html += '</div>';

  $calendar.html(html);
};

/*
 * Navigation and people
 */

/**
 * Previous, next, today, refresh.
 *
 * @returns {void}
 */
window.dolical.calendar.navigate = function() {
  const C     = window.dolical.calendar;
  const U     = window.dolical.util;
  const state = C.state;
  const nav   = $(this).attr('data-nav');

  if (nav === 'refresh') {
    C.load();
    return;
  }
  if (nav === 'today') {
    state.anchor = U.startOfDay(new Date());
  } else {
    const step = nav === 'prev' ? -1 : 1;
    if (state.view === 'month') {
      state.anchor = new Date(state.anchor.getFullYear(), state.anchor.getMonth() + step, 1);
    } else {
      state.anchor = U.addDays(state.anchor, step * ({day: 1, list: 14}[state.view] || 7));
    }
  }
  state.minicalMonth = null;
  state.scrollTop    = null;
  C.render();
  C.load();
};

/**
 * Switch to another view.
 *
 * @returns {void}
 */
window.dolical.calendar.changeView = function() {
  const C = window.dolical.calendar;
  C.state.view      = $(this).attr('data-view');
  C.state.scrollTop = null;
  C.persist();
  C.render();
  C.load();
};

/**
 * Go to the day view of a day.
 *
 * @param   {Event} event Click
 * @returns {void}
 */
window.dolical.calendar.gotoDay = function(event) {
  const C = window.dolical.calendar;
  event.stopPropagation();
  C.state.anchor    = window.dolical.util.fromDayKey($(this).attr('data-goto'));
  C.state.view      = 'day';
  C.state.scrollTop = null;
  C.persist();
  C.render();
  C.load();
};

/**
 * Pick a day in the mini calendar.
 *
 * @returns {void}
 */
window.dolical.calendar.minicalPick = function() {
  const C = window.dolical.calendar;
  C.state.anchor       = window.dolical.util.fromDayKey($(this).attr('data-minical-day'));
  C.state.minicalMonth = null;
  C.state.scrollTop    = null;
  $('.dolical-app').removeClass('is-sidebar-open');
  C.render();
  C.load();
};

/**
 * Browse the months of the mini calendar without changing the period.
 *
 * @returns {void}
 */
window.dolical.calendar.minicalNavigate = function() {
  const C     = window.dolical.calendar;
  const shown = C.state.minicalMonth || new Date(C.state.anchor.getFullYear(), C.state.anchor.getMonth(), 1);
  C.state.minicalMonth = new Date(shown.getFullYear(), shown.getMonth() + parseInt($(this).attr('data-minical-nav'), 10), 1);
  C.renderMinical();
};

/**
 * Show or hide the side bar on a small screen.
 *
 * @returns {void}
 */
window.dolical.calendar.toggleSidebar = function() {
  $('.dolical-app').toggleClass('is-sidebar-open');
};

/**
 * Check the boxes of the selected people.
 *
 * @returns {void}
 */
window.dolical.calendar.syncSidebar = function() {
  const state = window.dolical.calendar.state;
  $('.dolical-picker__check').each(function() {
    this.checked = state.users.indexOf(parseInt(this.value, 10)) !== -1;
  });
  $('.dolical-filter[data-filter="hideauto"]').prop('checked', !!state.filters.hideauto);
  $('.dolical-filter[data-filter="status"]').val(state.filters.status);
  $('.dolical-filter[data-filter="types"]').val(state.filters.types);
};

/**
 * Selected people changed : keep the order of the list, the current user first.
 *
 * @returns {void}
 */
window.dolical.calendar.setUsers = function(users) {
  const C      = window.dolical.calendar;
  const config = window.dolical.util.config();
  const order  = config.users.map(function(person) {
    return person.id;
  });
  C.state.users = order.filter(function(id) {
    return users.indexOf(id) !== -1;
  });
  C.syncSidebar();
  C.persist();
  C.render();
  C.load();
};

/**
 * A box of the list of people was clicked.
 *
 * @returns {void}
 */
window.dolical.calendar.pickFromList = function() {
  const users = [];
  $('.dolical-picker__check:checked').each(function() {
    users.push(parseInt(this.value, 10));
  });
  window.dolical.calendar.setUsers(users);
};

/**
 * Only me, or nobody.
 *
 * @returns {void}
 */
window.dolical.calendar.pickShortcut = function() {
  const config = window.dolical.util.config();
  window.dolical.calendar.setUsers($(this).attr('data-pick') === 'me' ? [config.currentUser] : []);
};

/**
 * Add the members of a group to the selected people.
 *
 * @returns {void}
 */
window.dolical.calendar.pickGroup = function() {
  const C       = window.dolical.calendar;
  const groupId = parseInt($(this).val(), 10);
  const group   = window.dolical.util.config().groups.find(function(item) {
    return item.id === groupId;
  });
  $(this).val('');
  if (group) {
    C.setUsers(C.state.users.concat(group.members));
  }
};

/**
 * Filter the list of people by name.
 *
 * @returns {void}
 */
window.dolical.calendar.searchPeople = function() {
  const search = $(this).val().toLowerCase().trim();
  $('.dolical-picker__item').each(function() {
    $(this).toggleClass('is-hidden', search !== '' && $(this).attr('data-name').indexOf(search) === -1);
  });
};

/**
 * Filters of the side bar.
 *
 * @returns {void}
 */
window.dolical.calendar.changeFilter = function() {
  const C      = window.dolical.calendar;
  const filter = $(this).attr('data-filter');
  if (filter === 'search') {
    return;
  }
  C.state.filters[filter] = filter === 'hideauto' ? (this.checked ? 1 : 0) : $(this).val();
  C.persist();
  C.load();
};

/**
 * Search in the events, once the typing pauses.
 *
 * @returns {void}
 */
window.dolical.calendar.changeSearch = function() {
  const C     = window.dolical.calendar;
  const value = $(this).val().trim();
  clearTimeout(C.searchTimer);
  C.searchTimer = setTimeout(function() {
    C.state.filters.search = value;
    C.load();
  }, 300);
};

/**
 * Keyboard shortcuts outside the fields : arrows to browse, T for today.
 *
 * @param   {KeyboardEvent} event Key
 * @returns {void}
 */
window.dolical.calendar.keyboard = function(event) {
  if (event.ctrlKey || event.metaKey || event.altKey || $(event.target).is('input, textarea, select, [contenteditable]') || $('#dolical-event-modal').hasClass('modal-active')) {
    return;
  }
  const map = {ArrowLeft: 'prev', ArrowRight: 'next', t: 'today', T: 'today'};
  if (map[event.key]) {
    $('.dolical-toolbar [data-nav="' + map[event.key] + '"]').trigger('click');
  }
};

/*
 * Clicks : open, create
 */

/**
 * Open the card of a clicked event.
 *
 * @param   {Event} event Click
 * @returns {void}
 */
window.dolical.calendar.clickEvent = function(event) {
  event.stopPropagation();
  if (window.dolical.calendar.justDragged) {
    return;
  }
  window.dolical.eventcard.open(parseInt($(this).attr('data-id'), 10), this);
};

/**
 * Create an event where an empty place of the calendar was clicked.
 *
 * @param   {Event} event Click
 * @returns {void}
 */
window.dolical.calendar.clickEmpty = function(event) {
  const C      = window.dolical.calendar;
  const U      = window.dolical.util;
  const config = U.config();
  const $cell  = $(this);

  if (!config.canCreate || C.justDragged || $(event.target).closest('.dolical-event, [data-goto]').length) {
    return;
  }
  // An open card is closed by this click, not replaced by a form
  if (window.dolical.eventcard.isOpen()) {
    window.dolical.eventcard.close();
    return;
  }

  const day    = U.fromDayKey($cell.attr('data-date'));
  const userId = parseInt($cell.attr('data-user'), 10) || 0;
  const params = {user: userId || (C.state.users.length === 1 ? C.state.users[0] : config.currentUser)};

  if ($cell.hasClass('ra-tg__allday-col')) {
    params.allday = 1;
    params.start  = Math.floor(day.getTime() / 1000);
    params.end    = Math.floor(U.addDays(day, 1).getTime() / 1000) - 1;
  } else {
    let minutes = 9 * 60;
    if ($cell.hasClass('ra-tg__col')) {
      minutes = Math.floor((event.pageY - $cell.offset().top) * 60 / C.HOUR_HEIGHT / 30) * 30;
    }
    const start  = new Date(day.getFullYear(), day.getMonth(), day.getDate(), 0, minutes);
    params.start = Math.floor(start.getTime() / 1000);
    params.end   = params.start + config.defaultDuration * 60;
  }

  window.dolical.eventform.open(params);
};

/**
 * The button "New event".
 *
 * @returns {void}
 */
window.dolical.calendar.createFromButton = function() {
  const C      = window.dolical.calendar;
  const config = window.dolical.util.config();
  const params = {user: C.state.users.length === 1 ? C.state.users[0] : config.currentUser};
  // Created on the displayed day when it is not today, at the next round hour
  const today = window.dolical.util.startOfDay(new Date());
  if (C.state.view === 'day' && C.state.anchor.getTime() !== today.getTime()) {
    params.start = Math.floor(C.state.anchor.getTime() / 1000) + 9 * 3600;
  }
  window.dolical.eventform.open(params);
};

/*
 * Drag and drop
 */

/**
 * Start to drag an event, or to stretch it by its bottom edge. Mouse only : on a touch screen the gesture
 * scrolls the page.
 *
 * @param   {PointerEvent} event Pointer
 * @returns {void}
 */
window.dolical.calendar.dragStart = function(event) {
  const C = window.dolical.calendar;
  if (event.button !== 0 || event.pointerType !== 'mouse') {
    return;
  }
  const $element = $(this);
  const calendarEvent = C.findEvent(parseInt($element.attr('data-id'), 10));
  if (!calendarEvent || !calendarEvent.editable || $element.attr('data-kind') === 'list') {
    return;
  }

  const kind = $element.attr('data-kind');
  let grab   = 0;
  if (kind === 'timed') {
    const column = $element.closest('.ra-tg__col');
    const day    = window.dolical.util.fromDayKey(column.attr('data-date'));
    grab = (event.clientY - column[0].getBoundingClientRect().top) * 60 / C.HOUR_HEIGHT - C.minuteOf(calendarEvent.s, day);
  }

  C.drag = {
    event: calendarEvent,
    $element: $element,
    kind: kind,
    resize: $(event.target).closest('.ra-block__resize').length > 0,
    startX: event.clientX,
    startY: event.clientY,
    grab: grab,
    fromUser: parseInt($element.attr('data-user'), 10) || 0,
    toUser: parseInt($element.attr('data-user'), 10) || 0,
    start: calendarEvent.s,
    end: calendarEvent.e,
    active: false,
    $ghost: null,
    $target: null
  };

  event.preventDefault();
  $(document).on('pointermove.dolicaldrag', C.dragMove).on('pointerup.dolicaldrag pointercancel.dolicaldrag', C.dragEnd);
};

/**
 * Follow the pointer.
 *
 * @param   {PointerEvent} event Pointer
 * @returns {void}
 */
window.dolical.calendar.dragMove = function(event) {
  const C    = window.dolical.calendar;
  const U    = window.dolical.util;
  const drag = C.drag;
  if (!drag) {
    return;
  }

  if (!drag.active) {
    if (Math.abs(event.clientX - drag.startX) + Math.abs(event.clientY - drag.startY) < 5) {
      return;
    }
    drag.active = true;
    drag.$element.addClass('is-dragging');
    $('body').addClass('dolical-is-dragging');
    window.dolical.eventcard.close();
    if (drag.kind === 'chip') {
      drag.$ghost = drag.$element.clone().addClass('ra-ghost').appendTo('body');
    }
  }

  const duration = drag.event.e - drag.event.s;
  const snap     = function(minutes) {
    return Math.round(minutes / C.SNAP) * C.SNAP;
  };

  if (drag.kind === 'timed' && drag.resize) {
    const column = drag.$element.closest('.ra-tg__col');
    const day    = U.fromDayKey(column.attr('data-date'));
    const top    = C.minuteOf(drag.event.s, day);
    const bottom = Math.min(1440, Math.max(top + C.SNAP, snap((event.clientY - column[0].getBoundingClientRect().top) * 60 / C.HOUR_HEIGHT)));
    drag.end = new Date(day.getFullYear(), day.getMonth(), day.getDate(), 0, bottom);
    drag.$element[0].style.setProperty('--ra-height', ((bottom - top) * C.HOUR_HEIGHT / 60) + 'px');
    drag.$element.find('.ra-block__time').text(U.time(drag.event.s) + ' – ' + U.time(drag.end));
    return;
  }

  if (drag.kind === 'timed') {
    let column = null;
    $('.dolical-calendar .ra-tg__col').each(function() {
      const rect = this.getBoundingClientRect();
      if (event.clientX >= rect.left && event.clientX < rect.right) {
        column = this;
      }
    });
    if (!column) {
      return;
    }
    const rect    = column.getBoundingClientRect();
    const day     = U.fromDayKey(column.getAttribute('data-date'));
    const minutes = Math.max(0, Math.min(1440 - C.SNAP, snap((event.clientY - rect.top) * 60 / C.HOUR_HEIGHT - drag.grab)));
    drag.start  = new Date(day.getFullYear(), day.getMonth(), day.getDate(), 0, minutes);
    drag.end    = new Date(drag.start.getTime() + duration);
    drag.toUser = parseInt(column.getAttribute('data-user'), 10) || 0;
    if (drag.$element.parent()[0] !== column) {
      drag.$element.appendTo(column);
    }
    drag.$element.addClass('is-moving');
    drag.$element[0].style.setProperty('--ra-top', (minutes * C.HOUR_HEIGHT / 60) + 'px');
    drag.$element.find('.ra-block__time').text(U.time(drag.start) + ' – ' + U.time(drag.end));
    return;
  }

  // Chips : a ghost follows the pointer, the cell under it is the target
  drag.$ghost.css({left: event.clientX + 8, top: event.clientY + 8});
  drag.$ghost.hide();
  const below = document.elementFromPoint(event.clientX, event.clientY);
  drag.$ghost.show();
  const $target = $(below).closest('.dolical-calendar .ra-droppable');
  $('.ra-droppable.is-drop-target').removeClass('is-drop-target');
  drag.$target = $target.length ? $target.addClass('is-drop-target') : null;
};

/**
 * Drop : send the new place of the event.
 *
 * @returns {void}
 */
window.dolical.calendar.dragEnd = function() {
  const C    = window.dolical.calendar;
  const U    = window.dolical.util;
  const drag = C.drag;

  $(document).off('.dolicaldrag');
  C.drag = null;
  $('body').removeClass('dolical-is-dragging');
  if (!drag || !drag.active) {
    return;
  }

  // The click that ends a drag must not open the card
  C.justDragged = true;
  setTimeout(function() {
    C.justDragged = false;
  }, 80);

  if (drag.$ghost) {
    drag.$ghost.remove();
  }
  $('.ra-droppable.is-drop-target').removeClass('is-drop-target');

  if (drag.kind === 'chip') {
    if (!drag.$target) {
      C.render();
      return;
    }
    const day = U.fromDayKey(drag.$target.attr('data-date'));
    const shift = Math.round((day - U.startOfDay(drag.event.s)) / 86400000);
    drag.start  = U.addDays(drag.event.s, shift);
    drag.end    = U.addDays(drag.event.e, shift);
    drag.toUser = parseInt(drag.$target.attr('data-user'), 10) || 0;
  }

  const unchanged = drag.start.getTime() === drag.event.s.getTime() && drag.end.getTime() === drag.event.e.getTime() && drag.toUser === drag.fromUser;
  if (unchanged) {
    C.render();
    return;
  }

  // Drawn at once at its new place, put back by the reload if the server refuses
  const moved = drag.event;
  moved.s = drag.start;
  moved.e = drag.end;
  if (drag.toUser && drag.fromUser && drag.toUser !== drag.fromUser) {
    moved.userIds = moved.userIds.filter(function(id) {
      return id !== drag.fromUser;
    }).concat([drag.toUser]);
    if (moved.ownerId === drag.fromUser) {
      moved.ownerId = drag.toUser;
    }
  }
  C.render();

  U.post({
    action: 'move',
    id: moved.id,
    start: Math.floor(drag.start.getTime() / 1000),
    end: Math.floor(drag.end.getTime() / 1000),
    allday: moved.allDay ? 1 : 0,
    from_user: drag.fromUser,
    to_user: drag.toUser
  }).done(function(response) {
    if (response && response.success) {
      // A date of a series moves alone : the others stay where they are
      U.toast(U.t(response.occurrence ? 'movedOccurrence' : 'moved'));
    } else {
      U.toast((response && response.error) || U.t('error'), 'error');
    }
  }).fail(function(xhr) {
    U.toast(U.xhrError(xhr), 'error');
  }).always(function() {
    C.load(true);
  });
};
