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
 * \file    public/caldavprofile.php
 * \ingroup dolical
 * \brief   Configuration profile of an iPhone, an iPad or a Mac, from the QR code of the page « Synchroniser mon
 *          téléphone » : no Dolibarr session on the phone, the link is the key. Usable once and for a few minutes, over
 *          HTTPS only ; then it gives nothing.
 */

if (!defined('NOREQUIREMENU')) {
    define('NOREQUIREMENU', '1');
}
if (!defined('NOLOGIN')) {
    define('NOLOGIN', '1');
}
// The link, usable once, is the protection : it comes from a QR code, never from a form of the instance
if (!defined('NOCSRFCHECK')) {
    define('NOCSRFCHECK', '1');
}
if (!defined('NOIPCHECK')) {
    define('NOIPCHECK', '1');
}
if (!defined('NOSESSION')) {
    define('NOSESSION', '1');
}
if (!defined('NOBROWSERNOTIF')) {
    define('NOBROWSERNOTIF', '1');
}

// For MultiCompany : the entity has to be known before main.inc.php, GETPOST does not exist yet
$entity = (!empty($_GET['entity']) ? (int) $_GET['entity'] : 1);
if (is_numeric($entity)) {
    define('DOLENTITY', $entity);
}

// Load DoliCal environment
if (file_exists('../dolical.main.inc.php')) {
    require_once __DIR__ . '/../dolical.main.inc.php';
} elseif (file_exists('../../dolical.main.inc.php')) {
    require_once __DIR__ . '/../../dolical.main.inc.php';
} else {
    die('Include of dolical main fails');
}

require_once __DIR__ . '/../lib/dolical_caldav_profile.lib.php';

global $conf, $db, $langs;

$langs->loadLangs(['dolical@dolical', 'errors']);

// Never kept by a browser or a proxy : the answer may carry a password
header('Cache-Control: no-store, no-cache, must-revalidate, private');
header('Pragma: no-cache');
header('X-Robots-Tag: noindex, nofollow');

if (!isModEnabled('dolical') || !dolical_caldav_enabled() || !dolical_caldav_profile_available()) {
    http_response_code(404);
    exit;
}

// A HEAD (a preview of the link) must not use it up : only the download does
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'HEAD') {
    header('Content-Type: application/x-apple-aspen-config');
    exit;
}

$taken = dolical_caldav_take_profile(GETPOST('t', 'alphanohtml'));
if (empty($taken)) {
    // Unknown, used or expired : nothing tells which
    http_response_code(410);
    header('Content-Type: text/html; charset=UTF-8');
    print '<!DOCTYPE html><html lang="' . dol_escape_htmltag(substr($langs->defaultlang, 0, 2)) . '"><head><meta charset="UTF-8">';
    print '<meta name="viewport" content="width=device-width, initial-scale=1"><title>' . dol_escape_htmltag($langs->transnoentities('DoliCalSyncPhone'), 0, 0, '', 1) . '</title></head>';
    print '<body style="font-family: sans-serif; max-width: 32em; margin: 3em auto; padding: 0 1em; color: #2d3748; line-height: 1.5;">';
    print '<h1 style="font-size: 1.3em; color: #014751;">' . dol_escape_htmltag($langs->transnoentities('DoliCalSyncPhone'), 0, 0, '', 1) . '</h1>';
    print '<p>' . dol_escape_htmltag($langs->transnoentities('DoliCalSyncProfileGone'), 0, 0, '', 1) . '</p></body></html>';
    exit;
}

dol_syslog('DoliCal : configuration profile given to the phone of user ' . $taken['user']->id, LOG_NOTICE);

header('Content-Type: application/x-apple-aspen-config');
header('Content-Disposition: attachment; filename="dolical-' . preg_replace('/[^A-Za-z0-9._-]/', '-', (string) $taken['user']->login) . '.mobileconfig"');
header('X-Content-Type-Options: nosniff');
print dolical_caldav_mobileconfig($taken['user'], $taken['password']);
