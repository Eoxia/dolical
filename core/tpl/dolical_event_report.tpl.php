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
 * \file    core/tpl/dolical_event_report.tpl.php
 * \ingroup dolical
 * \brief   Report of a meeting, in its modal : the text, written or dictated, and the media block of Saturne for
 *          the photos, documents and voice notes, stored with the attachments of the event.
 *
 * Rendered by ajax/event.php (inherits its scope). Expects: $card from dolical_get_event_card(), $canDictate,
 * $mediaBlock, $langs.
 */

$people = array_column($card['participants']['people'], 'name');
?>
<div class="modal-header">
    <h2 class="modal-title"><i class="far fa-file-alt pictofixedwidth"></i><?php echo $langs->trans('DoliCalReport'); ?></h2>
    <div class="modal-close"><i class="fas fa-times"></i></div>
</div>
<div class="modal-content">
    <form class="dolical-report" data-id="<?php echo (int) $card['id']; ?>" data-dictate="<?php echo $canDictate ? 1 : 0; ?>" autocomplete="off" novalidate>
        <div class="dolical-report__event">
            <strong><?php echo dol_escape_htmltag($card['label']); ?></strong>
            <span class="dolical-card__muted"><?php echo dol_escape_htmltag(implode(' · ', $card['dateLines'])); ?></span>
            <?php if (!empty($people)) : ?>
                <span class="dolical-card__muted"><i class="fas fa-user-friends"></i> <?php echo dol_escape_htmltag(implode(', ', $people)); ?></span>
            <?php endif; ?>
        </div>

        <div class="dolical-report__dictation">
            <i class="fas fa-microphone-alt"></i>
            <span><?php echo $langs->trans($canDictate ? 'DoliCalReportDictateHelp' : 'DoliCalReportDictateNoAi'); ?></span>
        </div>

        <textarea name="report" rows="10" placeholder="<?php echo dol_escape_htmltag($langs->trans('DoliCalReportPlaceholder')); ?>"><?php echo dol_escape_htmltag($card['report'], 0, 1); ?></textarea>
        <div class="dolical-report__status" aria-live="polite"></div>

        <div class="dolical-report__media">
            <span class="dolical-report__media-title"><i class="fas fa-photo-video pictofixedwidth"></i><?php echo $langs->trans('DoliCalReportMedia'); ?></span>
            <div class="dolical-report__media-block"><?php echo $mediaBlock; ?></div>
            <label class="dolical-dropzone dolical-report__drop">
                <input type="file" class="dolical-report__files" multiple>
                <i class="fas fa-cloud-upload-alt"></i>
                <span><?php echo $langs->trans('DoliCalDropFiles'); ?></span>
            </label>
        </div>

        <?php if ($card['canEdit'] && $card['percent'] >= 0 && $card['percent'] < 100) : ?>
            <label class="dolical-check">
                <input type="checkbox" name="done" value="1" checked>
                <span><?php echo $langs->trans('DoliCalReportMarkDone'); ?></span>
            </label>
        <?php endif; ?>
    </form>
</div>
<div class="modal-footer">
    <span class="dolical-form__error"></span>
    <button type="button" class="dolical-btn modal-close"><?php echo $langs->trans('Cancel'); ?></button>
    <button type="button" class="dolical-btn dolical-btn--primary dolical-report__submit"><i class="fas fa-check"></i><?php echo $langs->trans('Save'); ?></button>
</div>
