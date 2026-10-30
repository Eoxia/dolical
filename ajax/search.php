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
 * \file    ajax/search.php
 * \ingroup dolical
 * \brief   Search of the contacts, third parties, projects and documents of the event form, in the select2 format
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

// The calendar is for the internal users, as its pages
if (!$user->hasRight('dolical', 'read') || !dolical_can_create($user) || $user->socid > 0) {
    http_response_code(403);
    echo json_encode(['results' => []]);
    exit;
}

$type   = GETPOST('type', 'aZ09');
$search = GETPOST('q', 'alphanohtml');
$socid  = GETPOSTINT('socid');

switch ($type) {
    case 'contact':
        $results = dolical_search_contacts($user, $search, $socid);
        break;
    case 'thirdparty':
        $results = dolical_search_thirdparties($user, $search);
        break;
    case 'project':
        $results = dolical_search_projects($user, $search, $socid);
        break;
    case 'element':
        $results = dolical_search_elements($user, $search, $socid);
        break;
    default:
        $results = [];
}

echo json_encode(['results' => $results]);
