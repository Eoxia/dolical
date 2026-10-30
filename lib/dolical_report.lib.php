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
 * \file    lib/dolical_report.lib.php
 * \ingroup dolical
 * \brief   Report of a meeting, written once it is over, in the extra field dolical_report of the native event.
 *          Its files are the attachments of the event.
 */

require_once DOL_DOCUMENT_ROOT . '/core/class/extrafields.class.php';

/**
 * Whether the extra field of the report exists : it is created when the module is enabled
 *
 * @return bool
 */
function dolical_report_available(): bool
{
    global $db;

    static $available = null;
    if ($available === null) {
        $extrafields = new ExtraFields($db);
        $extrafields->fetch_name_optionals_label('actioncomm');
        $available = isset($extrafields->attributes['actioncomm']['label']['dolical_report']);
    }

    return $available;
}

/**
 * Report of an event
 *
 * @param  ActionComm $event Event
 * @return string            Plain text, empty when none was written
 */
function dolical_get_report(ActionComm $event): string
{
    if (!dolical_report_available() || empty($event->id)) {
        return '';
    }
    if (!isset($event->array_options['options_dolical_report'])) {
        $event->fetch_optionals();
    }

    return trim((string) ($event->array_options['options_dolical_report'] ?? ''));
}

/**
 * Write the report of an event, and mark it done when asked
 *
 * @param  ActionComm $event Event
 * @param  string     $text  Report, plain text
 * @param  bool       $done  Whether the event is done
 * @param  User       $user  Current user
 * @return int               > 0 when saved
 */
function dolical_save_report(ActionComm $event, string $text, bool $done, User $user): int
{
    if (!dolical_report_available()) {
        return -1;
    }

    $event->fetch_optionals();
    $event->array_options['options_dolical_report'] = trim($text) !== '' ? trim($text) : null;
    if ($event->updateExtraField('dolical_report', null, $user) <= 0) {
        return -1;
    }

    if ($done && $event->percentage >= 0 && $event->percentage < 100) {
        $event->oldcopy    = clone $event;
        $event->percentage = 100;
        return $event->update($user) > 0 ? 1 : -1;
    }

    return 1;
}

/**
 * Whether the user may write the report of an event : whoever may change it, or took part in it
 *
 * @param  User       $user  Current user
 * @param  ActionComm $event Event, resources loaded
 * @return bool
 */
function dolical_can_report(User $user, ActionComm $event): bool
{
    return dolical_report_available() && $event->type !== 'systemauto'
        && (dolical_can_edit_event($user, $event) || isset($event->userassigned[$user->id]) || (int) $event->userownerid === (int) $user->id);
}

/**
 * Whether the report of an event is awaited from the user : a meeting he took part in, over, without report
 *
 * @param  ActionComm $event Event, resources loaded
 * @param  User       $user  Current user
 * @return bool
 */
function dolical_report_due(ActionComm $event, User $user): bool
{
    if (!dolical_report_available() || $event->type === 'systemauto' || $event->percentage < 0) {
        return false;
    }
    $end = (int) ($event->datef ?: $event->datep);
    if ($end > dol_now() || $end < dol_now() - 30 * 86400) {
        return false;
    }
    $mine = (int) $event->userownerid === (int) $user->id || isset($event->userassigned[$user->id]);

    return $mine && dolical_get_report($event) === '';
}

/**
 * Ids of the events of a list that already have a report
 *
 * @param  int[] $ids Events
 * @return int[]
 */
function dolical_events_with_report(array $ids): array
{
    global $db;

    $ids = array_values(array_filter(array_map('intval', $ids)));
    if (empty($ids) || !dolical_report_available()) {
        return [];
    }

    $sql   = 'SELECT fk_object FROM ' . MAIN_DB_PREFIX . 'actioncomm_extrafields';
    $sql  .= ' WHERE fk_object IN (' . $db->sanitize(implode(',', $ids)) . ") AND dolical_report IS NOT NULL AND dolical_report <> ''";
    $resql = $db->query($sql);
    $found = [];
    if ($resql) {
        while ($obj = $db->fetch_object($resql)) {
            $found[] = (int) $obj->fk_object;
        }
        $db->free($resql);
    }

    return $found;
}
