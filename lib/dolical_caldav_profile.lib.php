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
 * \file    lib/dolical_caldav_profile.lib.php
 * \ingroup dolical
 * \brief   A phone set up in one scan, right after its synchronization password is made : a DAVx⁵ link for Android, a
 *          configuration profile for the iPhone. The profile is fetched from the phone without a Dolibarr session, by a
 *          link usable once and for DOLICAL_CALDAV_PROFILE_TTL seconds ; the password it holds is kept meanwhile in the
 *          parameter DOLICAL_CALDAV_PROFILE of the person, encrypted with a key only the link carries.
 */

require_once __DIR__ . '/dolical_caldav.lib.php';

/**
 * Seconds the link of the profile lasts
 */
const DOLICAL_CALDAV_PROFILE_TTL = 600;

/**
 * Cipher of the password waiting for the profile
 */
const DOLICAL_CALDAV_PROFILE_CIPHER = 'aes-256-gcm';

/**
 * Whether an iPhone profile can be offered : over HTTPS only (it carries the password), and with the cipher keeping the
 * password meanwhile
 *
 * @return bool
 */
function dolical_caldav_profile_available(): bool
{
    return isHTTPS() && function_exists('openssl_encrypt') && in_array(DOLICAL_CALDAV_PROFILE_CIPHER, openssl_get_cipher_methods(), true);
}

/**
 * Key of the password waiting for the profile : made from the link, never stored
 *
 * @param  string $secret Random part of the link
 * @return string         Raw key
 */
function dolical_caldav_profile_key(string $secret): string
{
    return hash('sha256', 'dolical-caldav-profile|' . $secret, true);
}

/**
 * Make the link of the profile of a person, with the password just made : the previous link stops working
 *
 * @param  User   $user     Person, his new synchronization password just made
 * @param  string $password That password, in clear
 * @return string           Token of the link (« <id of the person>.<secret> »), empty on error
 */
function dolical_caldav_new_profile_token(User $user, string $password): string
{
    global $conf, $db;

    if (!dolical_caldav_profile_available() || $password === '') {
        return '';
    }
    $secret = bin2hex(random_bytes(32));
    $iv     = random_bytes(12);
    $tag    = '';
    $cipher = openssl_encrypt($password, DOLICAL_CALDAV_PROFILE_CIPHER, dolical_caldav_profile_key($secret), OPENSSL_RAW_DATA, $iv, $tag);
    if ($cipher === false) {
        return '';
    }
    // « expiry|hash of the secret|iv, tag and password encrypted » : nothing usable without the link
    $stored = (dol_now() + DOLICAL_CALDAV_PROFILE_TTL) . '|' . hash('sha256', $secret) . '|' . base64_encode($iv . $tag . $cipher);
    if (dol_set_user_param($db, $conf, $user, ['DOLICAL_CALDAV_PROFILE' => $stored]) <= 0) {
        return '';
    }
    $user->conf->DOLICAL_CALDAV_PROFILE = $stored;

    return (int) $user->id . '.' . $secret;
}

/**
 * Forget the link of the profile of a person : used, expired, or its password renewed or removed
 *
 * @param  User $user Person
 * @return void
 */
function dolical_caldav_clear_profile(User $user): void
{
    global $conf, $db;

    if ((string) ($user->conf->DOLICAL_CALDAV_PROFILE ?? '') === '') {
        return;
    }
    dol_set_user_param($db, $conf, $user, ['DOLICAL_CALDAV_PROFILE' => '']);
    unset($user->conf->DOLICAL_CALDAV_PROFILE);
}

/**
 * Person and password of a link of profile, the link used up : given once, then never again
 *
 * @param  string $token Token of the link
 * @return array         ['user' => User, 'password' => string], empty when the link is unknown, used or expired
 */
function dolical_caldav_take_profile(string $token): array
{
    global $db;

    if (!preg_match('/^(\d+)\.([0-9a-f]{64})$/', $token, $matches)) {
        return [];
    }
    $person = new User($db);
    if ($person->fetch((int) $matches[1], '', '', 1) <= 0 || (int) $person->status !== 1) {
        return [];
    }
    $parts = explode('|', (string) ($person->conf->DOLICAL_CALDAV_PROFILE ?? ''), 3);
    if (count($parts) !== 3 || !hash_equals($parts[1], hash('sha256', $matches[2]))) {
        // Not this person's link : his own one stays usable
        return [];
    }
    // The right link : used up now, whatever comes next
    dolical_caldav_clear_profile($person);
    if ((int) $parts[0] < dol_now()) {
        return [];
    }
    $raw      = (string) base64_decode($parts[2], true);
    $password = strlen($raw) > 28 ? openssl_decrypt(substr($raw, 28), DOLICAL_CALDAV_PROFILE_CIPHER, dolical_caldav_profile_key($matches[2]), OPENSSL_RAW_DATA, substr($raw, 0, 12), substr($raw, 12, 16)) : false;
    // Still his synchronization password : not one renewed or removed since
    if (!is_string($password) || !dolical_caldav_check_password($person, $password)) {
        return [];
    }

    return ['user' => $person, 'password' => $password];
}

/**
 * Address of the link of the profile
 *
 * @param  string $token Token of the link
 * @return string
 */
function dolical_caldav_profile_url(string $token): string
{
    global $conf;

    return dol_buildpath('/dolical/public/caldavprofile.php', 3) . '?' . http_build_query(['entity' => (int) $conf->entity, 't' => $token]);
}

/**
 * Link opening DAVx⁵ with the connection filled in : davx5:// is HTTPS for it, caldav:// plain HTTP
 *
 * @param  string $login    Login of the person
 * @param  string $password His synchronization password
 * @return string           Empty when the address of the server cannot be read
 */
function dolical_caldav_davx5_link(string $login, string $password): string
{
    $url = parse_url(dolical_caldav_url());
    if (empty($url['host'])) {
        return '';
    }
    $scheme = ($url['scheme'] ?? '') === 'https' ? 'davx5' : 'caldav';

    return $scheme . '://' . rawurlencode($login) . ':' . rawurlencode($password) . '@' . $url['host'] . (!empty($url['port']) ? ':' . $url['port'] : '') . ($url['path'] ?? '/');
}

/**
 * Configuration profile of an iPhone, an iPad or a Mac : a CalDAV account on the server, with the login and the
 * password, the same identifier for a person and an instance so that installing it again replaces it
 *
 * @param  User   $person   Person
 * @param  string $password His synchronization password
 * @return string           Property list (XML)
 */
function dolical_caldav_mobileconfig(User $person, string $password): string
{
    global $langs, $mysoc;

    $server = dolical_caldav_url();
    $url    = parse_url($server);
    $host   = (string) ($url['host'] ?? '');
    $secure = ($url['scheme'] ?? '') === 'https';
    $port   = (int) ($url['port'] ?? ($secure ? 443 : 80));
    $base   = 'com.evarisk.dolical.' . preg_replace('/[^a-z0-9.-]/', '-', strtolower($host)) . '.' . (int) $person->id;
    $name   = $langs->transnoentities('DoliCalSyncProfileName', $mysoc->name ?? $host);
    $string = function (string $value): string {
        return '<string>' . htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8') . '</string>';
    };
    $uuid   = function (): string {
        $bytes    = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);

        return strtoupper(vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4)));
    };

    $xml  = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    $xml .= '<!DOCTYPE plist PUBLIC "-//Apple//DTD PLIST 1.0//EN" "http://www.apple.com/DTDs/PropertyList-1.0.dtd">' . "\n";
    $xml .= '<plist version="1.0"><dict>' . "\n";
    $xml .= '<key>PayloadContent</key><array><dict>' . "\n";
    $xml .= '<key>CalDAVAccountDescription</key>' . $string($name) . "\n";
    $xml .= '<key>CalDAVHostName</key>' . $string($host) . "\n";
    $xml .= '<key>CalDAVPort</key><integer>' . $port . '</integer>' . "\n";
    $xml .= '<key>CalDAVPrincipalURL</key>' . $string($server . 'principals/' . rawurlencode((string) $person->login) . '/') . "\n";
    $xml .= '<key>CalDAVUseSSL</key>' . ($secure ? '<true/>' : '<false/>') . "\n";
    $xml .= '<key>CalDAVUsername</key>' . $string((string) $person->login) . "\n";
    $xml .= '<key>CalDAVPassword</key>' . $string($password) . "\n";
    $xml .= '<key>PayloadDisplayName</key>' . $string($name) . "\n";
    $xml .= '<key>PayloadIdentifier</key>' . $string($base . '.caldav') . "\n";
    $xml .= '<key>PayloadType</key><string>com.apple.caldav.account</string>' . "\n";
    $xml .= '<key>PayloadUUID</key>' . $string($uuid()) . "\n";
    $xml .= '<key>PayloadVersion</key><integer>1</integer>' . "\n";
    $xml .= '</dict></array>' . "\n";
    $xml .= '<key>PayloadDescription</key>' . $string($langs->transnoentities('DoliCalSyncProfileDescription', $person->getFullName($langs))) . "\n";
    $xml .= '<key>PayloadDisplayName</key>' . $string($name) . "\n";
    $xml .= '<key>PayloadIdentifier</key>' . $string($base) . "\n";
    $xml .= '<key>PayloadRemovalDisallowed</key><false/>' . "\n";
    $xml .= '<key>PayloadType</key><string>Configuration</string>' . "\n";
    $xml .= '<key>PayloadUUID</key>' . $string($uuid()) . "\n";
    $xml .= '<key>PayloadVersion</key><integer>1</integer>' . "\n";
    $xml .= '</dict></plist>' . "\n";

    return $xml;
}

/**
 * QR code of a text, drawn by the library shipped with Dolibarr : no outside service, and nothing but the squares (the
 * text itself, a password maybe, is not written in the drawing)
 *
 * @param  string $text  Text to scan
 * @param  string $title Description of the drawing, for the screen readers
 * @return string        SVG, empty when the text cannot be drawn
 */
function dolical_qrcode_svg(string $text, string $title): string
{
    require_once TCPDF_PATH . 'tcpdf_barcodes_2d.php';

    $code  = new TCPDF2DBarcode($text, 'QRCODE,M');
    $array = $code->getBarcodeArray();
    if (empty($array['num_rows']) || empty($array['bcode'])) {
        return '';
    }
    // The squares in one path, a margin of 4 squares around as the readers expect
    $path = '';
    for ($row = 0; $row < $array['num_rows']; $row++) {
        for ($col = 0; $col < $array['num_cols']; $col++) {
            if (!empty($array['bcode'][$row][$col])) {
                $path .= 'M' . $col . ' ' . $row . 'h1v1h-1z';
            }
        }
    }
    $size = $array['num_cols'] + 8;

    return '<svg class="dolical-qrcode" xmlns="http://www.w3.org/2000/svg" viewBox="-4 -4 ' . $size . ' ' . ($array['num_rows'] + 8) . '" role="img" aria-label="' . dol_escape_htmltag($title) . '" shape-rendering="crispEdges">'
        . '<rect x="-4" y="-4" width="' . $size . '" height="' . ($array['num_rows'] + 8) . '" fill="#fff"/><path fill="#000" d="' . $path . '"/></svg>';
}
