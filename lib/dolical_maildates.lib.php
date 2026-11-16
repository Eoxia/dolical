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
 * \file    lib/dolical_maildates.lib.php
 * \ingroup dolical
 * \brief   Dates written in a mail, for the event planned from it : the mail read through the module Inbox, its last
 *          message only (the quoted ones are older), the dates found in French or in English with their hours.
 */

/**
 * Most dates offered for one mail
 */
const DOLICAL_MAIL_DATES_MAX = 6;

/**
 * Names of the days, French and English, to their ISO number
 */
const DOLICAL_MAIL_WEEKDAYS = [
    'lundi' => 1, 'mardi' => 2, 'mercredi' => 3, 'jeudi' => 4, 'vendredi' => 5, 'samedi' => 6, 'dimanche' => 7,
    'monday' => 1, 'tuesday' => 2, 'wednesday' => 3, 'thursday' => 4, 'friday' => 5, 'saturday' => 6, 'sunday' => 7,
];

/**
 * Names of the months and their short forms, French and English, to their number
 */
const DOLICAL_MAIL_MONTHS = [
    'janvier' => 1, 'janv' => 1, 'février' => 2, 'fevrier' => 2, 'févr' => 2, 'fevr' => 2, 'mars' => 3, 'avril' => 4,
    'avr' => 4, 'mai' => 5, 'juin' => 6, 'juillet' => 7, 'juil' => 7, 'août' => 8, 'aout' => 8, 'septembre' => 9,
    'sept' => 9, 'octobre' => 10, 'oct' => 10, 'novembre' => 11, 'nov' => 11, 'décembre' => 12, 'decembre' => 12,
    'déc' => 12, 'dec' => 12,
    'january' => 1, 'jan' => 1, 'february' => 2, 'feb' => 2, 'march' => 3, 'mar' => 3, 'april' => 4, 'apr' => 4,
    'may' => 5, 'june' => 6, 'jun' => 6, 'july' => 7, 'jul' => 7, 'august' => 8, 'aug' => 8, 'september' => 9,
    'sep' => 9, 'october' => 10, 'november' => 11, 'december' => 12,
];

/**
 * Dates of a mail of the module Inbox, read by its Message-ID with the rights of the user (Inbox journals the reading)
 *
 * @param  string $messageId Message-ID of the mail
 * @param  User   $user      User planning the event
 * @return array             Dates found, see dolical_find_dates()
 */
function dolical_mail_dates(string $messageId, User $user): array
{
    global $conf, $db;

    if ($messageId === '' || !isModEnabled('inbox') || !$user->hasRight('inbox', 'message', 'read')
        || !dol_include_once('/inbox/class/inbox.class.php') || !class_exists('Inbox') || !method_exists('Inbox', 'getMessage')) {
        return [];
    }

    // The mailbox of the mail : Inbox reads a mail by its mailbox and its Message-ID
    $sql  = 'SELECT a.email FROM ' . MAIN_DB_PREFIX . 'inbox_mail as m';
    $sql .= ' INNER JOIN ' . MAIN_DB_PREFIX . 'inbox_account as a ON a.rowid = m.fk_account';
    $sql .= " WHERE m.message_id = '" . $db->escape($messageId) . "' AND a.entity = " . ((int) $conf->entity);
    $sql .= $db->plimit(1);
    $resql = $db->query($sql);
    $obj   = $resql ? $db->fetch_object($resql) : null;
    if (!$obj || empty($obj->email)) {
        return [];
    }

    $inbox   = new Inbox($db);
    $message = $inbox->getMessage((string) $obj->email, $messageId, $user, 'dolical');
    if (!is_object($message) || (string) $message->bodyText === '') {
        return [];
    }

    $dates = dolical_find_dates(dolical_mail_last_message((string) $message->bodyText), (int) $message->date > 0 ? (int) $message->date : dol_now(), dolical_reference_timezone());

    // Planned today : a date already past is no meeting to plan (« demain » in a mail of last week)
    $now = dol_now();
    return array_values(array_filter($dates, function ($date) use ($now) {
        return $date['end'] >= $now;
    }));
}

/**
 * The text of the last message of a mail : the quoted messages under it (« Le … a écrit : », « De : … Envoyé : … »,
 * lines starting with « > ») and the signature are cut off
 *
 * @param  string $text Text body
 * @return string
 */
function dolical_mail_last_message(string $text): string
{
    $text = str_replace(["\r\n", "\r", "\xC2\xA0"], ["\n", "\n", ' '], $text);
    $cuts = [
        '/^\s*(?:le|on)\b[^\n]{0,200}(?:\n[^\n]{0,200})?\b(?:a écrit|a ecrit|wrote)\s*:/imu',
        '/^\s*-{2,}\s*(?:message d\'origine|original message|message transféré|message transfere|forwarded message)/imu',
        '/^\s*(?:de|from)\s*:[^\n]*\n\s*(?:envoyé|envoye|sent|date|à|a|to)\s*:/imu',
        '/^-- ?$/mu',
    ];
    foreach ($cuts as $pattern) {
        if (preg_match($pattern, $text, $match, PREG_OFFSET_CAPTURE) && $match[0][1] > 0) {
            $text = substr($text, 0, $match[0][1]);
        }
    }

    return trim(preg_replace('/^>.*$/mu', '', $text));
}

/**
 * Dates written in a text, as an event takes them : a day (« 18/11 », « mardi 18 novembre », « demain », « jeudi
 * prochain », « November 18th »…) and its hours when they follow it (« à 14h30 », « de 14h à 15h », « 2:30 pm »).
 * A day before the mail is not offered : it is the mail of before, a date of birth…
 *
 * @param  string $text      Text
 * @param  int    $reference Date of the mail : the day that « demain » and the year left out count from
 * @param  string $zone      Time zone the text is written in
 * @return array             [['start' => int, 'end' => int, 'hasTime' => bool, 'excerpt' => string]], in the order of the text
 */
function dolical_find_dates(string $text, int $reference, string $zone): array
{
    try {
        $tz = new DateTimeZone($zone);
    } catch (Exception $exception) {
        $tz = new DateTimeZone('UTC');
    }
    $today = (new DateTimeImmutable('@' . $reference))->setTimezone($tz)->setTime(0, 0);
    // One line, the same for the search and the excerpts : their offsets are the same
    $flat  = str_replace(["\r\n", "\r", "\n", "\t", "\xC2\xA0"], ' ', $text);
    $plain = mb_strtolower($flat, 'UTF-8');
    // Every other character than a letter of a name is one byte : the offsets of the patterns stay those of the text
    $weekdays = implode('|', array_keys(DOLICAL_MAIL_WEEKDAYS));
    $months   = implode('|', array_map(function ($name) {
        return preg_quote($name, '/');
    }, array_keys(DOLICAL_MAIL_MONTHS)));

    $patterns = [
        // 2026-11-18
        'iso'      => '/\b(\d{4})-(\d{1,2})-(\d{1,2})\b/u',
        // 18/11/2026, 18-11-26, 18.11.2026, 18/11
        'numeric'  => '/\b(\d{1,2})\/(\d{1,2})(?:\/(\d{4}|\d{2}))?\b|\b(\d{1,2})[.-](\d{1,2})[.-](\d{4}|\d{2})\b/u',
        // mardi 18 novembre 2026, le 1er décembre, 18 nov.
        'dayMonth' => '/\b(?:(?:' . $weekdays . ')\s+)?(\d{1,2})(?:er|st|nd|rd|th)?\s+(' . $months . ')\.?(?:\s+(\d{4}))?(?![a-zà-ÿ])/u',
        // November 18th, 2026
        'monthDay' => '/\b(?:(?:' . $weekdays . '),?\s+)?(' . $months . ')\.?\s+(\d{1,2})(?:st|nd|rd|th)?(?:,?\s+(\d{4}))?\b/u',
        // demain, après-demain, aujourd'hui, tomorrow, today
        'relative' => '/\b(après-demain|apres-demain|aujourd\'hui|aujourd’hui|demain|tomorrow|today)\b/u',
        // jeudi, jeudi prochain, next thursday : the next one, after the day of the mail
        'weekday'  => '/\b(?:next\s+)?(' . $weekdays . ')(?:\s+(prochain))?\b(?!\s+\d)/u',
    ];

    $found = [];
    $taken = [];
    foreach ($patterns as $kind => $pattern) {
        if (!preg_match_all($pattern, $plain, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
            continue;
        }
        foreach ($matches as $match) {
            $offset = $match[0][1];
            $length = strlen($match[0][0]);
            // A piece of text already read as a date (« mardi » of « mardi 18 novembre »)
            foreach ($taken as [$from, $to]) {
                if ($offset < $to && $offset + $length > $from) {
                    continue 2;
                }
            }
            $guessed = false;
            $day     = dolical_mail_date_day($kind, $match, $today, $guessed);
            // A day without its year further than six months is not a meeting (« 24/7 »), nor any day past two years
            if ($day === null || $day < $today || $day > $today->modify($guessed ? '+6 months' : '+2 years')) {
                continue;
            }
            $taken[] = [$offset, $offset + $length];
            $found[] = ['offset' => $offset, 'length' => $length, 'day' => $day, 'today' => $kind === 'relative' && $day == $today];
        }
    }
    usort($found, function ($a, $b) {
        return $a['offset'] - $b['offset'];
    });

    $duration = 60 * getDolGlobalInt('DOLICAL_DEFAULT_DURATION', 60);
    $results  = [];
    $seen     = [];
    foreach ($found as $date) {
        // Its hours : right after the day (« mardi 18 à 14h », « 18/11 de 9h à 10h30 ») or right before it (« 14h le 18/11 »)
        $after  = substr($plain, $date['offset'] + $date['length'], 40);
        $before = substr($plain, max(0, $date['offset'] - 16), min(16, $date['offset']));
        $hours  = dolical_mail_date_hours($after, true) ?? dolical_mail_date_hours($before, false);
        // « Aujourd'hui » alone is the day the mail was written, not a meeting
        if ($date['today'] && $hours === null) {
            continue;
        }

        $start = $date['day'];
        $end   = null;
        if ($hours !== null) {
            $start = $date['day']->setTime($hours[0], $hours[1]);
            if ($hours[2] !== null) {
                $end = $date['day']->setTime($hours[2], $hours[3]);
            }
        }
        $startTime = $start->getTimestamp();
        $endTime   = $end !== null && $end > $start ? $end->getTimestamp() : ($hours !== null ? $startTime + $duration : $start->modify('+1 day')->getTimestamp() - 1);
        $key       = $startTime . '-' . ($hours !== null ? 1 : 0);
        if (isset($seen[$key])) {
            continue;
        }
        $seen[$key] = true;

        $from      = max(0, $date['offset'] - 30);
        $excerpt   = trim(preg_replace('/\s+/', ' ', mb_strcut($flat, $from, $date['offset'] - $from + $date['length'] + 40, 'UTF-8')));
        $results[] = ['start' => $startTime, 'end' => $endTime, 'hasTime' => $hours !== null, 'excerpt' => ($from > 0 ? '…' : '') . $excerpt . '…'];
        if (count($results) >= DOLICAL_MAIL_DATES_MAX) {
            break;
        }
    }

    return $results;
}

/**
 * The day of a date found in a text
 *
 * @param  string            $kind  Pattern that found it
 * @param  array             $match Groups of the pattern, with their offsets
 * @param  DateTimeImmutable $today   Day of the mail, at midnight
 * @param  bool              $guessed Set to true when the year was not written
 * @return DateTimeImmutable|null     Midnight of the day, null when it is not a day
 */
function dolical_mail_date_day(string $kind, array $match, DateTimeImmutable $today, bool &$guessed = false): ?DateTimeImmutable
{
    $group = function (int $index) use ($match): string {
        return isset($match[$index]) && $match[$index][1] >= 0 ? (string) $match[$index][0] : '';
    };
    $year = 0;
    switch ($kind) {
        case 'iso':
            [$year, $month, $day] = [(int) $group(1), (int) $group(2), (int) $group(3)];
            break;
        case 'numeric':
            if ($group(1) !== '') {
                [$day, $month, $year] = [(int) $group(1), (int) $group(2), (int) $group(3)];
            } else {
                [$day, $month, $year] = [(int) $group(4), (int) $group(5), (int) $group(6)];
            }
            break;
        case 'dayMonth':
            [$day, $month, $year] = [(int) $group(1), DOLICAL_MAIL_MONTHS[$group(2)] ?? 0, (int) $group(3)];
            break;
        case 'monthDay':
            [$month, $day, $year] = [DOLICAL_MAIL_MONTHS[$group(1)] ?? 0, (int) $group(2), (int) $group(3)];
            break;
        case 'relative':
            $word = $group(1);
            if (in_array($word, ['après-demain', 'apres-demain'], true)) {
                return $today->modify('+2 days');
            }
            return in_array($word, ['demain', 'tomorrow'], true) ? $today->modify('+1 day') : $today;
        case 'weekday':
            $wanted = DOLICAL_MAIL_WEEKDAYS[$group(1)] ?? 0;
            if ($wanted === 0) {
                return null;
            }
            // The next one after the day of the mail : « jeudi » written a thursday is the one of the week after
            $gap = ($wanted - (int) $today->format('N') + 7) % 7;
            return $today->modify('+' . ($gap === 0 ? 7 : $gap) . ' days');
        default:
            return null;
    }

    if ($year > 0 && $year < 100) {
        $year += 2000;
    }
    $guessed = $year === 0;
    if ($guessed) {
        $year = (int) $today->format('Y');
    }
    if ($month < 1 || $month > 12 || $day < 1 || !checkdate($month, $day, $year)) {
        return null;
    }
    $date = $today->setDate($year, $month, $day);
    // Written without its year and already past : the one of the next year (« 5 janvier » written in december)
    if ($guessed && $date < $today && checkdate($month, $day, $year + 1)) {
        $date = $today->setDate($year + 1, $month, $day);
    }

    return $date;
}

/**
 * Hours written next to a day : « à 14h », « 14h30 », « 14:30 », « de 9h à 10h30 », « 9h-10h », « 2 pm », « 2:30pm »
 *
 * @param  string $text  The few characters after the day (or before it)
 * @param  bool   $after Whether the text follows the day : the hours open it, else they close it
 * @return array|null    [start hour, start minute, end hour or null, end minute or null], null when there is none
 */
function dolical_mail_date_hours(string $text, bool $after): ?array
{
    // « 2:30 pm » first : the form « 2:30 » would take it without its « pm »
    $time    = '(\d{1,2})(?::(\d{2}))?\s*(am|pm)\b|(\d{1,2})\s*(?:h|:)\s*(\d{2})?';
    $range   = '(?:\s*(?:-|–|à|a|au|to|until|jusqu\'à)\s*(?:' . $time . '))?';
    $pattern = $after
        ? '/^\s*[,.]?\s*(?:(?:à|a|vers|at|de|from|dès|des|entre|between|,)\s+)?(?:' . $time . ')' . $range . '/u'
        : '/(?:' . $time . ')' . $range . '\s*(?:,|le|the|on)?\s*$/u';
    if (!preg_match($pattern, $text, $match)) {
        return null;
    }
    $read = function (int $first) use ($match): ?array {
        if (isset($match[$first + 2]) && $match[$first + 2] !== '') {
            [$hour, $minute] = [(int) $match[$first], (int) ($match[$first + 1] ?? 0)];
            $hour = $hour % 12 + ($match[$first + 2] === 'pm' ? 12 : 0);
        } elseif (isset($match[$first + 3]) && $match[$first + 3] !== '') {
            [$hour, $minute] = [(int) $match[$first + 3], (int) ($match[$first + 4] ?? 0)];
        } else {
            return null;
        }
        return $hour <= 23 && $minute <= 59 ? [$hour, $minute] : null;
    };
    $start = $read(1);
    if ($start === null) {
        return null;
    }
    $end = $read(6);

    return [$start[0], $start[1], $end[0] ?? null, $end[1] ?? null];
}
