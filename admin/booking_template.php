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
 * \file    admin/booking_template.php
 * \ingroup dolical
 * \brief   Weekly template of the online booking, as the one of the appointments of Nextcloud : the slots offered each
 *          day of the week, placed in a week (js/modules/template.js), saved at once
 */

// Load DoliCal environment
if (file_exists('../dolical.main.inc.php')) {
    require_once __DIR__ . '/../dolical.main.inc.php';
} elseif (file_exists('../../dolical.main.inc.php')) {
    require_once __DIR__ . '/../../dolical.main.inc.php';
} else {
    die('Include of dolical main fails');
}

require_once DOL_DOCUMENT_ROOT . '/core/lib/admin.lib.php';
require_once __DIR__ . '/../lib/dolical.lib.php';
require_once __DIR__ . '/../lib/dolical_booking.lib.php';

global $conf, $db, $langs, $user;

$langs->loadLangs(['dolical@dolical', 'admin', 'agenda']);

$action = GETPOST('action', 'aZ09');

// Security check : the right of the module shows the setup, changing it is for the administrators, as in Dolibarr
$permissionToRead  = $user->hasRight('dolical', 'adminpage', 'read') || $user->admin;
$permissionToWrite = (bool) $user->admin;
if (!$permissionToRead || $user->socid > 0) {
    accessforbidden();
}

/*
 * Actions
 */

if ($action === 'save' && $permissionToWrite && dolical_valid_post()) {
    // JSON of the page, read as numbers only and checked slot by slot
    $template = dolical_booking_clean_template(json_decode(GETPOST('DOLICAL_BOOKING_SLOTS', 'none'), true));
    dolibarr_set_const($db, 'DOLICAL_BOOKING_SLOTS', dolical_booking_store_template($template), 'chaine', 0, '', $conf->entity);
    // The hours of before the template are no longer read
    dolibarr_del_const($db, 'DOLICAL_BOOKING_DAYS', $conf->entity);
    dolibarr_del_const($db, 'DOLICAL_BOOKING_HOURS', $conf->entity);
    setEventMessages($langs->trans('DoliCalTemplateSaved'), null);
    if (empty(array_filter($template))) {
        setEventMessages($langs->trans('DoliCalBookingTemplateEmpty'), null, 'warnings');
    }
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

/*
 * View
 */

$template = dolical_booking_template();
$title    = $langs->trans('ModuleSetup', 'DoliCal');

$days = [];
foreach (range(1, 7) as $isoDay) {
    $days[$isoDay] = $langs->transnoentities('Day' . ($isoDay % 7));
}
$config = [
    'slots'    => (object) $template,
    'length'   => dolical_booking_duration(),
    // The hours out of the day of work greyed, as in the calendar
    'dayStart' => min(23, max(0, getDolGlobalInt('DOLICAL_DAY_START_HOUR', 8))),
    'dayEnd'   => min(24, max(1, getDolGlobalInt('DOLICAL_DAY_END_HOUR', 19))),
    // Name of the slots, as the name of the appointment of Nextcloud
    'label'    => dolical_booking_text('DOLICAL_BOOKING_TITLE') !== '' ? dolical_booking_text('DOLICAL_BOOKING_TITLE') : $langs->transnoentities('DoliCalBookingTitle'),
    'days'     => (object) $days,
    'canWrite' => $permissionToWrite,
    'i18n'     => [
        'slots'     => $langs->transnoentities('DoliCalTemplateSlots', '%s'),
        'none'      => $langs->transnoentities('DoliCalBookingDayClosed'),
        'start'     => $langs->transnoentities('DoliCalTemplateStart'),
        'end'       => $langs->transnoentities('DoliCalTemplateEnd'),
        'length'    => $langs->transnoentities('DoliCalTemplateLength'),
        'count'     => $langs->transnoentities('DoliCalTemplateCount'),
        'delete'    => $langs->transnoentities('Delete'),
        'cancel'    => $langs->transnoentities('Cancel'),
        'ok'        => $langs->transnoentities('Validate'),
        'menu'      => $langs->transnoentities('DoliCalTemplateDayMenu'),
        'addSeries' => $langs->transnoentities('DoliCalTemplateAddSeries'),
        'copyTo'    => $langs->transnoentities('DoliCalTemplateCopyTo'),
        'clearDay'  => $langs->transnoentities('DoliCalTemplateClearDay'),
        'clearAsk'  => $langs->transnoentities('DoliCalTemplateClearAsk', '%s'),
        'overlap'   => $langs->transnoentities('DoliCalTemplateOverlap'),
        'skipped'   => $langs->transnoentities('DoliCalTemplateSkipped', '%s'),
        'badTime'   => $langs->transnoentities('DoliCalTemplateBadTime'),
        'unsaved'   => $langs->transnoentities('DoliCalTemplateUnsaved'),
        'edit'      => $langs->transnoentities('DoliCalTemplateEditSlot'),
    ],
];

$js = [dolical_asset('js/dolical.js'), dolical_asset('js/modules/util.js'), dolical_asset('js/modules/template.js')];
llxHeader('', $title, '', '', 0, 0, $js, [dolical_asset('css/dolical.css')]);

$linkBack = '<a href="' . DOL_URL_ROOT . '/admin/modules.php?restore_lastsearch_values=1">' . $langs->trans('BackToModuleList') . '</a>';
print load_fiche_titre($title, $linkBack, 'title_setup');

print dol_get_fiche_head(dolical_admin_prepare_head(), 'template', $title, -1, 'dolical_color@dolical');

require __DIR__ . '/../core/tpl/dolical_booking_template.tpl.php';

print dol_get_fiche_end();
llxFooter();
$db->close();
