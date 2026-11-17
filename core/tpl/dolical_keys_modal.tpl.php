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
 * \file    core/tpl/dolical_keys_modal.tpl.php
 * \ingroup dolical
 * \brief   The keyboard shortcuts and the right click of the calendar, listed : opened by « ? » or the button of the
 *          toolbar (js/modules/shortcuts.js).
 *
 * Rendered by the calendar. Expects: $langs.
 */

// [title of the group, [[keys, label]]]
$keyGroups = [
    [$langs->trans('DoliCalShortcutsCalendar'), [
        [['N'], $langs->trans('DoliCalNewEvent')],
        [['T'], $langs->trans('Today')],
        [["\u{2190}", "\u{2192}"], $langs->trans('DoliCalKeyPrevNext')],
        [['1', '2', '3', '4', '5'], $langs->trans('DoliCalKeyViews')],
        [['/'], $langs->trans('DoliCalSearchEvents')],
        [['R'], $langs->trans('Refresh')],
        [['?'], $langs->trans('DoliCalKeyHelp')],
    ]],
    [$langs->trans('DoliCalShortcutsCard'), [
        [['E'], $langs->trans('Modify')],
        [['F'], $langs->trans('DoliCalKeyDoneReopen')],
        [['C'], $langs->trans('DoliCalConfirmSlots')],
        [[$langs->trans('DoliCalKeyDelete')], $langs->trans('Delete')],
        [[$langs->trans('DoliCalKeyEscape')], $langs->trans('DoliCalKeyClose')],
    ]],
    [$langs->trans('DoliCalShortcutsMouse'), [
        [[$langs->trans('DoliCalKeyRightClick')], $langs->trans('DoliCalKeyRightClickHelp')],
    ]],
];
?>
<div class="dolical-modal dolical-keys-modal" id="dolical-keys-modal" role="dialog" aria-modal="true" aria-labelledby="dolical-keys-title">
    <div class="modal-container">
        <div class="modal-header">
            <h2 class="modal-title" id="dolical-keys-title"><i class="far fa-keyboard pictofixedwidth"></i><?php echo $langs->trans('DoliCalShortcuts'); ?></h2>
            <div class="modal-close" title="<?php echo dol_escape_htmltag($langs->trans('DoliCalKeyClose')); ?>"><i class="fas fa-times"></i></div>
        </div>
        <div class="modal-content">
            <div class="dolical-keys">
                <?php foreach ($keyGroups as [$groupTitle, $rows]) : ?>
                    <p class="dolical-keys__section"><?php echo dol_escape_htmltag($groupTitle); ?></p>
                    <?php foreach ($rows as [$keys, $label]) : ?>
                        <div class="dolical-keys__row">
                            <span class="dolical-keys__label"><?php echo dol_escape_htmltag($label); ?></span>
                            <span class="dolical-keys__keys">
                                <?php foreach ($keys as $key) : ?>
                                    <kbd><?php echo dol_escape_htmltag($key); ?></kbd>
                                <?php endforeach; ?>
                            </span>
                        </div>
                    <?php endforeach; ?>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>
