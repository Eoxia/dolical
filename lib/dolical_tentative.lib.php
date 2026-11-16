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
 * \file    lib/dolical_tentative.lib.php
 * \ingroup dolical
 * \brief   Appointments to confirm : the slots offered for one meeting (the dates of a mail, several dates typed in the
 *          form) are events of their own, marked « to confirm » and joined by a key (field dolical_tentative of the
 *          native event). Confirming one of them clears its mark and removes the others.
 */

/**
 * Whether the field of the mark exists : created when the module is enabled
 *
 * @return bool
 */
function dolical_tentative_available(): bool
{
    global $db;

    static $available = null;
    if ($available === null) {
        require_once DOL_DOCUMENT_ROOT . '/core/class/extrafields.class.php';
        $extrafields = new ExtraFields($db);
        $extrafields->fetch_name_optionals_label('actioncomm');
        $available = isset($extrafields->attributes['actioncomm']['label']['dolical_tentative']);
    }

    return $available;
}

/**
 * A new key for the slots offered together
 *
 * @return string
 */
function dolical_tentative_new_group(): string
{
    return 'g' . bin2hex(random_bytes(8));
}

/**
 * Key of the slots an event is offered with, '' when it is confirmed
 *
 * @param  ActionComm $event  Event
 * @param  bool       $reload Whether to read the field again
 * @return string
 */
function dolical_get_tentative(ActionComm $event, bool $reload = false): string
{
    if (!dolical_tentative_available() || empty($event->id)) {
        return '';
    }
    if ($reload || !isset($event->array_options) || !array_key_exists('options_dolical_tentative', (array) $event->array_options)) {
        $event->fetch_optionals();
    }

    return trim((string) ($event->array_options['options_dolical_tentative'] ?? ''));
}

/**
 * Mark an event to confirm, with the key of the slots offered with it, or confirm it ('')
 *
 * @param  ActionComm $event Event, saved
 * @param  string     $group Key, '' to confirm
 * @param  User       $user  Current user
 * @return int               > 0 when done
 */
function dolical_set_tentative(ActionComm $event, string $group, User $user): int
{
    if (!dolical_tentative_available() || empty($event->id)) {
        return $group === '' ? 1 : -1;
    }
    if (dolical_get_tentative($event, true) === $group) {
        return 1;
    }

    // Cleared as an empty text : updateExtraField() skips a null value
    $event->array_options['options_dolical_tentative'] = preg_match('/^g[0-9a-f]{16}$/', $group) ? $group : '';

    return $event->updateExtraField('dolical_tentative', null, $user) > 0 ? 1 : -1;
}

/**
 * The events to confirm among some events, with their key
 *
 * @param  int[] $ids Events
 * @return array      [id => key]
 */
function dolical_events_tentative(array $ids): array
{
    global $db;

    $ids = array_values(array_filter(array_map('intval', $ids)));
    if (empty($ids) || !dolical_tentative_available()) {
        return [];
    }

    $sql   = 'SELECT fk_object, dolical_tentative FROM ' . MAIN_DB_PREFIX . 'actioncomm_extrafields';
    $sql  .= ' WHERE fk_object IN (' . $db->sanitize(implode(',', $ids)) . ") AND dolical_tentative IS NOT NULL AND dolical_tentative <> ''";
    $resql = $db->query($sql);
    $found = [];
    if ($resql) {
        while ($obj = $db->fetch_object($resql)) {
            $found[(int) $obj->fk_object] = (string) $obj->dolical_tentative;
        }
        $db->free($resql);
    }

    return $found;
}

/**
 * The other slots offered with an event
 *
 * @param  ActionComm $event Event
 * @return int[]             Events, by date
 */
function dolical_tentative_siblings(ActionComm $event): array
{
    global $db;

    $group = dolical_get_tentative($event);
    if ($group === '') {
        return [];
    }

    $sql   = 'SELECT a.id FROM ' . MAIN_DB_PREFIX . 'actioncomm as a';
    $sql  .= ' INNER JOIN ' . MAIN_DB_PREFIX . 'actioncomm_extrafields as ef ON ef.fk_object = a.id';
    $sql  .= " WHERE ef.dolical_tentative = '" . $db->escape($group) . "' AND a.id <> " . ((int) $event->id);
    $sql  .= ' AND a.entity IN (' . getEntity('agenda') . ')';
    $sql  .= ' ORDER BY a.datep ASC';
    $resql = $db->query($sql);
    $ids   = [];
    if ($resql) {
        while ($obj = $db->fetch_object($resql)) {
            $ids[] = (int) $obj->id;
        }
        $db->free($resql);
    }

    return $ids;
}

/**
 * Confirm a slot : its mark is cleared, the other slots offered with it are removed (the people invited to them are
 * told, as by a deletion). A slot the user may not remove stays, to confirm.
 *
 * @param  ActionComm $event Event confirmed
 * @param  User       $user  Current user
 * @return array             ['success' => bool, 'removed' => int, 'kept' => int, 'cancelled' => int, 'errors' => string[]]
 */
function dolical_confirm_event(ActionComm $event, User $user): array
{
    $result   =['success' => false, 'removed' => 0, 'kept' => 0, 'cancelled' => 0, 'errors' => []];
    $siblings = dolical_tentative_siblings($event);
    if (dolical_set_tentative($event, '', $user) <= 0) {
        $result['errors'][] = $event->error ?: 'Error';
        return $result;
    }
    $result['success'] = true;

    foreach ($siblings as $id) {
        // Read as the calendar reads an event : in the entities of the user, if he may see it
        $other = dolical_load_event($id, $user);
        if ($other === null) {
            continue;
        }
        if (!dolical_can_delete_event($user, $other)) {
            $result['kept']++;
            continue;
        }
        $deleted = dolical_delete_event_series($other, 'one', $user);
        if ($deleted['success']) {
            $result['removed']   += max(1, (int) $deleted['deleted']);
            $result['cancelled'] += (int) $deleted['cancelled'];
        } else {
            $result['kept']++;
        }
        $result['errors'] = array_merge($result['errors'], $deleted['errors']);
    }

    return $result;
}
