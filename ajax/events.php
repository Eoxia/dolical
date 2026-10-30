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
 * \file    ajax/events.php
 * \ingroup dolical
 * \brief   Native events of a period for the people displayed by the calendar, as JSON
 */

if (!defined('NOTOKENRENEWAL')) {
    define('NOTOKENRENEWAL', '1');
}
if (!defined('NOREQUIREMENU')) {
    define('NOREQUIREMENU', '1');
}
if (!defined('NOREQUIREHTML')) {
    define('NOREQUIREHTML', '1');
}

if (file_exists('../dolical.main.inc.php')) {
    require_once __DIR__ . '/../dolical.main.inc.php';
} elseif (file_exists('../../dolical.main.inc.php')) {
    require_once __DIR__ . '/../../dolical.main.inc.php';
} else {
    die('Include of dolical main fails');
}

require_once __DIR__ . '/../lib/dolical_event.lib.php';

global $user;

header('Content-Type: application/json; charset=utf-8');

if (!$user->hasRight('dolical', 'read') || !$user->hasRight('agenda', 'myactions', 'read')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'NotEnoughPermissions']);
    exit;
}

$start   = GETPOSTINT('start');
$end     = GETPOSTINT('end');
$userIds = array_filter(array_map('intval', explode(',', GETPOST('users', 'intcomma'))));
$types   = array_filter(explode(',', GETPOST('types', 'alphanohtml')));

// More than six weeks is not a calendar page any more
if ($start <= 0 || $end <= $start || $end - $start > 50 * 86400) {
    echo json_encode(['success' => false, 'error' => 'BadPeriod']);
    exit;
}

$filters = [
    'hideauto'  => GETPOSTINT('hideauto'),
    'status'    => in_array(GETPOST('status', 'aZ09'), ['todo', 'done']) ? GETPOST('status', 'aZ09') : 'all',
    'types'     => $types,
    'socid'     => GETPOSTINT('socid'),
    'projectid' => GETPOSTINT('projectid'),
    'search'    => GETPOST('search', 'alphanohtml'),
];

echo json_encode(['success' => true, 'events' => dolical_fetch_events($user, $start, $end, $userIds, $filters)]);
