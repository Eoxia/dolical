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
 * \file    public/invitation.php
 * \ingroup dolical
 * \brief   Public page of an invitation : the person invited accepts, answers maybe or declines, without an account.
 *          The link is signed for one person and one event (dolical_invitation_signature()), it is the only key.
 */

if (!defined('NOREQUIREMENU')) {
    define('NOREQUIREMENU', '1');
}
if (!defined('NOLOGIN')) {
    define('NOLOGIN', '1');
}
// The signed link is the protection of the answer : it comes from a mail, never from a page of the instance
if (!defined('NOCSRFCHECK')) {
    define('NOCSRFCHECK', '1');
}
if (!defined('NOIPCHECK')) {
    define('NOIPCHECK', '1');
}
if (!defined('NOBROWSERNOTIF')) {
    define('NOBROWSERNOTIF', '1');
}

// For MultiCompany : the entity has to be known before main.inc.php, GETPOST does not exist yet
$entity = (!empty($_GET['entity']) ? (int) $_GET['entity'] : (!empty($_POST['entity']) ? (int) $_POST['entity'] : 1));
if (is_numeric($entity)) {
    define('DOLENTITY', $entity);
}

// Load DoliCal environment
if (file_exists('../dolical.main.inc.php')) {
    require_once __DIR__ . '/../dolical.main.inc.php';
} elseif (file_exists('../../dolical.main.inc.php')) {
    require_once __DIR__ . '/../../dolical.main.inc.php';
} else {
    die('Include of dolical main fails');
}

require_once DOL_DOCUMENT_ROOT . '/core/lib/company.lib.php';
require_once __DIR__ . '/../lib/dolical_invitation.lib.php';
require_once __DIR__ . '/../lib/dolical_recurrence.lib.php';

global $conf, $db, $langs, $mysoc;

saturne_load_langs(['agenda']);

$eventId   = GETPOSTINT('e');
$kind      = GETPOST('k', 'aZ09');
$personId  = GETPOSTINT('p');
$signature = GETPOST('s', 'aZ09');
$action    = GETPOST('action', 'aZ09');

/*
 * Check the link
 */

$event = null;
$valid = false;
if ($eventId > 0 && in_array($kind, ['u', 'c'], true) && $personId > 0 && $signature !== ''
    && hash_equals(dolical_invitation_signature((int) $conf->entity, $eventId, $kind, $personId), $signature)) {
    $event = new ActionComm($db);
    $valid = $event->fetch($eventId) > 0 && (int) $event->entity === (int) $conf->entity && dolical_is_participant($event, $kind, $personId);
}

$personName = '';
$status     = 0;
if ($valid) {
    if ($kind === 'u') {
        $person = new User($db);
        $person->fetch($personId);
        $status = (int) ($event->userassigned[$personId]['answer_status'] ?? 0);
    } else {
        $person = new Contact($db);
        $person->fetch($personId);
        $status = (int) ($event->socpeopleassigned[$personId]['answer_status'] ?? 0);
    }
    $personName = $person->getFullName($langs);
}

$linkParams = ['e' => $eventId, 'k' => $kind, 'p' => $personId, 'entity' => (int) $conf->entity, 's' => $signature];
$pageUrl    = $_SERVER['PHP_SELF'] . '?' . http_build_query($linkParams);
// A series is over once its last date is
$lastStart  = $valid ? (int) ($event->datef ?: $event->datep) : 0;
if ($valid && !empty($event->recurid)) {
    $lastStart = max($lastStart, (int) max(dolical_series_occurrences($event) ?: [0]));
}
$isPast     = $valid && $lastStart < dol_now();

/*
 * Actions
 */

if ($valid && GETPOSTINT('ics')) {
    $organizer = new User($db);
    $organizer->fetch($event->userownerid);
    header('Content-Type: text/calendar; charset=utf-8');
    header('Content-Disposition: attachment; filename="invitation.ics"');
    echo dolical_build_ics($event, $organizer, dol_buildpath('/dolical/public/invitation.php', 3) . '?' . http_build_query($linkParams), dolical_invitation_sequence($event), 'PUBLISH', !empty($event->recurid) ? ['series' => true] : []);
    exit;
}

if ($valid && $action === 'answer' && $_SERVER['REQUEST_METHOD'] === 'POST' && !$isPast) {
    $done = dolical_record_answer($event, $kind, $personId, GETPOSTINT('status'), $personName);
    // Post, redirect, get : a reload of the page does not answer twice
    header('Location: ' . $pageUrl . ($done ? '&done=1' : '&failed=1'));
    exit;
}

/*
 * View
 */

$organizerName = '';
if ($valid && $event->userownerid > 0) {
    $organizer = new User($db);
    if ($organizer->fetch($event->userownerid) > 0) {
        $organizerName = $organizer->getFullName($langs);
    }
}

$title = $valid ? dol_html_entity_decode((string) $event->label, ENT_QUOTES | ENT_HTML5) : $langs->trans('DoliCalInvitation');

$conf->dol_hide_topmenu  = 1;
$conf->dol_hide_leftmenu = 1;

llxHeader('', $title, '', '', 0, 0, [], ['/dolical/css/dolical.min.css'], '', 'dolical-public', '<div>', 1);

htmlPrintOnlineHeader($mysoc, $langs, 1, '', '', '', '');

require __DIR__ . '/../core/tpl/dolical_public_invitation.tpl.php';

llxFooter('', 'public');
$db->close();
