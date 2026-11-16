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
 * \file    public/booking.php
 * \ingroup dolical
 * \brief   Public page of the online booking : the customer chooses a person, a free slot of the week, gives his name
 *          and his email, and the appointment is in the agenda of the person. The link of the confirmation mail,
 *          signed for one contact and one event, shows the appointment and cancels it.
 */

if (!defined('NOREQUIREMENU')) {
    define('NOREQUIREMENU', '1');
}
if (!defined('NOLOGIN')) {
    define('NOLOGIN', '1');
}
// A public form : its own proof (dolical_booking_form_token()) and the signed link protect it, no session is needed
if (!defined('NOCSRFCHECK')) {
    define('NOCSRFCHECK', '1');
}
if (!defined('NOIPCHECK')) {
    define('NOIPCHECK', '1');
}
if (!defined('NOBROWSERNOTIF')) {
    define('NOBROWSERNOTIF', '1');
}

// For MultiCompany : the entity has to be known before main.inc.php, GETPOST does not exist yet
$entity = (!empty($_GET['entity']) ? (int) $_GET['entity'] : (!empty($_POST['entity']) ? (int) $_POST['entity'] : 1));
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

require_once DOL_DOCUMENT_ROOT . '/core/lib/company.lib.php';
require_once __DIR__ . '/../lib/dolical_booking.lib.php';

global $conf, $db, $langs, $mysoc;

$langs->loadLangs(['dolical@dolical', 'agenda', 'companies', 'errors', 'other']);

$action    = GETPOST('action', 'aZ09');
// The person comes from the key of his address : his id never shows
$userId    = dolical_booking_user_from_key(GETPOST('p', 'aZ09'));
$week      = max(0, GETPOSTINT('w'));
$slot      = GETPOSTINT('t');
$bookingId = GETPOSTINT('b');
$contactId = GETPOSTINT('c');
$signature = GETPOST('s', 'aZ09');
$isPost    = ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST';

$errors     = [];
$showScreen = 'closed';
$people     = dolical_booking_enabled() ? dolical_booking_people() : [];
$owner      = null;
$visitor    = [
    'name'    => dol_trunc(trim(GETPOST('name', 'alphanohtml')), 100, 'right', 'UTF-8', 1),
    'email'   => dol_trunc(trim(GETPOST('email', 'alphanohtml')), 128, 'right', 'UTF-8', 1),
    'phone'   => dol_trunc(trim(preg_replace('/[^0-9+().\s-]/', '', GETPOST('phone', 'alphanohtml'))), 30, 'right', 'UTF-8', 1),
    'company' => dol_trunc(trim(GETPOST('company', 'alphanohtml')), 100, 'right', 'UTF-8', 1),
    // Free text : its quotes kept, its HTML removed, escaped wherever it is shown
    'message' => dol_trunc(trim(GETPOST('message', 'nohtml')), 2000, 'right', 'UTF-8', 1),
];
// One line : the name and the company become the title of the event
$visitor['name']    = trim(preg_replace('/\s+/', ' ', $visitor['name']));
$visitor['company'] = trim(preg_replace('/\s+/', ' ', $visitor['company']));

// The anti-robot code of Dolibarr, when the setup asks for it (DOLICAL_BOOKING_CAPTCHA)
$captchaObject = null;
if (getDolGlobalInt('DOLICAL_BOOKING_CAPTCHA')) {
    require_once DOL_DOCUMENT_ROOT . '/core/lib/security2.lib.php';
    $handler = getDolGlobalString('MAIN_SECURITY_ENABLECAPTCHA_HANDLER', 'standard');
    $dirs    = array_merge(['main' => '/core/modules/security/captcha/'], is_array($conf->modules_parts['captcha'] ?? null) ? $conf->modules_parts['captcha'] : []);
    foreach ($dirs as $dir) {
        $classFile = dol_buildpath($dir . 'modCaptcha' . ucfirst($handler) . '.class.php', 0, 2);
        if ($classFile) {
            include_once $classFile;
            $className = 'modCaptcha' . ucfirst($handler);
            if (class_exists($className)) {
                $captchaObject = new $className($db, $conf, $langs, $user);
            }
            break;
        }
    }
}

/*
 * The appointment of a customer : the link of his confirmation mail
 */

$event  = null;
$valid  = false;
$isPast = false;
if ($bookingId > 0) {
    $showScreen = 'manage';
    if ($contactId > 0 && $signature !== '' && hash_equals(dolical_booking_signature((int) $conf->entity, $bookingId, $contactId), $signature)) {
        $event = new ActionComm($db);
        $valid = $event->fetch($bookingId) > 0 && (int) $event->entity === (int) $conf->entity && dolical_booking_is_booking($event) && isset($event->socpeopleassigned[$contactId]);
    }
    if ($valid) {
        $owner = new User($db);
        $valid = $owner->fetch((int) $event->userownerid) > 0;
    }
    $end    = $valid ? (int) ($event->datef ?: $event->datep) : 0;
    $isPast = $valid && $end < dol_now();
    // Readable a while after the appointment, as the link of an invitation
    $validity = getDolGlobalInt('DOLICAL_INVITATION_VALIDITY', 30);
    if ($isPast && $validity > 0 && $end + $validity * 86400 < dol_now()) {
        $valid = false;
    }
    $manageUrl = $valid ? dolical_booking_manage_url($event, $contactId) : '';

    if ($valid && !$isPast && GETPOSTINT('ics')) {
        header('Content-Type: text/calendar; charset=utf-8');
        header('Content-Disposition: attachment; filename="rendez-vous.ics"');
        echo dolical_build_ics(dolical_booking_customer_view($event, $owner), $owner, $manageUrl, dolical_invitation_sequence($event), 'PUBLISH', ['urlLabel' => 'DoliCalBookingIcsManage']);
        exit;
    }

    if ($valid && !$isPast && $action === 'cancel' && $isPost) {
        if (dolical_booking_cancel($event, $contactId)) {
            // Post, redirect, get : the page of the person, to book another slot
            header('Location: ' . dolical_booking_public_url(isset($people[(int) $owner->id]) ? (int) $owner->id : 0, 1) . '&cancelled=1');
        } else {
            header('Location: ' . $manageUrl . '&failed=1');
        }
        exit;
    }
} elseif (!empty($people)) {
    // Each person has his own link : the list of the people only when the setup asks for it
    $hub = dolical_booking_hub_enabled();
    if ($hub && $userId <= 0 && count($people) === 1) {
        $userId = (int) array_key_first($people);
    }
    if (!isset($people[$userId])) {
        $userId = 0;
    }
    $showScreen = $userId > 0 ? 'slots' : ($hub ? 'people' : 'closed');
}

/*
 * Booking of a slot
 */

if ($showScreen === 'slots') {
    $owner = new User($db);
    $owner->fetch($userId);
    $person = $people[$userId];

    if ($action === 'book' && $isPost && $slot > 0) {
        // A field the visitors do not see : only a robot fills it
        if (GETPOST('website', 'alphanohtml') !== '' || !dolical_booking_check_form_token(GETPOST('ft', 'alphanohtml'), $userId, $slot)) {
            $errors[] = $langs->trans('DoliCalBookingFormExpired');
        }
        if ($visitor['name'] === '') {
            $errors[] = $langs->trans('ErrorFieldRequired', $langs->transnoentities('DoliCalBookingName'));
        }
        if (!isValidEmail($visitor['email'])) {
            $errors[] = $langs->trans('ErrorBadEMail', dol_escape_htmltag($visitor['email']));
        }
        if (is_object($captchaObject) && method_exists($captchaObject, 'validateCodeAfterLoginSubmit') && !$captchaObject->validateCodeAfterLoginSubmit()) {
            $errors[] = $langs->trans('ErrorBadValueForCode');
        }
        if (empty($errors)) {
            $limit = dolical_booking_limit_reached($visitor['email']);
            if ($limit !== '') {
                $errors[] = $langs->trans($limit);
            }
        }
        if (empty($errors)) {
            $error  = '';
            $booked = dolical_booking_create($owner, $slot, $visitor, $error);
            if (!empty($booked)) {
                dolical_booking_record_ip();
                header('Location: ' . dolical_booking_manage_url($booked['event'], $booked['contactId']) . '&done=1');
                exit;
            }
            $errors[] = $langs->trans($error);
            if ($error === 'DoliCalBookingSlotTaken') {
                $slot = 0;
            }
        }
    }

    if ($slot > 0 && dolical_booking_is_free($userId, $slot)) {
        $showScreen = 'form';
        $formToken  = dolical_booking_form_token($userId, $slot);
    } else {
        if ($slot > 0 && empty($errors)) {
            $errors[] = $langs->trans('DoliCalBookingSlotTaken');
        }
        $slot = 0;

        $countSlots = function (array $days): int {
            return (int) array_sum(array_map('count', array_column($days, 'slots')));
        };
        $weekCount = dolical_booking_week_count();
        $week      = min($week, $weekCount - 1);
        $days      = dolical_booking_slots($userId, dolical_booking_week_start($week), 7);
        // The page opens on the first week that has a free slot, else on the first one
        if (!GETPOSTISSET('w') && $countSlots($days) === 0) {
            for ($offset = $week + 1; $offset < $weekCount; $offset++) {
                $next = dolical_booking_slots($userId, dolical_booking_week_start($offset), 7);
                if ($countSlots($next) > 0) {
                    $week = $offset;
                    $days = $next;
                    break;
                }
            }
        }
        $hasSlot   = $countSlots($days) > 0;
        $weekStart = dolical_booking_week_start($week);
        $openDays  = dolical_booking_days();
        $firstOpen = dolical_booking_window()[0];
    }
}

/*
 * View
 */

$pageUrl = dolical_booking_public_url(0, 1);
// The title the person wrote for his page, the one of his appointment on the page of a booking
$title   = dolical_booking_text('DOLICAL_BOOKING_TITLE', is_object($owner) ? (int) $owner->id : $userId);
if ($title === '') {
    $title = $langs->trans('DoliCalBookingTitle') . (!empty($mysoc->name) ? ' – ' . $mysoc->name : '');
}

$conf->dol_hide_topmenu  = 1;
$conf->dol_hide_leftmenu = 1;

llxHeader('', $title, '', '', 0, 0, [], [dolical_asset('css/dolical.css')], '', 'dolical-public', '<div>', 1);

require __DIR__ . '/../core/tpl/dolical_public_header.tpl.php';

$tplFile = __DIR__ . '/../core/tpl/dolical_public_booking_' . $showScreen . '.tpl.php';
if (is_readable($tplFile)) {
    require $tplFile;
}

llxFooter('', 'public');
$db->close();
