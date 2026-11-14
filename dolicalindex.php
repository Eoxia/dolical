<?php
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
 * \file    dolicalindex.php
 * \ingroup dolical
 * \brief   Calendar of DoliCal : the native events of one or several people, by day, week, team, month or list
 */

// Load DoliCal environment
if (file_exists('dolical.main.inc.php')) {
    require_once __DIR__ . '/dolical.main.inc.php';
} elseif (file_exists('../dolical.main.inc.php')) {
    require_once __DIR__ . '/../dolical.main.inc.php';
} else {
    die('Include of dolical main fails');
}

// Load DoliCal libraries
require_once __DIR__ . '/lib/dolical_event.lib.php';

global $conf, $db, $langs, $user;

$langs->loadLangs(['dolical@dolical', 'agenda', 'companies', 'errors']);

// Security check : the calendar is for the internal users
$permissionToRead = $user->hasRight('dolical', 'read') && $user->hasRight('agenda', 'myactions', 'read');
if (!$permissionToRead || $user->socid > 0) {
    accessforbidden();
}

/*
 * View
 */

$calendarUsers  = dolical_get_calendar_users($user);
$calendarGroups = dolical_get_calendar_groups($user, $calendarUsers);
$eventTypes     = dolical_get_event_types();

$views          = dolical_calendar_views();
$calendarConfig = dolical_calendar_config($user, $calendarUsers, $calendarGroups);

$title = $langs->trans('DoliCalCalendar');

// The calendar has its own side panel : the left menu only took its room
$conf->dol_hide_leftmenu = 1;

dolical_header($title, 'dolical-page');

require __DIR__ . '/core/tpl/dolical_calendar.tpl.php';

// End of page
llxFooter();
$db->close();
