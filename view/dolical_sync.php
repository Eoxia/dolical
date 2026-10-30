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
 * \file    view/dolical_sync.php
 * \ingroup dolical
 * \brief   Synchronize a phone, a Mac or Thunderbird with DoliCal : the address, the steps for each one, and the
 *          agendas of the colleagues to get as well
 */

// Load DoliCal environment
if (file_exists('../dolical.main.inc.php')) {
    require_once __DIR__ . '/../dolical.main.inc.php';
} elseif (file_exists('../../dolical.main.inc.php')) {
    require_once __DIR__ . '/../../dolical.main.inc.php';
} else {
    die('Include of dolical main fails');
}

require_once DOL_DOCUMENT_ROOT . '/core/lib/functions2.lib.php';
require_once __DIR__ . '/../lib/dolical_caldav.lib.php';

global $conf, $db, $langs, $user;

saturne_load_langs(['agenda']);

// Security check
$permissionToRead = $user->hasRight('dolical', 'read') && $user->hasRight('agenda', 'myactions', 'read');
saturne_check_access($permissionToRead);

$action = GETPOST('action', 'aZ09');

/*
 * Actions
 */

if ($action === 'saveshared' && dolical_can_read_all($user)) {
    // Only colleagues whose agenda the user may display
    $shared = array_values(array_intersect(GETPOST('shared', 'array:int'), array_keys(dolical_get_calendar_users($user))));
    $shared = array_values(array_diff($shared, [(int) $user->id]));
    if (dol_set_user_param($db, $conf, $user, ['DOLICAL_CALDAV_SHARED' => implode(',', $shared)]) > 0) {
        setEventMessages($langs->trans('DoliCalSyncSharedSaved'), null);
    } else {
        setEventMessages($langs->trans('Error'), null, 'errors');
    }
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

/*
 * View
 */

$syncUrl       = dolical_caldav_url();
$calendarUrl   = $syncUrl . 'calendars/' . rawurlencode($user->login) . '/agenda/';
$isSecure      = strpos($syncUrl, 'https://') === 0;
$canShare      = dolical_can_read_all($user);
$calendarUsers = $canShare ? dolical_get_calendar_users($user) : [];
$shared        = dolical_caldav_calendar_users($user);

$title = $langs->trans('DoliCalSyncPhone');

saturne_header(0, '', $title, '', '', 0, 0, [], [], '', 'dolical-page');

require __DIR__ . '/../core/tpl/dolical_sync.tpl.php';

// End of page
llxFooter();
$db->close();
