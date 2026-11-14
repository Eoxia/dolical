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
 * \file    view/dolical_import.php
 * \ingroup dolical
 * \brief   Import an agenda exported from Nextcloud, Google, Outlook... as an iCalendar file (.ics)
 */

// Load DoliCal environment
if (file_exists('../dolical.main.inc.php')) {
    require_once __DIR__ . '/../dolical.main.inc.php';
} elseif (file_exists('../../dolical.main.inc.php')) {
    require_once __DIR__ . '/../../dolical.main.inc.php';
} else {
    die('Include of dolical main fails');
}

require_once DOL_DOCUMENT_ROOT . '/core/lib/security.lib.php';
require_once __DIR__ . '/../lib/dolical_import.lib.php';

global $conf, $db, $langs, $user;

$langs->loadLangs(['dolical@dolical', 'agenda', 'errors']);

// Security check : the calendar is for the internal users, and an import creates events
$permissionToRead = $user->hasRight('dolical', 'read') && $user->hasRight('agenda', 'myactions', 'read');
if (!$permissionToRead || $user->socid > 0 || !dolical_can_create($user)) {
    accessforbidden();
}

$action = GETPOST('action', 'aZ09');

// The agenda of the user, or of a colleague with the right on the events of others
$owners = dolical_get_calendar_users($user);
if (!$user->hasRight('agenda', 'allactions', 'create')) {
    $owners = array_intersect_key($owners, [(int) $user->id => true]);
}

/*
 * Actions
 */

// Over post_max_size, PHP gives neither the file nor the fields
if (GETPOSTINT('uploadform') && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && empty($_POST) && empty($_FILES)) {
    setEventMessages($langs->trans('ErrorFileSizeTooLarge'), null, 'errors');
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

if ($action === 'import' && dolical_valid_post()) {
    $upload = $_FILES['icsfile'] ?? [];
    $status = (int) ($upload['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($status === UPLOAD_ERR_NO_FILE) {
        setEventMessages($langs->trans('ErrorFieldRequired', $langs->transnoentities('DoliCalImportFile')), null, 'errors');
    } elseif ($status !== UPLOAD_ERR_OK || !is_uploaded_file((string) $upload['tmp_name'])) {
        setEventMessages($langs->trans('ErrorFileSizeTooLarge'), null, 'errors');
    } elseif ((int) $upload['size'] > dolical_import_max_size() * 1024) {
        // Checked here too : the browser only warns
        setEventMessages($langs->trans('DoliCalImportFileTooLarge', price(round(dolical_import_max_size() / 1024, 1), 0, $langs, 0, 0) . ' ' . $langs->transnoentities('Mb')), null, 'errors');
    } else {
        $ownerId = GETPOSTINT('owner_id');
        $owner   = $user;
        if ($ownerId !== (int) $user->id && isset($owners[$ownerId])) {
            $owner = new User($db);
            $owner->fetch($ownerId);
        }

        // A whole agenda takes a while : longer than a page, never without end
        dolical_import_time_limit();
        $result = dolical_import_ics((string) $upload['tmp_name'], $owner, $user);
        if (isset($result['error'])) {
            setEventMessages($result['error'], null, 'errors');
        } else {
            $_SESSION['dolical_import'] = $result + ['file' => dol_sanitizeFileName((string) $upload['name']), 'owner' => $owner->getFullName($langs)];
        }
    }
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

// Undo an import : its events go, the dates of their series included
$canUndo = $user->hasRight('agenda', 'myactions', 'delete') || $user->hasRight('agenda', 'allactions', 'delete');
if ($action === 'undo' && $canUndo && dolical_valid_post()) {
    $key    = GETPOST('import_key', 'aZ09');
    $author = GETPOSTINT('import_author');
    $import = $key !== '' ? dolical_import_list($user, $key, $author) : [];
    if (empty($import)) {
        setEventMessages($langs->trans('ErrorRecordNotFound'), null, 'errors');
    } else {
        dolical_import_time_limit();
        $undone = dolical_import_undo($key, $author, $user);
        setEventMessages($langs->trans('DoliCalImportUndone', dol_print_date($import[0]['date'], 'dayhour', 'tzuser'), $undone['deleted']), null, $undone['deleted'] > 0 ? 'mesgs' : 'warnings');
        if ($undone['refused'] > 0) {
            setEventMessages($langs->trans('DoliCalImportUndoRefused', $undone['refused']), null, 'warnings');
        }
        if ($undone['failed'] > 0) {
            setEventMessages($langs->trans('DoliCalImportUndoFailed', $undone['failed']), null, 'errors');
        }
    }
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

/*
 * View
 */

// The result of the import just made, shown once
$result = $_SESSION['dolical_import'] ?? null;
unset($_SESSION['dolical_import']);

$maxSize = dolical_import_max_size();

// The imports whose events are still there, with the names of the people
$imports = $canUndo ? dolical_import_list($user) : [];
$people  = [];
foreach ($imports as $import) {
    foreach ([$import['owner'], $import['author']] as $personId) {
        if ($personId > 0 && !isset($people[$personId])) {
            $person = new User($db);
            $people[$personId] = $person->fetch($personId) > 0 ? $person->getFullName($langs) : '';
        }
    }
}

$title = $langs->trans('DoliCalImport');

dolical_header($title, 'dolical-page');

require __DIR__ . '/../core/tpl/dolical_import.tpl.php';

// End of page
llxFooter();
$db->close();
