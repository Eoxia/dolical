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
 * \file    core/tpl/dolical_booking_template.tpl.php
 * \ingroup dolical
 * \brief   Weekly template of the online booking, in the frame of the calendar : its toolbar (number of slots, time
 *          zone, cancel, save) and the week the script draws the slots in (js/modules/template.js), as the template of
 *          the appointments of Nextcloud.
 *
 * Rendered by admin/booking_template.php (inherits its scope). Expects: $config, $template, $permissionToWrite, $langs.
 */
?>
<form method="POST" action="<?php echo dol_escape_htmltag($_SERVER['PHP_SELF']); ?>" class="dolical-template-form">
    <input type="hidden" name="token" value="<?php echo newToken(); ?>">
    <input type="hidden" name="action" value="save">
    <input type="hidden" name="DOLICAL_BOOKING_SLOTS" value="<?php echo dol_escape_htmltag(dolical_booking_store_template($template)); ?>">

    <div class="dolical-main dolical-template-main">
        <header class="dolical-toolbar">
            <h2 class="dolical-toolbar__period"><?php echo $langs->trans('DoliCalTemplate'); ?></h2>
            <span class="dolical-badge dolical-template__count"></span>
            <div class="dolical-toolbar__spacer"></div>
            <span class="dolical-template__zone" title="<?php echo dol_escape_htmltag($langs->trans('DoliCalBookingTimezoneHelp')); ?>"><i class="fas fa-globe-europe"></i><?php echo dol_escape_htmltag(dolical_timezone_label(dolical_booking_timezone())); ?></span>
            <?php if ($permissionToWrite) : ?>
                <a class="dolical-btn dolical-template-cancel" href="<?php echo dol_escape_htmltag($_SERVER['PHP_SELF']); ?>"><?php echo $langs->trans('Cancel'); ?></a>
                <button type="submit" class="dolical-btn dolical-btn--primary"><i class="fas fa-save"></i><?php echo $langs->trans('Save'); ?></button>
            <?php endif; ?>
        </header>
        <?php if ($permissionToWrite) : ?>
            <p class="dolical-template__help"><i class="far fa-lightbulb"></i><span><?php echo $langs->trans('DoliCalTemplateHelp'); ?></span></p>
        <?php endif; ?>
        <div class="dolical-template<?php echo $permissionToWrite ? '' : ' is-readonly'; ?>" data-config="<?php echo dol_escape_htmltag(json_encode($config)); ?>"></div>
    </div>
</form>
<div class="dolical-toast"></div>
