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
 * \file    view/dolical_booking.php
 * \ingroup dolical
 * \brief   Page of a person for the online booking : the link of his public page to share, and the texts he writes
 *          for it (the common ones of the setup when he writes none). An administrator opens the one of a colleague
 *          with ?id=
 */

// Load DoliCal environment
if (file_exists('../dolical.main.inc.php')) {
    require_once __DIR__ . '/../dolical.main.inc.php';
} elseif (file_exists('../../dolical.main.inc.php')) {
    require_once __DIR__ . '/../../dolical.main.inc.php';
} else {
    die('Include of dolical main fails');
}

require_once __DIR__ . '/../lib/dolical_booking.lib.php';

global $conf, $db, $langs, $user;

$langs->loadLangs(['dolical@dolical', 'agenda', 'errors']);

// Security check : the calendar is for the internal users
$permissionToRead = $user->hasRight('dolical', 'read') && $user->hasRight('agenda', 'myactions', 'read');
if (!$permissionToRead || $user->socid > 0) {
    accessforbidden();
}

$action   = GETPOST('action', 'aZ09');
$personId = GETPOSTINT('id') > 0 ? GETPOSTINT('id') : (int) $user->id;
$isSelf   = $personId === (int) $user->id;

// The page of a colleague : for the administrators, who set up the booking
if (!$isSelf && !$user->admin) {
    accessforbidden();
}
$person = new User($db);
if ($person->fetch($personId) <= 0 || (int) $person->statut !== 1 || $person->socid > 0) {
    accessforbidden();
}

$selfUrl = $_SERVER['PHP_SELF'] . ($isSelf ? '' : '?id=' . $personId);

/*
 * Actions
 */

if ($action === 'savetexts' && dolical_valid_post()) {
    $texts = [];
    // The presentation is HTML of the editor, cleaned of anything that runs ; the titles are plain text
    foreach (array_keys(DOLICAL_BOOKING_TEXTS) as $constName) {
        $texts[$constName] = GETPOST($constName, $constName === 'DOLICAL_BOOKING_INTRO' ? 'restricthtml' : 'nohtml');
    }
    if (dolical_booking_save_person_texts($person, $texts)) {
        setEventMessages($langs->trans('DoliCalBookingTextsSaved'), null);
    } else {
        setEventMessages($langs->trans('Error'), null, 'errors');
    }
    header('Location: ' . $selfUrl);
    exit;
}

/*
 * View
 */

$isOpen    = getDolGlobalInt('DOLICAL_BOOKING') > 0;
$isOffered = in_array($personId, dolical_booking_user_ids(), true);
$publicUrl = dolical_booking_public_url($personId);
// The short link to share, when Saturne makes them : made once, only for a page that is open
$shortUrl  = $isOpen && $isOffered ? dolical_booking_short_url($personId, $user) : '';
$ownTexts  = dolical_booking_person_texts($personId);
$fields    = [
    'DOLICAL_BOOKING_TITLE'      => 'DoliCalBookingPageTitle',
    'DOLICAL_BOOKING_INTRO'      => 'DoliCalBookingIntro',
    'DOLICAL_BOOKING_FORM_TITLE' => 'DoliCalBookingFormTitle',
];

$title = $isSelf ? $langs->trans('DoliCalBookingMyPage') : $langs->trans('DoliCalBookingPageOf', $person->getFullName($langs));

dolical_header($title, 'dolical-page');

require __DIR__ . '/../core/tpl/dolical_booking.tpl.php';

// End of page
llxFooter();
$db->close();
