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
 * \file    lib/dolical_booking.lib.php
 * \ingroup dolical
 * \brief   Online booking : a public page where a customer picks a free slot in the agenda of a person of the company.
 *
 * Nothing new is stored. The free slots are read from the native events of the person, his approved leaves and the
 * public holidays ; the appointment is a native event (type « Meeting ») owned by the person, the customer a native
 * contact, found by his email or created. The event carries ref_ext DOLICAL_BOOKING_REF : the customer may cancel it
 * from the signed link of his confirmation mail, which removes it and frees the slot.
 */

require_once DOL_DOCUMENT_ROOT . '/core/lib/date.lib.php';
require_once __DIR__ . '/dolical_invitation.lib.php';
require_once __DIR__ . '/dolical_reminder.lib.php';

/**
 * External reference of the events booked online
 */
const DOLICAL_BOOKING_REF = 'dolical-booking';

/**
 * Opening hours offered when none are set up
 */
const DOLICAL_BOOKING_DEFAULT_HOURS = '09:00-12:00 14:00-18:00';

/**
 * Whether the online booking is open : switched on, with somebody to book
 *
 * @return bool
 */
function dolical_booking_enabled(): bool
{
    return getDolGlobalInt('DOLICAL_BOOKING') > 0 && !empty(dolical_booking_user_ids());
}

/**
 * Texts of the public page written in the setup, with their longest length : the title of the page, its presentation
 * (HTML of the editor of Dolibarr) and the title above the slots
 */
const DOLICAL_BOOKING_TEXTS = ['DOLICAL_BOOKING_TITLE' => 255, 'DOLICAL_BOOKING_INTRO' => 10000, 'DOLICAL_BOOKING_FORM_TITLE' => 255];

/**
 * Characters kept as numeric entities in the setup : all beyond Latin-1 (emojis, typographic signs), that a database in
 * latin1 would turn into « ? ». The mask keeps every bit of the code point : 0x10FFFF would drop the 0x10000 of an emoji
 */
const DOLICAL_BOOKING_ENTITY_MAP = [0x100, 0x10FFFF, 0, 0x1FFFFF];

/**
 * A text of the public page, as plain text : the one the person wrote for his page, else the common one of the setup
 *
 * @param  string $constName Key of DOLICAL_BOOKING_TEXTS
 * @param  int    $userId    Person whose page it is, 0 for the common text only
 * @return string            Empty when not set
 */
function dolical_booking_text(string $constName, int $userId = 0): string
{
    $own = $userId > 0 ? (dolical_booking_person_texts($userId)[$constName] ?? '') : '';

    return $own !== '' ? $own : trim(mb_decode_numericentity(getDolGlobalString($constName), DOLICAL_BOOKING_ENTITY_MAP, 'UTF-8'));
}

/**
 * Texts a person wrote for his own page : parameters of the user, under the names of the common ones, in the entity
 * of the page (the one his link opens, and the one he writes them in)
 *
 * @param  int   $userId Person
 * @return array         [key of DOLICAL_BOOKING_TEXTS => plain text], only the ones written
 */
function dolical_booking_person_texts(int $userId): array
{
    global $conf, $db;

    static $cache = [];
    if (isset($cache[$userId])) {
        return $cache[$userId];
    }

    $sql  = 'SELECT param, value FROM ' . MAIN_DB_PREFIX . 'user_param';
    $sql .= ' WHERE fk_user = ' . $userId . ' AND entity = ' . ((int) $conf->entity);
    $sql .= " AND param IN ('" . implode("', '", array_keys(DOLICAL_BOOKING_TEXTS)) . "')";

    $texts = [];
    $resql = $db->query($sql);
    while ($resql && ($obj = $db->fetch_object($resql))) {
        $text = trim(mb_decode_numericentity((string) $obj->value, DOLICAL_BOOKING_ENTITY_MAP, 'UTF-8'));
        if ($text !== '') {
            $texts[$obj->param] = $text;
        }
    }

    return $cache[$userId] = $texts;
}

/**
 * Save the texts of the page of a person ; an empty text is removed, the common one shows again
 *
 * @param  User  $person Person whose page it is
 * @param  array $texts  [key of DOLICAL_BOOKING_TEXTS => text typed, without HTML]
 * @return bool
 */
function dolical_booking_save_person_texts(User $person, array $texts): bool
{
    global $conf, $db;

    require_once DOL_DOCUMENT_ROOT . '/core/lib/functions2.lib.php';

    $params = [];
    foreach (array_keys(DOLICAL_BOOKING_TEXTS) as $constName) {
        $params[$constName] = dolical_booking_store_text($constName, (string) ($texts[$constName] ?? ''));
    }

    return dol_set_user_param($db, $conf, $person, $params, (int) $conf->entity) > 0;
}

/**
 * Video call link a person gives to the appointments booked on his page : a parameter of the user, in the entity of
 * the page, as his texts
 *
 * @param  int    $userId Person
 * @return string         Empty when he gave none
 */
function dolical_booking_person_visio(int $userId): string
{
    global $conf, $db;

    $sql   = 'SELECT value FROM ' . MAIN_DB_PREFIX . 'user_param';
    $sql  .= ' WHERE fk_user = ' . $userId . ' AND entity = ' . ((int) $conf->entity) . " AND param = 'DOLICAL_BOOKING_VISIO'";
    $resql = $db->query($sql);
    $obj   = $resql ? $db->fetch_object($resql) : null;

    return $obj ? dolical_visio_clean((string) $obj->value) : '';
}

/**
 * Save the video call link of the page of a person ; an empty one is removed
 *
 * @param  User   $person Person whose page it is
 * @param  string $url    Link, cleaned by dolical_visio_clean()
 * @return bool
 */
function dolical_booking_save_person_visio(User $person, string $url): bool
{
    global $conf, $db;

    require_once DOL_DOCUMENT_ROOT . '/core/lib/functions2.lib.php';

    return dol_set_user_param($db, $conf, $person, ['DOLICAL_BOOKING_VISIO' => $url], (int) $conf->entity) > 0;
}

/**
 * A text of the public page as it is stored : plain text, its lines kept, cut to its length, its emojis written as
 * numeric entities
 *
 * @param  string $constName Key of DOLICAL_BOOKING_TEXTS
 * @param  string $text      Text typed, without HTML
 * @return string
 */
function dolical_booking_store_text(string $constName, string $text): string
{
    $text = trim(str_replace("\r", '', $text));
    if ($constName === 'DOLICAL_BOOKING_INTRO') {
        // An editor emptied still sends an empty paragraph : nothing is written then, the common text shows
        $visible = preg_replace('/[\s\x{00A0}]+/u', '', dol_html_entity_decode(dol_string_nohtmltag($text, 0), ENT_QUOTES | ENT_HTML5));
        if ($visible === '' && stripos($text, '<img') === false) {
            $text = '';
        }
    } else {
        // A title stays on one line
        $text = preg_replace('/\s+/u', ' ', $text);
    }

    return mb_encode_numericentity(dol_trunc($text, DOLICAL_BOOKING_TEXTS[$constName] ?? 255, 'right', 'UTF-8', 1), DOLICAL_BOOKING_ENTITY_MAP, 'UTF-8');
}

/**
 * Editor of the presentation of the page : the WYSIWYG editor of Dolibarr when its module is on, a plain field else.
 * A presentation written before as plain text keeps its lines in it
 *
 * @param  string $htmlName Name of the field
 * @param  string $text     Presentation, HTML or plain text
 * @return string           HTML of the editor
 */
function dolical_booking_intro_editor(string $htmlName, string $text): string
{
    require_once DOL_DOCUMENT_ROOT . '/core/class/doleditor.class.php';

    $content = dol_textishtml($text) ? $text : dol_nl2br(dol_escape_htmltag($text, 0, 1));
    // No file browser : a picture of the server would not show on the public page
    $editor  = new DolEditor($htmlName, $content, '100%', 220, 'dolibarr_notes', '', false, 0, true, ROWS_6, '90%');

    return (string) $editor->Create(1);
}

/**
 * Whether the page without a person lists the people offered. Off for now : each person shares his own link (hidden
 * setting DOLICAL_BOOKING_HUB to show the list again)
 *
 * @return bool
 */
function dolical_booking_hub_enabled(): bool
{
    return getDolGlobalInt('DOLICAL_BOOKING_HUB') > 0;
}

/**
 * People offered on the public page : the active users chosen in the setup
 *
 * @return int[]
 */
function dolical_booking_user_ids(): array
{
    static $ids = null;
    if ($ids === null) {
        $ids = dolical_filter_active_users(explode(',', getDolGlobalString('DOLICAL_BOOKING_USERS')));
    }

    return $ids;
}

/**
 * Users that may be offered, for the setup : the active internal users of the entity
 *
 * @return array [id => name]
 */
function dolical_booking_candidates(): array
{
    global $db;

    $sql  = 'SELECT rowid, firstname, lastname, login FROM ' . MAIN_DB_PREFIX . 'user';
    $sql .= ' WHERE statut = 1 AND (fk_soc IS NULL OR fk_soc = 0) AND entity IN (' . getEntity('user') . ')';
    $sql .= ' ORDER BY firstname, lastname';

    $users = [];
    $resql = $db->query($sql);
    while ($resql && ($obj = $db->fetch_object($resql))) {
        $name                       = trim(dolGetFirstLastname($obj->firstname, $obj->lastname));
        $users[(int) $obj->rowid] = $name !== '' ? $name : $obj->login;
    }

    return $users;
}

/**
 * The people of the public page, with what the page shows of them : name, job, initials and color
 *
 * @return array [id => ['id', 'name', 'job', 'initials', 'color']], in the order of their names
 */
function dolical_booking_people(): array
{
    global $db;

    $ids = dolical_booking_user_ids();
    if (empty($ids)) {
        return [];
    }

    $sql  = 'SELECT rowid, firstname, lastname, login, job, color FROM ' . MAIN_DB_PREFIX . 'user';
    $sql .= ' WHERE rowid IN (' . $db->sanitize(implode(',', $ids)) . ')';
    $sql .= ' ORDER BY firstname, lastname';

    $people = [];
    $resql  = $db->query($sql);
    while ($resql && ($obj = $db->fetch_object($resql))) {
        $name = trim(dolGetFirstLastname($obj->firstname, $obj->lastname));
        if ($name === '') {
            $name = $obj->login;
        }
        $people[(int) $obj->rowid] = [
            'id'       => (int) $obj->rowid,
            'name'     => $name,
            'job'      => (string) $obj->job,
            'initials' => dolical_initials($name),
            'color'    => dolical_base_color((int) $obj->rowid, (string) $obj->color),
        ];
    }

    return $people;
}

/**
 * Default length of an appointment, in minutes : the length of a new slot of the weekly template
 *
 * @return int
 */
function dolical_booking_duration(): int
{
    return min(480, max(5, getDolGlobalInt('DOLICAL_BOOKING_DURATION', 30)));
}

/**
 * Weekly template of the slots, as the one of the appointments of Nextcloud : the slots offered each day of the week,
 * each with its start and its length, placed in a week in the setup. Stored as JSON in DOLICAL_BOOKING_SLOTS
 * ({"2": [[630, 60], [690, 60]], ...}, start and length in minutes). A setup saved before it had the same hours for the
 * days ticked (DOLICAL_BOOKING_DAYS, DOLICAL_BOOKING_HOURS), cut in appointments of the default length : read the same
 * way until the template is saved
 *
 * @return array [ISO day 1 monday … 7 sunday => [[start minute, length in minutes]]], every day given, sorted
 */
function dolical_booking_template(): array
{
    static $template = null;
    if ($template !== null) {
        return $template;
    }

    $stored = json_decode(getDolGlobalString('DOLICAL_BOOKING_SLOTS'), true);
    if (!is_array($stored)) {
        $stored = [];
        $length = dolical_booking_duration();
        $ranges = dolical_booking_hours(getDolGlobalString('DOLICAL_BOOKING_HOURS', DOLICAL_BOOKING_DEFAULT_HOURS));
        foreach (array_map('intval', explode(',', getDolGlobalString('DOLICAL_BOOKING_DAYS', '1,2,3,4,5'))) as $isoDay) {
            foreach ($ranges as [$from, $to]) {
                for ($minute = $from; $minute + $length <= $to; $minute += $length) {
                    $stored[$isoDay][] = [$minute, $length];
                }
            }
        }
    }

    return $template = dolical_booking_clean_template($stored);
}

/**
 * A weekly template as it was posted or stored, made safe : slots within their day, from 5 minutes to 8 hours, sorted,
 * a slot overlapping the previous one left out
 *
 * @param  mixed $data [ISO day => [[start minute, length]]]
 * @return array       [ISO day => [[start minute, length]]], every day given
 */
function dolical_booking_clean_template($data): array
{
    $template = array_fill_keys(range(1, 7), []);
    foreach (is_array($data) ? $data : [] as $isoDay => $slots) {
        $isoDay = (int) $isoDay;
        if ($isoDay < 1 || $isoDay > 7 || !is_array($slots)) {
            continue;
        }
        $day = [];
        foreach ($slots as $slot) {
            if (!is_array($slot) || !isset($slot[0], $slot[1]) || !is_numeric($slot[0]) || !is_numeric($slot[1])) {
                continue;
            }
            $start  = (int) $slot[0];
            $length = (int) $slot[1];
            if ($start >= 0 && $length >= 5 && $length <= 480 && $start + $length <= 1440) {
                $day[] = [$start, $length];
            }
        }
        usort($day, function ($a, $b) {
            return $a[0] <=> $b[0];
        });
        $end = -1;
        foreach ($day as $slot) {
            if ($slot[0] >= $end) {
                $template[$isoDay][] = $slot;
                $end = $slot[0] + $slot[1];
            }
        }
    }

    return $template;
}

/**
 * The weekly template as it is stored
 *
 * @param  array  $template From dolical_booking_clean_template()
 * @return string           JSON, the days without slot left out
 */
function dolical_booking_store_template(array $template): string
{
    return (string) json_encode((object) array_filter($template));
}

/**
 * Days of the week open to the bookings, ISO numbers : 1 monday … 7 sunday
 *
 * @return int[]
 */
function dolical_booking_days(): array
{
    return array_keys(array_filter(dolical_booking_template()));
}

/**
 * Lengths of the slots of the template, in minutes
 *
 * @return int[]
 */
function dolical_booking_template_lengths(): array
{
    $lengths = [];
    foreach (dolical_booking_template() as $slots) {
        foreach ($slots as $slot) {
            $lengths[$slot[1]] = true;
        }
    }

    return array_keys($lengths);
}

/**
 * Length of the appointment that starts at a slot : the one of its slot in the weekly template
 *
 * @param  int $start Start of the slot
 * @return int        Minutes
 */
function dolical_booking_slot_length(int $start): int
{
    $local  = (new DateTimeImmutable('@' . $start))->setTimezone(new DateTimeZone(dolical_booking_timezone()));
    $minute = (int) $local->format('G') * 60 + (int) $local->format('i');
    foreach (dolical_booking_template()[(int) $local->format('N')] as [$from, $length]) {
        if ($from === $minute) {
            return $length;
        }
    }

    return dolical_booking_duration();
}

/**
 * Opening hours read from a text : « 09:00-12:00 14:00-18:00 », « 9h-12h, 14h-18h30 ». Only the setup saved before the
 * weekly template still has them
 *
 * @param  string $text Hours as stored
 * @return array        [[start minute, end minute]], sorted, without overlap
 */
function dolical_booking_hours(string $text): array
{
    preg_match_all('/(\d{1,2})(?:\s*[:hH]\s*(\d{2})?)?\s*[-–à]\s*(\d{1,2})(?:\s*[:hH]\s*(\d{2})?)?/u', $text, $matches, PREG_SET_ORDER);
    $ranges = [];
    foreach ($matches as $match) {
        $start = (int) $match[1] * 60 + (int) ($match[2] ?? 0);
        $end   = (int) $match[3] * 60 + (int) ($match[4] ?? 0);
        if ($start < $end && $end <= 1440) {
            $ranges[] = [$start, $end];
        }
    }
    usort($ranges, function ($a, $b) {
        return $a[0] <=> $b[0];
    });

    // Ranges that overlap become one : a slot is never offered twice
    $merged = [];
    foreach ($ranges as $range) {
        $last = count($merged) - 1;
        if ($last >= 0 && $range[0] <= $merged[$last][1]) {
            $merged[$last][1] = max($merged[$last][1], $range[1]);
        } else {
            $merged[] = $range;
        }
    }

    return $merged;
}

/**
 * Time zone of the opening hours and of the slots : the one of the setup, whoever looks at the page
 *
 * @return string
 */
function dolical_booking_timezone(): string
{
    $zone = getDolGlobalString('DOLICAL_TIMEZONE', 'Europe/Paris');

    return in_array($zone, timezone_identifiers_list(), true) ? $zone : date_default_timezone_get();
}

/**
 * How far ahead an appointment may be booked, in weeks from today (DOLICAL_BOOKING_HORIZON_WEEKS)
 *
 * @return int
 */
function dolical_booking_horizon_weeks(): int
{
    return min(52, max(1, getDolGlobalInt('DOLICAL_BOOKING_HORIZON_WEEKS', 4)));
}

/**
 * First and last moment a slot may start : some hours from now (DOLICAL_BOOKING_NOTICE_HOURS), until some weeks from
 * today, that last day included
 *
 * @return int[] [first, last]
 */
function dolical_booking_window(): array
{
    $now     = dol_now();
    $zone    = new DateTimeZone(dolical_booking_timezone());
    $today   = (new DateTimeImmutable('@' . $now))->setTimezone($zone)->setTime(0, 0);
    $horizon = dolical_booking_horizon_weeks() * 7;

    return [$now + max(0, getDolGlobalInt('DOLICAL_BOOKING_NOTICE_HOURS', 2)) * 3600, $today->modify('+' . ($horizon + 1) . ' days')->getTimestamp()];
}

/**
 * Reminders the customer may get before his appointment : delay before the start in seconds => key of translation
 */
const DOLICAL_BOOKING_REMINDER_DELAYS = [
    604800 => 'DoliCalBookingRemindWeek',
    172800 => 'DoliCalBookingRemindTwoDays',
    86400  => 'DoliCalBookingRemindDay',
    7200   => 'DoliCalBookingRemindTwoHours',
    3600   => 'DoliCalBookingRemindHour',
];

/**
 * Reminders chosen in the setup (DOLICAL_BOOKING_REMINDERS), the shortest delay first
 *
 * @return int[] Delays before the start, in seconds
 */
function dolical_booking_reminder_delays(): array
{
    $delays = array_values(array_intersect(array_keys(DOLICAL_BOOKING_REMINDER_DELAYS), array_map('intval', explode(',', getDolGlobalString('DOLICAL_BOOKING_REMINDERS')))));
    sort($delays);

    return $delays;
}

/**
 * Settings of the page written as plain text in the setup (several lines, emojis kept), with their longest length
 */
const DOLICAL_BOOKING_SETTING_TEXTS = [
    'DOLICAL_BOOKING_EMAIL'          => 128,
    'DOLICAL_BOOKING_LOCATION'       => 128,
    'DOLICAL_BOOKING_PHONE'          => 100,
    'DOLICAL_BOOKING_CONFIRM_TEXT'   => 2000,
    'DOLICAL_BOOKING_ICS_TEXT'       => 2000,
    'DOLICAL_BOOKING_REMINDER_TEXT'  => 2000,
    'DOLICAL_BOOKING_CONSENT'        => 1000,
    'DOLICAL_BOOKING_BLOCKED'        => 5000,
    'DOLICAL_BOOKING_EVENT_LABEL'    => 128,
    'DOLICAL_BOOKING_VISIO'          => 1024,
    'DOLICAL_BOOKING_VISIO_SERVER'   => 255,
    'DOLICAL_BOOKING_REDIRECT_URL'   => 1024,
];

/**
 * A plain text setting as it is stored : its lines kept, cut to its length, its emojis written as numeric entities
 *
 * @param  string $constName Key of DOLICAL_BOOKING_SETTING_TEXTS
 * @param  string $text      Text typed, without HTML
 * @return string
 */
function dolical_booking_store_setting(string $constName, string $text): string
{
    $text = trim(str_replace("\r", '', $text));

    return mb_encode_numericentity(dol_trunc($text, DOLICAL_BOOKING_SETTING_TEXTS[$constName] ?? 255, 'right', 'UTF-8', 1), DOLICAL_BOOKING_ENTITY_MAP, 'UTF-8');
}

/**
 * Address of the organizer of the appointments booked online : the one the customers answer to and the organizer of
 * their calendar files (DOLICAL_BOOKING_EMAIL), the address of the person booked when none is set
 *
 * @param  User   $owner Person booked
 * @return string
 */
function dolical_booking_reply_to(User $owner): string
{
    $email = dolical_booking_text('DOLICAL_BOOKING_EMAIL');

    return isValidEmail($email) ? $email : (string) $owner->email;
}

/**
 * The person booked as the customer sees him in a calendar file : his name, the address of the organizer
 *
 * @param  User $owner Person booked
 * @return User
 */
function dolical_booking_organizer(User $owner): User
{
    $organizer        = clone $owner;
    $organizer->email = dolical_booking_reply_to($owner);

    return $organizer;
}

/**
 * How the form asks for the phone number : 'optional', 'required' or 'hidden' (DOLICAL_BOOKING_PHONE_FIELD)
 *
 * @return string
 */
function dolical_booking_phone_field(): string
{
    $mode = getDolGlobalString('DOLICAL_BOOKING_PHONE_FIELD', 'optional');

    return in_array($mode, ['optional', 'required', 'hidden'], true) ? $mode : 'optional';
}

/**
 * Whether an address may not book online : an address of the list of the setup (DOLICAL_BOOKING_BLOCKED), or of a
 * domain of it (« @domain.com », « *@domain.com » or « domain.com »)
 *
 * @param  string $email Address typed
 * @return bool
 */
function dolical_booking_blocked(string $email): bool
{
    $email  = dol_strtolower(trim($email));
    $domain = substr((string) strrchr($email, '@'), 1);
    foreach (preg_split('/[\s,;]+/', dol_strtolower(dolical_booking_text('DOLICAL_BOOKING_BLOCKED'))) as $entry) {
        if ($entry === '') {
            continue;
        }
        if ($entry === $email) {
            return true;
        }
        $isDomain = strpos($entry, '@') === false || strpos($entry, '@') === 0 || strpos($entry, '*@') === 0;
        if ($isDomain && $domain !== '' && ltrim($entry, '*@') === $domain) {
            return true;
        }
    }

    return false;
}

/**
 * Video call link of a new appointment : a room of its own when the setup asks for one (DOLICAL_BOOKING_VISIO_ROOMS,
 * on the server DOLICAL_BOOKING_VISIO_SERVER, Jitsi by default), else the link of the person, else the common link
 * of the setup (DOLICAL_BOOKING_VISIO)
 *
 * @param  User   $owner Person booked
 * @param  int    $start Start of the appointment
 * @return string        Empty when there is none
 */
function dolical_booking_visio_for(User $owner, int $start): string
{
    global $mysoc;

    if (getDolGlobalInt('DOLICAL_BOOKING_VISIO_ROOMS')) {
        $server = dolical_clean_url(dolical_booking_text('DOLICAL_BOOKING_VISIO_SERVER') ?: 'https://meet.jit.si/');
        if ($server !== '') {
            // Named after the page and the date, with a random end : a room nobody guesses, without the name of the
            // customer in an address seen by the server
            $title = dolical_booking_text('DOLICAL_BOOKING_TITLE', (int) $owner->id) ?: (string) ($mysoc->name ?? 'DoliCal');
            $name  = preg_replace('/[^A-Za-z0-9]+/', '', ucwords(dol_string_unaccent(dol_html_entity_decode($title, ENT_QUOTES | ENT_HTML5))));
            $room  = dol_trunc($name !== '' ? $name : 'DoliCal', 40, 'right', 'UTF-8', 1) . '-' . dolical_print_date($start, '%Y%m%d-%H%M', dolical_booking_timezone()) . '-' . bin2hex(random_bytes(4));

            return rtrim($server, '/') . '/' . $room;
        }
    }

    $own = dolical_booking_person_visio((int) $owner->id);

    return $own !== '' ? $own : dolical_clean_url(dolical_booking_text('DOLICAL_BOOKING_VISIO'));
}

/**
 * Title of an appointment booked online in the agenda of the person : the model of the setup
 * (DOLICAL_BOOKING_EVENT_LABEL, with __NAME__, __COMPANY__, __EMAIL__, __PHONE__, __TITLE__), « Appointment with »
 * the customer and his company when none is set
 *
 * @param  array  $visitor ['name', 'email', 'phone', 'company']
 * @param  User   $owner   Person booked
 * @return string
 */
function dolical_booking_event_label(array $visitor, User $owner): string
{
    global $langs;

    $model = dolical_booking_text('DOLICAL_BOOKING_EVENT_LABEL');
    if ($model === '') {
        $label = $langs->transnoentities('DoliCalBookingEventLabel', $visitor['name'] . ($visitor['company'] !== '' ? ' (' . $visitor['company'] . ')' : ''));
    } else {
        $label = str_replace(
            ['__NAME__', '__COMPANY__', '__EMAIL__', '__PHONE__', '__TITLE__'],
            [$visitor['name'], $visitor['company'], $visitor['email'], $visitor['phone'], dolical_booking_text('DOLICAL_BOOKING_TITLE', (int) $owner->id)],
            $model
        );
        // A value left empty leaves no empty brackets nor dashes behind
        $label = trim(preg_replace(['/\(\s*\)|\[\s*\]/', '/\s+([-–:,])(?=\s*$)/', '/\s{2,}/'], ['', '', ' '], $label), " \t-–:,");
    }

    return dol_trunc($label !== '' ? $label : $visitor['name'], 128, 'right', 'UTF-8', 1);
}

/**
 * Page the customer goes to once his appointment is booked (DOLICAL_BOOKING_REDIRECT_URL) : empty for the page of his
 * appointment
 *
 * @return string
 */
function dolical_booking_redirect_url(): string
{
    return dolical_clean_url(dolical_booking_text('DOLICAL_BOOKING_REDIRECT_URL'));
}

/**
 * Monday of a week, at midnight in the time zone of the bookings
 *
 * @param  int               $offset Weeks after the one of the first slot that may be booked
 * @return DateTimeImmutable
 */
function dolical_booking_week_start(int $offset): DateTimeImmutable
{
    $first = (new DateTimeImmutable('@' . dolical_booking_window()[0]))->setTimezone(new DateTimeZone(dolical_booking_timezone()))->setTime(0, 0);
    $first = $first->modify('-' . ((int) $first->format('N') - 1) . ' days');

    return $offset > 0 ? $first->modify('+' . $offset . ' weeks') : $first;
}

/**
 * Number of weeks the page may show, the one of the first slot included
 *
 * @return int
 */
function dolical_booking_week_count(): int
{
    $last = dolical_booking_window()[1];
    for ($count = 1; $count < 60; $count++) {
        if (dolical_booking_week_start($count)->getTimestamp() >= $last) {
            break;
        }
    }

    return $count;
}

/**
 * Times a person is taken over a period : his events, as the conflicts of the calendar read them. The automatic
 * events and the ones he declined leave him available, and so do the events marked free that last a day or more (all
 * day, or a task spread over weeks). A shorter event takes the slot even marked free : the modules that create
 * meetings often leave the mark to its default, free
 *
 * @param  int   $userId Person
 * @param  int   $from   Start of the period
 * @param  int   $to     End of the period
 * @return array         [[start, end]]
 */
function dolical_booking_busy(int $userId, int $from, int $to): array
{
    global $db;

    $taken = 'SELECT r.fk_actioncomm FROM ' . MAIN_DB_PREFIX . "actioncomm_resources as r WHERE r.fk_actioncomm = a.id AND r.element_type = 'user' AND r.fk_element = " . $userId;

    $sql  = 'SELECT a.datep, a.datep2, a.fulldayevent, a.transparency FROM ' . MAIN_DB_PREFIX . 'actioncomm as a';
    $sql .= ' LEFT JOIN ' . MAIN_DB_PREFIX . 'c_actioncomm as ca ON ca.id = a.fk_action';
    $sql .= ' WHERE a.entity IN (' . getEntity('agenda') . ')';
    $sql .= " AND a.datep < '" . $db->idate($to) . "' AND COALESCE(a.datep2, a.datep) >= '" . $db->idate($from) . "'";
    $sql .= " AND a.datep >= '" . $db->idate($from - dolical_longest_event()) . "'";
    $sql .= " AND (ca.type IS NULL OR ca.type <> 'systemauto')";
    // Assigned without having declined, or owner without a line of his own saying he declined
    $sql .= ' AND (EXISTS (' . $taken . ' AND COALESCE(r.answer_status, 0) <> -1)';
    $sql .= ' OR (a.fk_user_action = ' . $userId . ' AND NOT EXISTS (' . $taken . ' AND r.answer_status = -1)))';

    $busy  = [];
    $resql = $db->query($sql);
    if (!$resql) {
        dol_syslog(__FUNCTION__ . ' ' . $db->lasterror(), LOG_ERR);
        // Better no slot than a slot already taken
        return [[$from, $to]];
    }
    while ($obj = $db->fetch_object($resql)) {
        $start = (int) $db->jdate($obj->datep);
        $end   = $obj->datep2 ? (int) $db->jdate($obj->datep2) : $start;
        if (empty($obj->transparency) && (!empty($obj->fulldayevent) || $end - $start >= 86400)) {
            continue;
        }
        // An event without length takes its minute
        $busy[] = [$start, max($end, $start + 60)];
    }
    $db->free($resql);

    return $busy;
}

/**
 * Agendas whose events take a person of the online booking too, as the agendas checked for conflicts by the
 * appointments of Nextcloud : the ones of the setup take everybody (DOLICAL_BOOKING_CONFLICT_USERS), the ones of the
 * page of a person take him (parameter of the user of the same name)
 *
 * @param  int   $userId Person, 0 for the agendas of the setup only
 * @return int[]         Active users
 */
function dolical_booking_conflict_user_ids(int $userId = 0): array
{
    $ids = array_map('intval', explode(',', getDolGlobalString('DOLICAL_BOOKING_CONFLICT_USERS')));
    if ($userId > 0) {
        $ids = array_merge($ids, dolical_booking_person_conflict_ids($userId));
    }
    $ids = array_values(array_unique(array_filter($ids)));

    return empty($ids) ? [] : array_map('intval', dolical_filter_active_users($ids));
}

/**
 * Agendas a person chose on his page : their events take his slots too
 *
 * @param  int   $userId Person
 * @return int[]         Users, as saved
 */
function dolical_booking_person_conflict_ids(int $userId): array
{
    global $conf, $db;

    $sql   = 'SELECT value FROM ' . MAIN_DB_PREFIX . 'user_param';
    $sql  .= ' WHERE fk_user = ' . $userId . ' AND entity = ' . ((int) $conf->entity) . " AND param = 'DOLICAL_BOOKING_CONFLICT_USERS'";
    $resql = $db->query($sql);
    $obj   = $resql ? $db->fetch_object($resql) : null;

    return $obj ? array_values(array_filter(array_map('intval', explode(',', (string) $obj->value)))) : [];
}

/**
 * Save the agendas that take the slots of a person ; none removes the parameter
 *
 * @param  User  $person Person whose page it is
 * @param  int[] $ids    Users, active, the person left out
 * @return bool
 */
function dolical_booking_save_person_conflicts(User $person, array $ids): bool
{
    global $conf, $db;

    require_once DOL_DOCUMENT_ROOT . '/core/lib/functions2.lib.php';
    $ids = array_values(array_diff(array_map('intval', $ids), [(int) $person->id]));

    return dol_set_user_param($db, $conf, $person, ['DOLICAL_BOOKING_CONFLICT_USERS' => implode(',', $ids)], (int) $conf->entity) > 0;
}

/**
 * Days a person is away over a period : public holidays of the country of the company, and his approved leaves when
 * the Leave module is on
 *
 * @param  int               $userId Person
 * @param  DateTimeImmutable $from   First day, at midnight in the time zone of the bookings
 * @param  int               $days   Number of days
 * @return array                     ['Ymd' => true]
 */
function dolical_booking_closed_days(int $userId, DateTimeImmutable $from, int $days): array
{
    global $db;

    $closed = [];
    for ($i = 0; $i < $days; $i++) {
        $day = $from->modify('+' . $i . ' days');
        // The days of the dictionary of the public holidays, without counting the week-ends as such
        $gmt     = gmmktime(0, 0, 0, (int) $day->format('n'), (int) $day->format('j'), (int) $day->format('Y'));
        $holiday = num_public_holiday($gmt, $gmt, '', 1, 0, 0, 0, 0);
        if (is_numeric($holiday) && $holiday > 0) {
            $closed[$day->format('Ymd')] = true;
        }
    }

    if (isModEnabled('holiday')) {
        $last  = $from->modify('+' . max(0, $days - 1) . ' days');
        $sql   = 'SELECT date_debut, date_fin FROM ' . MAIN_DB_PREFIX . 'holiday';
        $sql  .= ' WHERE fk_user = ' . $userId . ' AND statut = 3 AND entity IN (' . getEntity('holiday') . ')';
        $sql  .= " AND date_debut <= '" . $db->escape($last->format('Y-m-d')) . "' AND date_fin >= '" . $db->escape($from->format('Y-m-d')) . "'";
        $resql = $db->query($sql);
        while ($resql && ($obj = $db->fetch_object($resql))) {
            // A half day away is a day away : nobody books the morning of an afternoon off by mistake
            $day = new DateTimeImmutable($obj->date_debut, $from->getTimezone());
            $end = new DateTimeImmutable($obj->date_fin, $from->getTimezone());
            for ($guard = 0; $day <= $end && $guard < 400; $guard++) {
                $closed[$day->format('Ymd')] = true;
                $day = $day->modify('+1 day');
            }
        }
    }

    return $closed;
}

/**
 * Free time asked around an appointment, in seconds : before it (DOLICAL_BOOKING_BUFFER_BEFORE) and after it
 * (DOLICAL_BOOKING_BUFFER_AFTER), in minutes in the setup. A slot is free when nothing else is in the agenda then
 *
 * @return int[] [before, after]
 */
function dolical_booking_buffers(): array
{
    return [min(240, max(0, getDolGlobalInt('DOLICAL_BOOKING_BUFFER_BEFORE'))) * 60, min(240, max(0, getDolGlobalInt('DOLICAL_BOOKING_BUFFER_AFTER'))) * 60];
}

/**
 * Free slots of a person, day by day : the slots of the weekly template, those already taken left out
 *
 * @param  int               $userId Person
 * @param  DateTimeImmutable $from   First day, at midnight in the time zone of the bookings
 * @param  int               $days   Number of days
 * @return array                     ['Ymd' => ['date' => midnight, 'open' => bool, 'slots' => [start timestamps]]]
 */
function dolical_booking_slots(int $userId, DateTimeImmutable $from, int $days): array
{
    $template          = dolical_booking_template();
    [$before, $after]  = dolical_booking_buffers();
    [$first, $last]    = dolical_booking_window();
    $closed            = dolical_booking_closed_days($userId, $from, $days);
    $busy              = dolical_booking_busy($userId, $from->getTimestamp() - $after, $from->modify('+' . $days . ' days')->getTimestamp() + $before);
    // The agendas that take everybody (the agenda of a team, of a room) and the ones of his page : their events take
    // this person too
    foreach (dolical_booking_conflict_user_ids($userId) as $conflictUserId) {
        if ($conflictUserId !== $userId) {
            $busy = array_merge($busy, dolical_booking_busy($conflictUserId, $from->getTimestamp() - $after, $from->modify('+' . $days . ' days')->getTimestamp() + $before));
        }
    }

    $result = [];
    for ($i = 0; $i < $days; $i++) {
        $day      = $from->modify('+' . $i . ' days');
        $key      = $day->format('Ymd');
        // The slots of that day of the week : a day without any is closed
        $daySlots = $template[(int) $day->format('N')] ?? [];
        $open     = !empty($daySlots) && !isset($closed[$key]);

        $slots = [];
        foreach ($open ? $daySlots : [] as [$minute, $length]) {
            // The wall clock of the day : the change of time does not shift the hours
            $start = $day->setTime(intdiv($minute, 60), $minute % 60)->getTimestamp();
            $end   = $start + $length * 60;
            if ($start < $first || $start >= $last) {
                continue;
            }
            foreach ($busy as [$busyStart, $busyEnd]) {
                if ($busyStart < $end + $after && $busyEnd > $start - $before) {
                    continue 2;
                }
            }
            $slots[] = $start;
        }
        $result[$key] = ['date' => $day->getTimestamp(), 'open' => $open, 'slots' => $slots];
    }

    return $result;
}

/**
 * Whether a slot is still free : one of the slots the page offers for its day
 *
 * @param  int  $userId Person
 * @param  int  $start  Start of the slot
 * @return bool
 */
function dolical_booking_is_free(int $userId, int $start): bool
{
    $day   = (new DateTimeImmutable('@' . $start))->setTimezone(new DateTimeZone(dolical_booking_timezone()))->setTime(0, 0);
    $slots = dolical_booking_slots($userId, $day, 1);

    return in_array($start, $slots[$day->format('Ymd')]['slots'] ?? [], true);
}

/**
 * A date in the time zone of the bookings, in words : « mardi 17 novembre 2026 »
 *
 * @param  int    $timestamp Date
 * @return string
 */
function dolical_booking_day(int $timestamp): string
{
    global $langs;

    $zone = dolical_booking_timezone();
    $text = dolical_print_date($timestamp, '%A', $zone) . ' ' . ((int) dolical_print_date($timestamp, '%d', $zone)) . ' ' . dolical_print_date($timestamp, '%B %Y', $zone);

    return strpos((string) $langs->defaultlang, 'fr') === 0 ? dol_strtolower($text) : $text;
}

/**
 * Hour of a slot in the time zone of the bookings : « 09:30 »
 *
 * @param  int    $timestamp Date
 * @return string
 */
function dolical_booking_hour(int $timestamp): string
{
    return dolical_print_date($timestamp, '%H:%M', dolical_booking_timezone());
}

/**
 * When an appointment takes place, in words : « mardi 17 novembre 2026, 09:30 – 10:00 »
 *
 * @param  int    $start Start
 * @param  int    $end   End
 * @return string
 */
function dolical_booking_when(int $start, int $end): string
{
    return dolical_booking_day($start) . ', ' . dolical_booking_hour($start) . ' – ' . dolical_booking_hour($end);
}

/**
 * Signature of the link a customer manages his appointment with : one contact for one event of one entity
 *
 * @param  int    $entity    Entity of the event
 * @param  int    $eventId   Event
 * @param  int    $contactId Contact
 * @return string
 */
function dolical_booking_signature(int $entity, int $eventId, int $contactId): string
{
    return substr(hash_hmac('sha256', 'dolical-booking|' . $entity . '|' . $eventId . '|' . $contactId, dolical_invitation_secret()), 0, 40);
}

/**
 * Key of the public page of a person : signed with the secret of the instance, as the invitation links. The id of the
 * user never shows in the address, and the page of a colleague cannot be guessed from one's own
 *
 * @param  int    $userId Person
 * @return string
 */
function dolical_booking_page_key(int $userId): string
{
    global $conf;

    return substr(hash_hmac('sha256', 'dolical-booking-page|' . ((int) $conf->entity) . '|' . $userId, dolical_invitation_secret()), 0, 20);
}

/**
 * Person whose public page a key opens : one of the people offered, compared in constant time
 *
 * @param  string $key Key of the address
 * @return int         Id of the user, 0 when the key opens no page
 */
function dolical_booking_user_from_key(string $key): int
{
    if (!preg_match('/^[0-9a-f]{20}$/', $key)) {
        return 0;
    }
    foreach (dolical_booking_user_ids() as $userId) {
        if (hash_equals(dolical_booking_page_key($userId), $key)) {
            return $userId;
        }
    }

    return 0;
}

/**
 * Address of the public page, for a person or for all of them
 *
 * @param  int    $userId Person, 0 for the choice of the person
 * @param  int    $mode   Mode of dol_buildpath() : 1 relative, 3 absolute
 * @return string
 */
function dolical_booking_public_url(int $userId = 0, int $mode = 3): string
{
    global $conf;

    $params = ['entity' => (int) $conf->entity];
    if ($userId > 0) {
        $params['p'] = dolical_booking_page_key($userId);
    }

    return dol_buildpath('/dolical/public/booking.php', $mode) . '?' . http_build_query($params);
}

/**
 * Short link of the public page of a person, made by the short links of Saturne when that module is enabled : the
 * same one each time, created the first time. DoliCal does not need Saturne, the long link stays without it
 *
 * @param  int    $userId Person
 * @param  User   $user   User asking, author of the short link when it is created
 * @return string         Short URL, empty without Saturne or on error
 */
function dolical_booking_short_url(int $userId, User $user): string
{
    global $db;

    if (!isModEnabled('saturne') || !file_exists(dol_buildpath('/saturne/class/saturneredirection.class.php'))) {
        return '';
    }
    dol_include_once('/saturne/class/saturneredirection.class.php');
    // A Saturne older than its short links has the class without this method
    if (!class_exists('SaturneRedirection') || !method_exists('SaturneRedirection', 'getShortUrl')) {
        return '';
    }

    try {
        $redirection = new SaturneRedirection($db);
        return (string) $redirection->getShortUrl(dolical_booking_public_url($userId), $user);
    } catch (Throwable $exception) {
        dol_syslog(__FUNCTION__ . ' ' . $exception->getMessage(), LOG_WARNING);
        return '';
    }
}

/**
 * Link a customer manages his appointment with : the page of the appointment, or its iCalendar file
 *
 * @param  ActionComm $event     Event
 * @param  int        $contactId Contact
 * @param  array      $more      More parameters, ['ics' => 1]...
 * @return string                Absolute URL
 */
function dolical_booking_manage_url(ActionComm $event, int $contactId, array $more = []): string
{
    global $conf;

    $entity = (int) (!empty($event->entity) ? $event->entity : $conf->entity);
    $params = ['b' => (int) $event->id, 'c' => $contactId, 'entity' => $entity, 's' => dolical_booking_signature($entity, (int) $event->id, $contactId)];

    return dol_buildpath('/dolical/public/booking.php', 3) . '?' . http_build_query(array_merge($params, $more));
}

/**
 * Proof that the form was given by the page, for this person and this slot, a few seconds ago at least : the robots
 * that post at once, or a form kept for hours, are turned away
 *
 * @param  int    $userId Person
 * @param  int    $start  Slot
 * @param  int    $issued When the form was given, now when 0
 * @return string
 */
function dolical_booking_form_token(int $userId, int $start, int $issued = 0): string
{
    global $conf;

    $issued = $issued > 0 ? $issued : dol_now();

    return $issued . '.' . substr(hash_hmac('sha256', 'dolical-booking-form|' . ((int) $conf->entity) . '|' . $userId . '|' . $start . '|' . $issued, dolical_invitation_secret()), 0, 32);
}

/**
 * Whether a form proof is right and its age reasonable
 *
 * @param  string $token  From dolical_booking_form_token()
 * @param  int    $userId Person
 * @param  int    $start  Slot
 * @return bool
 */
function dolical_booking_check_form_token(string $token, int $userId, int $start): bool
{
    if (!preg_match('/^(\d+)\.[0-9a-f]{32}$/', $token, $matches)) {
        return false;
    }
    $issued = (int) $matches[1];
    $age    = dol_now() - $issued;

    return hash_equals(dolical_booking_form_token($userId, $start, $issued), $token) && $age >= 2 && $age <= 7200;
}

/**
 * Whether the event is an appointment booked online
 *
 * @param  ActionComm $event Event
 * @return bool
 */
function dolical_booking_is_booking(ActionComm $event): bool
{
    return (string) $event->ref_ext === DOLICAL_BOOKING_REF;
}

/**
 * Appointments booked online by an address and still to come : a person does not fill an agenda alone
 *
 * @param  string $email Address
 * @return int[]         [event id => owner id]
 */
function dolical_booking_upcoming_for_email(string $email): array
{
    global $db;

    $sql  = 'SELECT a.id, a.fk_user_action FROM ' . MAIN_DB_PREFIX . 'actioncomm as a';
    $sql .= ' INNER JOIN ' . MAIN_DB_PREFIX . "actioncomm_resources as r ON r.fk_actioncomm = a.id AND r.element_type = 'socpeople'";
    $sql .= ' INNER JOIN ' . MAIN_DB_PREFIX . 'socpeople as sp ON sp.rowid = r.fk_element';
    $sql .= " WHERE a.ref_ext = '" . $db->escape(DOLICAL_BOOKING_REF) . "' AND a.entity IN (" . getEntity('agenda') . ')';
    $sql .= " AND a.datep > '" . $db->idate(dol_now()) . "' AND sp.email = '" . $db->escape($email) . "'";

    $events = [];
    $resql  = $db->query($sql);
    while ($resql && ($obj = $db->fetch_object($resql))) {
        $events[(int) $obj->id] = (int) $obj->fk_user_action;
    }

    return $events;
}

/**
 * Whether a visitor may book once more : a few appointments to come per address (DOLICAL_BOOKING_MAX_PER_EMAIL), a few
 * bookings an hour from one address IP (DOLICAL_BOOKING_MAX_PER_IP). 0 lifts a limit
 *
 * @param  string $email Address typed
 * @return string        Empty when allowed, else the key of translation of the reason
 */
function dolical_booking_limit_reached(string $email): string
{
    $perEmail = getDolGlobalInt('DOLICAL_BOOKING_MAX_PER_EMAIL', 3);
    if ($perEmail > 0 && count(dolical_booking_upcoming_for_email($email)) >= $perEmail) {
        return 'DoliCalBookingTooManyForEmail';
    }
    $perIp = getDolGlobalInt('DOLICAL_BOOKING_MAX_PER_IP', 5);
    if ($perIp > 0 && count(dolical_booking_recent_from_ip()) >= $perIp) {
        return 'DoliCalBookingTooManyForIp';
    }

    return '';
}

/**
 * File keeping the recent bookings of an address IP : a few bytes in the temporary directory of the module
 *
 * @return string
 */
function dolical_booking_throttle_file(): string
{
    global $conf;

    $dir = DOL_DATA_ROOT . '/dolical/temp/booking';
    dol_mkdir($dir);

    return $dir . '/' . hash('sha256', ($conf->file->instance_unique_id ?? '') . '|' . getUserRemoteIP()) . '.json';
}

/**
 * Bookings made from the address IP of the visitor in the last hour
 *
 * @return int[] Timestamps
 */
function dolical_booking_recent_from_ip(): array
{
    $file  = dolical_booking_throttle_file();
    $times = is_file($file) ? json_decode((string) @file_get_contents($file), true) : [];
    $since = dol_now() - 3600;

    return array_values(array_filter(is_array($times) ? array_map('intval', $times) : [], function ($time) use ($since) {
        return $time > $since;
    }));
}

/**
 * Count a booking on the address IP of the visitor
 *
 * @return void
 */
function dolical_booking_record_ip(): void
{
    $times   = dolical_booking_recent_from_ip();
    $times[] = dol_now();
    @file_put_contents(dolical_booking_throttle_file(), json_encode(array_slice($times, -50)), LOCK_EX);
}

/**
 * Contact of the customer : the active contact of his address, else a new contact, attached to the only third party
 * of the domain of his address when there is one
 *
 * @param  array  $visitor ['name', 'email', 'phone', 'company']
 * @param  User   $owner   Person booked, author of what is created
 * @param  string $error   Why the contact could not be made, filled
 * @return array           ['id' => contact id, 'socid' => third party id], id 0 on error
 */
function dolical_booking_contact(array $visitor, User $owner, string &$error): array
{
    global $db, $langs;

    $sql   = 'SELECT rowid, fk_soc FROM ' . MAIN_DB_PREFIX . 'socpeople';
    $sql  .= " WHERE email = '" . $db->escape($visitor['email']) . "' AND statut = 1 AND entity IN (" . getEntity('contact') . ')';
    $sql  .= ' ORDER BY rowid ASC' . $db->plimit(1);
    $resql = $db->query($sql);
    if ($resql && ($obj = $db->fetch_object($resql))) {
        return ['id' => (int) $obj->rowid, 'socid' => (int) $obj->fk_soc];
    }

    // The company of a professional address, never the one of a provider of everyone or of the instance itself
    require_once __DIR__ . '/dolical_caldav.lib.php';
    $domain = dol_strtolower(substr((string) strrchr($visitor['email'], '@'), 1));
    $socid  = $domain !== '' && !isset(dolical_caldav_internal_domains()[$domain]) ? dolical_caldav_find_company_by_domain($domain) : 0;

    $words              = preg_split('/\s+/', trim($visitor['name']), 2);
    $contact            = new Contact($db);
    $contact->firstname = count($words) > 1 ? $words[0] : '';
    $contact->lastname  = count($words) > 1 ? $words[1] : $words[0];
    $contact->email     = $visitor['email'];
    $contact->phone_pro = $visitor['phone'];
    $contact->socid     = $socid;
    $contact->statut    = 1;
    $contact->priv      = 0;
    $contact->note_private = $langs->transnoentities('DoliCalBookingContactNote') . ($visitor['company'] !== '' ? "\n" . $langs->transnoentities('Company') . ' : ' . $visitor['company'] : '');
    $contactId = $contact->create($owner);
    if ($contactId <= 0) {
        $error = $contact->error ?: implode(', ', $contact->errors);
        return ['id' => 0, 'socid' => 0];
    }

    return ['id' => (int) $contactId, 'socid' => $socid];
}

/**
 * Book a slot : the native event in the agenda of the person, the customer as its contact, the reminder of the person,
 * then the confirmation to the customer and the news to the person
 *
 * @param  User   $owner   Person booked
 * @param  int    $start   Slot
 * @param  array  $visitor ['name', 'email', 'phone', 'company', 'message']
 * @param  string $error   Why the booking failed, filled : a key of translation
 * @return array           ['event' => ActionComm, 'contactId' => int], empty on error
 */
function dolical_booking_create(User $owner, int $start, array $visitor, string &$error): array
{
    global $conf, $db, $langs;

    // Two visitors on the same slot at the same moment : the second one waits, then finds it taken
    $lockFile = DOL_DATA_ROOT . '/dolical/temp/booking/lock-' . ((int) $owner->id);
    dol_mkdir(dirname($lockFile));
    $lock = @fopen($lockFile, 'c');
    if ($lock) {
        flock($lock, LOCK_EX);
    }

    try {
        // The same form sent twice (a double click, a reload) : the appointment already made
        foreach (dolical_booking_upcoming_for_email($visitor['email']) as $eventId => $ownerId) {
            $existing = new ActionComm($db);
            if ($ownerId === (int) $owner->id && $existing->fetch($eventId) > 0 && (int) $existing->datep === $start) {
                return ['event' => $existing, 'contactId' => (int) array_key_first($existing->socpeopleassigned)];
            }
        }
        if (!dolical_booking_is_free((int) $owner->id, $start)) {
            $error = 'DoliCalBookingSlotTaken';
            return [];
        }

        // Written in the language of the person booked : the event is his
        $savedLangs = $langs;
        $langs      = dolical_booking_langs($owner);

        $contactError = '';
        $contact      = dolical_booking_contact($visitor, $owner, $contactError);
        if ($contact['id'] <= 0) {
            $langs = $savedLangs;
            dol_syslog(__FUNCTION__ . ' contact: ' . $contactError, LOG_ERR);
            $error = 'DoliCalBookingFailed';
            return [];
        }

        $note = [$langs->transnoentities('DoliCalBookingNoteHeader', dolical_booking_day(dol_now()) . ', ' . dolical_booking_hour(dol_now()))];
        $note[] = $langs->transnoentities('Email') . ' : ' . $visitor['email'];
        if ($visitor['phone'] !== '') {
            $note[] = $langs->transnoentities('Phone') . ' : ' . $visitor['phone'];
        }
        if ($visitor['company'] !== '') {
            $note[] = $langs->transnoentities('Company') . ' : ' . $visitor['company'];
        }
        if (!empty($visitor['consent'])) {
            // The text the customer agreed to, kept with the date : the proof of his consent
            $note[] = $langs->transnoentities('DoliCalBookingConsentGiven', dolical_booking_text('DOLICAL_BOOKING_CONSENT'));
        }
        if ($visitor['message'] !== '') {
            $note[] = '';
            $note[] = $visitor['message'];
        }

        $event                    = new ActionComm($db);
        $event->type_code         = dolical_booking_type_code();
        $event->label             = dolical_booking_event_label($visitor, $owner);
        $event->location          = dol_trunc(preg_replace('/\s+/', ' ', dolical_booking_text('DOLICAL_BOOKING_LOCATION')), 128, 'right', 'UTF-8', 1);
        $event->datep             = $start;
        // The length of its slot in the weekly template
        $event->datef             = $start + dolical_booking_slot_length($start) * 60;
        $event->fulldayevent      = 0;
        $event->percentage        = 0;
        $event->transparency      = 1;
        $event->userownerid       = (int) $owner->id;
        $event->userassigned      = [(int) $owner->id => ['id' => (int) $owner->id, 'mandatory' => 0, 'transparency' => 1, 'answer_status' => 1]];
        $event->socpeopleassigned = [$contact['id'] => ['id' => $contact['id']]];
        $event->contact_id        = $contact['id'];
        $event->socid             = $contact['socid'];
        $event->note_private      = implode('<br>', array_map(function ($line) {
            return dol_nl2br(dol_escape_htmltag($line, 0, 1));
        }, $note));
        $event->ref_ext           = DOLICAL_BOOKING_REF;
        $event->entity            = (int) $conf->entity;

        $db->begin();
        $eventId = $event->create($owner);
        if ($eventId <= 0) {
            $db->rollback();
            $langs = $savedLangs;
            dol_syslog(__FUNCTION__ . ' event: ' . $event->error . ' ' . implode(', ', $event->errors), LOG_ERR);
            $error = 'DoliCalBookingFailed';
            return [];
        }
        // The customer chose the slot : his answer is given
        $db->query('UPDATE ' . MAIN_DB_PREFIX . 'actioncomm_resources SET answer_status = 1 WHERE fk_actioncomm = ' . ((int) $eventId) . " AND element_type = 'socpeople' AND fk_element = " . $contact['id']);
        $db->commit();
    } finally {
        if ($lock) {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    $event->fetch($eventId);
    dolical_save_reminders($event, dolical_reminder_default($event->type_code), $owner);
    // The video call link : a room of its own, the one of the person or the common one. It goes with the confirmation,
    // and the person may change it in the agenda for this appointment only
    $visio = dolical_booking_visio_for($owner, $start);
    if ($visio !== '' && dolical_set_visio($event, $visio, $owner) <= 0) {
        dol_syslog(__FUNCTION__ . ' visio: ' . $event->error, LOG_WARNING);
    }
    $langs = $savedLangs;

    $visitor['contactId'] = $contact['id'];
    if (getDolGlobalInt('DOLICAL_BOOKING_MAIL_CONFIRM', 1)) {
        $mailError = dolical_booking_send_confirmation($event, $owner, $visitor);
        if ($mailError !== '') {
            dol_syslog(__FUNCTION__ . ' confirmation: ' . $mailError, LOG_WARNING);
        }
    }
    dolical_booking_notify_owner($event, $owner, $visitor, 'new');

    return ['event' => $event, 'contactId' => $contact['id']];
}

/**
 * Type of the events booked online : « Meeting » of the dictionary, the « Other » type when it is disabled
 *
 * @return string
 */
function dolical_booking_type_code(): string
{
    global $db;

    $resql = $db->query('SELECT code FROM ' . MAIN_DB_PREFIX . "c_actioncomm WHERE code = 'AC_RDV' AND active = 1");

    return ($resql && $db->num_rows($resql) > 0) ? 'AC_RDV' : 'AC_OTH';
}

/**
 * Languages of a person who reads a mail of DoliCal : his own, else the one of the instance
 *
 * @param  User      $person   User
 * @param  string    $language Language to use instead, the one of a contact : fr_FR...
 * @return Translate
 */
function dolical_booking_langs(User $person, string $language = ''): Translate
{
    global $conf;

    $outputLangs = new Translate('', $conf);
    $outputLangs->setDefaultLang($language !== '' ? $language : dolical_mail_language($person));
    $outputLangs->loadLangs(['main', 'agenda', 'companies', 'dolical@dolical']);

    return $outputLangs;
}

/**
 * The event as the customer sees it : named after the person he meets
 *
 * @param  ActionComm $event Event
 * @param  User       $owner Person booked
 * @return ActionComm
 */
function dolical_booking_customer_view(ActionComm $event, User $owner): ActionComm
{
    global $langs;

    // The title of the page when it is set, « Product demonstration » rather than « Appointment with … »
    $title       = dolical_booking_text('DOLICAL_BOOKING_TITLE', (int) $owner->id);
    $view        = clone $event;
    $view->label = $title !== '' ? $title : $langs->transnoentities('DoliCalBookingCustomerLabel', $owner->getFullName($langs));

    return $view;
}

/**
 * Lines of a mail to the customer : when, with whom, where, the video call, and the phone number of the setup
 *
 * @param  ActionComm $event     Event
 * @param  string     $when      When, in words
 * @param  string     $ownerName Person booked
 * @param  string     $visio     Video call link, empty when none
 * @param  Translate  $outputLangs Language of the mail
 * @return array                 Rows for dolical_mail_html()
 */
function dolical_booking_mail_rows(ActionComm $event, string $when, string $ownerName, string $visio, Translate $outputLangs): array
{
    $rows = [[$outputLangs->transnoentities('DoliCalMailWhen'), $when], [$outputLangs->transnoentities('DoliCalBookingWith'), $ownerName]];
    if (!empty($event->location)) {
        $rows[] = [$outputLangs->transnoentities('Location'), (string) $event->location];
    }
    if ($visio !== '') {
        $rows[] = dolical_visio_mail_row($visio, $outputLangs);
    }
    $phone = dolical_booking_text('DOLICAL_BOOKING_PHONE');
    if ($phone !== '') {
        $rows[] = [$outputLangs->transnoentities('Phone'), $phone];
    }

    return $rows;
}

/**
 * Calendar file of an appointment for its customer : the organizer address of the setup, the link of his appointment,
 * and the description of the setup for the calendar files (DOLICAL_BOOKING_ICS_TEXT)
 *
 * @param  ActionComm $view     Event as the customer sees it
 * @param  User       $owner    Person booked
 * @param  string     $url      Link of his appointment
 * @param  int        $sequence Version of the event
 * @return string
 */
function dolical_booking_ics(ActionComm $view, User $owner, string $url, int $sequence): string
{
    return dolical_build_ics($view, dolical_booking_organizer($owner), $url, $sequence, 'PUBLISH', ['urlLabel' => 'DoliCalBookingIcsManage', 'description' => dolical_booking_text('DOLICAL_BOOKING_ICS_TEXT')]);
}

/**
 * Confirmation to the customer, in the language of the page : the appointment, the iCalendar file to add it to his
 * calendar, and the link to cancel it. Its trace is the one of an invitation : a change of the date made in the
 * calendar later tells him too
 *
 * @param  ActionComm $event   Event, resources loaded
 * @param  User       $owner   Person booked
 * @param  array      $visitor ['name', 'email', 'contactId']
 * @return string              Empty when sent, the error otherwise
 */
function dolical_booking_send_confirmation(ActionComm $event, User $owner, array $visitor): string
{
    global $conf, $langs;

    $view      = dolical_booking_customer_view($event, $owner);
    $ownerName = $owner->getFullName($langs);
    $url       = dolical_booking_manage_url($event, (int) $visitor['contactId']);
    $when      = dolical_booking_when((int) $event->datep, (int) $event->datef);
    $visio     = dolical_get_visio($event);
    $template  = dolical_mail_template('dolical_booking', $langs->defaultlang);

    if ($template !== null) {
        $substitutions = array_merge(getCommonSubstitutionArray($langs, 0, null, $event), [
            '__DOLICAL_EVENT_LABEL__'    => $view->label,
            '__DOLICAL_EVENT_DATE__'     => $when,
            '__DOLICAL_EVENT_LOCATION__' => (string) $event->location,
            '__DOLICAL_EVENT_VISIO__'    => $visio,
            '__DOLICAL_ORGANIZER_NAME__' => $ownerName,
            '__DOLICAL_RECIPIENT_NAME__' => $visitor['name'],
            '__DOLICAL_ANSWER_LINK__'    => $url,
        ]);
        [$subject, $html] = dolical_mail_from_template($template, $substitutions, $langs);
    } else {
        $subject = $langs->transnoentities('DoliCalBookingMailSubject', $ownerName, $when);
        $html    = dolical_mail_html([
            'title'     => $view->label,
            'intro'     => $langs->transnoentities('DoliCalBookingMailIntro', $visitor['name']),
            'rows'      => dolical_booking_mail_rows($event, $when, $ownerName, $visio, $langs),
            'extra'     => dolical_booking_text('DOLICAL_BOOKING_CONFIRM_TEXT'),
            'button'    => [$url, $langs->transnoentities('DoliCalBookingManage')],
            'secondary' => [dolical_booking_manage_url($event, (int) $visitor['contactId'], ['ics' => 1]), $langs->transnoentities('DoliCalAddToCalendar')],
            'note'      => $langs->transnoentities('DoliCalBookingMailNote'),
        ]);
    }

    $tempDir = $conf->dolical->dir_temp ?? (DOL_DATA_ROOT . '/dolical/temp');
    dol_mkdir($tempDir);
    $icsFile = $tempDir . '/booking-' . ((int) $event->id) . '.ics';
    file_put_contents($icsFile, dolical_booking_ics($view, $owner, $url, 0));

    $replyTo = dolical_booking_reply_to($owner);
    $error   = dolical_send_mail($visitor['email'], $subject, $html, $replyTo, $ownerName, 'rea' . ((int) $event->id), [['path' => $icsFile, 'mime' => 'text/calendar', 'name' => 'rendez-vous.ics']]);
    dol_delete_file($icsFile, 0, 1, 0, null, false, 0);

    if ($error === '') {
        dolical_log_invitation($event, $owner, DOLICAL_CODE_INVITE, $langs->transnoentities('DoliCalTraceBookingSent', $visitor['name'], $when), $visitor['email'], $subject, (int) $visitor['contactId']);
    }

    return $error;
}

/**
 * Tell the person booked, in his language, that an appointment was booked or cancelled
 *
 * @param  ActionComm $event   Event
 * @param  User       $owner   Person booked
 * @param  array      $visitor ['name', 'email', 'phone', 'company', 'message']
 * @param  string     $kind    'new' or 'cancel'
 * @return void
 */
function dolical_booking_notify_owner(ActionComm $event, User $owner, array $visitor, string $kind): void
{
    global $langs;

    // The setup may keep the person from being told (DOLICAL_BOOKING_NOTIFY_NEW, DOLICAL_BOOKING_NOTIFY_CANCEL)
    if (empty($owner->email) || !isValidEmail($owner->email) || !getDolGlobalInt($kind === 'new' ? 'DOLICAL_BOOKING_NOTIFY_NEW' : 'DOLICAL_BOOKING_NOTIFY_CANCEL', 1)) {
        return;
    }

    $savedLangs = $langs;
    $langs      = dolical_booking_langs($owner);
    $when       = dolical_booking_when((int) $event->datep, (int) ($event->datef ?: $event->datep));

    $rows = [[$langs->transnoentities('DoliCalMailWhen'), $when], [$langs->transnoentities('Email'), $visitor['email']]];
    if (($visitor['phone'] ?? '') !== '') {
        $rows[] = [$langs->transnoentities('Phone'), $visitor['phone']];
    }
    if (($visitor['company'] ?? '') !== '') {
        $rows[] = [$langs->transnoentities('Company'), $visitor['company']];
    }
    if (($visitor['message'] ?? '') !== '') {
        $rows[] = [$langs->transnoentities('DoliCalBookingMessage'), $visitor['message']];
    }
    $visio = $kind === 'new' ? dolical_get_visio($event) : '';
    if ($visio !== '') {
        $rows[] = dolical_visio_mail_row($visio, $langs);
    }

    $isNew   = $kind === 'new';
    $subject = $langs->transnoentities($isNew ? 'DoliCalBookingOwnerSubject' : 'DoliCalBookingOwnerCancelSubject', $visitor['name'], $when);
    $html    = dolical_mail_html([
        'title'  => $langs->transnoentities($isNew ? 'DoliCalBookingOwnerTitle' : 'DoliCalBookingOwnerCancelTitle'),
        'intro'  => $langs->transnoentities($isNew ? 'DoliCalBookingOwnerIntro' : 'DoliCalBookingOwnerCancelIntro', $visitor['name']),
        'rows'   => $rows,
        'button' => $isNew ? [dol_buildpath('/dolical/dolicalindex.php', 3) . '?event=' . ((int) $event->id), $langs->transnoentities('DoliCalMailOpenEvent')] : [],
        'note'   => '',
    ]);
    $error = dolical_send_mail($owner->email, $subject, $html, $visitor['email'], 'DoliCal', 'rea' . ((int) $event->id));
    if ($error !== '') {
        dol_syslog(__FUNCTION__ . ' ' . $error, LOG_WARNING);
    }

    $langs = $savedLangs;
}

/**
 * The customer cancels his appointment : the event goes, the slot is free again, the person booked is told, the
 * customer gets the iCalendar file that takes it out of his calendar, and the history of the contact keeps a trace
 *
 * @param  ActionComm $event     Event, resources loaded
 * @param  int        $contactId Contact of the customer
 * @return bool
 */
function dolical_booking_cancel(ActionComm $event, int $contactId): bool
{
    global $conf, $db, $langs;

    $owner = new User($db);
    if ($owner->fetch((int) $event->userownerid) <= 0) {
        return false;
    }
    $contact = new Contact($db);
    $contact->fetch($contactId);
    $visitor = ['name' => $contact->getFullName($langs), 'email' => (string) $contact->email, 'phone' => '', 'company' => '', 'message' => ''];
    $when    = dolical_booking_when((int) $event->datep, (int) ($event->datef ?: $event->datep));

    // Read before the event is gone : its version for the calendar of the customer
    $view     = dolical_booking_customer_view($event, $owner);
    $sequence = dolical_invitation_sequence($event) + 1;

    require_once __DIR__ . '/dolical_series.lib.php';
    if (!dolical_delete_occurrence($event, $owner)) {
        dol_syslog(__FUNCTION__ . ' ' . $event->error, LOG_ERR);
        return false;
    }

    // Kept in the history of the contact and of his third party, the event being gone, in the language of the person
    $ownerLangs = dolical_booking_langs($owner);
    dolical_log_invitation($event, $owner, DOLICAL_CODE_CANCEL, $ownerLangs->transnoentities('DoliCalTraceBookingCancelled', $visitor['name'], $when), $visitor['email'], '', $contactId, false);

    // The setup may keep the customer from getting it (DOLICAL_BOOKING_MAIL_CANCEL)
    if ($visitor['email'] !== '' && isValidEmail($visitor['email']) && getDolGlobalInt('DOLICAL_BOOKING_MAIL_CANCEL', 1)) {
        $tempDir = $conf->dolical->dir_temp ?? (DOL_DATA_ROOT . '/dolical/temp');
        dol_mkdir($tempDir);
        $icsFile = $tempDir . '/booking-cancel-' . ((int) $event->id) . '.ics';
        file_put_contents($icsFile, dolical_build_ics($view, dolical_booking_organizer($owner), '', $sequence, 'CANCEL'));
        $rows = [[$langs->transnoentities('DoliCalMailWhen'), $when], [$langs->transnoentities('DoliCalBookingWith'), $owner->getFullName($langs)]];
        if (dolical_booking_text('DOLICAL_BOOKING_PHONE') !== '') {
            $rows[] = [$langs->transnoentities('Phone'), dolical_booking_text('DOLICAL_BOOKING_PHONE')];
        }
        $html = dolical_mail_html([
            'title' => $view->label,
            'intro' => $langs->transnoentities('DoliCalBookingCancelMailIntro', $visitor['name']),
            'rows'  => $rows,
            'button' => [dolical_booking_public_url((int) $owner->id), $langs->transnoentities('DoliCalBookingBookAgain')],
            'note'  => $langs->transnoentities('DoliCalMailCancelNote'),
        ]);
        dolical_send_mail($visitor['email'], $langs->transnoentities('DoliCalBookingCancelMailSubject', $when), $html, dolical_booking_reply_to($owner), $owner->getFullName($langs), 'rea' . ((int) $event->id), [['path' => $icsFile, 'mime' => 'text/calendar', 'name' => 'rendez-vous.ics']]);
        dol_delete_file($icsFile, 0, 1, 0, null, false, 0);
    }

    dolical_booking_notify_owner($event, $owner, $visitor, 'cancel');

    return true;
}

/**
 * Remind the customers of their appointments booked online, once for each reminder chosen in the setup, when its
 * moment has come. A reminder whose moment was already past when the appointment was booked is not sent (booked in the
 * morning for the afternoon : the confirmation is enough), and several due at once (the job stopped a while) make one
 * mail. Run by the scheduled job of the module, every few minutes
 *
 * @return array ['events' => int, 'sent' => int, 'errors' => string[]]
 */
function dolical_booking_send_due_reminders(): array
{
    global $db;

    $summary = ['events' => 0, 'sent' => 0, 'errors' => []];
    $delays  = dolical_booking_reminder_delays();
    if (empty($delays)) {
        return $summary;
    }

    $now  = dol_now();
    $sql  = 'SELECT a.id, a.datep, a.datec FROM ' . MAIN_DB_PREFIX . 'actioncomm as a';
    $sql .= " WHERE a.ref_ext = '" . $db->escape(DOLICAL_BOOKING_REF) . "' AND a.entity IN (" . getEntity('agenda') . ')';
    $sql .= " AND a.datep > '" . $db->idate($now) . "' AND a.datep <= '" . $db->idate($now + max($delays)) . "'";
    $sql .= ' AND a.percent < 100';
    $resql = $db->query($sql);
    if (!$resql) {
        $summary['errors'][] = $db->lasterror();
        return $summary;
    }

    // The moment of the latest reminder reached, for each appointment : the shortest delay already reached
    $due = [];
    while ($obj = $db->fetch_object($resql)) {
        $start   = (int) $db->jdate($obj->datep);
        $created = (int) $db->jdate($obj->datec);
        foreach ($delays as $delay) {
            if ($start - $delay <= $now) {
                if ($start - $delay > $created) {
                    $due[(int) $obj->id] = $start - $delay;
                }
                break;
            }
        }
    }
    $db->free($resql);

    foreach ($due as $eventId => $moment) {
        // Sent since that moment : this reminder is done
        if (dolical_booking_last_reminder($eventId) >= $moment) {
            continue;
        }
        $event = new ActionComm($db);
        $owner = new User($db);
        if ($event->fetch($eventId) <= 0 || $owner->fetch((int) $event->userownerid) <= 0) {
            continue;
        }
        $sending = dolical_booking_send_reminder($event, $owner);
        $summary['events']++;
        $summary['sent']  += $sending['sent'];
        $summary['errors'] = array_merge($summary['errors'], $sending['errors']);
    }

    return $summary;
}

/**
 * When the customers of an appointment were last reminded of it
 *
 * @param  int $eventId Event
 * @return int          Date of the last reminder, 0 when none was sent
 */
function dolical_booking_last_reminder(int $eventId): int
{
    global $db;

    $sql   = 'SELECT MAX(datep) as last FROM ' . MAIN_DB_PREFIX . 'actioncomm';
    $sql  .= ' WHERE fk_element = ' . $eventId . " AND elementtype = 'action' AND code = '" . $db->escape(DOLICAL_CODE_BOOKING_REMIND) . "'";
    $resql = $db->query($sql);
    $obj   = $resql ? $db->fetch_object($resql) : null;

    return ($obj && !empty($obj->last)) ? (int) $db->jdate($obj->last) : 0;
}

/**
 * Remind the customers of an appointment booked online, each one in his language : when, with whom, the video call,
 * and the link to see or cancel it. The ones who declined it are left out
 *
 * @param  ActionComm $event Event, resources loaded
 * @param  User       $owner Person booked
 * @return array             ['sent' => int, 'errors' => string[]]
 */
function dolical_booking_send_reminder(ActionComm $event, User $owner): array
{
    global $db, $langs;

    $result     = ['sent' => 0, 'errors' => []];
    $savedLangs = $langs;
    $visio      = dolical_get_visio($event);
    // The trace is written in the language of the person, the days named in it too
    $ownerLangs = dolical_booking_langs($owner);
    $langs      = $ownerLangs;
    $ownerWhen  = dolical_booking_when((int) $event->datep, (int) ($event->datef ?: $event->datep));

    foreach ($event->socpeopleassigned as $contactId => $resource) {
        $contact = new Contact($db);
        if ((int) ($resource['answer_status'] ?? 0) === -1 || $contact->fetch((int) $contactId) <= 0 || !isValidEmail((string) $contact->email)) {
            continue;
        }

        $langs     = dolical_booking_langs($owner, (string) ($contact->default_lang ?? ''));
        $name      = $contact->getFullName($langs);
        $ownerName = $owner->getFullName($langs);
        $view      = dolical_booking_customer_view($event, $owner);
        $when      = dolical_booking_when((int) $event->datep, (int) ($event->datef ?: $event->datep));
        $url       = dolical_booking_manage_url($event, (int) $contactId);
        $template  = dolical_mail_template('dolical_booking_reminder', $langs->defaultlang);

        if ($template !== null) {
            $substitutions = array_merge(getCommonSubstitutionArray($langs, 0, null, $event), [
                '__DOLICAL_EVENT_LABEL__'    => $view->label,
                '__DOLICAL_EVENT_DATE__'     => $when,
                '__DOLICAL_EVENT_LOCATION__' => (string) $event->location,
                '__DOLICAL_EVENT_VISIO__'    => $visio,
                '__DOLICAL_ORGANIZER_NAME__' => $ownerName,
                '__DOLICAL_RECIPIENT_NAME__' => $name,
                '__DOLICAL_ANSWER_LINK__'    => $url,
            ]);
            [$subject, $html] = dolical_mail_from_template($template, $substitutions, $langs);
        } else {
            $subject = $langs->transnoentities('DoliCalBookingReminderSubject', $view->label, $when);
            $html    = dolical_mail_html([
                'title'     => $view->label,
                'intro'     => $langs->transnoentities('DoliCalBookingReminderIntro', $name),
                'rows'      => dolical_booking_mail_rows($event, $when, $ownerName, $visio, $langs),
                'extra'     => dolical_booking_text('DOLICAL_BOOKING_REMINDER_TEXT'),
                'button'    => [$url, $langs->transnoentities('DoliCalBookingManage')],
                'secondary' => [dolical_booking_manage_url($event, (int) $contactId, ['ics' => 1]), $langs->transnoentities('DoliCalAddToCalendar')],
                'note'      => $langs->transnoentities('DoliCalBookingReminderNote'),
            ]);
        }

        $error = dolical_send_mail((string) $contact->email, $subject, $html, dolical_booking_reply_to($owner), $ownerName, 'rea' . ((int) $event->id));
        if ($error !== '') {
            $result['errors'][] = $contact->email . ' : ' . strip_tags($error);
            continue;
        }
        $result['sent']++;
        // In the history of the event and of the contact, in the language of the person
        dolical_log_invitation($event, $owner, DOLICAL_CODE_BOOKING_REMIND, $ownerLangs->transnoentities('DoliCalTraceBookingReminder', $name, $ownerWhen), (string) $contact->email, $subject, (int) $contactId);
    }
    $langs = $savedLangs;

    return $result;
}
