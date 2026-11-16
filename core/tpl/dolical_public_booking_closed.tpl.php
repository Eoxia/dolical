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
 * \file    core/tpl/dolical_public_booking_closed.tpl.php
 * \ingroup dolical
 * \brief   Public booking page when nobody can be booked : the booking is off, or no person is offered.
 *
 * Rendered by public/booking.php (inherits its scope). Expects: $langs, $mysoc.
 */
?>
<div class="dolical-public-page">
    <?php if (GETPOSTINT('cancelled')) : ?>
        <div class="dolical-public-notice dolical-public-notice--ok"><i class="fas fa-check-circle"></i><?php echo $langs->trans('DoliCalBookingCancelledNotice'); ?></div>
    <?php endif; ?>
    <div class="dolical-public-card dolical-public-card--invalid">
        <i class="far fa-calendar-times dolical-public-card__big-icon"></i>
        <h1><?php echo $langs->trans('DoliCalBookingClosed'); ?></h1>
        <p><?php echo $langs->trans('DoliCalBookingClosedHelp'); ?></p>
        <?php if (!empty($mysoc->email) || !empty($mysoc->phone)) : ?>
            <p class="dolical-booking-contact">
                <?php if (!empty($mysoc->phone)) : ?>
                    <span><i class="fas fa-phone-alt pictofixedwidth"></i><?php echo dol_escape_htmltag($mysoc->phone); ?></span>
                <?php endif; ?>
                <?php if (!empty($mysoc->email)) : ?>
                    <a href="mailto:<?php echo dol_escape_htmltag($mysoc->email); ?>"><i class="far fa-envelope pictofixedwidth"></i><?php echo dol_escape_htmltag($mysoc->email); ?></a>
                <?php endif; ?>
            </p>
        <?php endif; ?>
    </div>
</div>
