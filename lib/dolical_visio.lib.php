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
 * \file    lib/dolical_visio.lib.php
 * \ingroup dolical
 * \brief   Video call link of an event, in the extra field dolical_visio of the native event : a link of Teams, Meet,
 *          Zoom, Jitsi... sent with the invitations and the confirmation of an appointment booked online. The native
 *          field of the place holds 128 characters only, too few for the link of a Teams meeting.
 */

require_once DOL_DOCUMENT_ROOT . '/core/class/extrafields.class.php';

/**
 * Longest link kept : the size of the extra field
 */
const DOLICAL_VISIO_MAX = 1024;

/**
 * Whether the extra field of the link exists : it is created when the module is enabled
 *
 * @return bool
 */
function dolical_visio_available(): bool
{
    global $db;

    static $available = null;
    if ($available === null) {
        $extrafields = new ExtraFields($db);
        $extrafields->fetch_name_optionals_label('actioncomm');
        $available = isset($extrafields->attributes['actioncomm']['label']['dolical_visio']);
    }

    return $available;
}

/**
 * A link as typed, made safe : an address of the web only, its scheme added when it was typed without it
 * (« meet.google.com/abc-defg-hij »)
 *
 * @param  string $text Link typed
 * @return string       The link, empty when there is none or when it is not an address of the web
 */
function dolical_visio_clean(string $text): string
{
    $url = trim(preg_replace('/\s+/', '', $text));
    if ($url === '') {
        return '';
    }
    if (!preg_match('~^[a-z][a-z0-9+.-]*:~i', $url)) {
        $url = 'https://' . $url;
    }
    $url = sanitizeVal($url, 'url');
    // http(s) only : no javascript: nor data: link in a mail or a page
    if (strlen($url) > DOLICAL_VISIO_MAX || !preg_match('~^https?://[^/?#]+\.[^/?#]+~i', $url) || filter_var($url, FILTER_VALIDATE_URL) === false) {
        return '';
    }

    return $url;
}

/**
 * Video call link of an event
 *
 * @param  ActionComm $event  Event
 * @param  bool       $reload Read again from the database, the extra fields of the object may be older
 * @return string             Empty when there is none
 */
function dolical_get_visio(ActionComm $event, bool $reload = false): string
{
    if (!dolical_visio_available() || empty($event->id)) {
        return '';
    }
    if ($reload || !isset($event->array_options) || !array_key_exists('options_dolical_visio', (array) $event->array_options)) {
        $event->fetch_optionals();
    }

    return trim((string) ($event->array_options['options_dolical_visio'] ?? ''));
}

/**
 * Line of the link in the body of a mail : « Visio : Join the video call », the link under it
 *
 * @param  string    $url         Link
 * @param  Translate $outputLangs Language of the mail
 * @return array                  [label, text, link] for dolical_mail_html()
 */
function dolical_visio_mail_row(string $url, Translate $outputLangs): array
{
    return [$outputLangs->transnoentities('DoliCalVisioShort'), $outputLangs->transnoentities('DoliCalVisioJoin'), $url];
}

/**
 * Write the video call link of an event ; nothing is written when it is the same
 *
 * @param  ActionComm $event Event, saved
 * @param  string     $url   Link, cleaned by dolical_visio_clean() ; empty removes it
 * @param  User       $user  Current user
 * @return int               > 0 when saved or unchanged, < 0 on error
 */
function dolical_set_visio(ActionComm $event, string $url, User $user): int
{
    if (!dolical_visio_available() || empty($event->id)) {
        return $url === '' ? 1 : -1;
    }
    if (dolical_get_visio($event, true) === $url) {
        return 1;
    }

    // Removed as an empty text : updateExtraField() skips a null value (isset())
    $event->array_options['options_dolical_visio'] = $url;

    return $event->updateExtraField('dolical_visio', null, $user) > 0 ? 1 : -1;
}
