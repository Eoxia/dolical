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
 * \file    dav/caldav.php
 * \ingroup dolical
 * \brief   CalDAV server of DoliCal, on the SabreDAV library shipped with Dolibarr : DAVx⁵ on Android, the calendar of
 *          an iPhone or of a Mac, Thunderbird... read and write the agenda of the person, with his Dolibarr login.
 *
 * Address given to the phone : https://<dolibarr>/custom/dolical/dav/caldav.php/
 */

if (!defined('NOTOKENRENEWAL')) {
    define('NOTOKENRENEWAL', '1');
}
if (!defined('NOREQUIREMENU')) {
    define('NOREQUIREMENU', '1');
}
if (!defined('NOREQUIREHTML')) {
    define('NOREQUIREHTML', '1');
}
if (!defined('NOREQUIREAJAX')) {
    define('NOREQUIREAJAX', '1');
}
if (!defined('NOLOGIN')) {
    // The phone authenticates with HTTP Basic, checked below as the login page would
    define('NOLOGIN', '1');
}
if (!defined('NOCSRFCHECK')) {
    define('NOCSRFCHECK', '1');
}
if (!defined('NOSESSION')) {
    // A phone keeps no cookie : no session file at every synchronization
    define('NOSESSION', '1');
}
if (!defined('NOBROWSERNOTIF')) {
    define('NOBROWSERNOTIF', '1');
}

$res = 0;
if (!$res && file_exists('../../../main.inc.php')) {
    $res = @include '../../../main.inc.php';
}
if (!$res && file_exists('../../../../main.inc.php')) {
    $res = @include '../../../../main.inc.php';
}
if (!$res) {
    die('Include of main fails');
}

global $conf, $db, $langs, $user;

require_once DOL_DOCUMENT_ROOT . '/core/lib/security2.lib.php';
require_once DOL_DOCUMENT_ROOT . '/includes/sabre/autoload.php';
require_once __DIR__ . '/../class/dolicalcaldavbackend.class.php';
require_once __DIR__ . '/../class/dolicalprincipalbackend.class.php';

if (!isModEnabled('dolical') || !isModEnabled('agenda') || !dolical_caldav_enabled()) {
    http_response_code(404);
    exit;
}

// Login and password of Dolibarr, with the same checks as the login page and the native DAV server
$authBackend = new \Sabre\DAV\Auth\Backend\BasicCallBack(static function ($login, $password) {
    global $conf, $dolibarr_main_authentication, $langs, $user;

    if ($login === '' || $password === '') {
        return false;
    }
    $authModes = explode(',', empty($dolibarr_main_authentication) || $dolibarr_main_authentication === 'openid_connect' ? 'dolibarr' : $dolibarr_main_authentication);
    if (in_array('forceuser', $authModes, true) || checkLoginPassEntity($login, $password, (int) $conf->entity, $authModes, 'dav') !== $login) {
        dol_syslog('DoliCal CalDAV: authentication failed for ' . $login, LOG_NOTICE);
        // The security events of Dolibarr record it, as a failure on the login page
        $langs->load('errors');
        $user->context['audit'] = $langs->trans('ErrorBadLoginPassword') . ' - login=' . dol_trunc(sanitizeVal((string) $login, 'alphanohtml'), 64) . ' (CalDAV)';
        $user->call_trigger('USER_LOGIN_FAILED', $user);
        return false;
    }
    if ($user->fetch(0, $login, '', 1) <= 0 || (int) $user->status !== User::STATUS_ENABLED || $user->socid > 0 || $user->isNotIntoValidityDateRange()) {
        return false;
    }
    $user->loadRights();
    if (!$user->hasRight('dolical', 'read') || !$user->hasRight('agenda', 'myactions', 'read')) {
        dol_syslog('DoliCal CalDAV: ' . $login . ' may not read the agenda', LOG_NOTICE);
        return false;
    }

    // The names of the agendas and of the report in the language of the person : a phone sends no useful one
    $langs->setDefaultLang(dolical_mail_language($user));
    $langs->loadLangs(['main', 'agenda', 'dolical@dolical']);

    return true;
});
$authBackend->setRealm('DoliCal');

$server = new \Sabre\DAV\Server([
    new \Sabre\CalDAV\Principal\Collection(new DoliCalPrincipalBackend($user)),
    new \Sabre\CalDAV\CalendarRoot(new DoliCalPrincipalBackend($user), new DoliCalCalDavBackend($user)),
]);
$server->setBaseUri(dol_buildpath('/dolical/dav/caldav.php', 1) . '/');

$server->addPlugin(new \Sabre\DAV\Auth\Plugin($authBackend));
$aclPlugin                        = new \Sabre\DAVACL\Plugin();
$aclPlugin->hideNodesFromListings = true;
$server->addPlugin($aclPlugin);
$server->addPlugin(new \Sabre\CalDAV\Plugin());

$server->start();

$db->close();
