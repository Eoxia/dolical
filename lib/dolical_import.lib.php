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
 * \file    lib/dolical_import.lib.php
 * \ingroup dolical
 * \brief   Import of an iCalendar file (.ics) exported from another agenda : Nextcloud, Google, Outlook,
 *          Thunderbird... Each event becomes a native event, created the way the phones create theirs (see
 *          dolical_caldav_create()), and keeps its identifier : the same file imported again adds nothing twice.
 */

require_once DOL_DOCUMENT_ROOT . '/includes/sabre/autoload.php';
require_once __DIR__ . '/dolical_caldav.lib.php';

/**
 * Biggest file taken, in Ko : the setting DOLICAL_IMPORT_MAX_SIZE (10 Mo by default), within what PHP and Dolibarr
 * accept for an upload
 *
 * @return int
 */
function dolical_import_max_size(): int
{
    $server = (int) getMaxFileSizeArray()['maxmin'];
    $module = getDolGlobalInt('DOLICAL_IMPORT_MAX_SIZE', 10240);

    return $server > 0 && $module > 0 ? min($server, $module) : max($server, $module);
}

/**
 * Time an import or its removal may take, in seconds (DOLICAL_IMPORT_TIME_LIMIT, 10 minutes by default) : longer than
 * a page, never without end. A server already without limit keeps it
 *
 * @return void
 */
function dolical_import_time_limit(): void
{
    $current = (int) ini_get('max_execution_time');
    $wanted  = getDolGlobalInt('DOLICAL_IMPORT_TIME_LIMIT', 600);
    if ($current > 0 && $wanted > $current) {
        @set_time_limit($wanted);
    }
}

/**
 * Identifiers of the events of other agendas already in the agenda of a person, as owner or as participant
 *
 * @param  User  $owner Person
 * @return array        [uid => id of the event]
 */
function dolical_import_known_uids(User $owner): array
{
    global $db;

    $sql  = 'SELECT a.id, a.ref_ext FROM ' . MAIN_DB_PREFIX . 'actioncomm as a';
    $sql .= " WHERE a.ref_ext LIKE 'dav:%' AND a.entity IN (" . getEntity('agenda') . ')';
    $sql .= ' AND (a.fk_user_action = ' . ((int) $owner->id);
    $sql .= ' OR EXISTS (SELECT 1 FROM ' . MAIN_DB_PREFIX . "actioncomm_resources as r WHERE r.fk_actioncomm = a.id AND r.element_type = 'user' AND r.fk_element = " . ((int) $owner->id) . '))';
    $sql .= ' ORDER BY a.id';

    $known = [];
    $resql = $db->query($sql);
    while ($resql && ($obj = $db->fetch_object($resql))) {
        $parts = explode('|', (string) $obj->ref_ext, 2);
        if (($parts[1] ?? '') !== '' && !isset($known[$parts[1]])) {
            $known[$parts[1]] = (int) $obj->id;
        }
    }

    return $known;
}

/**
 * Add to an event already imported, and to the dates of its series, the people and the third party found since :
 * nothing is removed, nothing else changes, no mail is sent
 *
 * @param  int   $id     Event
 * @param  array $people From dolical_caldav_people()
 * @return bool          Whether something was added
 */
function dolical_import_complete(int $id, array $people): bool
{
    global $db;

    if (empty($people['users']) && empty($people['contacts']) && empty($people['socid'])) {
        return false;
    }

    // The event and the dates of its series
    $resql = $db->query('SELECT recurid FROM ' . MAIN_DB_PREFIX . 'actioncomm WHERE id = ' . $id);
    $obj   = $resql ? $db->fetch_object($resql) : null;
    if (!$obj) {
        return false;
    }
    $sql  = 'SELECT id, transparency FROM ' . MAIN_DB_PREFIX . 'actioncomm WHERE entity IN (' . getEntity('agenda') . ')';
    $sql .= !empty($obj->recurid) ? " AND recurid = '" . $db->escape($obj->recurid) . "'" : ' AND id = ' . $id;
    $events = [];
    $resql  = $db->query($sql);
    while ($resql && ($obj = $db->fetch_object($resql))) {
        $events[(int) $obj->id] = (int) $obj->transparency;
    }
    if (empty($events)) {
        return false;
    }
    $list = implode(', ', array_keys($events));

    $present = [];
    $resql   = $db->query('SELECT fk_actioncomm, element_type, fk_element FROM ' . MAIN_DB_PREFIX . 'actioncomm_resources WHERE fk_actioncomm IN (' . $list . ')');
    while ($resql && ($obj = $db->fetch_object($resql))) {
        $present[(int) $obj->fk_actioncomm][$obj->element_type][(int) $obj->fk_element] = true;
    }

    $added = false;
    $error = 0;
    $db->begin();
    foreach ($events as $eventId => $transparency) {
        foreach (['user' => $people['answers']['user'], 'socpeople' => $people['answers']['socpeople']] as $type => $answers) {
            foreach ($answers as $personId => $answer) {
                if (isset($present[$eventId][$type][$personId])) {
                    continue;
                }
                $sql    = 'INSERT INTO ' . MAIN_DB_PREFIX . 'actioncomm_resources (fk_actioncomm, element_type, fk_element, mandatory, transparency, answer_status)';
                $sql   .= ' VALUES (' . $eventId . ", '" . $type . "', " . ((int) $personId) . ', 0, ' . ($type === 'user' ? $transparency : 0) . ', ' . ((int) $answer) . ')';
                $error += $db->query($sql) ? 0 : 1;
                $added  = true;
            }
        }
    }
    // The third party and the first contact, where the event has none
    if (!empty($people['socid'])) {
        $sql    = 'UPDATE ' . MAIN_DB_PREFIX . 'actioncomm SET fk_soc = ' . ((int) $people['socid']) . ' WHERE id IN (' . $list . ') AND (fk_soc IS NULL OR fk_soc = 0)';
        $resql  = $db->query($sql);
        $error += $resql ? 0 : 1;
        $added  = $added || ($resql && $db->affected_rows($resql) > 0);
    }
    if (!empty($people['contacts'])) {
        $sql    = 'UPDATE ' . MAIN_DB_PREFIX . 'actioncomm SET fk_contact = ' . ((int) reset($people['contacts'])) . ' WHERE id IN (' . $list . ') AND (fk_contact IS NULL OR fk_contact = 0)';
        $error += $db->query($sql) ? 0 : 1;
    }
    if ($error) {
        dol_syslog(__FUNCTION__ . ' ' . $db->lasterror(), LOG_ERR);
        $db->rollback();
        return false;
    }
    $db->commit();

    return $added;
}

/**
 * Import the events of an iCalendar file into the agenda of a person. No mail is sent : the people taking part are
 * found by their address or their name and keep the answer they gave. An event already imported gets the people
 * and the third party found since
 *
 * @param  string $file  Path of the file
 * @param  User   $owner Person whose agenda gets the events
 * @param  User   $user  Person importing
 * @return array         ['key' => import key of the events created, 'events', 'series', 'dates', 'duplicates', 'linked',
 *                       'ignored' => int, 'failed' => string[], 'unknown' => [address => number of meetings],
 *                       'truncated' => the most appointments read when the file held more, else 0], or
 *                       ['error' => message] when the file is not an agenda
 */
function dolical_import_ics(string $file, User $owner, User $user): array
{
    global $langs;

    $handle = fopen($file, 'r');
    if ($handle === false) {
        return ['error' => $langs->trans('DoliCalImportBadFile')];
    }
    try {
        // The exports of the mail clients are not always strict : a line the library cannot read is left out
        $splitter = new \Sabre\VObject\Splitter\ICalendar($handle, \Sabre\VObject\Reader::OPTION_FORGIVING | \Sabre\VObject\Reader::OPTION_IGNORE_INVALID_LINES);
    } catch (Exception $exception) {
        dol_syslog(__FUNCTION__ . ' ' . $exception->getMessage(), LOG_WARNING);
        return ['error' => $langs->trans('DoliCalImportBadFile')];
    } finally {
        fclose($handle);
    }

    $result = ['key' => dolical_import_new_key(), 'events' => 0, 'series' => 0, 'dates' => 0, 'duplicates' => 0, 'linked' => 0, 'ignored' => 0, 'failed' => [], 'unknown' => [], 'truncated' => 0];
    $owned  = array_fill_keys(array_filter([dol_strtolower((string) $owner->email), dol_strtolower((string) $owner->personal_email)]), true);
    $known  = dolical_import_known_uids($owner);
    $ahead  = (new DateTimeImmutable('@' . dol_now()))->modify('+1 year')->getTimestamp();
    // At most so many appointments read from one file (DOLICAL_IMPORT_MAX_EVENTS) : the rest is left, and told
    $max    = getDolGlobalInt('DOLICAL_IMPORT_MAX_EVENTS', 5000);
    $taken  = 0;

    while ($vcalendar = $splitter->getNext()) {
        // Tasks and notes are not events
        if (!isset($vcalendar->VEVENT)) {
            continue;
        }
        if ($max > 0 && ++$taken > $max) {
            $result['truncated'] = $max;
            break;
        }
        try {
            $read = dolical_caldav_read($vcalendar->serialize(), (string) $owner->email);
        } catch (Exception $exception) {
            $result['ignored']++;
            continue;
        }
        if (isset($read['vevent']->STATUS) && strtoupper((string) $read['vevent']->STATUS) === 'CANCELLED') {
            $result['ignored']++;
            continue;
        }

        // The identifier kept as dolical_caldav_create() stores it, cut to the size of the field
        $uri       = 'import-' . ((int) $owner->id) . '-' . md5($read['uid']) . '.ics';
        $reference = explode('|', dol_trunc('dav:' . $uri . '|' . $read['uid'], 255, 'right', 'UTF-8', 1), 2);
        $uid       = (string) ($reference[1] ?? '');

        $people = dolical_caldav_people($read['emails'], $user, (int) $owner->id, $read['names']);
        foreach ($people['unknown'] as $email) {
            if (!isset($owned[$email])) {
                $result['unknown'][$email] = ($result['unknown'][$email] ?? 0) + 1;
            }
        }

        if ($uid !== '' && isset($known[$uid])) {
            $result['duplicates']++;
            if (dolical_import_complete($known[$uid], $people)) {
                $result['linked']++;
            }
            continue;
        }

        // A series of the past is taken up to a year ahead, one to come over its first year
        $until = max($ahead, (new DateTimeImmutable('@' . $read['start']))->modify('+1 year')->getTimestamp());
        // A meeting of another agenda is no task : neither to do nor late once past
        $read['percent'] = -1;
        $read['extra']   = dolical_import_extra($read, $people, $owned);
        $id              = dolical_caldav_create($user, $uri, $read, $owner, $until, $people);
        if ($id <= 0) {
            $label              = $read['label'] !== '' ? $read['label'] : $langs->transnoentities('DoliCalUntitled');
            $result['failed'][] = dol_print_date($read['start'], 'day') . ' – ' . dol_trunc($label, 80);
            continue;
        }
        $known[$uid] = $id;
        $result['events']++;
        dolical_import_tag($id, $result['key']);

        $dates = dolical_import_series_size($id);
        $result['dates'] += max(1, $dates);
        if ($dates > 1) {
            $result['series']++;
        }
    }
    // The people met most often first : the ones worth adding to the instance
    arsort($result['unknown']);

    return $result;
}

/**
 * What the other agenda shows of a meeting and the event has no field for, added under its description : the whole
 * title or place when longer than the event keeps, the people not found in the instance (or given without an address)
 * with their answer, the categories, the link of the video call
 *
 * @param  array $read   From dolical_caldav_read()
 * @param  array $people From dolical_caldav_people()
 * @param  array $owned  [address of the owner => true], left out
 * @return string        Lines, empty when nothing is left out
 */
function dolical_import_extra(array $read, array $people, array $owned): string
{
    global $langs;

    $vevent = $read['vevent'];
    $lines  = [];

    // A title or a place longer than the event keeps (ActionComm::create() cuts both at 128), whole
    $oneLine = function (string $text): string {
        return trim(preg_replace('/\s*[\r\n]+\s*/', ' ', $text));
    };
    if (dol_strlen($oneLine($read['label'])) > 128) {
        $lines[] = $langs->transnoentities('DoliCalImportExtraTitle', $oneLine($read['label']));
    }
    if (dol_strlen($oneLine($read['location'])) > 128) {
        $lines[] = $langs->transnoentities('DoliCalImportExtraPlace', $oneLine($read['location']));
    }

    // The people not found : the addresses of nobody, and the ones the agenda gave without an address
    $answers = [1 => 'DoliCalAnswerAccepted', 2 => 'DoliCalAnswerTentative', -1 => 'DoliCalAnswerDeclined', 0 => 'DoliCalAnswerPending'];
    $outside = [];
    $missing = $people['unknown'];
    foreach (array_keys($read['emails']) as $address) {
        if (!isValidEmail($address) && trim($address) !== '') {
            $missing[] = $address;
        }
    }
    foreach ($missing as $email) {
        if (isset($owned[$email])) {
            continue;
        }
        $name      = trim((string) ($read['names'][$email] ?? ''));
        $person    = ($name !== '' && $name !== $email ? $name . ' (' . $email . ')' : $email);
        $status    = $email === $read['organizer'] ? $langs->transnoentities('DoliCalOrganizer') : $langs->transnoentities($answers[(int) ($read['emails'][$email] ?? 0)] ?? 'DoliCalAnswerPending');
        $outside[] = '- ' . $person . ' : ' . $status;
    }
    if (!empty($outside)) {
        $lines[] = $langs->transnoentities('DoliCalImportExtraPeople');
        $lines   = array_merge($lines, $outside);
    }

    $categories = [];
    foreach ($vevent->select('CATEGORIES') as $property) {
        foreach ($property->getParts() as $category) {
            // Google writes its own kinds of events there, as addresses
            if (trim($category) !== '' && strpos($category, '://') === false) {
                $categories[trim($category)] = true;
            }
        }
    }
    if (!empty($categories)) {
        $lines[] = $langs->transnoentities('DoliCalImportExtraCategories', implode(', ', array_keys($categories)));
    }

    // The link of the meeting, unless the description already gives it
    foreach (['X-GOOGLE-CONFERENCE', 'URL'] as $name) {
        $link = isset($vevent->$name) ? trim((string) $vevent->$name) : '';
        if ($link !== '' && strpos($read['note'], $link) === false) {
            $lines[] = $langs->transnoentities('DoliCalImportExtraLink', $link);
        }
    }

    return implode("\n", $lines);
}

/**
 * Number of dates of the series an event starts
 *
 * @param  int $id Event
 * @return int     0 when the event is not a series
 */
function dolical_import_series_size(int $id): int
{
    global $db;

    $resql = $db->query('SELECT COUNT(id) as nb FROM ' . MAIN_DB_PREFIX . "actioncomm WHERE recurid = '" . $db->escape(dolical_new_recurid($id)) . "'");
    $obj   = $resql ? $db->fetch_object($resql) : null;

    return $obj ? (int) $obj->nb : 0;
}

/**
 * A new identifier for an import : the native import key of the events, the time of the import made unique
 *
 * @return string 14 characters
 */
function dolical_import_new_key(): string
{
    global $db;

    $now = dol_now();
    for ($shift = 0; $shift < 60; $shift++) {
        $key   = dol_print_date($now + $shift, '%Y%m%d%H%M%S');
        $resql = $db->query('SELECT id FROM ' . MAIN_DB_PREFIX . "actioncomm WHERE import_key = '" . $db->escape($key) . "' LIMIT 1");
        if ($resql && !$db->num_rows($resql)) {
            break;
        }
    }

    return $key;
}

/**
 * Mark an event created by an import, and the dates of its series, with the key of the import
 *
 * @param  int    $id  Event
 * @param  string $key Import key
 * @return void
 */
function dolical_import_tag(int $id, string $key): void
{
    global $db;

    $sql  = 'UPDATE ' . MAIN_DB_PREFIX . "actioncomm SET import_key = '" . $db->escape($key) . "'";
    $sql .= ' WHERE id = ' . $id . " OR recurid = '" . $db->escape(dolical_new_recurid($id)) . "'";
    $db->query($sql);
}

/**
 * The imports of agenda files whose events are still there : the ones the user made or that filled his agenda, all of
 * them with the right to delete the events of everyone. The events of the native import tool are not among them
 *
 * @param  User   $user   Current user
 * @param  string $key    One import only, with $author
 * @param  int    $author Person who made it
 * @return array          [['key', 'author', 'owner' => id, 'date', 'first', 'last' => timestamp, 'events' => meetings, 'dates' => int]]
 */
function dolical_import_list(User $user, string $key = '', int $author = 0): array
{
    global $db;

    $sql  = 'SELECT a.import_key, a.fk_user_author, MIN(a.fk_user_action) as owner, MIN(a.datec) as datec, MIN(a.datep) as first, MAX(a.datep) as last,';
    $sql .= " COUNT(a.id) as dates, SUM(CASE WHEN a.ref_ext LIKE 'dav:import-%' THEN 1 ELSE 0 END) as events";
    $sql .= ' FROM ' . MAIN_DB_PREFIX . 'actioncomm as a';
    $sql .= ' WHERE a.entity IN (' . getEntity('agenda') . ") AND a.import_key IS NOT NULL AND a.import_key <> ''";
    if ($key !== '') {
        $sql .= " AND a.import_key = '" . $db->escape($key) . "' AND a.fk_user_author = " . $author;
    }
    if (!$user->hasRight('agenda', 'allactions', 'delete')) {
        $sql .= ' AND (a.fk_user_author = ' . ((int) $user->id) . ' OR a.fk_user_action = ' . ((int) $user->id) . ')';
    }
    // Two imports in the same second by two people stay two imports
    $sql .= ' GROUP BY a.import_key, a.fk_user_author';
    $sql .= " HAVING SUM(CASE WHEN a.ref_ext LIKE 'dav:import-%' THEN 1 ELSE 0 END) > 0";
    $sql .= ' ORDER BY datec DESC';
    $sql .= $db->plimit(20);

    $imports = [];
    $resql   = $db->query($sql);
    while ($resql && ($obj = $db->fetch_object($resql))) {
        $imports[] = [
            'key'    => (string) $obj->import_key,
            'author' => (int) $obj->fk_user_author,
            'owner'  => (int) $obj->owner,
            'date'   => $db->jdate($obj->datec),
            'first'  => $db->jdate($obj->first),
            'last'   => $db->jdate($obj->last),
            'events' => (int) $obj->events,
            'dates'  => (int) $obj->dates,
        ];
    }

    return $imports;
}

/**
 * Delete the events an import created, the dates of their series included, the way the agenda deletes one : the
 * people taking part are not told, no more than they were told of the import
 *
 * @param  string $key    Import key
 * @param  int    $author Person who made it
 * @param  User   $user   Current user : an event he may not delete stays
 * @return array          ['deleted', 'refused', 'failed' => int]
 */
function dolical_import_undo(string $key, int $author, User $user): array
{
    global $db;

    $result = ['deleted' => 0, 'refused' => 0, 'failed' => 0];

    $sql  = 'SELECT id FROM ' . MAIN_DB_PREFIX . 'actioncomm';
    $sql .= ' WHERE entity IN (' . getEntity('agenda') . ") AND import_key = '" . $db->escape($key) . "' AND fk_user_author = " . $author;
    $ids   = [];
    $resql = $db->query($sql);
    while ($resql && ($obj = $db->fetch_object($resql))) {
        $ids[] = (int) $obj->id;
    }

    foreach ($ids as $id) {
        $event = new ActionComm($db);
        if ($event->fetch($id) <= 0) {
            continue;
        }
        if (!dolical_can_delete_event($user, $event)) {
            $result['refused']++;
        } elseif ($event->delete($user) > 0) {
            $result['deleted']++;
        } else {
            dol_syslog(__FUNCTION__ . ' ' . $id . ' ' . $event->error, LOG_ERR);
            $result['failed']++;
        }
    }

    return $result;
}
