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

    // Online booking : the people offered, the length of an appointment, the days and the hours open
    $bookingUsers = dolical_filter_active_users(GETPOST('DOLICAL_BOOKING_USERS', 'array:int'));
    $bookingDays  = array_values(array_intersect(range(1, 7), array_map('intval', GETPOST('DOLICAL_BOOKING_DAYS', 'array:int'))));
    dolibarr_set_const($db, 'DOLICAL_BOOKING', GETPOSTINT('DOLICAL_BOOKING') ? 1 : 0, 'integer', 0, '', $conf->entity);
    dolibarr_set_const($db, 'DOLICAL_BOOKING_USERS', implode(',', $bookingUsers), 'chaine', 0, '', $conf->entity);
    dolibarr_set_const($db, 'DOLICAL_BOOKING_DURATION', min(480, max(5, GETPOSTINT('DOLICAL_BOOKING_DURATION'))), 'integer', 0, '', $conf->entity);
    // No day at all is a choice too : 0 keeps it from falling back on the days by default
    dolibarr_set_const($db, 'DOLICAL_BOOKING_DAYS', !empty($bookingDays) ? implode(',', $bookingDays) : '0', 'chaine', 0, '', $conf->entity);
    $bookingHours = dolical_booking_hours(GETPOST('DOLICAL_BOOKING_HOURS', 'alphanohtml'));
    if (!empty($bookingHours)) {
        dolibarr_set_const($db, 'DOLICAL_BOOKING_HOURS', dolical_booking_format_hours($bookingHours), 'chaine', 0, '', $conf->entity);
    } else {
        setEventMessages($langs->trans('DoliCalBookingHoursInvalid'), null, 'errors');
    }
    // Texts of the page : plain titles, the presentation as HTML of the editor, cleaned of anything that runs
    foreach (array_keys(DOLICAL_BOOKING_TEXTS) as $constName) {
        $typed = GETPOST($constName, $constName === 'DOLICAL_BOOKING_INTRO' ? 'restricthtml' : 'nohtml');
        dolibarr_set_const($db, $constName, dolical_booking_store_text($constName, $typed), 'chaine', 0, '', $conf->entity);
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

$title   = $langs->trans('ModuleSetup', 'DoliCal');
$helpUrl = '';

llxHeader('', $title, $helpUrl);

// Subheader
$linkBack = '<a href="' . DOL_URL_ROOT . '/admin/modules.php?restore_lastsearch_values=1">' . $langs->trans('BackToModuleList') . '</a>';
print load_fiche_titre($title, $linkBack, 'title_setup');

// Configuration header
$head = dolical_admin_prepare_head();
print dol_get_fiche_head($head, 'settings', $title, -1, 'dolical_color@dolical');

print '<span class="opacitymedium">' . $langs->trans('DoliCalSetupHelp') . '</span><br><br>';

print '<form method="POST" action="' . $_SERVER['PHP_SELF'] . '">';
print '<input type="hidden" name="token" value="' . newToken() . '">';
print '<input type="hidden" name="action" value="update">';

print '<table class="noborder centpercent">';
print '<tr class="liste_titre"><td>' . $langs->trans('Parameters') . '</td><td>' . $langs->trans('Description') . '</td><td class="center">' . $langs->trans('Value') . '</td></tr>';

print '<tr class="oddeven"><td>' . $langs->trans('DoliCalDefaultView') . '</td><td>' . $langs->trans('DoliCalDefaultViewHelp') . '</td><td class="center">';
print '<select name="DOLICAL_DEFAULT_VIEW" class="minwidth150">';
foreach ($views as $view => $labelKey) {
    print '<option value="' . $view . '"' . (getDolGlobalString('DOLICAL_DEFAULT_VIEW', 'week') === $view ? ' selected' : '') . '>' . $langs->trans($labelKey) . '</option>';
}
print '</select></td></tr>';

print '<tr class="oddeven"><td>' . $langs->trans('DoliCalWorkingHours') . '</td><td>' . $langs->trans('DoliCalWorkingHoursHelp') . '</td><td class="center nowraponall">';
print '<input type="number" name="DOLICAL_DAY_START_HOUR" class="width50" min="0" max="23" value="' . getDolGlobalInt('DOLICAL_DAY_START_HOUR', 8) . '"> h → ';
print '<input type="number" name="DOLICAL_DAY_END_HOUR" class="width50" min="1" max="24" value="' . getDolGlobalInt('DOLICAL_DAY_END_HOUR', 19) . '"> h';
print '</td></tr>';

print '<tr class="oddeven"><td>' . $langs->trans('DoliCalDefaultDuration') . '</td><td>' . $langs->trans('DoliCalDefaultDurationHelp') . '</td><td class="center nowraponall">';
print '<input type="number" name="DOLICAL_DEFAULT_DURATION" class="width75" min="5" max="1440" step="5" value="' . getDolGlobalInt('DOLICAL_DEFAULT_DURATION', 60) . '"> ' . $langs->trans('Minutes');
print '</td></tr>';

$toggles = [
    'DOLICAL_SHOW_WEEKENDS'      => ['DoliCalShowWeekends', 'DoliCalShowWeekendsHelp', 1],
    'DOLICAL_HIDE_AUTO_EVENTS'   => ['DoliCalHideAutoEvents', 'DoliCalHideAutoEventsHelp', 1],
    'AGENDA_ENABLE_LINKED_ELEMENTS' => ['DoliCalNativeLinkedElements', 'DoliCalNativeLinkedElementsHelp', 0],
    'DOLICAL_NOTIFY_ORGANIZER'   => ['DoliCalNotifyOrganizer', 'DoliCalNotifyOrganizerHelp', 1],
    'DOLICAL_AUTO_REMIND'        => ['DoliCalAutoRemind', 'DoliCalAutoRemindHelp', 0],
    'DOLICAL_REMINDER_BY_DEFAULT' => ['DoliCalSetupReminderByDefault', 'DoliCalSetupReminderByDefaultHelp', 1],
    'DOLICAL_CALDAV'             => ['DoliCalSetupCalDav', 'DoliCalSetupCalDavHelp', 0],
    'DOLICAL_CALDAV_ALLOW_HTTP'  => ['DoliCalSetupCalDavHttp', 'DoliCalSetupCalDavHttpHelp', 0],
];
foreach ($toggles as $constName => $toggle) {
    print '<tr class="oddeven"><td>' . $langs->trans($toggle[0]) . '</td><td>' . $langs->trans($toggle[1]);
    // An option of the Agenda module, not of DoliCal (a hidden constant of the core) : it acts on every event
    if (strpos($constName, 'DOLICAL_') !== 0) {
        print '<br><span class="opacitymedium">' . img_picto('', 'warning', 'class="pictofixedwidth"') . $langs->trans('DoliCalSharedAgendaSetting', $constName) . '</span>';
    }
    print '</td><td class="center">';
    print '<input type="checkbox" name="' . $constName . '" value="1"' . (getDolGlobalInt($constName, $toggle[2]) ? ' checked' : '') . '>';
    print '</td></tr>';
}

print '<tr class="oddeven"><td>' . $langs->trans('DoliCalAutoRemindDays') . '</td><td>' . $langs->trans('DoliCalAutoRemindDaysHelp') . '</td><td class="center nowraponall">';
print '<input type="number" name="DOLICAL_AUTO_REMIND_DAYS" class="width50" min="1" max="30" value="' . getDolGlobalInt('DOLICAL_AUTO_REMIND_DAYS', 2) . '"> ' . $langs->trans('Days');
print '</td></tr>';

print '<tr class="oddeven"><td>' . $langs->trans('DoliCalTimezone') . '</td><td>' . $langs->trans('DoliCalTimezoneHelp') . '</td><td class="center">';
print '<select name="DOLICAL_TIMEZONE" class="minwidth200">';
foreach (timezone_identifiers_list() as $zone) {
    print '<option value="' . dol_escape_htmltag($zone) . '"' . (getDolGlobalString('DOLICAL_TIMEZONE', 'Europe/Paris') === $zone ? ' selected' : '') . '>' . dol_escape_htmltag($zone) . '</option>';
}
print '</select></td></tr>';

print '<tr class="oddeven"><td>' . $langs->trans('DoliCalMailTemplates') . '</td><td>' . $langs->trans('DoliCalMailTemplatesHelp') . '</td><td class="center">';
print '<a href="' . DOL_URL_ROOT . '/admin/mails_templates.php?search_type_template=dolical_invitation">' . $langs->trans('DoliCalMailTemplatesLink') . '</a>';
print '</td></tr>';

print '<tr class="oddeven"><td>' . $langs->trans('DoliCalEventTypes') . '</td><td>' . $langs->trans('DoliCalEventTypesHelp') . '</td><td class="center">';
print getDolGlobalString('AGENDA_USE_EVENT_TYPE') ? img_picto($langs->trans('Enabled'), 'switch_on') : img_picto($langs->trans('Disabled'), 'switch_off');
print ' <a href="' . DOL_URL_ROOT . '/admin/agenda_other.php">' . $langs->trans('DoliCalAgendaSetup') . '</a>';
print '</td></tr>';

print '</table>';

// Online booking : a public page where the customers book a free slot in the agenda of the people chosen
require_once DOL_DOCUMENT_ROOT . '/core/class/html.form.class.php';
$form = new Form($db);

print '<br><table class="noborder centpercent">';
print '<tr class="liste_titre"><td>' . $langs->trans('DoliCalBooking') . '</td><td>' . $langs->trans('Description') . '</td><td class="center">' . $langs->trans('Value') . '</td></tr>';

print '<tr class="oddeven"><td>' . $langs->trans('DoliCalBookingEnable') . '</td><td>' . $langs->trans('DoliCalBookingEnableHelp') . '</td><td class="center">';
print '<input type="checkbox" name="DOLICAL_BOOKING" value="1"' . (getDolGlobalInt('DOLICAL_BOOKING') ? ' checked' : '') . '>';
print '</td></tr>';

print '<tr class="oddeven"><td>' . $langs->trans('DoliCalBookingPeople') . '</td><td>' . $langs->trans('DoliCalBookingPeopleHelp') . '</td><td class="center">';
print $form->multiselectarray('DOLICAL_BOOKING_USERS', dolical_booking_candidates(), dolical_booking_user_ids(), 0, 0, 'minwidth300 maxwidth500 widthcentpercentminusx', 0, 0, '', '', $langs->trans('DoliCalBookingPeoplePlaceholder'));
print '</td></tr>';

print '<tr class="oddeven"><td>' . $langs->trans('DoliCalBookingDuration') . '</td><td>' . $langs->trans('DoliCalBookingDurationHelp') . '</td><td class="center nowraponall">';
print '<input type="number" name="DOLICAL_BOOKING_DURATION" class="width75" min="5" max="480" step="5" value="' . dolical_booking_duration() . '"> ' . $langs->trans('Minutes');
print '</td></tr>';

print '<tr class="oddeven"><td>' . $langs->trans('DoliCalBookingOpening') . '</td><td>' . $langs->trans('DoliCalBookingOpeningHelp', dol_escape_htmltag(dolical_booking_timezone())) . '</td><td class="center">';
foreach (range(1, 7) as $isoDay) {
    print '<label class="nowraponall marginrightonly"><input type="checkbox" name="DOLICAL_BOOKING_DAYS[]" value="' . $isoDay . '"' . (in_array($isoDay, dolical_booking_days(), true) ? ' checked' : '') . '> ' . $langs->trans('Day' . ($isoDay % 7)) . '</label> ';
}
print '<br><input type="text" name="DOLICAL_BOOKING_HOURS" class="minwidth200 margintoponly" placeholder="' . dol_escape_htmltag(DOLICAL_BOOKING_DEFAULT_HOURS) . '" value="' . dol_escape_htmltag(dolical_booking_format_hours(dolical_booking_hours())) . '">';
print '</td></tr>';

// Texts of the page : a title, a presentation of several lines, a title above the slots
foreach (['DOLICAL_BOOKING_TITLE' => 'DoliCalBookingPageTitle', 'DOLICAL_BOOKING_INTRO' => 'DoliCalBookingIntro', 'DOLICAL_BOOKING_FORM_TITLE' => 'DoliCalBookingFormTitle'] as $constName => $labelKey) {
    print '<tr class="oddeven"><td>' . $langs->trans($labelKey) . '</td><td>' . $langs->trans($labelKey . 'Help') . '</td><td class="center">';
    if ($constName === 'DOLICAL_BOOKING_INTRO') {
        print '<div class="left">' . dolical_booking_intro_editor($constName, dolical_booking_text($constName)) . '</div>';
    } else {
        print '<input type="text" name="' . $constName . '" class="quatrevingtpercent" maxlength="' . DOLICAL_BOOKING_TEXTS[$constName] . '" placeholder="' . dol_escape_htmltag($langs->trans($labelKey . 'Example')) . '" value="' . dol_escape_htmltag(dolical_booking_text($constName)) . '">';
    }
    print '</td></tr>';
}

if (dolical_booking_enabled()) {
    print '<tr class="oddeven"><td>' . $langs->trans('DoliCalBookingLinks') . '</td><td>' . $langs->trans('DoliCalBookingLinksHelp') . '</td><td>';
    // One link for each person ; the list of everybody only when the hidden setting DOLICAL_BOOKING_HUB asks for it
    $links = dolical_booking_hub_enabled() ? [0 => $langs->trans('DoliCalBookingLinkAll')] : [];
    foreach (dolical_booking_people() as $person) {
        $links[$person['id']] = $person['name'];
    }
    // One person only : the page opens on him, the link of everybody is the same
    if (count($links) === 2 && isset($links[0])) {
        unset($links[0]);
    }
    foreach ($links as $personId => $linkLabel) {
        // The short link of the person when Saturne makes them : the one to share
        $shortUrl = $personId > 0 ? dolical_booking_short_url((int) $personId, $user) : '';
        $url      = $shortUrl !== '' ? $shortUrl : dolical_booking_public_url((int) $personId);
        print '<div class="tdoverflowmax500">' . img_picto('', 'globe', 'class="pictofixedwidth"') . '<strong>' . dol_escape_htmltag($linkLabel) . '</strong> : ';
        // The texts of each person, written by him or by an administrator
        if ($personId > 0 && $permissionToWrite) {
            print '<a href="' . dol_buildpath('/dolical/view/dolical_booking.php', 1) . '?id=' . ((int) $personId) . '" title="' . dol_escape_htmltag($langs->trans('DoliCalBookingEditTexts')) . '">' . img_picto($langs->trans('DoliCalBookingEditTexts'), 'edit', 'class="pictofixedwidth"') . '</a>';
        }
        print '<a href="' . dol_escape_htmltag($url) . '" target="_blank" rel="noopener">' . dol_escape_htmltag($url) . '</a></div>';
    }
    print '</td></tr>';
}

print '</table>';

if ($permissionToWrite) {
    print '<div class="center"><input type="submit" class="button button-save" value="' . $langs->trans('Save') . '"></div>';
}
print '</form>';

print dol_get_fiche_end();
llxFooter();
$db->close();
