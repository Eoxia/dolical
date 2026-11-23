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
require_once __DIR__ . '/../lib/dolical_caldav_profile.lib.php';

global $conf, $db, $langs, $user;

$langs->loadLangs(['dolical@dolical', 'agenda', 'errors']);

// Security check : the calendar is for the internal users
$permissionToRead = $user->hasRight('dolical', 'read') && $user->hasRight('agenda', 'myactions', 'read');
if (!$permissionToRead || $user->socid > 0) {
    accessforbidden();
}

$action = GETPOST('action', 'aZ09');

/*
 * Actions
 */

// The synchronization password : made for the devices of the person, shown once, never his Dolibarr password
if (in_array($action, ['caldavpassword', 'caldavrevoke'], true) && dolical_caldav_enabled() && dolical_valid_post()) {
    if ($action === 'caldavpassword') {
        $password = dolical_caldav_new_password($user);
        if ($password !== '') {
            // Kept for the next page only : the redirection must not show it again on a reload. With it the link of
            // the iPhone profile, the previous one dropped with the previous password
            $_SESSION['dolical_caldav_password'] = $password;
            dolical_caldav_clear_profile($user);
            $_SESSION['dolical_caldav_profile'] = dolical_caldav_new_profile_token($user, $password);
        } else {
            setEventMessages($langs->trans('Error'), null, 'errors');
        }
    } elseif (dolical_caldav_revoke_password($user)) {
        dolical_caldav_clear_profile($user);
        setEventMessages($langs->trans('DoliCalSyncPasswordRevoked'), null);
    } else {
        setEventMessages($langs->trans('Error'), null, 'errors');
    }
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

if ($action === 'saveshared' && dolical_can_read_all($user) && dolical_valid_post()) {
    // Only colleagues whose agenda the user may display
    $shared = array_values(array_intersect(GETPOST('shared', 'array:int'), array_keys(dolical_get_calendar_users($user))));
    $shared = array_values(array_diff($shared, [(int) $user->id]));
    // Their lists of reminders too, only when asked for : by default the phone gets his own list only
    $sharedReminders = GETPOSTINT('sharedreminders') > 0 ? '1' : '';
    if (dol_set_user_param($db, $conf, $user, ['DOLICAL_CALDAV_SHARED' => implode(',', $shared), 'DOLICAL_CALDAV_SHARED_REMINDERS' => $sharedReminders]) > 0) {
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
$httpAllowed   = getDolGlobalInt('DOLICAL_CALDAV_ALLOW_HTTP') > 0;
$canShare      = dolical_can_read_all($user);
// The agendas of the colleagues are changed from the phone as in the calendar : with the right on the events of others
$sharedWrite   = dolical_caldav_writable($user, 0);
$calendarUsers = $canShare ? dolical_get_calendar_users($user) : [];
$shared        = dolical_caldav_calendar_users($user);
// The password just made, shown this once
$newPassword   = (string) ($_SESSION['dolical_caldav_password'] ?? '');
$profileToken  = $newPassword !== '' ? (string) ($_SESSION['dolical_caldav_profile'] ?? '') : '';
unset($_SESSION['dolical_caldav_password'], $_SESSION['dolical_caldav_profile']);
$syncPassword  = dolical_caldav_password($user);
// The phone set up in one scan, at the same time and as long as the password is shown : DAVx⁵ link, iPhone profile
$davx5Link     = $newPassword !== '' ? dolical_caldav_davx5_link((string) $user->login, $newPassword) : '';
$profileUrl    = $profileToken !== '' ? dolical_caldav_profile_url($profileToken) : '';
// Opened on the phone to set up : its own steps first, everything typed in it copied from this page. Its kind from the
// 'phone' key : the 'browseros' one of the core says linux for an Android phone
$onPhone       = in_array((string) ($conf->browser->layout ?? ''), ['phone', 'tablet'], true);
$phoneKind     = $onPhone ? (string) (getBrowserInfo((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''))['phone'] ?? '') : '';
$phoneDevice   = ['iphone' => 'apple', 'android' => 'android'][$phoneKind] ?? '';

$title = $langs->trans('DoliCalSyncPhone');

dolical_header($title, 'dolical-page');

require __DIR__ . '/../core/tpl/dolical_sync.tpl.php';

// End of page
llxFooter();
$db->close();
