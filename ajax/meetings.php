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
 * \file    ajax/meetings.php
 * \ingroup dolical
 * \brief   Meetings with a third party, as the block of a mail application reads them (hook inboxMailPanels) :
 *          the next one, the last one, a report awaited. Read only, JSON : lines, badges and one action.
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

global $langs, $user;

header('Content-Type: application/json; charset=utf-8');

// The calendar is for the internal users, as its pages
if (!$user->hasRight('dolical', 'read') || !$user->hasRight('agenda', 'myactions', 'read') || $user->socid > 0) {
    http_response_code(403);
    echo json_encode(['error' => $langs->transnoentities('NotEnoughPermissions')]);
    exit;
}

$langs->loadLangs(['dolical@dolical']);

$socid    = GETPOSTINT('socid');
$meetings = dolical_thirdparty_meetings($socid, $user);

/**
 * One meeting on one line : its day (and hour), its title
 *
 * @param  string     $key   Translation key of the line
 * @param  ActionComm $event Event
 * @return string
 */
$line = function (string $key, ActionComm $event) use ($langs): string {
    $when = dol_print_date((int) $event->datep, empty($event->fulldayevent) ? '%a %d/%m %H:%M' : '%a %d/%m', 'tzuserrel');
    $label = dol_trunc(dol_html_entity_decode((string) $event->label, ENT_QUOTES | ENT_HTML5), 60, 'right', 'UTF-8', 1);

    return $langs->transnoentities($key, $when, $label);
};
$page = function (ActionComm $event): string {
    return dol_buildpath('/dolical/view/dolical_event.php', 1) . '?id=' . ((int) $event->id) . '&dol_hide_topmenu=1&dol_hide_leftmenu=1';
};

$out = ['lines' => [], 'badges' => []];
if ($meetings['next'] !== null) {
    $out['lines'][] = $line('DoliCalMeetingsNext', $meetings['next']);
}
if ($meetings['last'] !== null) {
    $out['lines'][] = $line('DoliCalMeetingsLast', $meetings['last']);
}
if ($meetings['reportDue']) {
    // The report awaited first : the card of the last meeting, where it is written
    $out['badges'][] = ['label' => $langs->transnoentities('DoliCalMeetingsReportDue'), 'level' => 'warning'];
    $out['action']   = ['label' => $langs->transnoentities('DoliCalMeetingsWriteReport'), 'url' => $page($meetings['last']), 'title' => dol_html_entity_decode((string) $meetings['last']->label, ENT_QUOTES | ENT_HTML5), 'primary' => true];
} elseif ($meetings['next'] !== null) {
    $out['action'] = ['label' => $langs->transnoentities('DoliCalMeetingsOpenNext'), 'url' => $page($meetings['next']), 'title' => dol_html_entity_decode((string) $meetings['next']->label, ENT_QUOTES | ENT_HTML5), 'primary' => false];
}
if (empty($out['lines'])) {
    $out['lines'][] = $langs->transnoentities('DoliCalMeetingsNone');
}

echo json_encode($out);
