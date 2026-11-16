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
 * \file    admin/setup.php
 * \ingroup dolical
 * \brief   DoliCal setup page : opening view, hours of the grid, default duration, filters
 */

// Load DoliCal environment
if (file_exists('../dolical.main.inc.php')) {
    require_once __DIR__ . '/../dolical.main.inc.php';
} elseif (file_exists('../../dolical.main.inc.php')) {
    require_once __DIR__ . '/../../dolical.main.inc.php';
} else {
    die('Include of dolical main fails');
}

// Load Dolibarr libraries
require_once DOL_DOCUMENT_ROOT . '/core/lib/admin.lib.php';

// Load DoliCal libraries
require_once __DIR__ . '/../lib/dolical.lib.php';

global $conf, $db, $langs, $user;

$langs->loadLangs(['dolical@dolical', 'admin', 'agenda', 'errors']);

$action = GETPOST('action', 'aZ09');

// Security check : the right of the module shows the setup, changing it is for the administrators, as in Dolibarr
$permissionToRead  = $user->hasRight('dolical', 'adminpage', 'read') || $user->admin;
$permissionToWrite = (bool) $user->admin;
if (!$permissionToRead || $user->socid > 0) {
    accessforbidden();
}

$views = ['day' => 'DoliCalViewDay', 'week' => 'DoliCalViewWeek', 'team' => 'DoliCalViewTeam', 'month' => 'DoliCalViewMonth', 'list' => 'DoliCalViewList'];

/*
 * Actions
 */

if ($action == 'update' && $permissionToWrite && dolical_valid_post()) {
    $defaultView = GETPOST('DOLICAL_DEFAULT_VIEW', 'aZ09');
    $dayStart    = min(23, max(0, GETPOSTINT('DOLICAL_DAY_START_HOUR')));
    $dayEnd      = min(24, max($dayStart + 1, GETPOSTINT('DOLICAL_DAY_END_HOUR')));
    $duration    = min(1440, max(5, GETPOSTINT('DOLICAL_DEFAULT_DURATION')));

    dolibarr_set_const($db, 'DOLICAL_DEFAULT_VIEW', isset($views[$defaultView]) ? $defaultView : 'week', 'chaine', 0, '', $conf->entity);
    dolibarr_set_const($db, 'DOLICAL_DAY_START_HOUR', $dayStart, 'integer', 0, '', $conf->entity);
    dolibarr_set_const($db, 'DOLICAL_DAY_END_HOUR', $dayEnd, 'integer', 0, '', $conf->entity);
    dolibarr_set_const($db, 'DOLICAL_DEFAULT_DURATION', $duration, 'integer', 0, '', $conf->entity);
    dolibarr_set_const($db, 'DOLICAL_SHOW_WEEKENDS', GETPOSTINT('DOLICAL_SHOW_WEEKENDS') ? 1 : 0, 'integer', 0, '', $conf->entity);
    dolibarr_set_const($db, 'DOLICAL_HIDE_AUTO_EVENTS', GETPOSTINT('DOLICAL_HIDE_AUTO_EVENTS') ? 1 : 0, 'integer', 0, '', $conf->entity);
    dolibarr_set_const($db, 'DOLICAL_NOTIFY_ORGANIZER', GETPOSTINT('DOLICAL_NOTIFY_ORGANIZER') ? 1 : 0, 'integer', 0, '', $conf->entity);
    dolibarr_set_const($db, 'DOLICAL_AUTO_REMIND', GETPOSTINT('DOLICAL_AUTO_REMIND') ? 1 : 0, 'integer', 0, '', $conf->entity);
    dolibarr_set_const($db, 'DOLICAL_AUTO_REMIND_DAYS', min(30, max(1, GETPOSTINT('DOLICAL_AUTO_REMIND_DAYS'))), 'integer', 0, '', $conf->entity);
    foreach (['DOLICAL_REMINDER_BY_DEFAULT', 'DOLICAL_CALDAV', 'DOLICAL_CALDAV_ALLOW_HTTP'] as $constName) {
        dolibarr_set_const($db, $constName, GETPOSTINT($constName) ? 1 : 0, 'integer', 0, '', $conf->entity);
    }
    $timezone = GETPOST('DOLICAL_TIMEZONE', 'alphanohtml');
    if (in_array($timezone, timezone_identifiers_list(), true)) {
        dolibarr_set_const($db, 'DOLICAL_TIMEZONE', $timezone, 'chaine', 0, '', $conf->entity);
    }
    // Hidden constant of the Agenda module, not of DoliCal (the page says so) : the elements linked from the calendar
    // also show on the native card of every event
    dolibarr_set_const($db, 'AGENDA_ENABLE_LINKED_ELEMENTS', GETPOSTINT('AGENDA_ENABLE_LINKED_ELEMENTS') ? 1 : 0, 'chaine', 0, '', $conf->entity);

    setEventMessages($langs->trans('SetupSaved'), null);
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

/*
 * View
 */

$title = $langs->trans('ModuleSetup', 'DoliCal');

$js = [dolical_asset('js/dolical.js'), dolical_asset('js/modules/util.js'), dolical_asset('js/modules/setup.js')];
llxHeader('', $title, '', '', 0, 0, $js, [dolical_asset('css/dolical.css')]);

$linkBack = '<a href="' . DOL_URL_ROOT . '/admin/modules.php?restore_lastsearch_values=1">' . $langs->trans('BackToModuleList') . '</a>';
print load_fiche_titre($title, $linkBack, 'title_setup');

print dol_get_fiche_head(dolical_admin_prepare_head(), 'settings', $title, -1, 'dolical_color@dolical');

require __DIR__ . '/../core/tpl/dolical_setup.tpl.php';

print dol_get_fiche_end();
llxFooter();
$db->close();
