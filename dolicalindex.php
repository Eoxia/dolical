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
 * \file    dolicalindex.php
 * \ingroup dolical
 * \brief   Calendar of DoliCal : the native events of one or several people, by day, week, team, month or list
 */

// Load DoliCal environment
if (file_exists('dolical.main.inc.php')) {
    require_once __DIR__ . '/dolical.main.inc.php';
} elseif (file_exists('../dolical.main.inc.php')) {
    require_once __DIR__ . '/../dolical.main.inc.php';
} else {
    die('Include of dolical main fails');
}

// Load DoliCal libraries
require_once __DIR__ . '/lib/dolical_event.lib.php';

global $conf, $db, $langs, $user;

saturne_load_langs(['agenda', 'companies']);

// Security check
$permissionToRead = $user->hasRight('dolical', 'read') && $user->hasRight('agenda', 'myactions', 'read');
saturne_check_access($permissionToRead);

/*
 * Actions
 */

// The media block of a report sends its photos, documents and voice notes to the page that shows it, and draws
// itself again from the answer. Only into the documents of an event the user may write the report of
$action = GETPOST('action', 'aZ09');
if (in_array($action, ['uploadPhoto', 'uploadFile', 'linkPhoto', 'deletePhoto', 'deleteFile', 'add_audio', 'delete_audio'], true)) {
    require_once __DIR__ . '/lib/dolical_invitation.lib.php';

    $subDir     = GETPOST('sub_dir', 'alphanohtml');
    $token      = GETPOST('token', 'alpha');
    $mediaEvent = preg_match('/^\d+$/', $subDir) && GETPOST('module_name', 'alpha') === 'actions' ? dolical_load_event((int) $subDir, $user) : null;
    if ($mediaEvent === null || $token === '' || !in_array($token, [currentToken(), newToken()], true) || !dolical_can_report($user, $mediaEvent)) {
        http_response_code(403);
        exit;
    }

    dolical_report_media_action($action, $mediaEvent);
    // The messages of the upload would pop up on the next page : the block tells by itself
    unset($_SESSION['dol_events']);

    header('Content-Type: text/html; charset=utf-8');
    echo '<div>' . dolical_report_media_block($mediaEvent, true) . '</div>';
    exit;
}

/*
 * View
 */

$calendarUsers  = dolical_get_calendar_users($user);
$calendarGroups = dolical_get_calendar_groups($user, $calendarUsers);
$eventTypes     = dolical_get_event_types();

$defaultView = getDolGlobalString('DOLICAL_DEFAULT_VIEW', 'week');
$views       = ['day' => 'DoliCalViewDay', 'week' => 'DoliCalViewWeek', 'team' => 'DoliCalViewTeam', 'month' => 'DoliCalViewMonth', 'list' => 'DoliCalViewList'];

// Everything the JavaScript needs travels in one data attribute : no inline script
$calendarConfig = [
    'urls' => [
        'events' => dol_buildpath('/dolical/ajax/events.php', 1),
        'event'  => dol_buildpath('/dolical/ajax/event.php', 1),
        'search' => dol_buildpath('/dolical/ajax/search.php', 1),
    ],
    'token'           => newToken(),
    'currentUser'     => (int) $user->id,
    'canReadAll'      => dolical_can_read_all($user),
    'canCreate'       => dolical_can_create($user),
    'defaultView'     => isset($views[$defaultView]) ? $defaultView : 'week',
    'dayStart'        => min(23, max(0, getDolGlobalInt('DOLICAL_DAY_START_HOUR', 8))),
    'dayEnd'          => min(24, max(1, getDolGlobalInt('DOLICAL_DAY_END_HOUR', 19))),
    'showWeekends'    => getDolGlobalInt('DOLICAL_SHOW_WEEKENDS', 1),
    'hideAuto'        => getDolGlobalInt('DOLICAL_HIDE_AUTO_EVENTS', 1),
    'defaultDuration' => max(5, getDolGlobalInt('DOLICAL_DEFAULT_DURATION', 60)),
    'locale'          => str_replace('_', '-', $langs->defaultlang),
    'users'           => array_values($calendarUsers),
    'groups'          => $calendarGroups,
    'openEvent'       => GETPOSTINT('event'),
    'openCreate'      => GETPOST('action', 'aZ09') === 'create',
    'createParams'    => [
        'socid'      => GETPOSTINT('socid'),
        'projectid'  => GETPOSTINT('projectid'),
        'contactids' => GETPOST('contactids', 'intcomma'),
        'elements'   => GETPOST('elements', 'alphanohtml'),
    ],
    'i18n' => [
        'today'          => $langs->transnoentities('Today'),
        'allDay'         => $langs->transnoentities('DoliCalAllDay'),
        'noEvent'        => $langs->transnoentities('DoliCalNoEvent'),
        'noPeople'       => $langs->transnoentities('DoliCalNoPeopleSelected'),
        'more'           => $langs->transnoentities('DoliCalMoreEvents', '%COUNT%'),
        'week'           => $langs->transnoentities('DoliCalWeekShort'),
        'confirmDelete'  => $langs->transnoentities('DoliCalConfirmDelete'),
        'confirmFile'    => $langs->transnoentities('DoliCalConfirmDeleteFile'),
        'copied'         => $langs->transnoentities('DoliCalCopied'),
        'saved'          => $langs->transnoentities('DoliCalSaved'),
        'error'          => $langs->transnoentities('Error'),
        'loadError'      => $langs->transnoentities('DoliCalLoadError'),
        'labelRequired'  => $langs->transnoentities('ErrorFieldRequired', $langs->transnoentities('Title')),
        'datesInvalid'   => $langs->transnoentities('DoliCalDatesInvalid'),
        'filesSelected'  => $langs->transnoentities('DoliCalFilesSelected', '%COUNT%'),
        'untitled'       => $langs->transnoentities('DoliCalUntitled'),
        'searching'      => $langs->transnoentities('DoliCalSearching'),
        'noResults'      => $langs->transnoentities('NoResults'),
        'inputTooShort'  => $langs->transnoentities('DoliCalTypeToSearch'),
        'contacts'       => $langs->transnoentities('DoliCalContactsCount', '%COUNT%'),
        'moved'          => $langs->transnoentities('DoliCalMoved'),
        'cannotEdit'     => $langs->transnoentities('DoliCalCannotEdit'),
        'tooMany'        => $langs->transnoentities('DoliCalTooManyPeople'),
        'invitationsSent' => $langs->transnoentities('DoliCalInvitationsSent', '%COUNT%'),
        'cancelledSent'   => $langs->transnoentities('DoliCalCancellationsSent', '%COUNT%'),
        'remindersSent'   => $langs->transnoentities('DoliCalRemindersSent', '%COUNT%'),
        'confirmDeleteInvited' => $langs->transnoentities('DoliCalConfirmDeleteInvited', '%COUNT%'),
        'busyWarning'     => $langs->transnoentities('DoliCalBusyWarning'),
        'busyOther'       => $langs->transnoentities('DoliCalBusyOther'),
        'answerYes'       => $langs->transnoentities('DoliCalAnswerAccepted'),
        'answerMaybe'     => $langs->transnoentities('DoliCalAnswerTentative'),
        'answerNo'        => $langs->transnoentities('DoliCalAnswerDeclined'),
        'answerPending'   => $langs->transnoentities('DoliCalAnswerPending'),
        // Repetition : the choices are worded again from the day chosen
        'repeatNone'           => $langs->transnoentities('DoliCalRepeatNone'),
        'repeatDaily'          => $langs->transnoentities('DoliCalRepeatDaily'),
        'repeatWeekdays'       => $langs->transnoentities('DoliCalRepeatWeekdays'),
        'repeatWeekly'         => $langs->transnoentities('DoliCalRepeatWeekly', '%DAY%'),
        'repeatBiweekly'       => $langs->transnoentities('DoliCalRepeatBiweekly', '%DAY%'),
        'repeatMonthlyDay'     => $langs->transnoentities('DoliCalRepeatMonthlyDay', '%DATE%'),
        'repeatMonthlyWeekday' => $langs->transnoentities('DoliCalRepeatMonthlyWeekday', '%NTH%', '%DAY%'),
        'repeatYearly'         => $langs->transnoentities('DoliCalRepeatYearly', '%DATE%'),
        'nth1'                 => $langs->transnoentities('DoliCalNth1'),
        'nth2'                 => $langs->transnoentities('DoliCalNth2'),
        'nth3'                 => $langs->transnoentities('DoliCalNth3'),
        'nth4'                 => $langs->transnoentities('DoliCalNth4'),
        'nthLast'              => $langs->transnoentities('DoliCalNthLast'),
        'repeating'            => $langs->transnoentities('DoliCalRepeating'),
        'occurrencesCreated'   => $langs->transnoentities('DoliCalOccurrencesCreated', '%COUNT%'),
        'occurrencesTruncated' => $langs->transnoentities('DoliCalOccurrencesTruncated', '%COUNT%'),
        'movedOccurrence'      => $langs->transnoentities('DoliCalMovedOccurrence'),
        'deletedOccurrences'   => $langs->transnoentities('DoliCalDeletedOccurrences', '%COUNT%'),
        'untilInvalid'         => $langs->transnoentities('DoliCalRepeatUntilInvalid'),
        // Reminders and reports
        'notifBlocked'         => $langs->transnoentities('DoliCalReminderBlocked'),
        'reportSaved'          => $langs->transnoentities('DoliCalReportSaved'),
        'reportWriting'        => $langs->transnoentities('DoliCalReportWriting'),
        'reportWritten'        => $langs->transnoentities('DoliCalReportWritten'),
        'reportEmpty'          => $langs->transnoentities('DoliCalReportEmptyVoice'),
        'reportVoiceKept'      => $langs->transnoentities('DoliCalReportVoiceKept'),
        'reportAiFailed'       => $langs->transnoentities('DoliCalReportAiFailed', '%ERROR%'),
        'reportNudgeText'      => $langs->transnoentities('DoliCalReportNudgeText', '%LABEL%'),
        'hasReport'            => $langs->transnoentities('DoliCalReport'),
    ],
];

$title = $langs->trans('DoliCalCalendar');

// With the media library of DoliCal : the reports pick their photos in it
saturne_header(1, '', $title, '', '', 0, 0, [], [], '', 'dolical-page');

require __DIR__ . '/core/tpl/dolical_calendar.tpl.php';

// End of page
llxFooter();
$db->close();
