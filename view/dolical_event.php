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
 * \file    view/dolical_event.php
 * \ingroup dolical
 * \brief   One event alone, without the calendar : its form (action=create, or action=edit&id=) or its card (id=).
 *
 * Made to be opened in a small window : a link « Plan » of a card, or the window of another application that
 * embeds this page (iframe). Embedded, the page tells its parent what happens by postMessage (same origin only) :
 * {source: 'inbox-panel', action: 'title' | 'dirty' | 'done' | 'close'}. Alone, it comes back to the calendar.
 * Same parameters as the calendar for a new event : socid, projectid, contactids, elements, start, end, allday.
 */

// Load DoliCal environment
if (file_exists('../dolical.main.inc.php')) {
    require_once __DIR__ . '/../dolical.main.inc.php';
} elseif (file_exists('../../dolical.main.inc.php')) {
    require_once __DIR__ . '/../../dolical.main.inc.php';
} else {
    die('Include of dolical main fails');
}

require_once __DIR__ . '/../lib/dolical_event.lib.php';

global $conf, $db, $hookmanager, $langs, $user;

$langs->loadLangs(['dolical@dolical', 'agenda', 'companies', 'errors']);

// Security check : the calendar is for the internal users
$permissionToRead = $user->hasRight('dolical', 'read') && $user->hasRight('agenda', 'myactions', 'read');
if (!$permissionToRead || $user->socid > 0) {
    accessforbidden();
}

$action = GETPOST('action', 'aZ09');
$id     = GETPOSTINT('id');

// The other modules reach this page by the context of the form and the card : their scripts and styles (addHtmlHeader)
$hookmanager->initHooks(['dolicalevent']);

// What to show : a new event, the form of an event, or its card
$mode  = $id > 0 ? ($action === 'edit' ? 'edit' : 'card') : 'create';
$event = $id > 0 ? dolical_load_event($id, $user) : null;
if ($mode === 'create' && !dolical_can_create($user)) {
    accessforbidden();
}
if ($mode === 'edit' && $event !== null && !dolical_can_edit_event($user, $event)) {
    $mode = 'card';
}

/*
 * View
 */

$calendarUsers  = dolical_get_calendar_users($user);
$calendarGroups = dolical_get_calendar_groups($user, $calendarUsers);
$calendarConfig = dolical_calendar_config($user, $calendarUsers, $calendarGroups);

// The JavaScript of the page opens the form or the card at once (js/modules/single.js)
$calendarConfig['single'] = [
    'mode'     => $mode,
    'id'       => $event !== null ? (int) $event->id : 0,
    'missing'  => $id > 0 && $event === null,
    'calendar' => dol_buildpath('/dolical/dolicalindex.php', 1),
];

$title = $mode === 'create' ? $langs->trans('DoliCalNewEvent') : ($event !== null ? $event->label : $langs->trans('DoliCalCalendar'));

$conf->dol_hide_leftmenu = 1;

dolical_header($title, 'dolical-page dolical-page--single');
?>
<div class="dolical-single" data-config="<?php echo dol_escape_htmltag(json_encode($calendarConfig)); ?>">
    <?php if ($calendarConfig['single']['missing']) : ?>
        <div class="dolical-single__missing">
            <i class="far fa-calendar-times"></i>
            <p><?php echo $langs->trans('DoliCalEventGone'); ?></p>
            <button type="button" class="dolical-btn dolical-single__leave"><?php echo $langs->trans('CloseWindow'); ?></button>
        </div>
    <?php endif; ?>
    <a class="dolical-link dolical-single__back" href="<?php echo dol_buildpath('/dolical/dolicalindex.php', 1) . ($event !== null ? '?event=' . ((int) $event->id) : ''); ?>"><i class="fas fa-arrow-left pictofixedwidth"></i><?php echo $langs->trans('DoliCalBackToCalendar'); ?></a>
</div>

<div class="dolical-popover"></div>
<div class="dolical-toast"></div>

<div class="dolical-modal" id="dolical-event-modal">
    <div class="modal-container"></div>
</div>

<div class="dolical-modal" id="dolical-report-modal">
    <div class="modal-container"></div>
</div>

<?php require __DIR__ . '/../core/tpl/dolical_scope_modal.tpl.php'; ?>
<?php require __DIR__ . '/../core/tpl/dolical_ask_modal.tpl.php'; ?>
<?php
llxFooter();
$db->close();
