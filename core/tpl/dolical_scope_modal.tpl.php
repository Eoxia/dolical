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
 * \file    core/tpl/dolical_scope_modal.tpl.php
 * \ingroup dolical
 * \brief   Choice of the dates of a series a change or a deletion applies to : this one, the following ones, all.
 *
 * Rendered by the calendar and by the page of one event. Expects: $langs.
 */
?>
<div class="dolical-modal dolical-scope-modal" id="dolical-scope-modal">
    <div class="modal-container">
        <div class="modal-header">
            <h2 class="modal-title"><i class="fas fa-redo-alt pictofixedwidth"></i><span class="dolical-scope__title" data-edit="<?php echo dol_escape_htmltag($langs->trans('DoliCalScopeEditTitle')); ?>" data-delete="<?php echo dol_escape_htmltag($langs->trans('DoliCalScopeDeleteTitle')); ?>"></span></h2>
            <div class="modal-close"><i class="fas fa-times"></i></div>
        </div>
        <div class="modal-content">
            <?php foreach (['one' => 'DoliCalScopeOne', 'following' => 'DoliCalScopeFollowing', 'all' => 'DoliCalScopeAll'] as $scope => $labelKey) : ?>
                <label class="dolical-scope__choice" data-scope="<?php echo $scope; ?>">
                    <input type="radio" name="dolical_scope" value="<?php echo $scope; ?>"<?php echo $scope === 'one' ? ' checked' : ''; ?>>
                    <span><?php echo $langs->trans($labelKey); ?></span>
                </label>
            <?php endforeach; ?>
        </div>
        <div class="modal-footer">
            <button type="button" class="dolical-btn modal-close"><?php echo $langs->trans('Cancel'); ?></button>
            <button type="button" class="dolical-btn dolical-btn--primary dolical-scope__confirm"><i class="fas fa-check"></i><?php echo $langs->trans('Validate'); ?></button>
        </div>
    </div>
</div>
