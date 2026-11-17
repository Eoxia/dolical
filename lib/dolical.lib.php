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
 * \file    lib/dolical.lib.php
 * \ingroup dolical
 * \brief   Library files with common functions for DoliCal : admin tabs, permissions, people of the calendar
 */

/**
 * Prepare array of tabs for admin
 *
 * @return array Array of tabs
 */
function dolical_admin_prepare_head(): array
{
    global $conf, $langs;

    $langs->load('dolical@dolical');

    $h    = 0;
    $head = [];

    $head[$h][0] = dol_buildpath('dolical/admin/setup.php', 1);
    $head[$h][1] = $conf->browser->layout == 'classic' ? '<i class="fas fa-cog pictofixedwidth"></i>' . $langs->trans('ModuleSettings') : '<i class="fas fa-cog"></i>';
    $head[$h][2] = 'settings';
    $h++;

    // The online booking, in the sections of the appointments of Nextcloud, and its weekly template of the slots
    $head[$h][0] = dol_buildpath('dolical/admin/booking.php', 1);
    $head[$h][1] = $conf->browser->layout == 'classic' ? '<i class="far fa-calendar-check pictofixedwidth"></i>' . $langs->trans('DoliCalBooking') : '<i class="far fa-calendar-check"></i>';
    $head[$h][2] = 'booking';
    $h++;

    $head[$h][0] = dol_buildpath('dolical/admin/booking_template.php', 1);
    $head[$h][1] = $conf->browser->layout == 'classic' ? '<i class="fas fa-th pictofixedwidth"></i>' . $langs->trans('DoliCalTemplate') : '<i class="fas fa-th"></i>';
    $head[$h][2] = 'template';
    $h++;

    complete_head_from_modules($conf, $langs, null, $head, $h, 'dolical@dolical');

    complete_head_from_modules($conf, $langs, null, $head, $h, 'dolical@dolical', 'remove');

    return $head;
}

/**
 * A time zone and its gap with UTC : « Europe/Paris (UTC+01:00) ». The gap is the one of the date given, summer time
 * changes it
 *
 * @param  string $zone Time zone, Europe/Paris...
 * @param  int    $at   Date of the gap, now by default
 * @return string       Plain text
 */
function dolical_timezone_label(string $zone, int $at = 0): string
{
    try {
        $offset = (new DateTimeZone($zone))->getOffset(new DateTimeImmutable('@' . ($at > 0 ? $at : dol_now())));
    } catch (Exception $exception) {
        return $zone;
    }
    $utc = 'UTC' . ($offset < 0 ? '-' : '+') . sprintf('%02d:%02d', intdiv(abs($offset), 3600), intdiv(abs($offset) % 3600, 60));

    return $zone === 'UTC' ? $zone : $zone . ' (' . $utc . ')';
}

/**
 * A line of a setup page of DoliCal : the name of the setting and a line of help, its field beside them or under them
 *
 * @param  string $name     Name, translated
 * @param  string $helpHtml Help, HTML ('' for none)
 * @param  string $field    Field, HTML
 * @param  string $layout   'inline' : the field beside the name, 'stacked' : under it
 * @param  string $depends  Switch that shows the setting ('!' before its name : when it is off), '' when always shown
 * @return string           HTML
 */
function dolical_setting(string $name, string $helpHtml, string $field, string $layout = 'inline', string $depends = ''): string
{
    $html  = '<div class="dolical-setting dolical-setting--' . $layout . '"' . ($depends !== '' ? ' data-depends="' . dol_escape_htmltag($depends) . '"' : '') . '>';
    $html .= '<div class="dolical-setting__text"><span class="dolical-setting__name">' . $name . '</span>';
    $html .= $helpHtml !== '' ? '<span class="dolical-setting__help">' . $helpHtml . '</span>' : '';
    $html .= '</div><div class="dolical-setting__field">' . $field . '</div></div>';

    return $html;
}

/**
 * A switch of a setup page of DoliCal
 *
 * @param  string $inputName Name of the field
 * @param  bool   $checked   Whether it is on
 * @param  string $label     Label beside it, translated ('' for none)
 * @return string            HTML
 */
function dolical_setting_switch(string $inputName, bool $checked, string $label = ''): string
{
    return '<label class="dolical-switch dolical-setting__switch"><input type="checkbox" name="' . dol_escape_htmltag($inputName) . '" value="1"' . ($checked ? ' checked' : '') . '><span class="dolical-switch__track"></span>'
        . ($label !== '' ? '<span>' . $label . '</span>' : '') . '</label>';
}

/**
 * A number of a setup page of DoliCal and its unit
 *
 * @param  string $inputName Name of the field
 * @param  int    $value     Value
 * @param  int    $min       Smallest value
 * @param  int    $max       Largest value
 * @param  string $unit      Unit, translated ('' for none)
 * @param  int    $step      Step
 * @return string            HTML
 */
function dolical_setting_number(string $inputName, int $value, int $min, int $max, string $unit = '', int $step = 1): string
{
    return '<span class="dolical-setting__number"><input type="number" name="' . dol_escape_htmltag($inputName) . '" min="' . $min . '" max="' . $max . '" step="' . $step . '" value="' . min($max, max($min, $value)) . '">'
        . ($unit !== '' ? '<span>' . $unit . '</span>' : '') . '</span>';
}

/**
 * Head of a card of a setup page of DoliCal, its icon and its title
 *
 * @param  string $icon  Classes of the icon
 * @param  string $title Title, translated
 * @return string        HTML
 */
function dolical_setting_card_title(string $icon, string $title): string
{
    return '<h2><i class="' . dol_escape_htmltag($icon) . ' pictofixedwidth"></i>' . $title . '</h2>';
}

/**
 * URL of a file of the module, versioned by its date : the browsers fetch it again once it changed
 *
 * @param  string $path Path from the module directory, e.g. css/dolical.css
 * @return string       Path for llxHeader(), e.g. /dolical/css/dolical.css?v=1761830000
 */
function dolical_asset(string $path): string
{
    $file = __DIR__ . '/../' . $path;

    return '/dolical/' . $path . (is_file($file) ? '?v=' . filemtime($file) : '');
}

/**
 * Header of a page of DoliCal : the Dolibarr header with the style and the scripts of the module
 *
 * @param  string $title         Title of the page
 * @param  string $morecssonbody Class of the body
 * @return void
 */
function dolical_header(string $title, string $morecssonbody = ''): void
{
    // The entry point first, then the modules in the order their init must run
    $js = [dolical_asset('js/dolical.js')];
    foreach (['calendar', 'eventcard', 'eventform', 'import', 'report', 'sync', 'util', 'single'] as $module) {
        $js[] = dolical_asset('js/modules/' . $module . '.js');
    }

    llxHeader('', $title, '', '', 0, 0, $js, [dolical_asset('css/dolical.css')], '', $morecssonbody);
}

/**
 * Address of the logo of the company for the public pages, or nothing when it has none : never the logo of Dolibarr
 *
 * @return string Absolute URL or data URI, empty without logo
 */
function dolical_company_logo_url(): string
{
    global $conf, $dolibarr_main_url_root, $mysoc;

    // The logo itself rather than its thumbnail, sharp on the screens of the phones
    foreach (['logos/' => (string) ($mysoc->logo ?? ''), 'logos/thumbs/' => (string) ($mysoc->logo_small ?? '')] as $dir => $file) {
        if ($file !== '' && is_readable($conf->mycompany->dir_output . '/' . $dir . $file)) {
            $trimmed = dolical_trimmed_logo_uri($conf->mycompany->dir_output . '/' . $dir . $file);
            if ($trimmed !== '') {
                return $trimmed;
            }
            return $dolibarr_main_url_root . '/viewimage.php?modulepart=mycompany&entity=' . ((int) $conf->entity) . '&file=' . urlencode($dir . $file);
        }
    }

    return '';
}

/**
 * A PNG logo without its transparent margins, as a data URI : drawn in the middle of a large transparent canvas, it
 * would look tiny at the height of the header. Made once per version of the file, kept in the documents of DoliCal.
 *
 * @param  string $path Logo file
 * @return string       Data URI, empty when the logo is not a PNG, has no margin to remove or GD is missing
 */
function dolical_trimmed_logo_uri(string $path): string
{
    global $conf;

    $info = @getimagesize($path);
    if (empty($info) || $info[2] !== IMAGETYPE_PNG || !function_exists('imagecreatefrompng') || empty($conf->dolical->dir_output)) {
        return '';
    }

    $cacheDir = $conf->dolical->dir_output . '/logo';
    $cache    = $cacheDir . '/' . md5($path . '|' . filemtime($path) . '|' . filesize($path)) . '.png';
    $none     = $cache . '.none';
    if (is_file($none)) {
        return '';
    }
    if (!is_readable($cache)) {
        dol_mkdir($cacheDir);
        // The copies of the former logos
        foreach (glob($cacheDir . '/*.png*') ?: [] as $former) {
            @unlink($former);
        }

        $image = @imagecreatefrompng($path);
        $box   = $image ? dolical_drawn_box($image) : null;
        if (!$box || ($box[2] === imagesx($image) && $box[3] === imagesy($image))) {
            @file_put_contents($none, '');
            return '';
        }

        // Twice the width of the header at most : sharp on the phones, light in the page
        $width  = min($box[2], 600);
        $height = max(1, (int) round($box[3] * $width / $box[2]));
        $copy   = imagecreatetruecolor($width, $height);
        imagealphablending($copy, false);
        imagesavealpha($copy, true);
        imagecopyresampled($copy, $image, 0, 0, $box[0], $box[1], $width, $height, $box[2], $box[3]);
        if (!@imagepng($copy, $cache, 9)) {
            return '';
        }
    }

    $data = @file_get_contents($cache);

    return $data ? 'data:image/png;base64,' . base64_encode($data) : '';
}

/**
 * Box of what is drawn in an image : its pixels other than transparent (almost invisible ones count as transparent).
 * imagecropauto() cannot do it, it knows only the transparent colour of a palette.
 *
 * @param  GdImage|resource $image Image
 * @return int[]|null              [left, top, width, height], null when nothing is drawn
 */
function dolical_drawn_box($image): ?array
{
    if (!imageistruecolor($image)) {
        imagepalettetotruecolor($image);
    }
    $width  = imagesx($image);
    $height = imagesy($image);
    $drawn  = function (int $x, int $y) use ($image): bool {
        return ((imagecolorat($image, $x, $y) >> 24) & 0x7F) < 120;
    };
    $rowDrawn = function (int $y, int $from, int $to) use ($drawn): bool {
        for ($x = $from; $x <= $to; $x++) {
            if ($drawn($x, $y)) {
                return true;
            }
        }
        return false;
    };
    $colDrawn = function (int $x, int $from, int $to) use ($drawn): bool {
        for ($y = $from; $y <= $to; $y++) {
            if ($drawn($x, $y)) {
                return true;
            }
        }
        return false;
    };

    // From each side inwards, up to the first line with something drawn
    for ($top = 0; $top < $height && !$rowDrawn($top, 0, $width - 1); $top++);
    if ($top === $height) {
        return null;
    }
    for ($bottom = $height - 1; $bottom > $top && !$rowDrawn($bottom, 0, $width - 1); $bottom--);
    for ($left = 0; $left < $width - 1 && !$colDrawn($left, $top, $bottom); $left++);
    for ($right = $width - 1; $right > $left && !$colDrawn($right, $top, $bottom); $right--);

    return [$left, $top, $right - $left + 1, $bottom - $top + 1];
}

/**
 * Whether the request is a POST carrying the token of the session. main.inc.php checks it only as far as
 * MAIN_SECURITY_CSRF_WITH_TOKEN asks : every change made by DoliCal requires it, whatever the setup
 *
 * @return bool
 */
function dolical_valid_post(): bool
{
    $token = GETPOST('token', 'alpha');
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || $token === '') {
        return false;
    }

    // Compared in constant time, against the token of the page and the one of the session
    foreach ([(string) currentToken(), (string) newToken()] as $expected) {
        if ($expected !== '' && hash_equals($expected, $token)) {
            return true;
        }
    }

    return false;
}

/**
 * Whether the user may look at the agenda of other people (native right "read the events of others")
 *
 * @param  User $user Current user
 * @return bool
 */
function dolical_can_read_all(User $user): bool
{
    return (bool) $user->hasRight('agenda', 'allactions', 'read');
}

/**
 * Whether the user may create events, for himself at least
 *
 * @param  User $user Current user
 * @return bool
 */
function dolical_can_create(User $user): bool
{
    return $user->hasRight('agenda', 'myactions', 'create') || $user->hasRight('agenda', 'allactions', 'create');
}

/**
 * Whether the user is the owner, the author or one of the people assigned to the event
 *
 * @param  User        $user  Current user
 * @param  ActionComm  $event Event, resources loaded
 * @return bool
 */
function dolical_is_event_mine(User $user, ActionComm $event): bool
{
    return $event->userownerid == $user->id || $event->authorid == $user->id || isset($event->userassigned[$user->id]);
}

/**
 * Same rule as comm/action/card.php : the events of everyone, or mine when I take part in them
 *
 * @param  User       $user  Current user
 * @param  ActionComm $event Event, resources loaded
 * @return bool
 */
function dolical_can_read_event(User $user, ActionComm $event): bool
{
    if ($user->socid > 0 && $event->socid != $user->socid) {
        return false;
    }

    return dolical_can_read_all($user) || ($user->hasRight('agenda', 'myactions', 'read') && dolical_is_event_mine($user, $event));
}

/**
 * Whether the user may modify the event : same rule as comm/action/card.php, the events of everyone, or the
 * ones I created or own. Being only invited lets me answer, not rewrite the event
 *
 * @param  User       $user  Current user
 * @param  ActionComm $event Event
 * @return bool
 */
function dolical_can_edit_event(User $user, ActionComm $event): bool
{
    $owned = empty($event->id) || $event->authorid == $user->id || $event->userownerid == $user->id;

    return $user->hasRight('agenda', 'allactions', 'create') || ($owned && $user->hasRight('agenda', 'myactions', 'create'));
}

/**
 * Whether the user may delete the event, same rule as comm/action/card.php
 *
 * @param  User       $user  Current user
 * @param  ActionComm $event Event
 * @return bool
 */
function dolical_can_delete_event(User $user, ActionComm $event): bool
{
    $owned = $event->authorid == $user->id || $event->userownerid == $user->id;

    return $user->hasRight('agenda', 'allactions', 'delete') || ($owned && $user->hasRight('agenda', 'myactions', 'delete'));
}

/**
 * Color of a person in the calendar : the color set on the user card, else a stable color of the palette
 *
 * @param  int    $userId    Id of the user
 * @param  string $userColor Color stored on the user card, hexadecimal without #
 * @return string            Hexadecimal color with #
 */
function dolical_user_color(int $userId, string $userColor = ''): string
{
    // The color the current user chose for that person, for himself only
    $mine = dolical_my_colors();

    return isset($mine[$userId]) ? '#' . $mine[$userId] : dolical_base_color($userId, $userColor);
}

/**
 * Color of a person as everybody sees it : the one set on the user card, else a stable color of the palette
 *
 * @param  int    $userId    Id of the user
 * @param  string $userColor Color stored on the user card, hexadecimal without #
 * @return string            Hexadecimal color with #
 */
function dolical_base_color(int $userId, string $userColor = ''): string
{
    if (preg_match('/^#?([0-9a-f]{6})$/i', $userColor, $matches)) {
        return '#' . strtolower($matches[1]);
    }
    $palette = dolical_palette();

    return $palette[abs($userId) % count($palette)];
}

/**
 * Colors of the calendar : distinct enough side by side, dark enough to carry a white label
 *
 * @return string[] Hexadecimal colors with #
 */
function dolical_palette(): array
{
    return ['#63acc9', '#e07a5f', '#81b29a', '#9b5de5', '#f2a541', '#3d5a80', '#d64f7a', '#2a9d8f', '#8d6e63', '#5c7cfa', '#c0a020', '#65a30d'];
}

/**
 * Colors the current user gave to people in his calendar : a preference of his (user parameter DOLICAL_COLORS), the
 * others keep theirs
 *
 * @return array [user id => 'rrggbb']
 */
function dolical_my_colors(): array
{
    global $user;

    $colors = json_decode((string) (is_object($user) && isset($user->conf->DOLICAL_COLORS) ? $user->conf->DOLICAL_COLORS : ''), true);
    $clean  = [];
    foreach (is_array($colors) ? $colors : [] as $personId => $color) {
        if ((int) $personId > 0 && preg_match('/^[0-9a-f]{6}$/i', (string) $color)) {
            $clean[(int) $personId] = strtolower((string) $color);
        }
    }

    return $clean;
}

/**
 * Duration of an event within a day, the way people say it : 45 min, 1 h, 1 h 30
 *
 * @param  int    $seconds Duration
 * @return string
 */
function dolical_format_duration(int $seconds): string
{
    $minutes = (int) round($seconds / 60);
    $hours   = intdiv($minutes, 60);
    $rest    = $minutes % 60;

    if ($hours === 0) {
        return $rest . ' min';
    }

    return $hours . ' h' . ($rest > 0 ? ' ' . sprintf('%02d', $rest) : '');
}

/**
 * Initials of a name, for the avatars
 *
 * @param  string $name Full name
 * @return string       One or two capital letters
 */
function dolical_initials(string $name): string
{
    $words    = preg_split('/[\s\-\.]+/u', trim($name), -1, PREG_SPLIT_NO_EMPTY);
    $initials = '';
    foreach (array_slice($words, 0, 2) as $word) {
        $initials .= dol_strtoupper(dol_substr($word, 0, 1));
    }

    return $initials !== '' ? $initials : '?';
}

/**
 * People whose agenda the user may display : every active internal employee with the right to read all the
 * events, only himself otherwise. The current user keeps his own agenda even when he is not an employee
 *
 * @param  User  $user Current user
 * @return array       [id => ['id', 'name', 'login', 'email', 'color' (the one the current user chose, else
 *                     'baseColor'), 'baseColor' (the one everybody sees), 'initials']], current user first
 */
function dolical_get_calendar_users(User $user): array
{
    global $db;

    $sql  = 'SELECT u.rowid, u.lastname, u.firstname, u.login, u.email, u.color';
    $sql .= ' FROM ' . MAIN_DB_PREFIX . 'user as u';
    $sql .= ' WHERE u.statut = 1';
    $sql .= ' AND (u.fk_soc IS NULL OR u.fk_soc = 0)';
    $sql .= ' AND (u.employee = 1 OR u.rowid = ' . ((int) $user->id) . ')';
    $sql .= ' AND u.entity IN (' . getEntity('user') . ')';
    if (!dolical_can_read_all($user)) {
        $sql .= ' AND u.rowid = ' . ((int) $user->id);
    }
    $sql .= ' ORDER BY u.firstname, u.lastname';

    $users = [];
    $resql = $db->query($sql);
    if ($resql) {
        while ($obj = $db->fetch_object($resql)) {
            $name = trim(dolGetFirstLastname($obj->firstname, $obj->lastname));
            if ($name === '') {
                $name = $obj->login;
            }
            $users[(int) $obj->rowid] = [
                'id'        => (int) $obj->rowid,
                'name'      => $name,
                'login'     => $obj->login,
                'email'     => (string) $obj->email,
                'color'     => dolical_user_color((int) $obj->rowid, (string) $obj->color),
                'baseColor' => dolical_base_color((int) $obj->rowid, (string) $obj->color),
                'initials'  => dolical_initials($name),
            ];
        }
        $db->free($resql);
    }

    // The current user opens the list : his agenda is the one shown first
    if (isset($users[$user->id])) {
        $users = [$user->id => $users[$user->id]] + $users;
    }

    return $users;
}

/**
 * Keep the ids of active users of the entity only : what comes from the browser is not trusted
 *
 * @param  int[] $userIds Ids to check
 * @return int[]          Ids of active users, in the given order
 */
function dolical_filter_active_users(array $userIds): array
{
    global $db;

    $userIds = array_values(array_unique(array_filter(array_map('intval', $userIds))));
    if (empty($userIds)) {
        return [];
    }

    $sql  = 'SELECT rowid FROM ' . MAIN_DB_PREFIX . 'user';
    $sql .= ' WHERE rowid IN (' . $db->sanitize(implode(',', $userIds)) . ')';
    $sql .= ' AND statut = 1 AND entity IN (' . getEntity('user') . ')';

    $active = [];
    $resql  = $db->query($sql);
    if ($resql) {
        while ($obj = $db->fetch_object($resql)) {
            $active[] = (int) $obj->rowid;
        }
        $db->free($resql);
    }

    return array_values(array_intersect($userIds, $active));
}

/**
 * User groups with their members, to show the agenda of a whole team in one click
 *
 * @param  User  $user  Current user
 * @param  array $users People the user may display, from dolical_get_calendar_users()
 * @return array        [['id', 'name', 'members' => [ids]]]
 */
function dolical_get_calendar_groups(User $user, array $users): array
{
    global $db;

    if (!dolical_can_read_all($user) || empty($users)) {
        return [];
    }

    $sql  = 'SELECT g.rowid, g.nom as name, ugu.fk_user';
    $sql .= ' FROM ' . MAIN_DB_PREFIX . 'usergroup as g';
    $sql .= ' INNER JOIN ' . MAIN_DB_PREFIX . 'usergroup_user as ugu ON ugu.fk_usergroup = g.rowid';
    $sql .= ' WHERE g.entity IN (' . getEntity('usergroup') . ')';
    $sql .= ' ORDER BY g.nom';

    $groups = [];
    $resql  = $db->query($sql);
    if ($resql) {
        while ($obj = $db->fetch_object($resql)) {
            if (!isset($users[(int) $obj->fk_user])) {
                continue;
            }
            if (!isset($groups[(int) $obj->rowid])) {
                $groups[(int) $obj->rowid] = ['id' => (int) $obj->rowid, 'name' => $obj->name, 'members' => []];
            }
            $groups[(int) $obj->rowid]['members'][] = (int) $obj->fk_user;
        }
        $db->free($resql);
    }

    return array_values($groups);
}

/**
 * Event types of the dictionary the user can pick, the automatic ones excluded
 *
 * @return array [code => ['code', 'label', 'color', 'picto']]
 */
function dolical_get_event_types(): array
{
    global $db, $langs;

    $langs->load('agenda');

    $sql  = 'SELECT id, code, libelle as label, color, picto, type';
    $sql .= ' FROM ' . MAIN_DB_PREFIX . 'c_actioncomm';
    $sql .= ' WHERE active = 1';
    $sql .= " AND type <> 'systemauto'";
    $sql .= ' ORDER BY position, id';

    $types = [];
    $resql = $db->query($sql);
    if ($resql) {
        while ($obj = $db->fetch_object($resql)) {
            // Same rule as the dictionary : the translation of the code wins over the stored label
            $label = $langs->trans('Action' . $obj->code);
            if ($label === 'Action' . $obj->code) {
                $label = $obj->label;
            }
            $types[$obj->code] = [
                'code'  => $obj->code,
                'label' => $label,
                'color' => $obj->color ? '#' . ltrim($obj->color, '#') : '',
                'picto' => (string) $obj->picto,
            ];
        }
        $db->free($resql);
    }

    return $types;
}
