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
 * \file    lib/dolical_report.lib.php
 * \ingroup dolical
 * \brief   Report of a meeting, written or dictated once it is over. The text goes to the extra field
 *          dolical_report of the native event, its photos, documents and voice notes to the documents of the
 *          event, through the media block of Saturne.
 */

require_once DOL_DOCUMENT_ROOT . '/core/class/extrafields.class.php';

/**
 * Whether the extra field of the report exists : it is created when the module is enabled
 *
 * @return bool
 */
function dolical_report_available(): bool
{
    global $db;

    static $available = null;
    if ($available === null) {
        $extrafields = new ExtraFields($db);
        $extrafields->fetch_name_optionals_label('actioncomm');
        $available = isset($extrafields->attributes['actioncomm']['label']['dolical_report']);
    }

    return $available;
}

/**
 * Report of an event
 *
 * @param  ActionComm $event Event
 * @return string            Plain text, empty when none was written
 */
function dolical_get_report(ActionComm $event): string
{
    if (!dolical_report_available() || empty($event->id)) {
        return '';
    }
    if (!isset($event->array_options['options_dolical_report'])) {
        $event->fetch_optionals();
    }

    return trim((string) ($event->array_options['options_dolical_report'] ?? ''));
}

/**
 * Write the report of an event, and mark it done when asked
 *
 * @param  ActionComm $event Event
 * @param  string     $text  Report, plain text
 * @param  bool       $done  Whether the event is done
 * @param  User       $user  Current user
 * @return int               > 0 when saved
 */
function dolical_save_report(ActionComm $event, string $text, bool $done, User $user): int
{
    if (!dolical_report_available()) {
        return -1;
    }

    $event->fetch_optionals();
    $event->array_options['options_dolical_report'] = trim($text) !== '' ? trim($text) : null;
    if ($event->updateExtraField('dolical_report', null, $user) <= 0) {
        return -1;
    }

    if ($done && $event->percentage >= 0 && $event->percentage < 100) {
        $event->oldcopy    = clone $event;
        $event->percentage = 100;
        return $event->update($user) > 0 ? 1 : -1;
    }

    return 1;
}

/**
 * Whether the user may write the report of an event : whoever may change it, or took part in it
 *
 * @param  User       $user  Current user
 * @param  ActionComm $event Event, resources loaded
 * @return bool
 */
function dolical_can_report(User $user, ActionComm $event): bool
{
    return dolical_report_available() && $event->type !== 'systemauto'
        && (dolical_can_edit_event($user, $event) || isset($event->userassigned[$user->id]) || (int) $event->userownerid === (int) $user->id);
}

/**
 * Whether the report of an event is awaited from the user : a meeting he took part in, over, without report
 *
 * @param  ActionComm $event Event, resources loaded
 * @param  User       $user  Current user
 * @return bool
 */
function dolical_report_due(ActionComm $event, User $user): bool
{
    if (!dolical_report_available() || $event->type === 'systemauto' || $event->percentage < 0) {
        return false;
    }
    $end = (int) ($event->datef ?: $event->datep);
    if ($end > dol_now() || $end < dol_now() - 30 * 86400) {
        return false;
    }
    $mine = (int) $event->userownerid === (int) $user->id || isset($event->userassigned[$user->id]);

    return $mine && dolical_get_report($event) === '';
}

/**
 * Ids of the events of a list that already have a report
 *
 * @param  int[] $ids Events
 * @return int[]
 */
function dolical_events_with_report(array $ids): array
{
    global $db;

    $ids = array_values(array_filter(array_map('intval', $ids)));
    if (empty($ids) || !dolical_report_available()) {
        return [];
    }

    $sql   = 'SELECT fk_object FROM ' . MAIN_DB_PREFIX . 'actioncomm_extrafields';
    $sql  .= ' WHERE fk_object IN (' . $db->sanitize(implode(',', $ids)) . ") AND dolical_report IS NOT NULL AND dolical_report <> ''";
    $resql = $db->query($sql);
    $found = [];
    if ($resql) {
        while ($obj = $db->fetch_object($resql)) {
            $found[] = (int) $obj->fk_object;
        }
        $db->free($resql);
    }

    return $found;
}

/**
 * Give the media block of Saturne the documents of the agenda : it stores into $conf->{module}->dir_output and
 * links its files with modulepart={module}, and "actions" is the modulepart document.php checks the rights of
 * the agenda for
 *
 * @return void
 */
function dolical_media_alias(): void
{
    global $conf;

    if (empty($conf->actions) || !is_object($conf->actions)) {
        $conf->actions = new stdClass();
    }
    $conf->actions->dir_output      = $conf->agenda->multidir_output[$conf->entity] ?? $conf->agenda->dir_output;
    $conf->actions->multidir_output = $conf->agenda->multidir_output ?? [];
}

/**
 * Media block of the report : photos (camera, several at once, edited before sending), documents and voice
 * notes, all stored with the attachments of the event
 *
 * @param  ActionComm $event   Event
 * @param  bool       $canEdit Whether the user may add and remove
 * @return string              HTML
 */
function dolical_report_media_block(ActionComm $event, bool $canEdit): string
{
    dolical_media_alias();

    $dir     = dolical_get_event_dir($event);
    $existed = is_dir($dir);

    $html = saturne_render_media_block('actions', (string) ((int) $event->id), 'ra-report', '', [
        'show_photo'   => true,
        'show_file'    => true,
        'show_audio'   => true,
        'show_upload'  => $canEdit,
        // The picking button opens the media library of DoliCal, a photo of the device joins it on its way
        'show_library' => true,
    ]);

    // The block makes its directory as soon as it is drawn : an event left without media keeps none
    if (!$existed && is_dir($dir) && count(scandir($dir)) <= 2) {
        dol_delete_dir($dir);
    }

    return $html;
}

/**
 * What the media block of a report asks the page : photos (camera, device or media library), documents, voice
 * notes, their removal. Everything lands in the documents of the event ; a photo also joins the media library of
 * DoliCal, as Saturne does for its own blocks
 *
 * @param  string     $action uploadPhoto, uploadFile, linkPhoto, deletePhoto, deleteFile, add_audio or delete_audio
 * @param  ActionComm $event  Event
 * @return void
 */
function dolical_report_media_action(string $action, ActionComm $event): void
{
    global $conf;

    $dir = dolical_get_event_dir($event);
    dol_mkdir($dir);
    $thumbs = function (string $path): void {
        foreach (['mini', 'small', 'medium', 'large'] as $size) {
            saturne_vignette($path, getDolGlobalInt('SATURNE_MEDIA_MAX_WIDTH_' . dol_strtoupper($size)), getDolGlobalInt('SATURNE_MEDIA_MAX_HEIGHT_' . dol_strtoupper($size)), '_' . $size);
        }
    };

    switch ($action) {
        case 'uploadPhoto':
            // Images only, told by their content : the camera button must not bring anything else in
            $files = isset($_FILES['userfile']['tmp_name']) ? (array) $_FILES['userfile']['tmp_name'] : [];
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            foreach ($files as $tmpName) {
                if ($tmpName !== '' && strpos((string) $finfo->file($tmpName), 'image/') !== 0) {
                    return;
                }
            }
            $overwrite = GETPOSTINT('overwrite') ? 1 : 0;
            if (dol_add_file_process($dir, $overwrite, 1, 'userfile', '', null, '', 1, $event) > 0) {
                foreach ((array) $_FILES['userfile']['name'] as $name) {
                    $path = $dir . '/' . dol_sanitizeFileName($name);
                    if (dol_is_file($path)) {
                        $thumbs($path);
                        saturne_add_media_to_library('dolical', $path, 0, !$overwrite);
                    }
                }
            }
            break;

        case 'uploadFile':
            dol_add_file_process($dir, 0, 1, 'userfile', '', null, '', 1, $event);
            break;

        case 'linkPhoto':
            // Photos of the media library of DoliCal : copied, the library keeps its own
            $library = $conf->ecm->multidir_output[$conf->entity] . '/dolical/medias';
            foreach (GETPOST('filenames', 'array') as $name) {
                $name = dol_sanitizeFileName($name);
                if ($name !== '' && image_format_supported($name) >= 0 && dol_is_file($library . '/' . $name) && dol_copy($library . '/' . $name, $dir . '/' . $name) > 0) {
                    $thumbs($dir . '/' . $name);
                }
            }
            break;

        case 'deletePhoto':
        case 'deleteFile':
        case 'delete_audio':
            // A file of the event only : its name, without any path
            $name = basename(GETPOST('filename', 'alphanohtml'));
            if ($name !== '' && dol_is_file($dir . '/' . $name)) {
                try {
                    dol_delete_file($dir . '/' . $name, 0, 0, 0, $event);
                } catch (Throwable $exception) {
                    // Accented name and ECM table still in latin1 : the lookup of the index fails, the file still goes
                    dol_delete_file($dir . '/' . $name, 0, 0, 0, null, false, 0);
                }
                foreach (['mini', 'small', 'medium', 'large'] as $size) {
                    dol_delete_file($dir . '/thumbs/' . saturne_get_thumb_name($name, $size), 0, 0, 0, null, false, 0);
                }
            }
            break;

        case 'add_audio':
            // Named as the media block lists its voice notes, whatever the browser recorded in
            if (!empty($_FILES['audio']['tmp_name']) && is_uploaded_file($_FILES['audio']['tmp_name'])) {
                move_uploaded_file($_FILES['audio']['tmp_name'], $dir . '/' . dol_print_date(dol_now(), 'dayhourlog') . '_audio.wav');
            }
            break;
    }
}

/**
 * Newest voice note of an event, the one just recorded for the dictation
 *
 * @param  ActionComm $event Event
 * @return string            Full path, empty when none
 */
function dolical_latest_voice_note(ActionComm $event): string
{
    $files = dol_dir_list(dolical_get_event_dir($event), 'files', 0, '_audio\.(wav|webm|ogg|mp3|m4a|mp4)$', '', 'date', SORT_DESC);

    return !empty($files) ? $files[0]['fullname'] : '';
}
