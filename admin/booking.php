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
 * \file    admin/booking.php
 * \ingroup dolical
 * \brief   Setup of the online booking, in the sections of the appointments of Nextcloud : planning, contact, mails and
 *          notifications, form, security, reminders, video call, advanced. The slots are set in the weekly template
 *          (admin/booking_template.php)
 */

// Load DoliCal environment
if (file_exists('../dolical.main.inc.php')) {
    require_once __DIR__ . '/../dolical.main.inc.php';
} elseif (file_exists('../../dolical.main.inc.php')) {
    require_once __DIR__ . '/../../dolical.main.inc.php';
} else {
    die('Include of dolical main fails');
}

require_once DOL_DOCUMENT_ROOT . '/core/lib/admin.lib.php';
require_once DOL_DOCUMENT_ROOT . '/core/class/html.form.class.php';
require_once __DIR__ . '/../lib/dolical.lib.php';
require_once __DIR__ . '/../lib/dolical_booking.lib.php';

global $conf, $db, $langs, $user;

$langs->loadLangs(['dolical@dolical', 'admin', 'agenda', 'errors']);

$action = GETPOST('action', 'aZ09');

// Security check : the right of the module shows the setup, changing it is for the administrators, as in Dolibarr
$permissionToRead  = $user->hasRight('dolical', 'adminpage', 'read') || $user->admin;
$permissionToWrite = (bool) $user->admin;
if (!$permissionToRead || $user->socid > 0) {
    accessforbidden();
}

// Switches of the page and their value by default
$switches = [
    'DOLICAL_BOOKING'               => 0,
    'DOLICAL_BOOKING_MAIL_CONFIRM'  => 1,
    'DOLICAL_BOOKING_MAIL_CANCEL'   => 1,
    'DOLICAL_BOOKING_NOTIFY_NEW'    => 1,
    'DOLICAL_BOOKING_NOTIFY_CANCEL' => 1,
    'DOLICAL_BOOKING_CAPTCHA'       => 0,
    'DOLICAL_BOOKING_VISIO_ROOMS'   => 0,
];
// Numbers of the page : [default, min, max]
$numbers = [
    'DOLICAL_BOOKING_DURATION'      => [30, 5, 480],
    'DOLICAL_BOOKING_BUFFER_BEFORE' => [0, 0, 240],
    'DOLICAL_BOOKING_BUFFER_AFTER'  => [0, 0, 240],
    'DOLICAL_BOOKING_NOTICE_HOURS'  => [2, 0, 720],
    'DOLICAL_BOOKING_HORIZON_WEEKS' => [4, 1, 52],
    'DOLICAL_BOOKING_MAX_PER_EMAIL' => [3, 0, 100],
    'DOLICAL_BOOKING_MAX_PER_IP'    => [5, 0, 100],
];
// Addresses of the web among the texts : refused when they are not one
$urls = ['DOLICAL_BOOKING_VISIO', 'DOLICAL_BOOKING_VISIO_SERVER', 'DOLICAL_BOOKING_REDIRECT_URL'];

/*
 * Actions
 */

// The email template of a mail of the booking, opened in the editor of Dolibarr : made from the mail when there is none
if ($action === 'mailtemplate' && $permissionToWrite && dolical_valid_post()) {
    $type = GETPOST('type', 'aZ09');
    if (in_array($type, ['dolical_booking', 'dolical_booking_reminder'], true)) {
        dolical_open_template($type);
    }
    setEventMessages($langs->trans('Error') . ' ' . $db->lasterror(), null, 'errors');
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

if ($action === 'update' && $permissionToWrite && dolical_valid_post()) {
    foreach (array_keys($switches) as $constName) {
        dolibarr_set_const($db, $constName, GETPOSTINT($constName) ? 1 : 0, 'integer', 0, '', $conf->entity);
    }
    foreach ($numbers as $constName => [$default, $min, $max]) {
        dolibarr_set_const($db, $constName, min($max, max($min, GETPOSTINT($constName))), 'integer', 0, '', $conf->entity);
    }

    $bookingUsers = dolical_filter_active_users(GETPOST('DOLICAL_BOOKING_USERS', 'array:int'));
    dolibarr_set_const($db, 'DOLICAL_BOOKING_USERS', implode(',', $bookingUsers), 'chaine', 0, '', $conf->entity);
    // The agendas whose events take everybody (an agenda of the team) : none chosen is a choice too
    $conflictUsers = dolical_filter_active_users(GETPOST('DOLICAL_BOOKING_CONFLICT_USERS', 'array:int'));
    dolibarr_set_const($db, 'DOLICAL_BOOKING_CONFLICT_USERS', implode(',', $conflictUsers), 'chaine', 0, '', $conf->entity);
    // The reminders of the customers : none chosen is a choice too, the empty list sends nothing
    $reminders = array_values(array_intersect(array_keys(DOLICAL_BOOKING_REMINDER_DELAYS), array_map('intval', GETPOST('DOLICAL_BOOKING_REMINDERS', 'array:int'))));
    dolibarr_set_const($db, 'DOLICAL_BOOKING_REMINDERS', implode(',', $reminders), 'chaine', 0, '', $conf->entity);
    $phoneField = GETPOST('DOLICAL_BOOKING_PHONE_FIELD', 'aZ09');
    dolibarr_set_const($db, 'DOLICAL_BOOKING_PHONE_FIELD', in_array($phoneField, ['optional', 'required', 'hidden'], true) ? $phoneField : 'optional', 'chaine', 0, '', $conf->entity);

    // Texts of the page : plain titles, the presentation as HTML of the editor, cleaned of anything that runs
    foreach (array_keys(DOLICAL_BOOKING_TEXTS) as $constName) {
        $typed = GETPOST($constName, $constName === 'DOLICAL_BOOKING_INTRO' ? 'restricthtml' : 'nohtml');
        dolibarr_set_const($db, $constName, dolical_booking_store_text($constName, $typed), 'chaine', 0, '', $conf->entity);
    }
    // The other texts, plain : an address typed wrong is said and the one before is kept
    foreach (array_keys(DOLICAL_BOOKING_SETTING_TEXTS) as $constName) {
        $typed = trim(GETPOST($constName, 'nohtml'));
        if ($typed !== '' && in_array($constName, $urls, true)) {
            $clean = dolical_clean_url($typed);
            if ($clean === '') {
                setEventMessages($langs->trans('DoliCalBookingUrlInvalid', $typed), null, 'errors');
                continue;
            }
            $typed = $clean;
        }
        if ($constName === 'DOLICAL_BOOKING_EMAIL' && $typed !== '' && !isValidEmail($typed)) {
            setEventMessages($langs->trans('DoliCalBookingEmailInvalid', $typed), null, 'errors');
            continue;
        }
        dolibarr_set_const($db, $constName, dolical_booking_store_setting($constName, $typed), 'chaine', 0, '', $conf->entity);
    }

    if (GETPOSTINT('DOLICAL_BOOKING') && empty($bookingUsers)) {
        setEventMessages($langs->trans('DoliCalBookingNobody'), null, 'warnings');
    }

    setEventMessages($langs->trans('SetupSaved'), null);
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

/*
 * View
 */

$title = $langs->trans('ModuleSetup', 'DoliCal');
$form  = new Form($db);

// The weekly template in one line : how many slots, which days
$template  = array_filter(dolical_booking_template());
$slotCount = (int) array_sum(array_map('count', $template));
$slotDays  = implode(', ', array_map(function ($isoDay) use ($langs) {
    return $langs->trans('Day' . ($isoDay % 7));
}, array_keys($template)));

// Addresses of the pages : one for each person, the short link when Saturne makes them
$links = [];
if (dolical_booking_enabled()) {
    $people = dolical_booking_hub_enabled() ? [0 => $langs->transnoentities('DoliCalBookingLinkAll')] : [];
    foreach (dolical_booking_people() as $person) {
        $people[$person['id']] = $person['name'];
    }
    // One person only : the page opens on him, the link of everybody is the same
    if (count($people) === 2 && isset($people[0])) {
        unset($people[0]);
    }
    foreach ($people as $personId => $personName) {
        $shortUrl = $personId > 0 ? dolical_booking_short_url((int) $personId, $user) : '';
        $links[]  = [
            'name' => $personName,
            'url'  => $shortUrl !== '' ? $shortUrl : dolical_booking_public_url((int) $personId),
            'edit' => $personId > 0 && $permissionToWrite ? dol_buildpath('/dolical/view/dolical_booking.php', 1) . '?id=' . ((int) $personId) : '',
        ];
    }
}

$js = [dolical_asset('js/dolical.js'), dolical_asset('js/modules/util.js'), dolical_asset('js/modules/setup.js')];
llxHeader('', $title, '', '', 0, 0, $js, [dolical_asset('css/dolical.css')]);

$linkBack = '<a href="' . DOL_URL_ROOT . '/admin/modules.php?restore_lastsearch_values=1">' . $langs->trans('BackToModuleList') . '</a>';
print load_fiche_titre($title, $linkBack, 'title_setup');

print dol_get_fiche_head(dolical_admin_prepare_head(), 'booking', $title, -1, 'dolical_color@dolical');

require __DIR__ . '/../core/tpl/dolical_booking_setup.tpl.php';

print dol_get_fiche_end();
llxFooter();
$db->close();
