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
 * @return array                ['vcalendar', 'vevent', 'uid', 'label', 'start', 'end', 'fullday', 'location', 'note', 'busy', 'rrule', 'answer' => int|null, 'alarm' => seconds|null, 'emails' => [email => partstat]]
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

    // The answer of the person, and every address with its answer
    $answers = ['ACCEPTED' => 1, 'TENTATIVE' => 2, 'DECLINED' => -1, 'NEEDS-ACTION' => 0];
    $emails  = [];
    $answer  = null;
    foreach ($vevent->select('ATTENDEE') as $attendee) {
        $address = dol_strtolower(preg_replace('/^mailto:/i', '', (string) $attendee->getValue()));
        $status  = isset($attendee['PARTSTAT']) ? strtoupper((string) $attendee['PARTSTAT']) : 'NEEDS-ACTION';
        $emails[$address] = $answers[$status] ?? 0;
        if ($email !== '' && $address === dol_strtolower($email)) {
            $answer = $answers[$status] ?? 0;
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
 * Users and contacts of the instance behind the addresses of a phone event
 *
 * @param  string[] $emails Addresses
 * @param  User     $user   The one synchronizing, left out ; the contacts are the ones he may see
 * @return array            ['users' => int[], 'contacts' => int[]]
 */
function dolical_caldav_people(array $emails, User $user): array
{
    global $db;

    $exceptUserId = (int) $user->id;

    $found  = ['users' => [], 'contacts' => []];
    $emails = array_values(array_filter(array_map('dol_strtolower', $emails), 'isValidEmail'));
    if (empty($emails)) {
        return $found;
    }
    $list = "'" . implode("', '", array_map([$db, 'escape'], $emails)) . "'";

    $resql = $db->query('SELECT rowid FROM ' . MAIN_DB_PREFIX . 'user WHERE statut = 1 AND entity IN (0, ' . getEntity('user') . ') AND LOWER(email) IN (' . $list . ')');
    while ($resql && ($obj = $db->fetch_object($resql))) {
        if ((int) $obj->rowid !== $exceptUserId) {
            $found['users'][] = (int) $obj->rowid;
        }
    }
    // One contact per address, the oldest the user may see
    $seen  = [];
    $resql = $db->query('SELECT rowid, LOWER(email) as email FROM ' . MAIN_DB_PREFIX . 'socpeople WHERE entity IN (' . getEntity('contact') . ') AND statut = 1 AND LOWER(email) IN (' . $list . ') ORDER BY rowid');
    while ($resql && ($obj = $db->fetch_object($resql))) {
        if (!isset($seen[$obj->email]) && dolical_can_see_object($user, 'contact', (int) $obj->rowid)) {
            $seen[$obj->email]   = true;
            $found['contacts'][] = (int) $obj->rowid;
        }
    }

    return $found;
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
 * Create the events of a phone : one, or a series with the dates the phone computes from its rule
 *
 * @param  User   $user Person synchronizing
 * @param  string $uri  File name chosen by the phone
 * @param  array  $read From dolical_caldav_read()
 * @return int          Id of the event, < 0 on error
 */
function dolical_caldav_create(User $user, string $uri, array $read): int
{
    global $db, $langs;

    // A series : the dates as the phone computes them, its exceptions included, a year ahead at most. Read before
    // anything is written : a phone sends again what failed, it must not leave half a series behind
    $occurrences = [];
    if ($read['rrule'] !== '') {
        try {
            $from     = new DateTimeImmutable('@' . $read['start']);
            $expanded = $read['vcalendar']->expand($from, $from->modify('+1 year'));
            foreach ($expanded->select('VEVENT') as $occurrence) {
                $dates = dolical_caldav_dates($occurrence);
                if ($dates[0] > $read['start'] && count($occurrences) < DOLICAL_RECURRENCE_MAX) {
                    $occurrences[] = [$dates[0], $dates[1]];
                }
            }
        } catch (Exception $exception) {
            dol_syslog(__FUNCTION__ . ' ' . $exception->getMessage(), LOG_WARNING);
            $occurrences = [];
        }
    }

    $people = dolical_caldav_people(array_keys($read['emails']), $user);
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
        'percent'      => 0,
        'busy'         => $read['busy'] ? 1 : 0,
        'owner_id'     => (int) $user->id,
        'user_ids'     => $people['users'],
        'socid'        => 0,
        'contact_ids'  => $people['contacts'],
        'project_id'   => 0,
        'elements'     => [],
        'note'         => trim($read['note']),
        'note_changed' => 1,
    ];
    $id = dolical_save_event($event, $data, $user);
    if ($id <= 0) {
        return -1;
    }
    $event->fetch($id);
    if ($read['alarm'] !== null) {
        dolical_caldav_set_alarm($event, $read['alarm'], $user);
    }

    if (!empty($occurrences)) {
        $last = end($occurrences);
        dolical_set_series([$id], dolical_new_recurid($id), dolical_recurrence_normalize($read['rrule']), (int) $last[0]);
        $event->fetch($id);
        foreach (dolical_create_occurrences($event, $occurrences, $user) as $occurrenceId) {
            $copy = new ActionComm($db);
            if ($read['alarm'] !== null && $copy->fetch($occurrenceId) > 0) {
                dolical_caldav_set_alarm($copy, $read['alarm'], $user);
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
