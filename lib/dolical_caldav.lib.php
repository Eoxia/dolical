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
 * \file    lib/dolical_caldav.lib.php
 * \ingroup dolical
 * \brief   Synchronization with the calendars of the phones and of the mail clients (CalDAV) : the native events of a
 *          person written as iCalendar, and the events created, moved, answered or deleted on a phone written back
 *          into them. Nothing new is stored : the file name and the identifier a phone gives to an event it created
 *          go to the external reference of the native event.
 */

require_once __DIR__ . '/dolical_series.lib.php';

/**
 * Days of the past a phone gets : the older events stay in Dolibarr only
 */
const DOLICAL_CALDAV_PAST_DAYS = 180;

/**
 * Dates of a series walked through at most, from a phone or an imported file, before the ones kept
 */
const DOLICAL_CALDAV_MAX_STEPS = 10000;

/**
 * Whether the synchronization is open
 *
 * @return bool
 */
function dolical_caldav_enabled(): bool
{
    return getDolGlobalInt('DOLICAL_CALDAV', 1) > 0;
}

/**
 * Address to give to the phone
 *
 * @return string
 */
function dolical_caldav_url(): string
{
    return dol_buildpath('/dolical/dav/caldav.php', 3) . '/';
}

/**
 * Agendas a person gets on the phone : his own, then the ones of the colleagues he chose, read only
 *
 * @param  User  $user Person
 * @return int[]       Ids of users, his own first
 */
function dolical_caldav_calendar_users(User $user): array
{
    $ids = [(int) $user->id];
    if (dolical_can_read_all($user)) {
        $chosen = array_filter(array_map('intval', explode(',', (string) ($user->conf->DOLICAL_CALDAV_SHARED ?? ''))));
        $ids    = array_merge($ids, array_intersect($chosen, array_keys(dolical_get_calendar_users($user))));
    }

    return array_values(array_unique($ids));
}

/**
 * Name of the file of an event in the calendar : the one the phone chose, else one made from its id
 *
 * @param  int    $id     Event
 * @param  string $refExt External reference of the event
 * @return string
 */
function dolical_caldav_uri(int $id, string $refExt): string
{
    if (strpos($refExt, 'dav:') === 0) {
        $uri = explode('|', substr($refExt, 4), 2)[0];
        if ($uri !== '') {
            return $uri;
        }
    }

    return 'dolical-' . $id . '.ics';
}

/**
 * Description of an event for the phone : its own, then its report under a line the way back recognizes
 *
 * @param  string $note   Description of the event, HTML or text
 * @param  string $report Report
 * @return string
 */
function dolical_caldav_description(string $note, string $report): string
{
    $text = trim(dol_html_entity_decode(dol_string_nohtmltag($note, 0), ENT_QUOTES | ENT_HTML5));
    if ($report !== '') {
        $text .= ($text !== '' ? "\n\n" : '') . dolical_caldav_report_mark() . "\n" . $report;
    }

    return $text;
}

/**
 * Line putting the report apart from the description, on the phone
 *
 * @return string
 */
function dolical_caldav_report_mark(): string
{
    global $langs;

    return '— ' . $langs->transnoentities('DoliCalReport') . ' —';
}

/**
 * Events of the agenda of a person, as iCalendar objects
 *
 * @param  User $user           Person synchronizing
 * @param  int  $calendarUserId Person whose agenda it is
 * @return array                [uri => ['id', 'uri', 'etag', 'size', 'lastmodified', 'calendardata', 'component', 'event' => id]]
 */
function dolical_caldav_objects(User $user, int $calendarUserId): array
{
    global $db, $langs;

    $langs->loadLangs(['agenda', 'dolical@dolical']);

    $sql  = 'SELECT a.id, a.ref_ext, a.label, a.note, a.location, a.datep, a.datep2, a.fulldayevent, a.transparency, a.percent,';
    $sql .= ' a.fk_user_action, a.datec, a.tms, a.entity, a.recurid';
    $sql .= ' FROM ' . MAIN_DB_PREFIX . 'actioncomm as a';
    $sql .= ' LEFT JOIN ' . MAIN_DB_PREFIX . 'c_actioncomm as ca ON ca.id = a.fk_action';
    $sql .= ' WHERE a.entity IN (' . getEntity('agenda') . ')';
    $sql .= " AND a.datep IS NOT NULL AND COALESCE(a.datep2, a.datep) >= '" . $db->idate(dol_now() - DOLICAL_CALDAV_PAST_DAYS * 86400) . "'";
    $sql .= " AND (ca.type IS NULL OR ca.type <> 'systemauto')";
    $sql .= ' AND (a.fk_user_action = ' . $calendarUserId;
    $sql .= ' OR EXISTS (SELECT r.fk_actioncomm FROM ' . MAIN_DB_PREFIX . "actioncomm_resources as r WHERE r.fk_actioncomm = a.id AND r.element_type = 'user' AND r.fk_element = " . $calendarUserId . '))';
    // Declined : out of the calendar of the phone, as it is out of the day of the person
    $sql .= ' AND NOT EXISTS (SELECT r.fk_actioncomm FROM ' . MAIN_DB_PREFIX . "actioncomm_resources as r WHERE r.fk_actioncomm = a.id AND r.element_type = 'user' AND r.fk_element = " . $calendarUserId . ' AND r.answer_status = -1 AND a.fk_user_action <> ' . $calendarUserId . ')';
    if ($user->socid > 0) {
        $sql .= ' AND a.fk_soc = ' . ((int) $user->socid);
    }
    $sql .= ' ORDER BY a.datep ASC';
    $sql .= $db->plimit(3000);

    $rows  = [];
    $resql = $db->query($sql);
    if (!$resql) {
        dol_syslog(__FUNCTION__ . ' ' . $db->lasterror(), LOG_ERR);
        return [];
    }
    while ($obj = $db->fetch_object($resql)) {
        $rows[(int) $obj->id] = $obj;
    }
    $db->free($resql);
    if (empty($rows)) {
        return [];
    }
    $idList = $db->sanitize(implode(',', array_keys($rows)));

    // People of the events, with their answer, in two queries
    $people = [];
    $sql    = 'SELECT r.fk_actioncomm, r.element_type, r.fk_element, r.answer_status, u.firstname as ufirst, u.lastname as ulast, u.email as uemail,';
    $sql   .= ' sp.firstname as cfirst, sp.lastname as clast, sp.email as cemail';
    $sql   .= ' FROM ' . MAIN_DB_PREFIX . 'actioncomm_resources as r';
    $sql   .= ' LEFT JOIN ' . MAIN_DB_PREFIX . "user as u ON r.element_type = 'user' AND u.rowid = r.fk_element";
    $sql   .= ' LEFT JOIN ' . MAIN_DB_PREFIX . "socpeople as sp ON r.element_type = 'socpeople' AND sp.rowid = r.fk_element";
    $sql   .= " WHERE r.element_type IN ('user', 'socpeople') AND r.fk_actioncomm IN (" . $idList . ')';
    $sql   .= ' ORDER BY r.rowid';
    $resql  = $db->query($sql);
    while ($resql && ($obj = $db->fetch_object($resql))) {
        $isUser = $obj->element_type === 'user';
        $people[(int) $obj->fk_actioncomm][] = [
            'kind'   => $isUser ? 'u' : 'c',
            'id'     => (int) $obj->fk_element,
            'name'   => trim($isUser ? $obj->ufirst . ' ' . $obj->ulast : $obj->cfirst . ' ' . $obj->clast),
            'email'  => trim((string) ($isUser ? $obj->uemail : $obj->cemail)),
            'status' => (int) $obj->answer_status,
        ];
    }

    // The reminders of the person become the alarms of the phone
    $alarms = [];
    $resql  = $db->query('SELECT fk_actioncomm, offsetvalue, offsetunit FROM ' . MAIN_DB_PREFIX . 'actioncomm_reminder WHERE fk_user = ' . $calendarUserId . ' AND fk_actioncomm IN (' . $idList . ') ORDER BY rowid');
    while ($resql && ($obj = $db->fetch_object($resql))) {
        $alarms[(int) $obj->fk_actioncomm][(int) $obj->offsetvalue * (DOLICAL_REMINDER_UNITS[$obj->offsetunit] ?? 60)] = true;
    }

    $reports = array_flip(dolical_events_with_report(array_keys($rows)));
    $texts   = [];
    if (!empty($reports)) {
        $resql = $db->query('SELECT fk_object, dolical_report FROM ' . MAIN_DB_PREFIX . 'actioncomm_extrafields WHERE fk_object IN (' . $db->sanitize(implode(',', array_keys($reports))) . ')');
        while ($resql && ($obj = $db->fetch_object($resql))) {
            $texts[(int) $obj->fk_object] = trim((string) $obj->dolical_report);
        }
    }

    $owners  = [];
    $objects = [];
    foreach ($rows as $id => $row) {
        $ownerId = (int) $row->fk_user_action;
        if ($ownerId > 0 && !isset($owners[$ownerId])) {
            $owner = new User($db);
            $owners[$ownerId] = $owner->fetch($ownerId) > 0 ? ['name' => $owner->getFullName($langs), 'email' => (string) $owner->email] : ['name' => '', 'email' => ''];
        }
        $data = dolical_caldav_vcalendar($row, $people[$id] ?? [], $owners[$ownerId] ?? ['name' => '', 'email' => ''], array_keys($alarms[$id] ?? []), $texts[$id] ?? '');
        $uri  = dolical_caldav_uri($id, (string) $row->ref_ext);

        $objects[$uri] = [
            'id'           => $id,
            'event'        => $id,
            'uri'          => $uri,
            'lastmodified' => (int) $db->jdate($row->tms),
            'etag'         => '"' . md5($data) . '"',
            'size'         => strlen($data),
            'calendardata' => $data,
            'component'    => 'vevent',
        ];
    }

    return $objects;
}

/**
 * One event as an iCalendar object. Written the same way every time, its tag only changes with the event
 *
 * @param  object $row     Row of llx_actioncomm
 * @param  array  $people  Participants, with their answer
 * @param  array  $owner   ['name', 'email'] of the organizer
 * @param  int[]  $alarms  Delays of the reminders before the start, in seconds
 * @param  string $report  Report of the meeting
 * @return string
 */
function dolical_caldav_vcalendar($row, array $people, array $owner, array $alarms, string $report): string
{
    global $db;

    $event           = new ActionComm($db);
    $event->id       = (int) $row->id;
    $event->entity   = (int) $row->entity;
    $event->ref_ext  = (string) $row->ref_ext;
    $start           = (int) $db->jdate($row->datep);
    $end             = $row->datep2 ? max($start, (int) $db->jdate($row->datep2)) : $start;
    $utc             = new DateTimeZone('UTC');

    $vcalendar = new \Sabre\VObject\Component\VCalendar(['PRODID' => '-//Eoxia//DoliCal//FR']);
    $vevent    = $vcalendar->createComponent('VEVENT', [
        'UID'     => dolical_event_uid($event),
        'DTSTAMP' => new DateTime('@' . (int) $db->jdate($row->tms), $utc),
        'SUMMARY' => dol_html_entity_decode((string) $row->label, ENT_QUOTES | ENT_HTML5),
    ]);
    $vcalendar->add($vevent);
    if (!empty($row->datec)) {
        $vevent->add('CREATED', new DateTime('@' . (int) $db->jdate($row->datec), $utc));
    }
    $vevent->add('LAST-MODIFIED', new DateTime('@' . (int) $db->jdate($row->tms), $utc));

    if (!empty($row->fulldayevent)) {
        // A day, in the time zone of the agenda : the phone shows it on that day wherever it is
        $first = dolical_local_date($start);
        $last  = dolical_local_date($end)->modify('+1 day');
        $vevent->add('DTSTART', new DateTime($first->format('Y-m-d')), ['VALUE' => 'DATE']);
        $vevent->add('DTEND', new DateTime($last->format('Y-m-d')), ['VALUE' => 'DATE']);
    } else {
        $vevent->add('DTSTART', new DateTime('@' . $start, $utc));
        $vevent->add('DTEND', new DateTime('@' . max($end, $start + 60), $utc));
    }

    if (trim((string) $row->location) !== '') {
        $vevent->add('LOCATION', dol_html_entity_decode((string) $row->location, ENT_QUOTES | ENT_HTML5));
    }
    $description = dolical_caldav_description((string) $row->note, $report);
    if ($description !== '') {
        $vevent->add('DESCRIPTION', $description);
    }
    $vevent->add('TRANSP', !empty($row->transparency) ? 'OPAQUE' : 'TRANSPARENT');
    $vevent->add('STATUS', 'CONFIRMED');
    $vevent->add('URL', dol_buildpath('/dolical/dolicalindex.php', 3) . '?event=' . ((int) $row->id));

    // Organizer and participants with an email, their answer as the phone knows it
    $partstat = [1 => 'ACCEPTED', 2 => 'TENTATIVE', -1 => 'DECLINED', 0 => 'NEEDS-ACTION'];
    $ownerId  = (int) $row->fk_user_action;
    if ($owner['email'] !== '' && !empty($people)) {
        $vevent->add('ORGANIZER', 'mailto:' . $owner['email'], ['CN' => $owner['name']]);
    }
    foreach ($people as $person) {
        if ($person['email'] === '' || !isValidEmail($person['email'])) {
            continue;
        }
        $isOwner = $person['kind'] === 'u' && $person['id'] === $ownerId;
        if ($isOwner && count($people) === 1) {
            continue;
        }
        $vevent->add('ATTENDEE', 'mailto:' . $person['email'], [
            'CN'       => $person['name'],
            'CUTYPE'   => 'INDIVIDUAL',
            'ROLE'     => $isOwner ? 'CHAIR' : 'REQ-PARTICIPANT',
            'PARTSTAT' => $isOwner ? 'ACCEPTED' : ($partstat[$person['status']] ?? 'NEEDS-ACTION'),
        ]);
    }

    sort($alarms);
    foreach (array_unique($alarms) as $seconds) {
        $alarm = $vcalendar->createComponent('VALARM', ['ACTION' => 'DISPLAY', 'DESCRIPTION' => dol_html_entity_decode((string) $row->label, ENT_QUOTES | ENT_HTML5)]);
        $alarm->add('TRIGGER', $seconds > 0 ? '-' . dolical_caldav_duration($seconds) : 'PT0S', ['RELATED' => 'START']);
        $vevent->add($alarm);
    }

    return $vcalendar->serialize();
}

/**
 * Delay in the iCalendar syntax : PT15M, PT2H, P1D, P1W
 *
 * @param  int    $seconds Delay
 * @return string
 */
function dolical_caldav_duration(int $seconds): string
{
    if ($seconds % 604800 === 0) {
        return 'P' . ($seconds / 604800) . 'W';
    }
    if ($seconds % 86400 === 0) {
        return 'P' . ($seconds / 86400) . 'D';
    }
    if ($seconds % 3600 === 0) {
        return 'PT' . ($seconds / 3600) . 'H';
    }

    return 'PT' . max(1, (int) round($seconds / 60)) . 'M';
}

/**
 * What a phone sent, read : the main event, its dates as the agenda stores them, and the answer and alarm of the
 * person
 *
 * @param  string $calendarData iCalendar object
 * @param  string $email        Email of the person synchronizing, to find his answer
 * @return array                ['vcalendar', 'vevent', 'uid', 'label', 'start', 'end', 'fullday', 'location', 'note', 'busy', 'rrule', 'answer' => int|null, 'alarm' => seconds|null, 'emails' => [email => partstat], 'names' => [email => name shown], 'organizer' => email]
 * @throws \Sabre\DAV\Exception\BadRequest
 */
function dolical_caldav_read(string $calendarData, string $email): array
{
    try {
        $vcalendar = \Sabre\VObject\Reader::read($calendarData);
    } catch (Exception $exception) {
        throw new \Sabre\DAV\Exception\BadRequest('Invalid iCalendar : ' . $exception->getMessage());
    }

    // The main event : the one without RECURRENCE-ID, the exceptions of a series go with it
    $vevent = null;
    foreach ($vcalendar->select('VEVENT') as $candidate) {
        if (!isset($candidate->{'RECURRENCE-ID'})) {
            $vevent = $candidate;
            break;
        }
    }
    if ($vevent === null || !isset($vevent->DTSTART)) {
        throw new \Sabre\DAV\Exception\BadRequest('No event in the calendar object');
    }
    [$startTs, $endTs, $fullday] = dolical_caldav_dates($vevent);

    // The answer of the person, and every address with its answer and the name shown with it
    $answers = ['ACCEPTED' => 1, 'TENTATIVE' => 2, 'DECLINED' => -1, 'NEEDS-ACTION' => 0];
    $emails  = [];
    $names   = [];
    $answer  = null;
    foreach ($vevent->select('ATTENDEE') as $attendee) {
        $address = dol_strtolower(preg_replace('/^mailto:/i', '', (string) $attendee->getValue()));
        $status  = isset($attendee['PARTSTAT']) ? strtoupper((string) $attendee['PARTSTAT']) : 'NEEDS-ACTION';
        $emails[$address] = $answers[$status] ?? 0;
        $names[$address]  = isset($attendee['CN']) ? trim((string) $attendee['CN']) : '';
        if ($email !== '' && $address === dol_strtolower($email)) {
            $answer = $answers[$status] ?? 0;
        }
    }
    // The organizer takes part too, even when the agenda did not list him among the attendees
    $organizer = '';
    if (isset($vevent->ORGANIZER)) {
        $address   = dol_strtolower(preg_replace('/^mailto:/i', '', (string) $vevent->ORGANIZER->getValue()));
        $organizer = $address;
        if (!isset($emails[$address])) {
            $emails[$address] = 1;
            $names[$address]  = isset($vevent->ORGANIZER['CN']) ? trim((string) $vevent->ORGANIZER['CN']) : '';
        }
    }

    // The first alarm before the start, the one a reminder can hold
    $alarm = null;
    foreach ($vevent->select('VALARM') as $valarm) {
        if (!isset($valarm->TRIGGER) || (isset($valarm->TRIGGER['VALUE']) && strtoupper((string) $valarm->TRIGGER['VALUE']) === 'DATE-TIME')) {
            continue;
        }
        try {
            $interval = \Sabre\VObject\DateTimeParser::parseDuration((string) $valarm->TRIGGER->getValue());
        } catch (Exception $exception) {
            continue;
        }
        $seconds = (new DateTimeImmutable('@0'))->add($interval)->getTimestamp();
        if ($seconds <= 0 && (!isset($valarm->TRIGGER['RELATED']) || strtoupper((string) $valarm->TRIGGER['RELATED']) === 'START')) {
            $alarm = $alarm === null ? -$seconds : min($alarm, -$seconds);
        }
    }

    return [
        'vcalendar' => $vcalendar,
        'vevent'    => $vevent,
        'uid'       => isset($vevent->UID) ? preg_replace('/[\x00-\x1F\x7F]/', '', (string) $vevent->UID) : '',
        'label'     => isset($vevent->SUMMARY) ? trim((string) $vevent->SUMMARY) : '',
        'start'     => $startTs,
        'end'       => $endTs,
        'fullday'   => $fullday,
        'location'  => isset($vevent->LOCATION) ? trim((string) $vevent->LOCATION) : '',
        'note'      => isset($vevent->DESCRIPTION) ? (string) $vevent->DESCRIPTION : '',
        'busy'      => !isset($vevent->TRANSP) || strtoupper((string) $vevent->TRANSP) !== 'TRANSPARENT',
        'rrule'     => isset($vevent->RRULE) ? (string) $vevent->RRULE : '',
        'answer'    => $answer,
        'alarm'     => $alarm,
        'emails'    => $emails,
        'names'     => $names,
        'organizer' => $organizer,
    ];
}

/**
 * Dates of an event of a phone as the agenda stores them : a day from 00:00 to 23:59:59 of its last day, where the
 * phone counts up to the next day excluded
 *
 * @param  \Sabre\VObject\Component\VEvent $vevent Event, with its DTSTART
 * @return array                                   [start, end, fullday]
 */
function dolical_caldav_dates(\Sabre\VObject\Component\VEvent $vevent): array
{
    try {
        $zone = new DateTimeZone(dolical_reference_timezone());
    } catch (Exception $exception) {
        $zone = new DateTimeZone('UTC');
    }
    $fullday = !$vevent->DTSTART->hasTime();
    $start   = $vevent->DTSTART->getDateTime($zone);
    if (isset($vevent->DTEND)) {
        $end = $vevent->DTEND->getDateTime($zone);
    } elseif (isset($vevent->DURATION)) {
        $end = $start->add($vevent->DURATION->getDateInterval());
    } else {
        $end = $fullday ? $start->modify('+1 day') : $start;
    }

    if ($fullday) {
        $first = new DateTimeImmutable($start->format('Y-m-d') . ' 00:00:00', $zone);
        $last  = new DateTimeImmutable($end->format('Y-m-d') . ' 00:00:00', $zone);
        $last  = $last > $first ? $last->modify('-1 second') : $first->setTime(23, 59, 59);

        return [$first->getTimestamp(), $last->getTimestamp(), true];
    }

    return [$start->getTimestamp(), max($start->getTimestamp(), $end->getTimestamp()), false];
}

/**
 * Users, contacts and third party of the instance behind the people of an iCalendar event, with the answer each one
 * gave. A person is found by his address (the professional or the personal one of a user), else by his name : the
 * one the agenda shows with the address, or the "firstname.lastname" of the address, when only one person bears it.
 * The third party is the one with that address, else the one of a contact found, else the only one of the domain the
 * outside addresses share : neither the mail providers of everyone nor the domains of the users of the instance
 *
 * @param  array $emails       [address => answer 1, 2, -1 or 0], as dolical_caldav_read() gives them
 * @param  User  $user         The one acting : the contacts and third parties are the ones he may see
 * @param  int   $exceptUserId Owner of the event, left out ; the one acting when 0
 * @param  array $names        [address => name shown], as dolical_caldav_read() gives them
 * @return array               ['users' => int[], 'contacts' => int[], 'socid' => int, 'unknown' => string[] addresses of nobody,
 *                             'answers' => ['user' => [id => answer], 'socpeople' => [id => answer]]]
 */
function dolical_caldav_people(array $emails, User $user, int $exceptUserId = 0, array $names = []): array
{
    global $db;

    $exceptUserId = $exceptUserId > 0 ? $exceptUserId : (int) $user->id;

    $found   = ['users' => [], 'contacts' => [], 'socid' => 0, 'unknown' => [], 'answers' => ['user' => [], 'socpeople' => []]];
    $answers = [];
    foreach ($emails as $email => $answer) {
        $email = dol_strtolower((string) $email);
        if (isValidEmail($email)) {
            $answers[$email] = (int) $answer;
        }
    }
    if (empty($answers)) {
        return $found;
    }
    $list = "'" . implode("', '", array_map([$db, 'escape'], array_keys($answers))) . "'";

    $people = [];
    $addUser = function (int $userId, string $email) use (&$found, &$people, $answers, $exceptUserId) {
        $people[$email] = true;
        if ($userId !== $exceptUserId && !isset($found['answers']['user'][$userId])) {
            $found['users'][]                   = $userId;
            $found['answers']['user'][$userId] = $answers[$email] ?? 0;
        }
    };
    $companies = [];
    $addContact = function (int $contactId, int $socid, string $email) use (&$found, &$people, &$companies, $answers) {
        $people[$email] = true;
        if (!isset($found['answers']['socpeople'][$contactId])) {
            $found['contacts'][]                         = $contactId;
            $found['answers']['socpeople'][$contactId] = $answers[$email] ?? 0;
            if ($socid > 0) {
                $companies['contact'][] = $socid;
            }
        }
    };

    $sql   = 'SELECT rowid, LOWER(email) as email, LOWER(personal_email) as personal FROM ' . MAIN_DB_PREFIX . 'user';
    $sql  .= ' WHERE statut = 1 AND entity IN (0, ' . getEntity('user') . ') AND (LOWER(email) IN (' . $list . ') OR LOWER(personal_email) IN (' . $list . '))';
    $resql = $db->query($sql);
    while ($resql && ($obj = $db->fetch_object($resql))) {
        $addUser((int) $obj->rowid, isset($answers[$obj->email]) ? $obj->email : $obj->personal);
    }
    // One contact per address, the oldest the user may see
    $seen  = [];
    $resql = $db->query('SELECT rowid, fk_soc, LOWER(email) as email FROM ' . MAIN_DB_PREFIX . 'socpeople WHERE entity IN (' . getEntity('contact') . ') AND statut = 1 AND LOWER(email) IN (' . $list . ') ORDER BY rowid');
    while ($resql && ($obj = $db->fetch_object($resql))) {
        if (!isset($seen[$obj->email]) && dolical_can_see_object($user, 'contact', (int) $obj->rowid)) {
            $seen[$obj->email] = true;
            $addContact((int) $obj->rowid, (int) $obj->fk_soc, $obj->email);
        }
    }

    // The others by their name, when only one person bears it
    foreach (array_keys($answers) as $email) {
        if (isset($people[$email])) {
            continue;
        }
        $guesses = dolical_caldav_name_guesses($email, (string) ($names[$email] ?? ''));
        if (empty($guesses)) {
            continue;
        }
        $match = dolical_caldav_find_by_name('user', $guesses);
        if ($match !== null) {
            $addUser($match['id'], $email);
            continue;
        }
        $match = dolical_caldav_find_by_name('socpeople', $guesses);
        if ($match !== null && dolical_can_see_object($user, 'contact', $match['id'])) {
            $addContact($match['id'], $match['socid'], $email);
        }
    }

    // The third party : the one with the address, else the one of a contact, else the only one of a domain
    $resql = $db->query('SELECT rowid FROM ' . MAIN_DB_PREFIX . 'societe WHERE entity IN (' . getEntity('societe') . ') AND status = 1 AND LOWER(email) IN (' . $list . ') ORDER BY rowid');
    while ($resql && ($obj = $db->fetch_object($resql))) {
        $companies['email'][] = (int) $obj->rowid;
    }
    // A domain tells the company only when every outside address of the event leads to the same one : a meeting with
    // a company not in the instance, where someone of another one takes part, is not the meeting of the latter
    $internal = dolical_caldav_internal_domains();
    $domains  = [];
    foreach (array_keys($answers) as $email) {
        $domain = substr((string) strrchr($email, '@'), 1);
        if ($domain !== '' && !isset($internal[$domain])) {
            $domains[$domain] = dolical_caldav_find_company_by_domain($domain);
        }
    }
    if (!empty($domains) && count(array_unique($domains)) === 1 && reset($domains) > 0) {
        $companies['domain'][] = reset($domains);
    }
    foreach (['email', 'contact', 'domain'] as $source) {
        foreach ($companies[$source] ?? [] as $socid) {
            if (dolical_can_see_object($user, 'societe', $socid)) {
                $found['socid'] = $socid;
                break 2;
            }
        }
    }

    foreach (array_keys($answers) as $email) {
        if (!isset($people[$email])) {
            $found['unknown'][] = $email;
        }
    }

    return $found;
}

/**
 * A name as it is compared : no accent, no case, words of letters only
 *
 * @param  string $name Name
 * @return string
 */
function dolical_caldav_name_key(string $name): string
{
    return trim(preg_replace('/[^a-z]+/', ' ', dol_strtolower(dol_string_unaccent($name))));
}

/**
 * Names a person behind an address may bear : the one the agenda shows, and the "firstname.lastname" of the address.
 * A single word (a login, a lastname alone) names nobody for sure
 *
 * @param  string $email Address
 * @param  string $name  Name shown with it
 * @return array         Names, as dolical_caldav_name_key() gives them
 */
function dolical_caldav_name_guesses(string $email, string $name): array
{
    $guesses = [];
    if ($name !== '' && strpos($name, '@') === false) {
        $guesses[] = dolical_caldav_name_key($name);
    }
    $local = (string) strstr($email, '@', true);
    if (preg_match('/^[^._\-+]+[._\-][^._\-+]+$/', $local)) {
        $guesses[] = dolical_caldav_name_key($local);
    }

    return array_values(array_unique(array_filter($guesses, function ($guess) {
        return strpos($guess, ' ') !== false;
    })));
}

/**
 * The only active user, or the only active contact, bearing one of some names, firstname or lastname first
 *
 * @param  string $table   'user' or 'socpeople'
 * @param  array  $guesses Names, as dolical_caldav_name_key() gives them
 * @return array|null      ['id', 'socid'] ; null when nobody or several people bear them
 */
function dolical_caldav_find_by_name(string $table, array $guesses): ?array
{
    global $db;

    $words = [];
    foreach ($guesses as $guess) {
        foreach (explode(' ', $guess) as $word) {
            $words[$word] = "'" . $db->escape($word) . "'";
        }
    }

    $sql = 'SELECT rowid, firstname, lastname' . ($table === 'socpeople' ? ', fk_soc' : '') . ' FROM ' . MAIN_DB_PREFIX . $table;
    if ($table === 'user') {
        $sql .= ' WHERE statut = 1 AND entity IN (0, ' . getEntity('user') . ')';
    } else {
        $sql .= ' WHERE statut = 1 AND entity IN (' . getEntity('contact') . ')';
    }
    // The database narrows on the lastname, its collation ignores case and accents ; the name is checked whole below
    $sql .= ' AND lastname IN (' . implode(', ', $words) . ')';

    $matches = [];
    $resql   = $db->query($sql);
    while ($resql && ($obj = $db->fetch_object($resql))) {
        $first = dolical_caldav_name_key((string) $obj->firstname);
        $last  = dolical_caldav_name_key((string) $obj->lastname);
        if ($first !== '' && $last !== '' && array_intersect($guesses, [$first . ' ' . $last, $last . ' ' . $first])) {
            $matches[(int) $obj->rowid] = ['id' => (int) $obj->rowid, 'socid' => (int) ($obj->fk_soc ?? 0)];
        }
    }

    return count($matches) === 1 ? reset($matches) : null;
}

/**
 * Mail domains that say nothing of the company of a person : the providers of everyone, and the domains of the users
 * of the instance and of its own company
 *
 * @return array [domain => true]
 */
function dolical_caldav_internal_domains(): array
{
    global $db, $mysoc;

    static $domains = null;
    if ($domains !== null) {
        return $domains;
    }

    $domains = array_fill_keys(['gmail.com', 'googlemail.com', 'outlook.com', 'outlook.fr', 'hotmail.com', 'hotmail.fr', 'live.com', 'live.fr', 'msn.com',
        'yahoo.com', 'yahoo.fr', 'icloud.com', 'me.com', 'aol.com', 'orange.fr', 'wanadoo.fr', 'free.fr', 'sfr.fr', 'neuf.fr', 'laposte.net', 'bbox.fr',
        'gmx.fr', 'gmx.com', 'protonmail.com', 'proton.me'], true);
    $resql = $db->query('SELECT DISTINCT LOWER(SUBSTRING_INDEX(email, \'@\', -1)) as domain FROM ' . MAIN_DB_PREFIX . "user WHERE statut = 1 AND email LIKE '%@%'");
    while ($resql && ($obj = $db->fetch_object($resql))) {
        $domains[$obj->domain] = true;
    }
    if (!empty($mysoc->email) && strpos($mysoc->email, '@') !== false) {
        $domains[dol_strtolower(substr(strrchr($mysoc->email, '@'), 1))] = true;
    }

    return $domains;
}

/**
 * The only active third party whose address or website is on a domain
 *
 * @param  string $domain Domain, lower case
 * @return int            Id, 0 when none or several
 */
function dolical_caldav_find_company_by_domain(string $domain): int
{
    global $db;

    static $cache = [];
    if (isset($cache[$domain])) {
        return $cache[$domain];
    }

    $like  = $db->escape($db->escapeforlike($domain));
    $sql   = 'SELECT rowid, LOWER(email) as email, LOWER(url) as url FROM ' . MAIN_DB_PREFIX . 'societe';
    $sql  .= ' WHERE entity IN (' . getEntity('societe') . ") AND status = 1 AND (LOWER(email) LIKE '%@" . $like . "' OR LOWER(url) LIKE '%" . $like . "%')";
    $found = [];
    $resql = $db->query($sql);
    while ($resql && ($obj = $db->fetch_object($resql))) {
        $host = (string) parse_url(preg_match('#^[a-z]+://#', (string) $obj->url) ? (string) $obj->url : 'http://' . $obj->url, PHP_URL_HOST);
        $host = preg_replace('/^www\./', '', $host);
        if (substr((string) $obj->email, -strlen('@' . $domain)) === '@' . $domain || $host === $domain) {
            $found[(int) $obj->rowid] = true;
        }
    }

    return $cache[$domain] = count($found) === 1 ? (int) array_key_first($found) : 0;
}

/**
 * Reminder of the person from the alarm of the phone : same delay, the kinds he already had, else a notification
 *
 * @param  ActionComm $event   Event
 * @param  int|null   $seconds Delay before the start, null to remove
 * @param  User       $user    Person synchronizing
 * @return void
 */
function dolical_caldav_set_alarm(ActionComm $event, ?int $seconds, User $user): void
{
    $current = dolical_get_reminders($event, (int) $user->id);
    $types   = !empty($current['types']) ? $current['types'] : array_slice(array_keys(dolical_reminder_types()), 0, 1);
    $unit    = 'i';
    $offset  = (int) round(((int) $seconds) / 60);
    foreach (['w' => 10080, 'd' => 1440, 'h' => 60] as $candidate => $minutes) {
        if ($offset > 0 && $offset % $minutes === 0) {
            $unit   = $candidate;
            $offset = $offset / $minutes;
            break;
        }
    }
    dolical_save_reminders($event, dolical_reminder_clean(['enabled' => $seconds !== null, 'offset' => $offset, 'unit' => $unit, 'types' => $types, 'everyone' => false]), $user);
}

/**
 * Write the answer of a person to an event, and to the dates to come of its series
 *
 * @param  ActionComm $event  Event, resources loaded
 * @param  int        $userId Person
 * @param  int        $status 1, 2, -1 or 0
 * @return void
 */
function dolical_caldav_set_answer(ActionComm $event, int $userId, int $status): void
{
    global $db;

    if (isset($event->userassigned[$userId])) {
        $db->query('UPDATE ' . MAIN_DB_PREFIX . 'actioncomm_resources SET answer_status = ' . $status . ' WHERE fk_actioncomm = ' . ((int) $event->id) . " AND element_type = 'user' AND fk_element = " . $userId);
    } elseif ((int) $event->userownerid !== $userId) {
        return;
    } else {
        $db->query('INSERT INTO ' . MAIN_DB_PREFIX . 'actioncomm_resources (fk_actioncomm, element_type, fk_element, mandatory, transparency, answer_status) VALUES (' . ((int) $event->id) . ", 'user', " . $userId . ', 0, ' . ((int) $event->transparency) . ', ' . $status . ')');
    }
}

/**
 * Dates of a series as its rule, its extra dates (RDATE), its exceptions and its moved dates give them, the cancelled
 * ones left out : the last ones up to a date, as many as a series of the agenda holds. A date changed on its own keeps
 * its own title, place and description
 *
 * @param  array $read  From dolical_caldav_read()
 * @param  int   $until Last start to take
 * @return array        [[0 => start, 1 => end, 'label', 'location', 'note']] in order, the first one being the event
 *                      the series starts with ; empty on error
 */
function dolical_caldav_occurrences(array $read, int $until): array
{
    // A rule more frequent than a day (every hour, every minute, or several hours or minutes a day) is no series the
    // agenda holds, and would be walked through date after date : the event is kept alone
    if (preg_match('/(^|;)\s*(FREQ=(SECONDLY|MINUTELY|HOURLY)|BY(HOUR|MINUTE|SECOND)=[^;]*,)/i', (string) $read['rrule'])) {
        dol_syslog(__FUNCTION__ . ' rule more frequent than a day, kept as one event: ' . $read['rrule'], LOG_INFO);
        return [];
    }

    try {
        $zone = new DateTimeZone(dolical_reference_timezone());
    } catch (Exception $exception) {
        $zone = new DateTimeZone('UTC');
    }

    $occurrences = [];
    $seen        = [];
    $end         = new DateTimeImmutable('@' . $until);
    // The dates before the ones kept are walked through too : the library would stop on its own count of them
    $limit = \Sabre\VObject\Settings::$maxRecurrences;
    \Sabre\VObject\Settings::$maxRecurrences = -1;
    try {
        $iterator = new \Sabre\VObject\Recur\EventIterator($read['vcalendar']->select('VEVENT'), null, $zone);
        // At most DOLICAL_CALDAV_MAX_STEPS dates walked through : a daily series of more than 25 years, or a list of
        // extra dates without end, stops there
        for ($steps = 0; $iterator->valid() && $iterator->getDtStart() <= $end && $steps < DOLICAL_CALDAV_MAX_STEPS; $steps++) {
            $occurrence = $iterator->getEventObject();
            // The extra dates often repeat the first one : the same date twice is one date
            $startKey = $iterator->getDtStart()->getTimestamp();
            if (!isset($seen[$startKey]) && (!isset($occurrence->STATUS) || strtoupper((string) $occurrence->STATUS) !== 'CANCELLED')) {
                $seen[$startKey] = true;
                [$first, $last, $fullday] = dolical_caldav_dates($occurrence);
                if ($fullday) {
                    // The library gives the end of a date as its start plus a length in seconds : across a change of
                    // time it is an hour off midnight, and the date would lose or win a day
                    $next = $iterator->getDtEnd()->setTimezone($zone)->modify('+12 hours')->setTime(0, 0);
                    $day  = (new DateTimeImmutable('@' . $first))->setTimezone($zone);
                    $last = ($next > $day ? $next->modify('-1 second') : $day->setTime(23, 59, 59))->getTimestamp();
                }
                $occurrences[] = [
                    $first,
                    $last,
                    'label'    => isset($occurrence->SUMMARY) ? trim((string) $occurrence->SUMMARY) : $read['label'],
                    'location' => isset($occurrence->LOCATION) ? trim((string) $occurrence->LOCATION) : $read['location'],
                    'note'     => isset($occurrence->DESCRIPTION) ? (string) $occurrence->DESCRIPTION : $read['note'],
                ];
                if (count($occurrences) > DOLICAL_RECURRENCE_MAX + 1) {
                    array_shift($occurrences);
                }
            }
            $iterator->next();
        }
    } catch (Exception $exception) {
        dol_syslog(__FUNCTION__ . ' ' . $exception->getMessage(), LOG_WARNING);
        $occurrences = [];
    } finally {
        \Sabre\VObject\Settings::$maxRecurrences = $limit;
    }

    return $occurrences;
}

/**
 * Create the events of an iCalendar object, sent by a phone or read from an imported file : one, or a series with
 * the dates its rule and its extra dates give, the answers already given and the alarm of the owner
 *
 * @param  User       $user   Person acting : the one synchronizing, or the one importing
 * @param  string     $uri    File name of the event in the calendar
 * @param  array      $read   From dolical_caldav_read(), read with the address of the owner ; 'extra' : text added to
 *                            every description, 'percent' : status, to do (0) when not given
 * @param  User|null  $owner  Person whose agenda gets the event, the one acting when null
 * @param  int        $until  Last start of a series ; a year after its first date when 0
 * @param  array|null $people From dolical_caldav_people(), when already found
 * @return int                Id of the event, < 0 on error
 */
function dolical_caldav_create(User $user, string $uri, array $read, ?User $owner = null, int $until = 0, ?array $people = null): int
{
    global $db, $langs;

    $owner = $owner ?? $user;

    // A series : the dates as the rule computes them, its extra dates and exceptions included. Read before anything
    // is written : a phone sends again what failed, it must not leave half a series behind
    $occurrences = [];
    if ($read['rrule'] !== '' || isset($read['vevent']->RDATE)) {
        $occurrences = dolical_caldav_occurrences($read, $until > 0 ? $until : (new DateTimeImmutable('@' . $read['start']))->modify('+1 year')->getTimestamp());
        if (!empty($occurrences)) {
            // The series starts with the first date kept : the first one of the rule may be cancelled, or too old, or
            // changed on its own
            $first            = array_shift($occurrences);
            $read['start']    = $first[0];
            $read['end']      = $first[1];
            $read['label']    = $first['label'];
            $read['location'] = $first['location'];
            $read['note']     = $first['note'];
        }
    }
    // What the importer adds to every description (people outside the instance, categories, links)
    $extra = trim((string) ($read['extra'] ?? ''));
    $note  = function (string $text) use ($extra): string {
        return trim(trim($text) . ($extra !== '' ? "\n\n" . $extra : ''));
    };

    $people = $people ?? dolical_caldav_people($read['emails'], $user, (int) $owner->id, $read['names'] ?? []);
    $types  = getDolGlobalString('AGENDA_USE_EVENT_TYPE') ? dolical_get_event_types() : [];
    $type   = getDolGlobalString('AGENDA_USE_EVENT_TYPE_DEFAULT', 'AC_RDV');

    $event          = new ActionComm($db);
    $event->ref_ext = dol_trunc('dav:' . $uri . '|' . $read['uid'], 255, 'right', 'UTF-8', 1);
    $data           = [
        'label'        => $read['label'] !== '' ? $read['label'] : $langs->transnoentities('DoliCalUntitled'),
        'type_code'    => isset($types[$type]) ? $type : 'AC_RDV',
        'fullday'      => $read['fullday'] ? 1 : 0,
        'start'        => $read['start'],
        'end'          => $read['end'],
        'location'     => $read['location'],
        'percent'      => (int) ($read['percent'] ?? 0),
        'busy'         => $read['busy'] ? 1 : 0,
        'owner_id'     => (int) $owner->id,
        'user_ids'     => $people['users'],
        'socid'        => $people['socid'],
        'contact_ids'  => $people['contacts'],
        'project_id'   => 0,
        'elements'     => [],
        'note'         => $note($read['note']),
        'note_changed' => 1,
    ];
    $id = dolical_save_event($event, $data, $user);
    if ($id <= 0) {
        return -1;
    }

    // The answers already given, the owner's included : before the dates of the series, which copy them
    if (!empty($read['answer'])) {
        dolical_caldav_set_answer($event, (int) $owner->id, (int) $read['answer']);
    }
    foreach ($people['answers']['user'] as $userId => $answer) {
        if ($answer !== 0) {
            dolical_caldav_set_answer($event, (int) $userId, $answer);
        }
    }
    foreach ($people['answers']['socpeople'] as $contactId => $answer) {
        if ($answer !== 0 && isset($event->socpeopleassigned[$contactId])) {
            $db->query('UPDATE ' . MAIN_DB_PREFIX . 'actioncomm_resources SET answer_status = ' . ((int) $answer) . ' WHERE fk_actioncomm = ' . ((int) $id) . " AND element_type = 'socpeople' AND fk_element = " . ((int) $contactId));
        }
    }

    $event->fetch($id);
    if ($read['alarm'] !== null) {
        dolical_caldav_set_alarm($event, $read['alarm'], $owner);
    }

    if (!empty($occurrences)) {
        // Extra dates without a rule make a series of their own dates only
        $last = end($occurrences);
        dolical_set_series([$id], dolical_new_recurid($id), $read['rrule'] !== '' ? dolical_recurrence_normalize($read['rrule']) : '', (int) $last[0]);
        $event->fetch($id);
        foreach (dolical_create_occurrences($event, $occurrences, $user) as $index => $occurrenceId) {
            $occurrence = $occurrences[$index];
            // A date changed on its own keeps its own text, written the way dolical_save_event() writes it
            if ($occurrence['label'] !== $read['label'] || $occurrence['location'] !== $read['location'] || $occurrence['note'] !== $read['note']) {
                $label    = trim(preg_replace('/\s*[\r\n]+\s*/', ' ', sanitizeVal($occurrence['label'], 'alphanohtml')));
                $location = trim(preg_replace('/\s*[\r\n]+\s*/', ' ', sanitizeVal($occurrence['location'], 'alphanohtml')));
                $sql      = 'UPDATE ' . MAIN_DB_PREFIX . "actioncomm SET label = '" . $db->escape(dol_trunc($label !== '' ? $label : $event->label, 255, 'right', 'UTF-8', 1)) . "',";
                $sql     .= " location = '" . $db->escape(dol_trunc($location, 128, 'right', 'UTF-8', 1)) . "',";
                $sql     .= " note = '" . $db->escape(sanitizeVal($note($occurrence['note']), 'restricthtml')) . "'";
                $sql     .= ' WHERE id = ' . ((int) $occurrenceId);
                $db->query($sql);
            }
            $copy = new ActionComm($db);
            if ($read['alarm'] !== null && $copy->fetch($occurrenceId) > 0) {
                dolical_caldav_set_alarm($copy, $read['alarm'], $owner);
            }
        }
    }

    return $id;
}

/**
 * Write back what a phone changed : only what differs from what it was given, the rest of the event (documents,
 * third party, project, people without email...) stays as it is. Whoever may not change the event only answers
 * and sets his own reminder
 *
 * @param  User       $user    Person synchronizing
 * @param  ActionComm $event   Event, resources loaded
 * @param  string     $served  iCalendar object the phone was given
 * @param  array      $read    From dolical_caldav_read()
 * @return int                 > 0 when done
 */
function dolical_caldav_update(User $user, ActionComm $event, string $served, array $read): int
{
    $before = dolical_caldav_read($served, (string) $user->email);

    if ($read['answer'] !== null && $read['answer'] !== $before['answer'] && (int) $event->userownerid !== (int) $user->id) {
        dolical_caldav_set_answer($event, (int) $user->id, (int) $read['answer']);
    }
    if ($read['alarm'] !== $before['alarm']) {
        dolical_caldav_set_alarm($event, $read['alarm'], $user);
    }

    if (!dolical_can_edit_event($user, $event)) {
        return 1;
    }

    $data    = dolical_event_values($event);
    $changed = false;
    if ($read['label'] !== $before['label']) {
        $data['label'] = $read['label'] !== '' ? $read['label'] : $data['label'];
        $changed       = true;
    }
    if ($read['start'] !== $before['start'] || $read['end'] !== $before['end'] || $read['fullday'] !== $before['fullday']) {
        $data['start']   = $read['start'];
        $data['end']     = $read['end'];
        $data['fullday'] = $read['fullday'] ? 1 : 0;
        $changed         = true;
    }
    if ($read['location'] !== $before['location']) {
        $data['location'] = $read['location'];
        $changed          = true;
    }
    if ($read['busy'] !== $before['busy']) {
        $data['busy'] = $read['busy'] ? 1 : 0;
        $changed      = true;
    }
    if (trim($read['note']) !== trim($before['note'])) {
        // The report travels under the description : what the phone changed is the description above it
        $note = "\n" . $read['note'];
        $mark = strpos($note, "\n" . dolical_caldav_report_mark());
        if ($mark !== false) {
            $note = substr($note, 0, $mark);
        }
        $data['note']         = trim($note);
        $data['note_changed'] = 1;
        $changed              = true;
    }
    if (!$changed) {
        return 1;
    }

    $result = dolical_save_event($event, $data, $user);
    if ($result > 0) {
        dolical_shift_reminders($event);
    }

    return $result;
}

/**
 * Remove an event from the phone : deleted by whoever may, declined by the others, gone from his calendar either way
 *
 * @param  User       $user  Person synchronizing
 * @param  ActionComm $event Event, resources loaded
 * @return bool
 */
function dolical_caldav_delete(User $user, ActionComm $event): bool
{
    if (dolical_can_delete_event($user, $event)) {
        return dolical_delete_event_series($event, 'one', $user)['success'];
    }
    dolical_caldav_set_answer($event, (int) $user->id, -1);

    return true;
}
