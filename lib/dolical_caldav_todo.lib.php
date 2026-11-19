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
 * \file    lib/dolical_caldav_todo.lib.php
 * \ingroup dolical
 * \brief   Reminders of the phones (VTODO) : a list « Reminders » per person beside his agenda, each reminder a native
 *          event of the type « Reminder ». Its due date is the date of the event (none without due date), ticked it is
 *          done (100 %), unticked to do (0 %). Nothing new is stored, as for the events.
 */

require_once __DIR__ . '/dolical_caldav.lib.php';

/**
 * Code of the event type of the reminders, in the dictionary of the event types
 */
const DOLICAL_TODO_TYPE = 'AC_DOLICAL_TODO';

/**
 * Id of the event type of the reminders, added to the dictionary the first time it is needed : at the activation, or
 * by the first phone of an instance updated without it
 *
 * @return int Id, 0 when it could not be added
 */
function dolical_todo_type_id(): int
{
    global $db;
    static $typeId = 0;

    if ($typeId > 0) {
        return $typeId;
    }
    $resql = $db->query('SELECT id FROM ' . MAIN_DB_PREFIX . "c_actioncomm WHERE code = '" . $db->escape(DOLICAL_TODO_TYPE) . "'");
    $obj   = $resql ? $db->fetch_object($resql) : null;
    if ($obj) {
        return $typeId = (int) $obj->id;
    }

    // The ids of this dictionary are given, not counted by the database : the next one, far from the small ids the core
    // and the modules give themselves
    $resql = $db->query('SELECT MAX(id) as maxid FROM ' . MAIN_DB_PREFIX . 'c_actioncomm');
    $obj   = $resql ? $db->fetch_object($resql) : null;
    $newId = max(1000, (int) ($obj->maxid ?? 0) + 1);
    $sql   = 'INSERT INTO ' . MAIN_DB_PREFIX . 'c_actioncomm (id, code, type, libelle, module, active, position)';
    $sql  .= ' VALUES (' . $newId . ", '" . $db->escape(DOLICAL_TODO_TYPE) . "', 'module', 'Reminder', 'dolical@dolical', 1, " . $newId . ')';
    if (!$db->query($sql)) {
        dol_syslog(__FUNCTION__ . ' ' . $db->lasterror(), LOG_ERR);
        return 0;
    }

    return $typeId = $newId;
}

/**
 * Reminders of a person as the list of his phone gives them : the ones to do, and the ones done in the last
 * DOLICAL_CALDAV_PAST_DAYS days
 *
 * @param  User  $user           Person synchronizing
 * @param  int   $calendarUserId Person whose list it is
 * @return array                 [uri => object of the CalDAV backend]
 */
function dolical_caldav_todo_objects(User $user, int $calendarUserId): array
{
    global $db;

    $typeId = dolical_todo_type_id();
    if ($typeId <= 0) {
        return [];
    }

    $sql  = 'SELECT a.id, a.ref_ext, a.label, a.note, a.datep, a.fulldayevent, a.percent, a.priority, a.datec, a.tms, a.entity';
    $sql .= ' FROM ' . MAIN_DB_PREFIX . 'actioncomm as a';
    $sql .= ' WHERE a.entity IN (' . getEntity('agenda') . ') AND a.fk_action = ' . $typeId;
    $sql .= " AND (a.percent < 100 OR a.tms >= '" . $db->idate(dol_now() - DOLICAL_CALDAV_PAST_DAYS * 86400) . "')";
    $sql .= ' AND (a.fk_user_action = ' . $calendarUserId;
    $sql .= ' OR EXISTS (SELECT r.fk_actioncomm FROM ' . MAIN_DB_PREFIX . "actioncomm_resources as r WHERE r.fk_actioncomm = a.id AND r.element_type = 'user' AND r.fk_element = " . $calendarUserId . '))';
    if ($user->socid > 0) {
        $sql .= ' AND a.fk_soc = ' . ((int) $user->socid);
    }
    $sql .= ' ORDER BY a.id ASC';
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

    // The reminders Dolibarr sends before the due date become the alarms of the phone
    $alarms = [];
    $resql  = $db->query('SELECT fk_actioncomm, offsetvalue, offsetunit FROM ' . MAIN_DB_PREFIX . 'actioncomm_reminder WHERE fk_user = ' . $calendarUserId . ' AND fk_actioncomm IN (' . $db->sanitize(implode(',', array_keys($rows))) . ') ORDER BY rowid');
    while ($resql && ($obj = $db->fetch_object($resql))) {
        $alarms[(int) $obj->fk_actioncomm][(int) $obj->offsetvalue * (DOLICAL_REMINDER_UNITS[$obj->offsetunit] ?? 60)] = true;
    }

    $objects = [];
    foreach ($rows as $id => $row) {
        $data = dolical_caldav_vtodo($row, array_keys($alarms[$id] ?? []));
        $uri  = dolical_caldav_uri($id, (string) $row->ref_ext);

        $objects[$uri] = [
            'id'           => $id,
            'event'        => $id,
            'uri'          => $uri,
            'lastmodified' => (int) $db->jdate($row->tms),
            'etag'         => '"' . md5($data) . '"',
            'size'         => strlen($data),
            'calendardata' => $data,
            'component'    => 'vtodo',
        ];
    }

    return $objects;
}

/**
 * A reminder written for a phone (VTODO)
 *
 * @param  object $row    Row of llx_actioncomm
 * @param  int[]  $alarms Delays of the alarms before the due date, in seconds
 * @return string
 */
function dolical_caldav_vtodo($row, array $alarms): string
{
    global $db;

    $event          = new ActionComm($db);
    $event->id      = (int) $row->id;
    $event->entity  = (int) $row->entity;
    $event->ref_ext = (string) $row->ref_ext;
    $utc            = new DateTimeZone('UTC');
    $label          = dol_html_entity_decode((string) $row->label, ENT_QUOTES | ENT_HTML5);

    $vcalendar = new \Sabre\VObject\Component\VCalendar(['PRODID' => '-//Eoxia//DoliCal//FR']);
    $vtodo     = $vcalendar->createComponent('VTODO', [
        'UID'     => dolical_event_uid($event),
        'DTSTAMP' => new DateTime('@' . (int) $db->jdate($row->tms), $utc),
        'SUMMARY' => $label,
    ]);
    $vcalendar->add($vtodo);
    if (!empty($row->datec)) {
        $vtodo->add('CREATED', new DateTime('@' . (int) $db->jdate($row->datec), $utc));
    }
    $vtodo->add('LAST-MODIFIED', new DateTime('@' . (int) $db->jdate($row->tms), $utc));

    $due = !empty($row->datep) ? (int) $db->jdate($row->datep) : 0;
    if ($due > 0) {
        if (!empty($row->fulldayevent)) {
            // A day, in the time zone of the agenda : the phone shows it on that day wherever it is
            $vtodo->add('DUE', new DateTime(dolical_local_date($due)->format('Y-m-d')), ['VALUE' => 'DATE']);
        } else {
            $vtodo->add('DUE', new DateTime('@' . $due, $utc));
        }
    }
    $description = dolical_caldav_description((string) $row->note, '');
    if ($description !== '') {
        $vtodo->add('DESCRIPTION', $description);
    }
    if ((int) $row->priority > 0) {
        $vtodo->add('PRIORITY', min(9, (int) $row->priority));
    }
    if ((int) $row->percent >= 100) {
        $vtodo->add('STATUS', 'COMPLETED');
        $vtodo->add('PERCENT-COMPLETE', 100);
        $vtodo->add('COMPLETED', new DateTime('@' . (int) $db->jdate($row->tms), $utc));
    } else {
        $vtodo->add('STATUS', 'NEEDS-ACTION');
    }
    $vtodo->add('URL', dol_buildpath('/comm/action/card.php', 3) . '?id=' . ((int) $row->id));

    // The alarms at their date : the phones write them so for a reminder, it has no start to count from
    if ($due > 0) {
        sort($alarms);
        foreach (array_unique($alarms) as $seconds) {
            $alarm = $vcalendar->createComponent('VALARM', ['ACTION' => 'DISPLAY', 'DESCRIPTION' => $label]);
            $alarm->add('TRIGGER', gmdate('Ymd\THis\Z', $due - $seconds), ['VALUE' => 'DATE-TIME']);
            $vtodo->add($alarm);
        }
    }

    return $vcalendar->serialize();
}

/**
 * What a phone sent for a reminder, read
 *
 * @param  string $calendarData iCalendar object
 * @return array                ['vtodo', 'uid', 'label', 'note', 'due' => int|null, 'fullday', 'done', 'priority', 'alarm' => seconds before the due date|null]
 * @throws \Sabre\DAV\Exception\BadRequest
 */
function dolical_caldav_read_todo(string $calendarData): array
{
    try {
        $vcalendar = \Sabre\VObject\Reader::read($calendarData);
    } catch (Exception $exception) {
        throw new \Sabre\DAV\Exception\BadRequest('Invalid iCalendar : ' . $exception->getMessage());
    }

    // The reminder itself : the one without RECURRENCE-ID
    $vtodo = null;
    foreach ($vcalendar->select('VTODO') as $candidate) {
        if (!isset($candidate->{'RECURRENCE-ID'})) {
            $vtodo = $candidate;
            break;
        }
    }
    if ($vtodo === null) {
        throw new \Sabre\DAV\Exception\BadRequest('No reminder in the calendar object');
    }

    try {
        $zone = new DateTimeZone(dolical_reference_timezone());
    } catch (Exception $exception) {
        $zone = new DateTimeZone('UTC');
    }
    // The due date, or the start for the applications that only give one
    $due     = null;
    $fullday = false;
    $date    = $vtodo->DUE ?? $vtodo->DTSTART ?? null;
    if ($date !== null) {
        $fullday = !$date->hasTime();
        $moment  = $date->getDateTime($zone);
        $due     = ($fullday ? $moment->setTime(0, 0) : $moment)->getTimestamp();
    }

    $status = isset($vtodo->STATUS) ? strtoupper((string) $vtodo->STATUS) : '';
    $done   = $status === 'COMPLETED' || ($status === '' && isset($vtodo->COMPLETED));

    // The first alarm, as a delay before the due date : at a date (the iPhone), or counted from the due date
    $alarm = null;
    foreach ($vtodo->select('VALARM') as $valarm) {
        if (!isset($valarm->TRIGGER) || $due === null) {
            continue;
        }
        if (isset($valarm->TRIGGER['VALUE']) && strtoupper((string) $valarm->TRIGGER['VALUE']) === 'DATE-TIME') {
            $seconds = $due - $valarm->TRIGGER->getDateTime()->getTimestamp();
        } else {
            try {
                $interval = \Sabre\VObject\DateTimeParser::parseDuration((string) $valarm->TRIGGER->getValue());
            } catch (Exception $exception) {
                continue;
            }
            $seconds = -(new DateTimeImmutable('@0'))->add($interval)->getTimestamp();
        }
        if ($seconds >= 0) {
            $alarm = $alarm === null ? $seconds : max($alarm, $seconds);
        }
    }

    return [
        'vtodo'    => $vtodo,
        'uid'      => isset($vtodo->UID) ? preg_replace('/[\x00-\x1F\x7F]/', '', (string) $vtodo->UID) : '',
        'label'    => isset($vtodo->SUMMARY) ? trim((string) $vtodo->SUMMARY) : '',
        'note'     => isset($vtodo->DESCRIPTION) ? (string) $vtodo->DESCRIPTION : '',
        'due'      => $due,
        'fullday'  => $fullday,
        'done'     => $done,
        'priority' => isset($vtodo->PRIORITY) ? max(0, min(9, (int) (string) $vtodo->PRIORITY)) : 0,
        'alarm'    => $alarm,
    ];
}

/**
 * Write a reminder of a phone into its event : created in the list of its owner, or changed where the phone changed it
 *
 * @param  ActionComm $event  Event, new (its ref_ext set) or loaded with its resources
 * @param  array      $read   From dolical_caldav_read_todo()
 * @param  array|null $before What the phone was given, read the same way, for a change ; null for a new reminder
 * @param  User       $owner  Person whose list it is
 * @param  User       $user   Person synchronizing
 * @return int                Id of the event, < 0 on error
 */
function dolical_caldav_save_todo(ActionComm $event, array $read, ?array $before, User $owner, User $user): int
{
    global $db, $langs;

    $typeId = dolical_todo_type_id();
    if ($typeId <= 0) {
        return -1;
    }
    $isNew = empty($event->id);
    // Only what the phone changed : a description written in Dolibarr keeps its layout while the phone leaves it
    $changed = function (string $key) use ($read, $before): bool {
        return $before === null || $read[$key] !== $before[$key];
    };

    if (!$isNew) {
        $event->oldcopy = clone $event;
    }
    if ($changed('label')) {
        $label        = trim(preg_replace('/\s*[\r\n]+\s*/', ' ', sanitizeVal($read['label'], 'alphanohtml')));
        $event->label = dol_trunc($label !== '' ? $label : $langs->transnoentities('DoliCalUntitled'), 255, 'right', 'UTF-8', 1);
    }
    if ($changed('note') && trim($read['note']) !== trim((string) ($before['note'] ?? ''))) {
        $event->note_private = sanitizeVal($read['note'], 'restricthtml');
    }
    if ($changed('due') || $changed('fullday')) {
        $event->datep        = $read['due'] ?? '';
        $event->datef        = $event->datep;
        $event->fulldayevent = $read['due'] !== null && $read['fullday'] ? 1 : 0;
    }
    if ($changed('done')) {
        $event->percentage = $read['done'] ? 100 : 0;
    }
    if ($changed('priority')) {
        $event->priority = $read['priority'];
    }

    if ($isNew) {
        $event->type_id      = $typeId;
        $event->type_code    = DOLICAL_TODO_TYPE;
        $event->transparency = 0;
        $event->userownerid  = (int) $owner->id;
        $event->userassigned = [(int) $owner->id => ['id' => (int) $owner->id, 'mandatory' => 0, 'answer_status' => 0, 'transparency' => 0]];
        $id                  = $event->create($user);
        if ($id <= 0) {
            dol_syslog(__FUNCTION__ . ' ' . $event->error . ' ' . implode(', ', (array) $event->errors), LOG_ERR);
            return -1;
        }
        // Without a due date, no date at all : the core gives the time of the creation to an event without start
        if ($read['due'] === null) {
            $db->query('UPDATE ' . MAIN_DB_PREFIX . 'actioncomm SET datep = NULL, datep2 = NULL, fulldayevent = 0 WHERE id = ' . ((int) $id));
        }
    } else {
        $id = $event->update($user) > 0 ? (int) $event->id : -1;
        if ($id <= 0) {
            dol_syslog(__FUNCTION__ . ' ' . $event->error . ' ' . implode(', ', (array) $event->errors), LOG_ERR);
            return -1;
        }
    }

    // The alarm of the owner : a reminder of Dolibarr before the due date, or none
    if ($changed('alarm') || $changed('due')) {
        $event->fetch($id);
        dolical_caldav_set_alarm($event, $read['due'] !== null ? $read['alarm'] : null, $owner);
    }

    return $id;
}
