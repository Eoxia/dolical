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
 * \file    core/tpl/dolical_ask_modal.tpl.php
 * \ingroup dolical
 * \brief   Window that asks before an action of the calendar (confirm a slot, delete an event or a file), as the one of
 *          the undoing of an import : its title, what the action does, the button that does it. Filled by
 *          window.dolical.util.ask().
 *
 * Rendered by the calendar and by the page of one event. Expects: $langs.
 */
?>
<div class="dolical-modal dolical-confirm-modal dolical-ask-modal" id="dolical-ask-modal" role="dialog" aria-modal="true" aria-labelledby="dolical-ask-title">
    <div class="modal-container">
        <div class="modal-header">
            <h2 class="modal-title" id="dolical-ask-title"></h2>
            <div class="modal-close" title="<?php echo dol_escape_htmltag($langs->trans('Cancel')); ?>"><i class="fas fa-times"></i></div>
        </div>
        <div class="modal-content">
            <div class="dolical-confirm">
                <div class="dolical-confirm__icon"><i class="fas fa-question"></i></div>
                <div class="dolical-confirm__body">
                    <p class="dolical-confirm__text"></p>
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="dolical-btn modal-close"><?php echo $langs->trans('Cancel'); ?></button>
            <button type="button" class="dolical-btn dolical-btn--primary dolical-ask__confirm"><i class="fas fa-check"></i><span></span></button>
        </div>
    </div>
</div>
