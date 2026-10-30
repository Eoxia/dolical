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
 * \file    lib/dolical_recurrence.lib.php
 * \ingroup dolical
 * \brief   Recurring events, as the native agenda stores them : one llx_actioncomm row per occurrence, the
 *          occurrences of a series sharing recurid, with the rule in recurrule and the last day in recurdateend.
 *          The rule is written in the iCalendar syntax (FREQ=WEEKLY;BYDAY=MO), the native one is still read.
 */

require_once DOL_DOCUMENT_ROOT . '/includes/sabre/autoload.php';

/**
 * Most occurrences a series may hold : a daily event over a year
 */
const DOLICAL_RECURRENCE_MAX = 366;

/**
 * Days of the week in the iCalendar syntax, by the number of date('w')
 */
const DOLICAL_WEEKDAYS = ['SU', 'MO', 'TU', 'WE', 'TH', 'FR', 'SA'];

/**
 * A date in the time zone the series repeats in : the hour of the occurrences stays the same across the
 * changes of summer time
 *
 * @param  int               $timestamp Date
 * @return DateTimeImmutable
 */
function dolical_local_date(int $timestamp): DateTimeImmutable
{
    try {
        $zone = new DateTimeZone(dolical_reference_timezone());
    } catch (Exception $exception) {
        $zone = new DateTimeZone('UTC');
    }

    return (new DateTimeImmutable('@' . $timestamp))->setTimezone($zone);
}

/**
 * Rule of the native card (FREQ=WEEKLY_BYDAY1, FREQ=MONTHLY_BYMONTHDAY12...) in the iCalendar syntax
 *
 * @param  string $rule Stored rule
 * @return string       iCalendar rule without its end, empty when the event does not repeat
 */
function dolical_recurrence_normalize(string $rule): string
{
    $rule = strtoupper(trim($rule));
    if ($rule === '' || !preg_match('/FREQ=(DAILY|WEEKLY|MONTHLY|YEARLY)/', $rule, $freq)) {
        return '';
    }
    // Native syntax : the parameters are glued with underscores and carry no "="
    if (strpos($rule, '_BY') !== false) {
        if (preg_match('/_BYDAY(\d)/', $rule, $matches)) {
            return 'FREQ=WEEKLY;BYDAY=' . DOLICAL_WEEKDAYS[((int) $matches[1]) % 7];
        }
        if (preg_match('/_BYMONTHDAY(\d+)/', $rule, $matches)) {
            return 'FREQ=MONTHLY;BYMONTHDAY=' . ((int) $matches[1]);
        }

        return 'FREQ=' . $freq[1];
    }

    // The end is kept apart, in recurdateend
    $parts = array_filter(explode(';', $rule), function ($part) {
        return $part !== '' && !preg_match('/^(UNTIL|COUNT|WKST)=/', $part);
    });

    return implode(';', $parts);
}

/**
 * Number of the week of the month a date falls in, -1 for the last one : « the first monday », « the last friday »
 *
 * @param  DateTimeImmutable $date Date
 * @return int                     1 to 4, or -1
 */
function dolical_week_of_month(DateTimeImmutable $date): int
{
    $day = (int) $date->format('j');
    if ($day + 7 > (int) $date->format('t')) {
        return -1;
    }

    return (int) ceil($day / 7);
}

/**
 * Rule of a kind of repetition offered by the form, from the start of the event
 *
 * @param  string $kind  'daily', 'weekdays', 'weekly', 'biweekly', 'monthly_day', 'monthly_weekday' or 'yearly'
 * @param  int    $start Start of the event
 * @return string        iCalendar rule, empty for an unknown kind
 */
function dolical_recurrence_rule(string $kind, int $start): string
{
    $date = dolical_local_date($start);
    $day  = DOLICAL_WEEKDAYS[(int) $date->format('w')];

    switch ($kind) {
        case 'daily':
            return 'FREQ=DAILY';
        case 'weekdays':
            return 'FREQ=WEEKLY;BYDAY=MO,TU,WE,TH,FR';
        case 'weekly':
            return 'FREQ=WEEKLY;BYDAY=' . $day;
        case 'biweekly':
            return 'FREQ=WEEKLY;INTERVAL=2;BYDAY=' . $day;
        case 'monthly_day':
            return 'FREQ=MONTHLY;BYMONTHDAY=' . ((int) $date->format('j'));
        case 'monthly_weekday':
            return 'FREQ=MONTHLY;BYDAY=' . dolical_week_of_month($date) . $day;
        case 'yearly':
            return 'FREQ=YEARLY';
    }

    return '';
}

/**
 * Kind of repetition of a rule, as the form offers it, 'custom' when the form has no such choice
 *
 * @param  string $rule  iCalendar rule
 * @param  int    $start Start of the event
 * @return string        '' when the event does not repeat
 */
function dolical_recurrence_kind(string $rule, int $start): string
{
    $rule = dolical_recurrence_normalize($rule);
    if ($rule === '') {
        return '';
    }
    foreach (['daily', 'weekdays', 'weekly', 'biweekly', 'monthly_day', 'monthly_weekday', 'yearly'] as $kind) {
        if (dolical_recurrence_rule($kind, $start) === $rule) {
            return $kind;
        }
    }

    return 'custom';
}

/**
 * Name of a day of the week, as a sentence writes it : lower case in French
 *
 * @param  DateTimeImmutable $date Date
 * @param  string            $format strftime format of the part to write
 * @return string
 */
function dolical_recurrence_word(DateTimeImmutable $date, string $format): string
{
    global $langs;

    // dol_print_date() in GMT of a date already shifted to its zone writes its wall time
    $text = dol_print_date($date->getTimestamp() + $date->getOffset(), $format, 'gmt');

    return strpos((string) $langs->defaultlang, 'fr') === 0 ? dol_strtolower($text) : $text;
}

/**
 * Choices of repetition of the form, worded from the start of the event : « Toutes les semaines le lundi »
 *
 * @param  int   $start Start of the event
 * @return array        [kind => label], the first one being « does not repeat »
 */
function dolical_recurrence_choices(int $start): array
{
    global $langs;

    $date    = dolical_local_date($start);
    $dayName = dolical_recurrence_word($date, '%A');
    $week    = dolical_week_of_month($date);

    return [
        ''                => $langs->trans('DoliCalRepeatNone'),
        'daily'           => $langs->trans('DoliCalRepeatDaily'),
        'weekdays'        => $langs->trans('DoliCalRepeatWeekdays'),
        'weekly'          => $langs->trans('DoliCalRepeatWeekly', $dayName),
        'biweekly'        => $langs->trans('DoliCalRepeatBiweekly', $dayName),
        'monthly_day'     => $langs->trans('DoliCalRepeatMonthlyDay', (int) $date->format('j')),
        'monthly_weekday' => $langs->trans('DoliCalRepeatMonthlyWeekday', $langs->trans($week === -1 ? 'DoliCalNthLast' : 'DoliCalNth' . $week), $dayName),
        'yearly'          => $langs->trans('DoliCalRepeatYearly', ((int) $date->format('j')) . ' ' . dolical_recurrence_word($date, '%B')),
    ];
}

/**
 * The repetition of an event in words, its end included : « Toutes les semaines le lundi, jusqu'au 28/12/2026 »
 *
 * @param  string $rule  Stored rule
 * @param  int    $start Start of the event
 * @param  int    $until Last day of the series, 0 when unknown
 * @return string        Empty when the event does not repeat
 */
function dolical_recurrence_label(string $rule, int $start, int $until = 0): string
{
    global $langs;

    $kind = dolical_recurrence_kind($rule, $start);
    if ($kind === '') {
        return '';
    }
    $text = $kind === 'custom' ? $langs->trans('DoliCalRepeatCustom') : dolical_recurrence_choices($start)[$kind];
    if ($until > 0) {
        $text .= ', ' . $langs->trans('DoliCalRepeatUntil', dolical_print_date($until, 'day'));
    }

    return $text;
}

/**
 * Default end of a new series : six months later, five years for a yearly one
 *
 * @param  string $kind  Kind of repetition
 * @param  int    $start Start of the event
 * @return int           Last day, at noon
 */
function dolical_recurrence_default_until(string $kind, int $start): int
{
    $date = dolical_local_date($start)->setTime(12, 0);

    return $date->modify($kind === 'yearly' ? '+5 years' : '+6 months')->getTimestamp();
}

/**
 * Occurrences following an event in its series, up to the last day of the series
 *
 * The duration is taken on the wall clock : an event of 10:00 – 11:00 stays so on both sides of a change of
 * summer time, and an event of the whole day stays on its day.
 *
 * @param  int    $start Start of the event the series starts from
 * @param  int    $end   End of that event
 * @param  string $rule  iCalendar rule
 * @param  int    $until Last day of the series
 * @param  int    $max   Most occurrences returned
 * @return array         [[start, end]], the event itself excluded
 */
function dolical_recurrence_following(int $start, int $end, string $rule, int $until, int $max = DOLICAL_RECURRENCE_MAX): array
{
    $rule = dolical_recurrence_normalize($rule);
    if ($rule === '' || $until <= $start || $max <= 0) {
        return [];
    }

    $first    = dolical_local_date($start);
    $duration = $first->diff(dolical_local_date(max($start, $end)));
    $limit    = dolical_local_date($until)->setTime(23, 59, 59);

    $occurrences = [];
    try {
        $iterator = new \Sabre\VObject\Recur\RRuleIterator($rule, $first);
        while ($iterator->valid() && count($occurrences) < $max) {
            $current = $iterator->current();
            if ($current > $limit) {
                break;
            }
            if ($current->getTimestamp() > $start) {
                $occurrences[] = [$current->getTimestamp(), $current->add($duration)->getTimestamp()];
            }
            $iterator->next();
        }
    } catch (Exception $exception) {
        dol_syslog(__FUNCTION__ . ' ' . $rule . ' ' . $exception->getMessage(), LOG_WARNING);
        return [];
    }

    return $occurrences;
}

/**
 * The occurrences of the series of an event, in their order
 *
 * @param  ActionComm $event Event
 * @return array             [id => start], empty when the event is not part of a series
 */
function dolical_series_occurrences(ActionComm $event): array
{
    global $db;

    if (empty($event->recurid)) {
        return [];
    }

    $sql  = 'SELECT id, datep FROM ' . MAIN_DB_PREFIX . 'actioncomm';
    $sql .= " WHERE recurid = '" . $db->escape($event->recurid) . "'";
    $sql .= ' AND entity = ' . ((int) $event->entity);
    // A series of the native agenda is named after the second it was made : its author tells it apart from another
    // one made in the same second
    if (!preg_match('/^ev\d+$/', (string) $event->recurid)) {
        $sql .= ' AND fk_user_author = ' . ((int) $event->authorid);
    }
    $sql .= ' ORDER BY datep ASC, id ASC';

    $occurrences = [];
    $resql       = $db->query($sql);
    if ($resql) {
        while ($obj = $db->fetch_object($resql)) {
            $occurrences[(int) $obj->id] = (int) $db->jdate($obj->datep);
        }
        $db->free($resql);
    }

    return $occurrences;
}

/**
 * Write the series fields of events : ActionComm::update() leaves them out
 *
 * @param  int[]       $ids    Events
 * @param  string|null $recurid Series, null to take the events out of any series
 * @param  string      $rule   iCalendar rule
 * @param  int         $until  Last day of the series
 * @return bool
 */
function dolical_set_series(array $ids, ?string $recurid, string $rule = '', int $until = 0): bool
{
    global $db;

    $ids = array_values(array_filter(array_map('intval', $ids)));
    if (empty($ids)) {
        return true;
    }

    $sql  = 'UPDATE ' . MAIN_DB_PREFIX . 'actioncomm SET';
    if ($recurid === null) {
        $sql .= ' recurid = NULL, recurrule = NULL, recurdateend = NULL';
    } else {
        $sql .= " recurid = '" . $db->escape($recurid) . "', recurrule = '" . $db->escape($rule) . "', recurdateend = " . ($until > 0 ? "'" . $db->idate($until) . "'" : 'NULL');
    }
    $sql .= ' WHERE id IN (' . $db->sanitize(implode(',', $ids)) . ')';

    return (bool) $db->query($sql);
}

/**
 * Id of a new series, from the event it starts with : unique as the ids of the events are, and the series keeps
 * the identifier the calendars of the guests know that event by (see dolical_event_uid())
 *
 * @param  int    $firstId Event the series starts with
 * @return string
 */
function dolical_new_recurid(int $firstId): string
{
    return 'ev' . $firstId;
}

/**
 * Create the following occurrences of an event, copies of it : same people, answers, contacts and documents,
 * not its attachments nor its report
 *
 * @param  ActionComm $model       First event of the series, saved
 * @param  array      $occurrences [[start, end]] from dolical_recurrence_following()
 * @param  User       $user        Current user
 * @return int[]                   Ids created
 */
function dolical_create_occurrences(ActionComm $model, array $occurrences, User $user): array
{
    global $db;

    $links = array_filter(dolical_get_event_links($model), function ($link) {
        return !$link['primary'];
    });

    $ids = [];
    foreach ($occurrences as $occurrence) {
        $copy = new ActionComm($db);
        if ($copy->fetch($model->id) <= 0) {
            break;
        }
        $copy->fetch_optionals();
        $copy->id     = 0;
        $copy->ref    = '';
        // The mapping of an event created from a phone belongs to that event only
        $copy->ref_ext = '';
        $copy->datep   = (int) $occurrence[0];
        $copy->datef   = (int) $occurrence[1];
        unset($copy->array_options['options_dolical_report']);
        // create() renames the native link on the way in : it must get the element code
        if (!empty($copy->elementtype)) {
            $copy->elementtype = dolical_element_from_actioncomm((string) $copy->elementtype);
        }

        $id = $copy->create($user);
        if ($id <= 0) {
            dol_syslog(__FUNCTION__ . ' ' . $copy->error, LOG_ERR);
            break;
        }
        foreach ($links as $link) {
            $copy->add_object_linked($link['element'], $link['id']);
        }
        // create() writes the contacts without their answer : an answer given to the series holds for each date
        foreach ($model->socpeopleassigned as $contactId => $contact) {
            if (!empty($contact['answer_status'])) {
                $db->query('UPDATE ' . MAIN_DB_PREFIX . 'actioncomm_resources SET answer_status = ' . ((int) $contact['answer_status']) . ' WHERE fk_actioncomm = ' . ((int) $id) . " AND element_type = 'socpeople' AND fk_element = " . ((int) $contactId));
            }
        }
        $ids[] = (int) $id;
    }

    return $ids;
}
