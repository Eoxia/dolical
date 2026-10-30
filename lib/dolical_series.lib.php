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
 * \file    lib/dolical_series.lib.php
 * \ingroup dolical
 * \brief   Saving and deleting an event together with its series : « this event », « this event and the following
 *          ones » or « all the events », the way the calendars of Google or Outlook ask. Every occurrence stays a
 *          native event of its own.
 */

require_once __DIR__ . '/dolical_invitation.lib.php';
require_once __DIR__ . '/dolical_recurrence.lib.php';
require_once __DIR__ . '/dolical_reminder.lib.php';
require_once __DIR__ . '/dolical_report.lib.php';

/**
 * Values of an event in the shape of the form, so that a part of them can be changed and the event saved again
 *
 * @param  ActionComm $event Event, resources loaded
 * @return array             Data of dolical_save_event()
 */
function dolical_event_values(ActionComm $event): array
{
    return [
        'label'        => (string) $event->label,
        'type_code'    => (string) $event->type_code,
        'fullday'      => (int) $event->fulldayevent,
        'start'        => (int) $event->datep,
        'end'          => (int) ($event->datef ?: $event->datep),
        'location'     => (string) $event->location,
        'percent'      => (int) $event->percentage,
        'busy'         => (int) $event->transparency,
        'owner_id'     => (int) $event->userownerid,
        'user_ids'     => array_values(array_diff(array_map('intval', array_keys($event->userassigned)), [(int) $event->userownerid])),
        'socid'        => (int) $event->socid,
        'contact_ids'  => array_values(array_unique(array_merge($event->contact_id > 0 ? [(int) $event->contact_id] : [], array_map('intval', array_keys($event->socpeopleassigned))))),
        'project_id'   => (int) $event->fk_project,
        'elements'     => array_keys(dolical_get_event_links($event)),
        'note'         => (string) $event->note_private,
        'note_changed' => 0,
    ];
}

/**
 * Hours of an occurrence after the hours of the series changed : its own day, the new hour, the new duration
 *
 * @param  int   $occurrenceStart Start of the occurrence
 * @param  int   $newStart        New start of the event edited
 * @param  int   $newEnd          New end of the event edited
 * @return int[]                  [start, end]
 */
function dolical_wall_shift(int $occurrenceStart, int $newStart, int $newEnd): array
{
    $start = dolical_local_date($newStart);
    $moved = dolical_local_date($occurrenceStart)->setTime((int) $start->format('G'), (int) $start->format('i'), (int) $start->format('s'));

    return [$moved->getTimestamp(), $moved->add($start->diff(dolical_local_date(max($newStart, $newEnd))))->getTimestamp()];
}

/**
 * Remove an occurrence without telling anyone : the series it belonged to is told as a whole. The traces of its
 * invitations go to an occurrence that stays, the history of who was invited lives on
 *
 * @param  ActionComm $event  Occurrence
 * @param  User       $user   Current user
 * @param  int        $heirId Occurrence of the same series receiving its traces, 0 to delete them
 * @return bool
 */
function dolical_delete_occurrence(ActionComm $event, User $user, int $heirId = 0): bool
{
    global $db;

    if ($event->delete($user) <= 0) {
        return false;
    }
    dolical_delete_event_dir($event);
    if ($heirId > 0) {
        $db->query('UPDATE ' . MAIN_DB_PREFIX . 'actioncomm SET fk_element = ' . $heirId . ' WHERE fk_element = ' . ((int) $event->id) . " AND elementtype = 'action'");
    } else {
        dolical_delete_invitation_traces((int) $event->id, $user);
    }

    return true;
}

/**
 * Save an event from the form, its series with it when it repeats, and the reminders of the people
 *
 * Beside the fields of dolical_save_event(), $data carries :
 * - recurrence  : kind of repetition ('' none, 'custom' keeps the rule of the event)
 * - recur_until : last day of the series
 * - recur_scope : 'one', 'following' or 'all', for an event already part of a series
 * - reminder    : setting for dolical_reminder_clean(), null to leave the reminders as they are
 *
 * A change of the rule, of its end or of the day rebuilds the occurrences to come from the edited one ; the
 * occurrences already past are never deleted, their report and attachments stay. « This and the following »
 * cuts the series in two : the ones before keep the old series, ended the day before.
 *
 * @param  ActionComm $event Event, new or loaded with its resources
 * @param  array      $data  Values of the form
 * @param  User       $user  Current user
 * @return array             ['id', 'error', 'scope', 'created', 'updated', 'removed', 'truncated', 'oldStart', 'ruleChanged', 'oldSeriesFirst']
 */
function dolical_save_event_series(ActionComm $event, array $data, User $user): array
{
    global $db;

    $result = ['id' => 0, 'error' => '', 'scope' => 'one', 'created' => 0, 'updated' => 0, 'removed' => 0, 'truncated' => false, 'oldStart' => (int) $event->datep, 'ruleChanged' => false, 'oldSeriesFirst' => 0];

    $isNew    = empty($event->id);
    $oldStart = (int) $event->datep;
    $oldRule  = dolical_recurrence_normalize((string) $event->recurrule);
    $oldUntil = !empty($event->recurdateend) ? (int) $event->recurdateend : 0;
    $recurid  = (string) $event->recurid;
    $siblings = (!$isNew && $recurid !== '') ? dolical_series_occurrences($event) : [];
    $inSeries = !empty($siblings);

    $kind     = (string) ($data['recurrence'] ?? '');
    $newStart = (int) $data['start'];
    $newRule  = $kind === 'custom' ? $oldRule : ($kind !== '' ? dolical_recurrence_rule($kind, $newStart) : '');
    $newUntil = 0;
    if ($newRule !== '') {
        $newUntil = (int) ($data['recur_until'] ?? 0);
        if ($newUntil <= $newStart) {
            $newUntil = $oldUntil > $newStart ? $oldUntil : dolical_recurrence_default_until($kind, $newStart);
        }
    }
    $sameDay     = function (int $a, int $b): bool {
        return $a > 0 && $b > 0 && dolical_local_date($a)->format('Ymd') === dolical_local_date($b)->format('Ymd');
    };
    $scope = in_array($data['recur_scope'] ?? '', ['one', 'following', 'all'], true) ? $data['recur_scope'] : 'all';
    // One date moved to another day keeps the rule of its series : « every monday » is not « every tuesday » for it
    if ($inSeries && $scope === 'one' && $kind === dolical_recurrence_kind($oldRule, $oldStart) && ($newRule === '' || $sameDay($newUntil, $oldUntil))) {
        $newRule  = $oldRule;
        $newUntil = $oldUntil;
    }

    $ruleChanged = $newRule !== $oldRule || ($newRule !== '' && !$sameDay($newUntil, $oldUntil));
    $dayChanged  = !$isNew && !$sameDay($newStart, $oldStart);
    $setting     = isset($data['reminder']) && is_array($data['reminder']) ? dolical_reminder_clean($data['reminder']) : null;

    $result['ruleChanged'] = $ruleChanged;
    // A new rule cannot be for one date only
    if ($inSeries && $scope === 'one' && $ruleChanged) {
        $scope = 'following';
    }

    $id = dolical_save_event($event, $data, $user);
    if ($id <= 0) {
        $result['error'] = $event->error;
        return $result;
    }
    $result['id'] = $id;

    // Every occurrence saved or created gets the reminders of the form
    $touched = [$id];

    if (!$inSeries) {
        // A single event, or one that starts to repeat
        if ($newRule !== '') {
            $result['scope'] = 'all';
            dolical_set_series([$id], dolical_new_recurid($id), $newRule, $newUntil);
            $event->fetch($id);
            $following          = dolical_recurrence_following((int) $event->datep, (int) $event->datef, $newRule, $newUntil);
            $result['truncated'] = count($following) >= DOLICAL_RECURRENCE_MAX;
            $created            = dolical_create_occurrences($event, $following, $user);
            $result['created']  = count($created);
            $touched            = array_merge($touched, $created);
        }
    } elseif ($scope === 'one') {
        // Only this date : it stays in its series, an exception of it
        $result['scope'] = 'one';
    } else {
        $result['scope'] = $scope;
        $now             = dol_now();
        $earlier         = [];
        $later           = [];
        foreach ($siblings as $siblingId => $siblingStart) {
            if ($siblingId === $id) {
                continue;
            }
            if ($siblingStart > $oldStart || ($siblingStart === $oldStart && $siblingId > $id)) {
                $later[$siblingId] = $siblingStart;
            } else {
                $earlier[$siblingId] = $siblingStart;
            }
        }
        $targets  = $scope === 'all' ? $earlier + $later : $later;
        $rebuild  = $ruleChanged || $dayChanged;
        $seriesId = $recurid;

        // « This and the following » : the ones before keep the old series, ended the day before
        if ($scope === 'following' && !empty($earlier)) {
            $seriesId                 = dolical_new_recurid($id);
            $result['oldSeriesFirst'] = (int) array_key_first($earlier);
            dolical_set_series(array_keys($earlier), $recurid, $oldRule, dolical_local_date($oldStart)->modify('-1 day')->setTime(12, 0)->getTimestamp());
        }

        // The other occurrences take the changes of the form, each one on its own date and with its own progress
        $kept = [];
        foreach ($targets as $targetId => $targetStart) {
            $target = new ActionComm($db);
            if ($target->fetch($targetId) <= 0) {
                continue;
            }
            // Rebuilt from the edited one : the ones to come go, the ones already past stay as they were
            if ($rebuild && $targetStart >= $now) {
                if (dolical_delete_occurrence($target, $user, $id)) {
                    $result['removed']++;
                }
                continue;
            }
            $targetData            = $data;
            $targetData['percent'] = (int) $target->percentage;
            if ($rebuild) {
                // Another day or another rule : an occurrence already past keeps its hours
                $targetData['start']   = (int) $target->datep;
                $targetData['end']     = (int) ($target->datef ?: $target->datep);
                $targetData['fullday'] = (int) $target->fulldayevent;
            } else {
                [$targetData['start'], $targetData['end']] = dolical_wall_shift($targetStart, $newStart, (int) $data['end']);
            }
            if (dolical_save_event($target, $targetData, $user) > 0) {
                $result['updated']++;
                $kept[] = $targetId;
            }
        }

        $members = $scope === 'all' ? array_merge([$id], $kept) : array_merge([$id], array_values(array_intersect(array_keys($later), $kept)));
        if ($newRule === '') {
            // No longer repeating : the event stands alone, the dates already past of a series too
            dolical_set_series($members, null);
        } else {
            dolical_set_series($members, $seriesId, $newRule, $newUntil);
            if ($rebuild) {
                $event->fetch($id);
                $following           = dolical_recurrence_following((int) $event->datep, (int) $event->datef, $newRule, $newUntil);
                $result['truncated'] = count($following) >= DOLICAL_RECURRENCE_MAX;
                $created             = dolical_create_occurrences($event, $following, $user);
                $result['created']   = count($created);
                $touched             = array_merge($touched, $created);
            }
        }
        $touched = array_merge($touched, $kept);
    }

    foreach (array_unique($touched) as $touchedId) {
        $occurrence = new ActionComm($db);
        if ($occurrence->fetch($touchedId) <= 0) {
            continue;
        }
        if ($setting !== null) {
            dolical_save_reminders($occurrence, $setting, $user);
        } else {
            dolical_shift_reminders($occurrence);
        }
    }

    $event->fetch($id);

    return $result;
}

/**
 * Delete an event, or the part of its series asked, and tell the people invited by email : a cancellation of the
 * date or of the series, or the news of a series now ending sooner
 *
 * @param  ActionComm $event Event, resources loaded
 * @param  string     $scope 'one', 'following' or 'all'
 * @param  User       $user  Current user
 * @return array             ['success' => bool, 'deleted' => int, 'cancelled' => int, 'errors' => string[]]
 */
function dolical_delete_event_series(ActionComm $event, string $scope, User $user): array
{
    global $db;

    $result   = ['success' => false, 'deleted' => 0, 'cancelled' => 0, 'errors' => []];
    $siblings = dolical_series_occurrences($event);
    if (count($siblings) <= 1) {
        $scope = 'one';
    }
    $start = (int) $event->datep;

    if ($scope === 'one') {
        // The people invited by email have the event in their calendar : read them before it is gone
        $history  = dolical_invitation_history($event);
        $toCancel = dolical_invited_only(dolical_invitation_recipients($event), $history);
        $options  = count($siblings) > 1 ? ['recurrenceId' => $start] : [];
        // The traces of a date of a series belong to the series : they go to another date of it
        $heirId   = (int) (array_key_first(array_diff_key($siblings, [$event->id => 0])) ?? 0);
        $mail     = dolical_send_invitations($event, $user, $toCancel, 'cancel', $options);
        if (!dolical_delete_occurrence($event, $user, $heirId)) {
            $result['errors'][] = $event->error;
            return $result;
        }
        return ['success' => true, 'deleted' => 1, 'cancelled' => $mail['sent'], 'errors' => $mail['errors']];
    }

    $removed = [];
    foreach ($siblings as $siblingId => $siblingStart) {
        if ($scope === 'all' || $siblingStart > $start || ($siblingStart === $start && $siblingId >= $event->id)) {
            $removed[$siblingId] = $siblingStart;
        }
    }
    $remaining = array_diff_key($siblings, $removed);

    $first = new ActionComm($db);
    $first->fetch((int) array_key_first($siblings));
    $history  = dolical_invitation_history($first);
    $invited  = dolical_invited_only(dolical_invitation_recipients($first), $history);

    if (empty($remaining)) {
        // The whole series goes : one cancellation of the series for each person
        $mail = dolical_send_invitations($first, $user, $invited, 'cancel', ['series' => true]);
        $result['cancelled'] = $mail['sent'];
        $result['errors']    = $mail['errors'];
    }

    $heirId = (int) (array_key_first($remaining) ?? 0);
    foreach (array_keys($removed) as $removedId) {
        $occurrence = new ActionComm($db);
        if ($occurrence->fetch($removedId) > 0 && dolical_delete_occurrence($occurrence, $user, $heirId)) {
            $result['deleted']++;
        }
    }

    if (!empty($remaining)) {
        // The series now ends the day before : its people get the change, their calendar drops the rest
        dolical_set_series(array_keys($remaining), (string) $event->recurid, dolical_recurrence_normalize((string) $event->recurrule), dolical_local_date($start)->modify('-1 day')->setTime(12, 0)->getTimestamp());
        $heir = new ActionComm($db);
        if ($heir->fetch($heirId) > 0) {
            $mail = dolical_send_invitations($heir, $user, dolical_invited_only(dolical_invitation_recipients($heir), dolical_invitation_history($heir)), 'update', ['series' => true]);
            $result['errors'] = $mail['errors'];
        }
    }

    $result['success'] = $result['deleted'] > 0;

    return $result;
}
