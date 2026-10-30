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
 * \file    lib/dolical_invitation.lib.php
 * \ingroup dolical
 * \brief   Invitations by email : signed answer link, iCalendar file, mails, answers and their trace.
 *
 * Nothing new is stored. The answer of each participant is the native answer_status of
 * llx_actioncomm_resources ; a person invited by a plain email becomes a native contact ; every invitation sent
 * and every answer received leaves an automatic event (AC_OTH_AUTO) linked to the event, carrying the email, as
 * Dolibarr does for the documents it sends. The link needs no storage either : it is signed with the unique key
 * of the instance, for one person and one event.
 */

require_once DOL_DOCUMENT_ROOT . '/core/class/CMailFile.class.php';
require_once __DIR__ . '/dolical_event.lib.php';

/**
 * Codes of the automatic events left by the invitations
 */
const DOLICAL_CODE_INVITE = 'AC_DOLICAL_INVITE';
const DOLICAL_CODE_UPDATE = 'AC_DOLICAL_UPDATE';
const DOLICAL_CODE_REMIND = 'AC_DOLICAL_REMIND';
const DOLICAL_CODE_CANCEL = 'AC_DOLICAL_CANCEL';
const DOLICAL_CODE_ANSWER = 'AC_DOLICAL_ANSWER';

/**
 * What each kind of mail needs : its code of trace, its template type, its texts, whether it carries the answer
 * link, and how the iCalendar file treats the copy the person already has
 */
const DOLICAL_MAIL_MODES = [
    'invitation' => ['code' => DOLICAL_CODE_INVITE, 'template' => 'dolical_invitation', 'subject' => 'DoliCalMailInvitationSubject', 'intro' => 'DoliCalMailInvitationIntro', 'note' => 'DoliCalMailInvitationNote', 'trace' => 'DoliCalTraceInvitationSent', 'answer' => true, 'method' => 'PUBLISH', 'bump' => 0],
    'update'     => ['code' => DOLICAL_CODE_UPDATE, 'template' => 'dolical_update', 'subject' => 'DoliCalMailUpdateSubject', 'intro' => 'DoliCalMailUpdateIntro', 'note' => 'DoliCalMailInvitationNote', 'trace' => 'DoliCalTraceUpdateSent', 'answer' => true, 'method' => 'PUBLISH', 'bump' => 1],
    'reminder'   => ['code' => DOLICAL_CODE_REMIND, 'template' => 'dolical_reminder', 'subject' => 'DoliCalMailReminderSubject', 'intro' => 'DoliCalMailReminderIntro', 'note' => 'DoliCalMailInvitationNote', 'trace' => 'DoliCalTraceReminderSent', 'answer' => true, 'method' => 'PUBLISH', 'bump' => 0],
    'cancel'     => ['code' => DOLICAL_CODE_CANCEL, 'template' => 'dolical_cancel', 'subject' => 'DoliCalMailCancelSubject', 'intro' => 'DoliCalMailCancelIntro', 'note' => 'DoliCalMailCancelNote', 'trace' => 'DoliCalTraceCancelSent', 'answer' => false, 'method' => 'CANCEL', 'bump' => 1],
];

/**
 * Signature of an answer link : one person ('u' user, 'c' contact) for one event of one entity
 *
 * @param  int    $entity   Entity of the event
 * @param  int    $eventId  Id of the event
 * @param  string $kind     'u' or 'c'
 * @param  int    $personId Id of the user or of the contact
 * @return string
 */
function dolical_invitation_signature(int $entity, int $eventId, string $kind, int $personId): string
{
    global $conf;

    $secret = !empty($conf->file->instance_unique_id) ? $conf->file->instance_unique_id : DOL_DATA_ROOT . getDolGlobalString('MAIN_INFO_SOCIETE_NOM');

    return substr(hash_hmac('sha256', 'dolical-invitation|' . $entity . '|' . $eventId . '|' . $kind . '|' . $personId, $secret), 0, 40);
}

/**
 * Public link a person follows to answer
 *
 * @param  ActionComm $event    Event
 * @param  string     $kind     'u' or 'c'
 * @param  int        $personId Id of the user or of the contact
 * @param  bool       $ics      Link to the iCalendar file instead of the page
 * @return string               Absolute URL
 */
function dolical_invitation_url(ActionComm $event, string $kind, int $personId, bool $ics = false): string
{
    global $conf;

    $entity = (int) (!empty($event->entity) ? $event->entity : $conf->entity);
    $params = ['e' => (int) $event->id, 'k' => $kind, 'p' => $personId, 'entity' => $entity, 's' => dolical_invitation_signature($entity, (int) $event->id, $kind, $personId)];
    if ($ics) {
        $params['ics'] = 1;
    }

    return dol_buildpath('/dolical/public/invitation.php', 3) . '?' . http_build_query($params);
}

/**
 * Time zone the dates are written in when no browser tells it : mails, public page
 *
 * @return string
 */
function dolical_reference_timezone(): string
{
    $zones = timezone_identifiers_list();
    if (!empty($_SESSION['dol_tz_string']) && in_array($_SESSION['dol_tz_string'], $zones, true)) {
        return $_SESSION['dol_tz_string'];
    }
    $zone = getDolGlobalString('DOLICAL_TIMEZONE', 'Europe/Paris');

    return in_array($zone, $zones, true) ? $zone : date_default_timezone_get();
}

/**
 * dol_print_date() in a given time zone
 *
 * @param  int    $timestamp Date
 * @param  string $format    Format of dol_print_date()
 * @param  string $zone      Time zone, the reference one by default
 * @return string
 */
function dolical_print_date(int $timestamp, string $format, string $zone = ''): string
{
    try {
        $offset = (new DateTimeZone($zone !== '' ? $zone : dolical_reference_timezone()))->getOffset(new DateTime('@' . $timestamp));
    } catch (Exception $exception) {
        $offset = 0;
    }

    return dol_print_date($timestamp + $offset, $format, 'gmt');
}

/**
 * A day in words, without leading zero : « mardi 3 novembre 2026 ». French writes the day and the month in
 * lower case, Dolibarr translates them with a capital
 *
 * @param  int    $timestamp Date
 * @return string
 */
function dolical_print_day(int $timestamp): string
{
    global $langs;

    $text = dolical_print_date($timestamp, '%A') . ' ' . ((int) dolical_print_date($timestamp, '%d')) . ' ' . dolical_print_date($timestamp, '%B %Y');

    return strpos((string) $langs->defaultlang, 'fr') === 0 ? dol_strtolower($text) : $text;
}

/**
 * When the event takes place, in words : « mercredi 28 octobre 2026, 11:00 – 12:30 »
 *
 * @param  ActionComm $event Event
 * @return string
 */
function dolical_event_when(ActionComm $event): string
{
    global $langs;

    $start   = (int) $event->datep;
    $end     = (int) ($event->datef ?: $event->datep);
    $sameDay = dolical_print_date($start, '%Y%m%d') === dolical_print_date($end, '%Y%m%d');

    if ($event->fulldayevent) {
        $text = dolical_print_day($start);
        if (!$sameDay) {
            $text .= ' → ' . dolical_print_day($end);
        }
        return $text . ' (' . $langs->transnoentities('DoliCalAllDay') . ')';
    }
    if ($sameDay) {
        return dolical_print_day($start) . ', ' . dolical_print_date($start, '%H:%M') . ' – ' . dolical_print_date($end, '%H:%M');
    }

    return dolical_print_day($start) . ' ' . dolical_print_date($start, '%H:%M') . ' → ' . dolical_print_day($end) . ' ' . dolical_print_date($end, '%H:%M');
}

/**
 * When a series takes place, in words : « Toutes les semaines le lundi, 10:00 – 11:00, du lundi 2 novembre 2026
 * au lundi 28 décembre 2026 »
 *
 * @param  ActionComm $event First event of the series
 * @return string
 */
function dolical_series_when(ActionComm $event): string
{
    global $langs;

    require_once __DIR__ . '/dolical_recurrence.lib.php';

    $start = (int) $event->datep;
    $end   = (int) ($event->datef ?: $event->datep);
    $text  = dolical_recurrence_label((string) $event->recurrule, $start);
    if ($text === '') {
        return dolical_event_when($event);
    }
    $text .= ', ' . ($event->fulldayevent ? $langs->transnoentities('DoliCalAllDay') : dolical_print_date($start, '%H:%M') . ' – ' . dolical_print_date($end, '%H:%M'));
    // The last date there is, rather than the end of the rule : a series cut the day before a monday ends on a sunday
    $dates = !empty($event->id) ? dolical_series_occurrences($event) : [];
    $last  = !empty($dates) ? (int) max($dates) : (int) $event->recurdateend;
    if ($last > $start) {
        return $text . ', ' . $langs->transnoentities('DoliCalSeriesFromTo', dolical_print_day($start), dolical_print_day($last));
    }

    return $text . ', ' . $langs->transnoentities('DoliCalSeriesFrom', dolical_print_day($start));
}

/**
 * Events the invitations of an event are counted on : all the dates of its series, the traces of a series
 * belonging to the date they were sent from
 *
 * @param  ActionComm $event Event
 * @return int[]
 */
function dolical_invitation_scope(ActionComm $event): array
{
    global $db;

    if (empty($event->recurid)) {
        return [(int) $event->id];
    }

    $ids   = [(int) $event->id];
    $resql = $db->query('SELECT id FROM ' . MAIN_DB_PREFIX . "actioncomm WHERE recurid = '" . $db->escape($event->recurid) . "' AND entity = " . ((int) $event->entity));
    while ($resql && ($obj = $db->fetch_object($resql))) {
        $ids[] = (int) $obj->id;
    }

    return array_values(array_unique($ids));
}

/**
 * People an invitation can be sent to : the participants with an email, the organizer excepted
 *
 * @param  ActionComm $event    Event, resources loaded
 * @param  string     $kind     Only this person : 'u' or 'c', all of them when empty
 * @param  int        $personId Only this person : id of the user or of the contact
 * @return array                [['kind', 'id', 'name', 'email', 'status']]
 */
function dolical_invitation_recipients(ActionComm $event, string $kind = '', int $personId = 0): array
{
    $recipients = [];
    foreach (dolical_get_event_participants($event)['people'] as $person) {
        $personKind = $person['kind'] === 'user' ? 'u' : 'c';
        if ($person['owner'] || $person['email'] === '' || !isValidEmail($person['email'])) {
            continue;
        }
        if ($kind !== '' && ($personKind !== $kind || $person['id'] !== $personId)) {
            continue;
        }
        $recipients[] = ['kind' => $personKind, 'id' => $person['id'], 'name' => $person['name'], 'email' => $person['email'], 'status' => (int) $person['status']];
    }

    return $recipients;
}

/**
 * The recipients already invited by email : only them have the event in their calendar
 *
 * @param  array $recipients From dolical_invitation_recipients()
 * @param  array $history    From dolical_invitation_history()
 * @return array
 */
function dolical_invited_only(array $recipients, array $history): array
{
    return array_values(array_filter($recipients, function ($recipient) use ($history) {
        return isset($history[dol_strtolower($recipient['email'])]);
    }));
}

/**
 * Last mail sent to each address, read from the automatic events left by the sendings
 *
 * @param  ActionComm $event Event
 * @param  string[]   $codes Kinds of mails, the invitations and their changes by default
 * @return array             [lowercase email => timestamp]
 */
function dolical_invitation_history(ActionComm $event, array $codes = [DOLICAL_CODE_INVITE, DOLICAL_CODE_UPDATE, DOLICAL_CODE_REMIND]): array
{
    global $db;

    $sql  = 'SELECT email_to, datep FROM ' . MAIN_DB_PREFIX . 'actioncomm';
    $sql .= ' WHERE fk_element IN (' . $db->sanitize(implode(',', dolical_invitation_scope($event))) . ") AND elementtype = 'action'";
    $sql .= " AND code IN ('" . implode("', '", array_map([$db, 'escape'], $codes)) . "')";
    $sql .= ' ORDER BY datep ASC';

    $history = [];
    $resql   = $db->query($sql);
    if ($resql) {
        while ($obj = $db->fetch_object($resql)) {
            $history[dol_strtolower(trim((string) $obj->email_to))] = (int) $db->jdate($obj->datep);
        }
        $db->free($resql);
    }

    return $history;
}

/**
 * Version of the event for the calendars : how many times its change was sent to the participants
 *
 * @param  ActionComm $event Event
 * @return int
 */
function dolical_invitation_sequence(ActionComm $event): int
{
    global $db;

    $sql   = 'SELECT COUNT(DISTINCT datep) as nb FROM ' . MAIN_DB_PREFIX . 'actioncomm';
    $sql  .= ' WHERE fk_element IN (' . $db->sanitize(implode(',', dolical_invitation_scope($event))) . ") AND elementtype = 'action' AND code = '" . DOLICAL_CODE_UPDATE . "'";
    $resql = $db->query($sql);
    $obj   = $resql ? $db->fetch_object($resql) : null;

    return $obj ? (int) $obj->nb : 0;
}

/**
 * VTIMEZONE of a zone over a period : one part for each change of time, as PHP knows them
 *
 * @param  string   $zone Time zone
 * @param  int      $from Start of the period
 * @param  int      $to   End of the period
 * @return string[]       Lines of the component
 */
function dolical_ics_timezone(string $zone, int $from, int $to): array
{
    try {
        $timezone = new DateTimeZone($zone);
    } catch (Exception $exception) {
        return [];
    }
    $offset = function (int $seconds): string {
        return ($seconds < 0 ? '-' : '+') . sprintf('%02d%02d', intdiv(abs($seconds), 3600), intdiv(abs($seconds) % 3600, 60));
    };

    $transitions = $timezone->getTransitions($from - 366 * 86400, $to + 366 * 86400);
    $lines       = ['BEGIN:VTIMEZONE', 'TZID:' . $zone];
    $previous    = null;
    foreach ($transitions as $transition) {
        if ($previous !== null) {
            $kind    = $transition['isdst'] ? 'DAYLIGHT' : 'STANDARD';
            $lines[] = 'BEGIN:' . $kind;
            // The local time the change happens at, read on the clock before it
            $lines[] = 'DTSTART:' . gmdate('Ymd\THis', $transition['ts'] + $previous['offset']);
            $lines[] = 'TZOFFSETFROM:' . $offset((int) $previous['offset']);
            $lines[] = 'TZOFFSETTO:' . $offset((int) $transition['offset']);
            $lines[] = 'TZNAME:' . $transition['abbr'];
            $lines[] = 'END:' . $kind;
        }
        $previous = $transition;
    }
    // A zone without change of time : its offset alone
    if (count($lines) === 2 && $previous !== null) {
        $lines = array_merge($lines, ['BEGIN:STANDARD', 'DTSTART:19700101T000000', 'TZOFFSETFROM:' . $offset((int) $previous['offset']), 'TZOFFSETTO:' . $offset((int) $previous['offset']), 'TZNAME:' . $previous['abbr'], 'END:STANDARD']);
    }
    $lines[] = 'END:VTIMEZONE';

    return $lines;
}

/**
 * Identifier of an event for the calendars : the same in the invitations and in the synchronization, the one the
 * phone chose for an event created on it
 *
 * @param  ActionComm $event  Event
 * @param  bool       $series The identifier of its whole series
 * @return string
 */
function dolical_event_uid(ActionComm $event, bool $series = false): string
{
    global $conf;

    $host   = parse_url(DOL_MAIN_URL_ROOT, PHP_URL_HOST) ?: 'dolibarr';
    $entity = (int) ($event->entity ?: $conf->entity);
    if ($series && !empty($event->recurid)) {
        // A series made here is named after its first event : it keeps the identifier of that event
        if (preg_match('/^ev(\d+)$/', (string) $event->recurid, $matches)) {
            return 'dolical-' . $entity . '-' . $matches[1] . '@' . $host;
        }
        return 'dolical-' . $entity . '-r' . preg_replace('/[^A-Za-z0-9-]/', '', (string) $event->recurid) . '@' . $host;
    }
    $dav = dolical_dav_reference($event);
    if ($dav['uid'] !== '') {
        return $dav['uid'];
    }

    return 'dolical-' . $entity . '-' . ((int) $event->id) . '@' . $host;
}

/**
 * iCalendar file of the event, to add it to any calendar. Published, not requested : the answer goes through
 * the link, the buttons of the mail clients would only write to the organizer
 *
 * @param  ActionComm $event     Event
 * @param  User       $organizer Organizer
 * @param  string     $url       Answer link of the person
 * @param  int        $sequence  Version of the event : the number of changes sent, so that a calendar replaces its copy
 * @param  string     $method    'PUBLISH', or 'CANCEL' to take the event out of the calendar of the person
 * @param  array      $options   ['series' => true] for the whole series of the event, given by its first date ;
 *                               ['recurrenceId' => original start] for one date of a series
 * @return string
 */
function dolical_build_ics(ActionComm $event, User $organizer, string $url, int $sequence = 0, string $method = 'PUBLISH', array $options = []): string
{
    global $langs;

    $escape = function (string $text): string {
        return str_replace(["\\", ';', ',', "\r\n", "\n", "\r"], ["\\\\", '\;', '\,', '\n', '\n', ''], $text);
    };
    $utc  = function (int $timestamp): string {
        return gmdate('Ymd\THis\Z', $timestamp);
    };
    $end  = (int) ($event->datef ?: $event->datep);

    $lines = [
        'BEGIN:VCALENDAR',
        'VERSION:2.0',
        'PRODID:-//Eoxia//DoliCal//FR',
        'CALSCALE:GREGORIAN',
        'METHOD:' . ($method === 'CANCEL' ? 'CANCEL' : 'PUBLISH'),
        'BEGIN:VEVENT',
        'UID:' . dolical_event_uid($event, !empty($options['series']) || !empty($options['recurrenceId'])),
        'DTSTAMP:' . $utc(dol_now()),
        'SEQUENCE:' . max(0, $sequence),
    ];
    $isSeries = !empty($options['series']) && !empty($event->recurid);
    if ($event->fulldayevent) {
        $lines[] = 'DTSTART;VALUE=DATE:' . dolical_print_date((int) $event->datep, '%Y%m%d');
        $lines[] = 'DTEND;VALUE=DATE:' . dolical_print_date($end + 86400, '%Y%m%d');
    } elseif ($isSeries) {
        // A series repeats on the wall clock : in its time zone, else it would shift by an hour at the change of time
        $zone    = dolical_reference_timezone();
        $lines[] = 'DTSTART;TZID=' . $zone . ':' . dolical_print_date((int) $event->datep, '%Y%m%dT%H%M%S', $zone);
        $lines[] = 'DTEND;TZID=' . $zone . ':' . dolical_print_date(max($end, (int) $event->datep + 60), '%Y%m%dT%H%M%S', $zone);
    } else {
        $lines[] = 'DTSTART:' . $utc((int) $event->datep);
        $lines[] = 'DTEND:' . $utc(max($end, (int) $event->datep + 60));
    }
    // A series : the rule and its last day ; one date of it : the date it replaces
    if ($isSeries) {
        $rule = dolical_recurrence_normalize((string) $event->recurrule);
        if ($rule !== '') {
            if (!empty($event->recurdateend)) {
                $last  = dolical_local_date((int) $event->recurdateend)->setTime(23, 59, 59)->getTimestamp();
                $rule .= ';UNTIL=' . ($event->fulldayevent ? dolical_print_date($last, '%Y%m%d') : $utc($last));
            }
            $lines[] = 'RRULE:' . $rule;
        }
    } elseif (!empty($options['recurrenceId'])) {
        $lines[] = $event->fulldayevent ? 'RECURRENCE-ID;VALUE=DATE:' . dolical_print_date((int) $options['recurrenceId'], '%Y%m%d') : 'RECURRENCE-ID:' . $utc((int) $options['recurrenceId']);
    }
    $lines[] = 'SUMMARY:' . $escape(dol_html_entity_decode((string) $event->label, ENT_QUOTES | ENT_HTML5));
    if (!empty($event->location)) {
        $lines[] = 'LOCATION:' . $escape((string) $event->location);
    }
    if ($url !== '') {
        $lines[] = 'DESCRIPTION:' . $escape($langs->transnoentities('DoliCalIcsAnswerHere') . ' ' . $url);
        $lines[] = 'URL:' . $url;
    }
    if (!empty($organizer->email)) {
        $lines[] = 'ORGANIZER;CN=' . $escape($organizer->getFullName($langs)) . ':mailto:' . $organizer->email;
    }
    $lines[] = 'STATUS:' . ($method === 'CANCEL' ? 'CANCELLED' : 'CONFIRMED');
    $lines[] = 'END:VEVENT';
    // The time zone of a series, described before the event for the calendars that do not know it by its name (Outlook)
    if ($isSeries && !$event->fulldayevent) {
        array_splice($lines, array_search('BEGIN:VEVENT', $lines, true), 0, dolical_ics_timezone(dolical_reference_timezone(), (int) $event->datep, (int) ($event->recurdateend ?: $event->datep)));
    }
    $lines[] = 'END:VCALENDAR';

    // Lines of 75 octets at most, the next ones starting with a space
    $folded = [];
    foreach ($lines as $line) {
        while (strlen($line) > 75) {
            $cut = 75;
            while ($cut > 1 && (ord($line[$cut]) & 0xC0) === 0x80) {
                $cut--;
            }
            $folded[] = substr($line, 0, $cut);
            $line     = ' ' . substr($line, $cut);
        }
        $folded[] = $line;
    }

    return implode("\r\n", $folded) . "\r\n";
}

/**
 * Template of a mail written by the administrator in the email templates of Dolibarr, if any
 *
 * @param  string $type          'dolical_invitation', 'dolical_update' or 'dolical_answer'
 * @param  string $language      Language of the mail, fr_FR...
 * @return array|null            ['topic', 'content']
 */
function dolical_mail_template(string $type, string $language): ?array
{
    global $db;

    // Read in SQL : CEmailTemplate::fetch() by id adds an empty label to its filter and finds nothing
    $sql  = 'SELECT topic, content FROM ' . MAIN_DB_PREFIX . 'c_email_templates';
    $sql .= " WHERE type_template = '" . $db->escape($type) . "' AND active = 1";
    $sql .= ' AND entity IN (' . getEntity('c_email_templates') . ')';
    $sql .= " AND (lang IS NULL OR lang = '' OR lang = '" . $db->escape($language) . "')";
    $sql .= " ORDER BY (lang = '" . $db->escape($language) . "') DESC, position ASC, rowid ASC";
    $sql .= $db->plimit(1);

    $resql = $db->query($sql);
    if ($resql && ($obj = $db->fetch_object($resql))) {
        return ['topic' => (string) $obj->topic, 'content' => (string) $obj->content];
    }

    return null;
}

/**
 * Body of a mail drawn by the template of the module
 *
 * @param  array $mail ['title', 'intro', 'rows' => [[label, value]], 'button' => [url, text], 'secondary' => [url, text], 'note']
 * @return string      HTML
 */
function dolical_mail_html(array $mail): string
{
    global $langs, $mysoc;

    ob_start();
    include __DIR__ . '/../core/tpl/dolical_mail.tpl.php';

    return (string) ob_get_clean();
}

/**
 * Send a mail through the mail setup of Dolibarr
 *
 * @param  string $to          Recipient
 * @param  string $subject     Subject
 * @param  string $html        Body
 * @param  string $replyTo     Answer address, the organizer
 * @param  string $senderName  Name shown as sender
 * @param  string $trackId     Track id of the mail
 * @param  array  $attachments [['path', 'mime', 'name']]
 * @return string              Empty when sent, the error otherwise
 */
function dolical_send_mail(string $to, string $subject, string $html, string $replyTo, string $senderName, string $trackId, array $attachments = []): string
{
    // The robot address of the instance sends, the organizer receives the replies : the domain of the
    // organizer would fail the checks of the receiving servers
    $fromEmail = getDolGlobalString('MAIN_MAIL_EMAIL_FROM', $replyTo);
    $from      = $senderName !== '' ? dol_string_nospecial($senderName, ' ', [',', ';', '<', '>', '"']) . ' <' . $fromEmail . '>' : $fromEmail;

    $mail = new CMailFile(
        $subject,
        $to,
        $from,
        $html,
        array_column($attachments, 'path'),
        array_column($attachments, 'mime'),
        array_column($attachments, 'name'),
        '',
        '',
        0,
        1,
        '',
        '',
        $trackId,
        '',
        'standard',
        $replyTo
    );
    if (!empty($mail->error)) {
        return $mail->error;
    }

    return $mail->sendfile() ? '' : ($mail->error ?: 'Error');
}

/**
 * Leave an automatic event about the invitation on the event : who, when, which address
 *
 * @param  ActionComm $event     Event
 * @param  User       $author    Who sent, or the organizer for an answer received
 * @param  string     $code      DOLICAL_CODE_*
 * @param  string     $label     Label of the trace
 * @param  string     $emailTo   Address concerned
 * @param  string     $subject   Subject of the mail
 * @param  int        $contactId Contact concerned
 * @param  bool       $linked    Linked to the event ; a cancellation is not, the event is gone and the trace stays
 *                               in the history of the third party and of the contact
 * @return void
 */
function dolical_log_invitation(ActionComm $event, User $author, string $code, string $label, string $emailTo = '', string $subject = '', int $contactId = 0, bool $linked = true): void
{
    global $db;

    $trace = new ActionComm($db);
    $trace->type_code     = 'AC_OTH_AUTO';
    $trace->code          = $code;
    $trace->label         = dol_trunc($label, 250, 'right', 'UTF-8', 1);
    $trace->datep         = dol_now();
    $trace->datef         = $trace->datep;
    $trace->percentage    = -1;
    $trace->userownerid   = (int) ($author->id ?: $event->userownerid);
    $trace->userassigned  = [$trace->userownerid => ['id' => $trace->userownerid]];
    $trace->socid         = (int) $event->socid;
    $trace->contact_id    = $contactId;
    $trace->fk_project    = (int) $event->fk_project;
    if ($linked) {
        $trace->fk_element  = (int) $event->id;
        $trace->elementtype = 'action';
    }
    $trace->email_to      = $emailTo;
    $trace->email_subject = $subject;
    $trace->email_from    = getDolGlobalString('MAIN_MAIL_EMAIL_FROM');
    $trace->create($author, 1);
}

/**
 * Remove the automatic events the invitations of an event left : they would point to nothing
 *
 * @param  int  $eventId Id of the deleted event
 * @param  User $user    Current user
 * @return void
 */
function dolical_delete_invitation_traces(int $eventId, User $user): void
{
    global $db;

    $sql  = 'SELECT id FROM ' . MAIN_DB_PREFIX . 'actioncomm';
    $sql .= ' WHERE fk_element = ' . $eventId . " AND elementtype = 'action'";
    $sql .= " AND code IN ('" . DOLICAL_CODE_INVITE . "', '" . DOLICAL_CODE_UPDATE . "', '" . DOLICAL_CODE_REMIND . "', '" . DOLICAL_CODE_ANSWER . "')";

    $resql = $db->query($sql);
    while ($resql && ($obj = $db->fetch_object($resql))) {
        $trace = new ActionComm($db);
        if ($trace->fetch((int) $obj->id) > 0) {
            $trace->delete($user, 1);
        }
    }
}

/**
 * Send an invitation, the news of a change, a reminder or a cancellation to some participants
 *
 * @param  ActionComm $event      Event, resources loaded
 * @param  User       $sender     Who sends
 * @param  array      $recipients From dolical_invitation_recipients()
 * @param  string     $mode       Key of DOLICAL_MAIL_MODES : 'invitation', 'update', 'reminder' or 'cancel'
 * @param  array      $options    Options of dolical_build_ics() : the whole series, or one date of it
 * @return array                  ['sent' => int, 'errors' => string[]]
 */
function dolical_send_invitations(ActionComm $event, User $sender, array $recipients, string $mode = 'invitation', array $options = []): array
{
    global $conf, $db, $langs;

    $langs->load('dolical@dolical');

    $result = ['sent' => 0, 'errors' => []];
    if (empty($recipients) || !isset(DOLICAL_MAIL_MODES[$mode])) {
        return $result;
    }
    $settings = DOLICAL_MAIL_MODES[$mode];

    $organizer = new User($db);
    if ($event->userownerid <= 0 || $organizer->fetch($event->userownerid) <= 0) {
        $organizer = $sender;
    }
    $organizerName = $organizer->getFullName($langs);
    $replyTo       = !empty($organizer->email) ? $organizer->email : (string) $sender->email;
    $label         = dol_html_entity_decode((string) $event->label, ENT_QUOTES | ENT_HTML5);
    $when          = !empty($options['series']) && !empty($event->recurid) ? dolical_series_when($event) : dolical_event_when($event);
    $template      = dolical_mail_template($settings['template'], $langs->defaultlang);
    // Counted once : the traces of this sending would raise it from one mail to the next
    $sequence      = dolical_invitation_sequence($event) + $settings['bump'];
    $tempDir       = $conf->dolical->dir_temp ?? (DOL_DATA_ROOT . '/dolical/temp');
    dol_mkdir($tempDir);

    foreach ($recipients as $recipient) {
        // A cancelled event has nothing left to answer
        $url = $settings['answer'] ? dolical_invitation_url($event, $recipient['kind'], (int) $recipient['id']) : '';

        $substitutions = array_merge(getCommonSubstitutionArray($langs, 0, null, $event), [
            '__DOLICAL_EVENT_LABEL__'    => $label,
            '__DOLICAL_EVENT_DATE__'     => $when,
            '__DOLICAL_EVENT_LOCATION__' => (string) $event->location,
            '__DOLICAL_ORGANIZER_NAME__' => $organizerName,
            '__DOLICAL_RECIPIENT_NAME__' => $recipient['name'],
            '__DOLICAL_ANSWER_LINK__'    => $url,
        ]);

        if ($template !== null) {
            $subject = make_substitutions($template['topic'], $substitutions, $langs);
            $html    = make_substitutions($template['content'], $substitutions, $langs);
            if (!dol_textishtml($html)) {
                $html = dol_nl2br(dol_escape_htmltag($html, 0, 1));
            }
        } else {
            $subject = $langs->transnoentities($settings['subject'], $label);
            $rows    = [[$langs->transnoentities('DoliCalMailWhen'), $when]];
            if (!empty($event->location)) {
                $rows[] = [$langs->transnoentities('Location'), (string) $event->location];
            }
            $rows[] = [$langs->transnoentities('DoliCalOrganizer'), $organizerName];
            $html   = dolical_mail_html([
                'title'     => $label,
                'intro'     => $langs->transnoentities($settings['intro'], $recipient['name'], $organizerName),
                'rows'      => $rows,
                'button'    => $settings['answer'] ? [$url, $langs->transnoentities('DoliCalMailAnswerButton')] : [],
                'secondary' => $settings['answer'] ? [dolical_invitation_url($event, $recipient['kind'], (int) $recipient['id'], true), $langs->transnoentities('DoliCalAddToCalendar')] : [],
                'note'      => $langs->transnoentities($settings['note']),
            ]);
        }

        $icsFile = $tempDir . '/invitation-' . ((int) $event->id) . '-' . $recipient['kind'] . ((int) $recipient['id']) . '.ics';
        file_put_contents($icsFile, dolical_build_ics($event, $organizer, $url, $sequence, $settings['method'], $options));

        $error = dolical_send_mail($recipient['email'], $subject, $html, $replyTo, $organizerName, 'rea' . ((int) $event->id), [['path' => $icsFile, 'mime' => 'text/calendar', 'name' => 'invitation.ics']]);
        dol_delete_file($icsFile, 0, 1, 0, null, false, 0);

        if ($error !== '') {
            $result['errors'][] = $recipient['email'] . ' : ' . strip_tags($error);
            continue;
        }

        $result['sent']++;
        dolical_log_invitation(
            $event,
            $sender,
            $settings['code'],
            $langs->transnoentities($settings['trace'], $recipient['name'], $label),
            $recipient['email'],
            $subject,
            $recipient['kind'] === 'c' ? (int) $recipient['id'] : 0,
            $mode !== 'cancel'
        );
    }

    return $result;
}

/**
 * Language a person reads his mails in, also from a scheduled job : his own, else the one of the instance, else
 * the one of the country of the company. "auto" means the browser, and a scheduled job has none : it would fall
 * back on English
 *
 * @param  User   $person User
 * @return string         Language code, fr_FR...
 */
function dolical_mail_language(User $person): string
{
    global $mysoc;

    if (!empty($person->lang)) {
        return $person->lang;
    }
    $default = getDolGlobalString('MAIN_LANG_DEFAULT');
    if ($default !== '' && $default !== 'auto') {
        return $default;
    }
    if (!empty($mysoc->country_code)) {
        $fromCountry = getLanguageCodeFromCountryCode($mysoc->country_code);
        if (!empty($fromCountry)) {
            return $fromCountry;
        }
    }

    return 'auto';
}

/**
 * Participants invited by email who have not answered yet
 *
 * @param  ActionComm $event Event, resources loaded
 * @return array             From dolical_invitation_recipients()
 */
function dolical_pending_recipients(ActionComm $event): array
{
    return array_values(array_filter(dolical_invited_only(dolical_invitation_recipients($event), dolical_invitation_history($event)), function ($recipient) {
        return $recipient['status'] === 0;
    }));
}

/**
 * Remind once the guests who have not answered, for the events starting within some days. Run by the scheduled
 * job of the module ; each organizer sends in his own language
 *
 * @param  int   $days Events starting between now and now + $days days
 * @return array       ['events' => int, 'sent' => int, 'errors' => string[]]
 */
function dolical_send_due_reminders(int $days): array
{
    global $conf, $db, $langs;

    $now  = dol_now();
    $sql  = 'SELECT DISTINCT a.id FROM ' . MAIN_DB_PREFIX . 'actioncomm as a';
    $sql .= ' INNER JOIN ' . MAIN_DB_PREFIX . "actioncomm as t ON t.fk_element = a.id AND t.elementtype = 'action'";
    $sql .= " AND t.code IN ('" . DOLICAL_CODE_INVITE . "', '" . DOLICAL_CODE_UPDATE . "')";
    $sql .= ' WHERE a.entity IN (' . getEntity('agenda') . ')';
    $sql .= " AND a.datep > '" . $db->idate($now) . "' AND a.datep <= '" . $db->idate($now + max(1, $days) * 86400) . "'";
    $sql .= ' AND a.percent < 100';

    $summary = ['events' => 0, 'sent' => 0, 'errors' => []];
    $resql   = $db->query($sql);
    if (!$resql) {
        $summary['errors'][] = $db->lasterror();
        return $summary;
    }
    $ids = [];
    while ($obj = $db->fetch_object($resql)) {
        $ids[] = (int) $obj->id;
    }

    $savedLangs = $langs;
    foreach ($ids as $id) {
        $event = new ActionComm($db);
        if ($event->fetch($id) <= 0) {
            continue;
        }
        // Once per guest : a person already reminded is not reminded again
        $reminded = dolical_invitation_history($event, [DOLICAL_CODE_REMIND]);
        $pending  = array_values(array_filter(dolical_pending_recipients($event), function ($recipient) use ($reminded) {
            return !isset($reminded[dol_strtolower($recipient['email'])]);
        }));
        $organizer = new User($db);
        if (empty($pending) || $event->userownerid <= 0 || $organizer->fetch($event->userownerid) <= 0) {
            continue;
        }

        $langs = new Translate('', $conf);
        $langs->setDefaultLang(dolical_mail_language($organizer));
        $langs->loadLangs(['main', 'agenda', 'dolical@dolical']);

        $result = dolical_send_invitations($event, $organizer, $pending, 'reminder');
        $summary['events']++;
        $summary['sent']  += $result['sent'];
        $summary['errors'] = array_merge($summary['errors'], $result['errors']);
    }
    $langs = $savedLangs;

    return $summary;
}

/**
 * Whether a person is still one of the participants of the event
 *
 * @param  ActionComm $event    Event, resources loaded
 * @param  string     $kind     'u' or 'c'
 * @param  int        $personId Id of the user or of the contact
 * @return bool
 */
function dolical_is_participant(ActionComm $event, string $kind, int $personId): bool
{
    if ($kind === 'u') {
        return isset($event->userassigned[$personId]) || (int) $event->userownerid === $personId;
    }

    return isset($event->socpeopleassigned[$personId]) || (int) $event->contact_id === $personId;
}

/**
 * Record the answer of a participant in the native answer_status, leave its trace and tell the organizer
 *
 * @param  ActionComm $event    Event, resources loaded
 * @param  string     $kind     'u' or 'c'
 * @param  int        $personId Id of the user or of the contact
 * @param  int        $status   1 accepted, 2 maybe, -1 declined
 * @param  string     $name     Name of the person
 * @return bool
 */
function dolical_record_answer(ActionComm $event, string $kind, int $personId, int $status, string $name): bool
{
    global $conf, $db, $langs;

    if (!in_array($status, [-1, 1, 2], true) || !dolical_is_participant($event, $kind, $personId)) {
        return false;
    }

    $type   = $kind === 'u' ? 'user' : 'socpeople';
    $exists = $kind === 'u' ? isset($event->userassigned[$personId]) : isset($event->socpeopleassigned[$personId]);
    if ($exists) {
        $sql  = 'UPDATE ' . MAIN_DB_PREFIX . 'actioncomm_resources SET answer_status = ' . ((int) $status);
        $sql .= ' WHERE fk_actioncomm = ' . ((int) $event->id) . " AND element_type = '" . $type . "' AND fk_element = " . ((int) $personId);
    } else {
        // The main contact or the owner may have no line of their own yet
        $sql  = 'INSERT INTO ' . MAIN_DB_PREFIX . 'actioncomm_resources (fk_actioncomm, element_type, fk_element, mandatory, transparency, answer_status)';
        $sql .= ' VALUES (' . ((int) $event->id) . ", '" . $type . "', " . ((int) $personId) . ', 0, 0, ' . ((int) $status) . ')';
    }
    if (!$db->query($sql)) {
        return false;
    }

    // The invitation of a series is answered once for all its dates to come
    if (!empty($event->recurid)) {
        $sql  = 'UPDATE ' . MAIN_DB_PREFIX . 'actioncomm_resources SET answer_status = ' . ((int) $status);
        $sql .= " WHERE element_type = '" . $type . "' AND fk_element = " . ((int) $personId);
        $sql .= ' AND fk_actioncomm IN (SELECT id FROM (SELECT id FROM ' . MAIN_DB_PREFIX . "actioncomm WHERE recurid = '" . $db->escape($event->recurid) . "'";
        $sql .= ' AND entity = ' . ((int) $event->entity) . " AND datep >= '" . $db->idate(min((int) $event->datep, dol_now())) . "') as series)";
        $db->query($sql);
    }

    $organizer = new User($db);
    if ($event->userownerid <= 0 || $organizer->fetch($event->userownerid) <= 0) {
        return true;
    }

    // The organizer reads it in his own language, whoever answered
    $outputLangs = new Translate('', $conf);
    $outputLangs->setDefaultLang(dolical_mail_language($organizer));
    $outputLangs->loadLangs(['main', 'agenda', 'dolical@dolical']);

    $answerKeys = [1 => 'DoliCalAnswerAccepted', 2 => 'DoliCalAnswerTentative', -1 => 'DoliCalAnswerDeclined'];
    $answerText = $outputLangs->transnoentities($answerKeys[$status]);
    $label      = dol_html_entity_decode((string) $event->label, ENT_QUOTES | ENT_HTML5);

    dolical_log_invitation($event, $organizer, DOLICAL_CODE_ANSWER, $outputLangs->transnoentities('DoliCalTraceAnswer', $name, $answerText, $label), '', '', $kind === 'c' ? $personId : 0);

    if (!getDolGlobalInt('DOLICAL_NOTIFY_ORGANIZER', 1) || empty($organizer->email) || ($kind === 'u' && $personId === (int) $organizer->id)) {
        return true;
    }

    $savedLangs = $langs;
    $langs      = $outputLangs;
    $eventUrl   = dol_buildpath('/dolical/dolicalindex.php', 3) . '?event=' . ((int) $event->id);
    $template   = dolical_mail_template('dolical_answer', $outputLangs->defaultlang);
    if ($template !== null) {
        $substitutions = array_merge(getCommonSubstitutionArray($outputLangs, 0, null, $event), [
            '__DOLICAL_EVENT_LABEL__'    => $label,
            '__DOLICAL_EVENT_DATE__'     => dolical_event_when($event),
            '__DOLICAL_EVENT_LOCATION__' => (string) $event->location,
            '__DOLICAL_ORGANIZER_NAME__' => $organizer->getFullName($outputLangs),
            '__DOLICAL_RECIPIENT_NAME__' => $name,
            '__DOLICAL_ANSWER__'         => $answerText,
            '__DOLICAL_EVENT_LINK__'     => $eventUrl,
        ]);
        $subject = make_substitutions($template['topic'], $substitutions, $outputLangs);
        $html    = make_substitutions($template['content'], $substitutions, $outputLangs);
        if (!dol_textishtml($html)) {
            $html = dol_nl2br(dol_escape_htmltag($html, 0, 1));
        }
    } else {
        $subject = $outputLangs->transnoentities('DoliCalMailAnswerSubject', $name, $answerText, $label);
        $html    = dolical_mail_html([
            'title'  => $label,
            'intro'  => $outputLangs->transnoentities('DoliCalMailAnswerIntro', $name, $answerText),
            'rows'   => [[$outputLangs->transnoentities('DoliCalMailWhen'), dolical_event_when($event)]],
            'button' => [$eventUrl, $outputLangs->transnoentities('DoliCalMailOpenEvent')],
            'note'   => '',
        ]);
    }
    dolical_send_mail($organizer->email, $subject, $html, '', 'DoliCal', 'rea' . ((int) $event->id));
    $langs = $savedLangs;

    return true;
}

/**
 * Contacts of the form : ids of existing contacts, and people typed as an email, found among the contacts
 * or created as new contacts of the third party of the event
 *
 * @param  array  $values Values of the field : ids, or 'new:' followed by the typed text, URL encoded
 * @param  int    $socid  Third party of the event
 * @param  User   $user   Current user
 * @param  array  $errors Errors, filled
 * @return int[]          Ids of contacts
 */
function dolical_resolve_contacts(array $values, int $socid, User $user, array &$errors): array
{
    global $db, $langs;

    $ids = [];
    foreach ($values as $value) {
        $value = (string) $value;
        if (preg_match('/^\d+$/', $value)) {
            $ids[] = (int) $value;
            continue;
        }
        if (strpos($value, 'new:') !== 0) {
            continue;
        }

        // "Jean Dupont <jean@example.com>", "Jean Dupont jean@example.com" or the address alone
        $typed = trim(rawurldecode(substr($value, 4)));
        $name  = '';
        $email = $typed;
        if (preg_match('/^(.*?)\s*<([^<>\s]+)>\s*$/', $typed, $matches) || preg_match('/^(.*?)\s*([^\s<>]+@[^\s<>]+)\s*$/', $typed, $matches)) {
            $name  = trim($matches[1], " \t\"'");
            $email = trim($matches[2]);
        }
        if (!isValidEmail($email)) {
            $errors[] = $langs->trans('ErrorBadEMail', $typed);
            continue;
        }

        $sql  = 'SELECT rowid FROM ' . MAIN_DB_PREFIX . 'socpeople';
        $sql .= " WHERE email = '" . $db->escape($email) . "' AND statut = 1 AND entity IN (" . getEntity('contact') . ')';
        $sql .= ' ORDER BY ' . ($socid > 0 ? '(fk_soc = ' . ((int) $socid) . ') DESC, ' : '') . 'rowid ASC';
        $sql .= $db->plimit(1);
        $resql = $db->query($sql);
        if ($resql && ($obj = $db->fetch_object($resql))) {
            $ids[] = (int) $obj->rowid;
            continue;
        }

        if (!$user->hasRight('societe', 'contact', 'creer')) {
            $errors[] = $langs->trans('DoliCalCannotCreateContact', $email);
            continue;
        }

        if ($name === '') {
            $name = ucwords(str_replace(['.', '_', '-'], ' ', substr($email, 0, strpos($email, '@'))));
        }
        $words   = preg_split('/\s+/', $name, 2);
        $contact = new Contact($db);
        $contact->firstname = count($words) > 1 ? $words[0] : '';
        $contact->lastname  = count($words) > 1 ? $words[1] : $words[0];
        $contact->email     = $email;
        $contact->socid     = max(0, $socid);
        $contact->statut    = 1;
        $contact->priv      = 0;
        $contactId          = $contact->create($user);
        if ($contactId > 0) {
            $ids[] = (int) $contactId;
        } else {
            $errors[] = $email . ' : ' . ($contact->error ?: implode(', ', $contact->errors));
        }
    }

    return array_values(array_unique(array_filter($ids)));
}
