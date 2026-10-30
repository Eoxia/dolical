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
 * \file    lib/dolical_reminder.lib.php
 * \ingroup dolical
 * \brief   Reminders before an event, the native ones of llx_actioncomm_reminder : a browser notification shown by
 *          the pages of Dolibarr (lib_notification.js.php), an email sent by the scheduled job "SendEmailsReminders".
 */

require_once DOL_DOCUMENT_ROOT . '/comm/action/class/actioncommreminder.class.php';

/**
 * Units of the delay, as the native reminders store them
 */
const DOLICAL_REMINDER_UNITS = ['i' => 60, 'h' => 3600, 'd' => 86400, 'w' => 604800];

/**
 * Kinds of reminders the instance sends, set in the setup of the agenda
 *
 * @return array ['browser' => label, 'email' => label]
 */
function dolical_reminder_types(): array
{
    global $langs;

    $types = [];
    if (getDolGlobalString('AGENDA_REMINDER_BROWSER')) {
        $types['browser'] = $langs->trans('DoliCalReminderBrowser');
    }
    if (getDolGlobalString('AGENDA_REMINDER_EMAIL')) {
        $types['email'] = $langs->trans('DoliCalReminderEmail');
    }

    return $types;
}

/**
 * Reminder a new event starts with : on or off as set up, with the delay of the native agenda
 *
 * @param  string $typeCode Type of the event, some types may get one by default (AGENDA_DEFAULT_REMINDER_EVENT_TYPES)
 * @return array            ['enabled' => bool, 'offset' => int, 'unit' => string, 'types' => string[], 'everyone' => bool]
 */
function dolical_reminder_default(string $typeCode = ''): array
{
    $types   = array_keys(dolical_reminder_types());
    $enabled = getDolGlobalInt('DOLICAL_REMINDER_BY_DEFAULT', 1) > 0;
    $byType  = json_decode(getDolGlobalString('AGENDA_DEFAULT_REMINDER_EVENT_TYPES', '[]'), true);
    if (!$enabled && is_array($byType) && $typeCode !== '' && in_array($typeCode, $byType, true)) {
        $enabled = true;
    }
    $unit = getDolGlobalString('AGENDA_DEFAULT_REMINDER_OFFSET_UNIT', 'i');

    return [
        'enabled'  => $enabled && !empty($types),
        'offset'   => max(0, getDolGlobalInt('AGENDA_DEFAULT_REMINDER_OFFSET', 15)),
        'unit'     => isset(DOLICAL_REMINDER_UNITS[$unit]) ? $unit : 'i',
        // A notification of the browser first : the email is only for whom wants it
        'types'    => in_array('browser', $types, true) ? ['browser'] : array_slice($types, 0, 1),
        'everyone' => true,
    ];
}

/**
 * Reminders of a person for an event
 *
 * @param  ActionComm $event  Event
 * @param  int        $userId Person
 * @return array              ['enabled' => bool, 'offset', 'unit', 'types' => string[], 'sent' => bool]
 */
function dolical_get_reminders(ActionComm $event, int $userId): array
{
    global $db;

    $setting = ['enabled' => false, 'offset' => 0, 'unit' => 'i', 'types' => [], 'sent' => false];
    if (empty($event->id) || $userId <= 0) {
        return $setting;
    }

    $sql  = 'SELECT typeremind, offsetvalue, offsetunit, status FROM ' . MAIN_DB_PREFIX . 'actioncomm_reminder';
    $sql .= ' WHERE fk_actioncomm = ' . ((int) $event->id) . ' AND fk_user = ' . ((int) $userId);
    $sql .= ' ORDER BY rowid ASC';
    $resql = $db->query($sql);
    if (!$resql) {
        return $setting;
    }
    while ($obj = $db->fetch_object($resql)) {
        $setting['enabled'] = true;
        $setting['offset']  = (int) $obj->offsetvalue;
        $setting['unit']    = isset(DOLICAL_REMINDER_UNITS[$obj->offsetunit]) ? $obj->offsetunit : 'i';
        $setting['sent']    = $setting['sent'] || (int) $obj->status === ActionCommReminder::STATUS_DONE;
        if (!in_array($obj->typeremind, $setting['types'], true)) {
            $setting['types'][] = (string) $obj->typeremind;
        }
    }
    $db->free($resql);

    return $setting;
}

/**
 * Reminder of the form, checked : a known unit, a known kind, a delay of a year at most
 *
 * @param  array $values ['enabled', 'offset', 'unit', 'types', 'everyone']
 * @return array         Same keys
 */
function dolical_reminder_clean(array $values): array
{
    $available = array_keys(dolical_reminder_types());
    $types     = array_values(array_intersect((array) ($values['types'] ?? []), $available));
    $unit      = isset(DOLICAL_REMINDER_UNITS[$values['unit'] ?? '']) ? $values['unit'] : 'i';

    return [
        'enabled'  => !empty($values['enabled']) && !empty($types),
        'offset'   => min((int) (366 * 86400 / DOLICAL_REMINDER_UNITS[$unit]), max(0, (int) ($values['offset'] ?? 0))),
        'unit'     => $unit,
        'types'    => $types,
        'everyone' => !empty($values['everyone']),
    ];
}

/**
 * Set the reminders of an event : the ones not sent yet are replaced, a reminder already sent stays as it is
 *
 * @param  ActionComm $event   Event, resources loaded
 * @param  array      $setting From dolical_reminder_clean()
 * @param  User       $user    Current user
 * @return int                 Number of reminders written, < 0 on error
 */
function dolical_save_reminders(ActionComm $event, array $setting, User $user): int
{
    global $conf, $db;

    // Mine only, or the ones of everyone taking part, as the native card does
    $userIds = [(int) $user->id];
    if ($setting['everyone']) {
        $userIds = array_values(array_unique(array_merge($userIds, array_map('intval', array_keys($event->userassigned)), $event->userownerid > 0 ? [(int) $event->userownerid] : [])));
    }

    $sql  = 'DELETE FROM ' . MAIN_DB_PREFIX . 'actioncomm_reminder';
    $sql .= ' WHERE fk_actioncomm = ' . ((int) $event->id) . ' AND status = ' . ActionCommReminder::STATUS_TODO;
    $sql .= ' AND fk_user IN (' . $db->sanitize(implode(',', $userIds)) . ')';
    if (!$db->query($sql)) {
        return -1;
    }
    if (!$setting['enabled']) {
        return 0;
    }

    $dateRemind = (int) $event->datep - $setting['offset'] * DOLICAL_REMINDER_UNITS[$setting['unit']];
    // An event already gone needs no reminder, nor would the notification make sense
    if ((int) $event->datep < dol_now()) {
        return 0;
    }

    // A reminder already sent for the same delay is not sent twice
    $sent = [];
    $sql  = 'SELECT fk_user, typeremind FROM ' . MAIN_DB_PREFIX . 'actioncomm_reminder WHERE fk_actioncomm = ' . ((int) $event->id) . ' AND status <> ' . ActionCommReminder::STATUS_TODO;
    $resql = $db->query($sql);
    if ($resql) {
        while ($obj = $db->fetch_object($resql)) {
            $sent[$obj->fk_user . ':' . $obj->typeremind] = true;
        }
    }

    $written = 0;
    foreach ($userIds as $userId) {
        foreach ($setting['types'] as $type) {
            if (isset($sent[$userId . ':' . $type])) {
                continue;
            }
            $reminder                    = new ActionCommReminder($db);
            $reminder->entity            = (int) ($event->entity ?: $conf->entity);
            $reminder->fk_actioncomm     = (int) $event->id;
            $reminder->fk_user           = $userId;
            $reminder->typeremind        = $type;
            $reminder->offsetvalue       = $setting['offset'];
            $reminder->offsetunit        = $setting['unit'];
            $reminder->dateremind        = $dateRemind;
            $reminder->status            = ActionCommReminder::STATUS_TODO;
            $reminder->fk_email_template = $type === 'email' && getDolGlobalInt('AGENDA_DEFAULT_REMINDER_EMAIL_MODEL') > 0 ? getDolGlobalInt('AGENDA_DEFAULT_REMINDER_EMAIL_MODEL') : null;
            if ($reminder->create($user) <= 0) {
                dol_syslog(__FUNCTION__ . ' ' . $reminder->error, LOG_ERR);
                return -1;
            }
            $written++;
        }
    }

    return $written;
}

/**
 * Move the reminders not sent yet with their event : the native update leaves them at the old date
 *
 * @param  ActionComm $event Event, at its new date
 * @return void
 */
function dolical_shift_reminders(ActionComm $event): void
{
    global $db;

    $sql   = 'SELECT rowid, offsetvalue, offsetunit FROM ' . MAIN_DB_PREFIX . 'actioncomm_reminder';
    $sql  .= ' WHERE fk_actioncomm = ' . ((int) $event->id) . ' AND status = ' . ActionCommReminder::STATUS_TODO;
    $resql = $db->query($sql);
    if (!$resql) {
        return;
    }
    $rows = [];
    while ($obj = $db->fetch_object($resql)) {
        $rows[] = $obj;
    }
    $db->free($resql);

    foreach ($rows as $obj) {
        $unit = DOLICAL_REMINDER_UNITS[$obj->offsetunit] ?? 60;
        $db->query('UPDATE ' . MAIN_DB_PREFIX . "actioncomm_reminder SET dateremind = '" . $db->idate((int) $event->datep - (int) $obj->offsetvalue * $unit) . "' WHERE rowid = " . ((int) $obj->rowid));
    }
}

/**
 * A reminder in words : « 15 minutes avant · notification »
 *
 * @param  array $setting From dolical_get_reminders()
 * @return string         Empty without reminder
 */
function dolical_reminder_label(array $setting): string
{
    global $langs;

    if (empty($setting['enabled'])) {
        return '';
    }
    $units = ['i' => 'DoliCalReminderMinutes', 'h' => 'DoliCalReminderHours', 'd' => 'DoliCalReminderDays', 'w' => 'DoliCalReminderWeeks'];
    $when  = $setting['offset'] > 0 ? $langs->trans('DoliCalReminderBefore', $setting['offset'] . ' ' . dol_strtolower($langs->trans($units[$setting['unit']] ?? 'DoliCalReminderMinutes'))) : $langs->trans('DoliCalReminderAtStart');
    $types = array_intersect_key(dolical_reminder_types(), array_flip($setting['types']));

    return $when . (!empty($types) ? ' · ' . dol_strtolower(implode(', ', $types)) : '');
}
