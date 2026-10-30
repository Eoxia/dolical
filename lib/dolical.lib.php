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

    complete_head_from_modules($conf, $langs, null, $head, $h, 'dolical@dolical');

    complete_head_from_modules($conf, $langs, null, $head, $h, 'dolical@dolical', 'remove');

    return $head;
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
    foreach (['calendar', 'eventcard', 'eventform', 'report', 'sync', 'util'] as $module) {
        $js[] = dolical_asset('js/modules/' . $module . '.js');
    }

    llxHeader('', $title, '', '', 0, 0, $js, [dolical_asset('css/dolical.css')], '', $morecssonbody);
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

    return ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && $token !== '' && in_array($token, [currentToken(), newToken()], true);
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
    if (preg_match('/^#?([0-9a-f]{6})$/i', $userColor, $matches)) {
        return '#' . strtolower($matches[1]);
    }

    // Distinct enough side by side, dark enough to carry a white label
    $palette = ['#63acc9', '#e07a5f', '#81b29a', '#9b5de5', '#f2a541', '#3d5a80', '#d64f7a', '#2a9d8f', '#8d6e63', '#5c7cfa', '#c0a020', '#65a30d'];

    return $palette[abs($userId) % count($palette)];
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
 * @return array       [id => ['id', 'name', 'login', 'email', 'color', 'initials']], current user first
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
                'id'       => (int) $obj->rowid,
                'name'     => $name,
                'login'    => $obj->login,
                'email'    => (string) $obj->email,
                'color'    => dolical_user_color((int) $obj->rowid, (string) $obj->color),
                'initials' => dolical_initials($name),
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
