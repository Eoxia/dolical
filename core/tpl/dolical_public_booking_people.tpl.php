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
 * \file    core/tpl/dolical_public_booking_people.tpl.php
 * \ingroup dolical
 * \brief   Public booking page, first step : the person to meet.
 *
 * Rendered by public/booking.php (inherits its scope). Expects: $people, $langs, $conf.
 */
?>
<div class="dolical-public-page">
    <?php if (GETPOSTINT('cancelled')) : ?>
        <div class="dolical-public-notice dolical-public-notice--ok"><i class="fas fa-check-circle"></i><?php echo $langs->trans('DoliCalBookingCancelledNotice'); ?></div>
    <?php endif; ?>
    <div class="dolical-public-card">
        <div class="dolical-public-card__kicker"><i class="far fa-calendar-check pictofixedwidth"></i><?php echo $langs->trans('DoliCalBookingTitle'); ?></div>
        <h1><?php echo $langs->trans('DoliCalBookingChoosePerson'); ?></h1>

        <div class="dolical-booking-people">
            <?php foreach ($people as $person) : ?>
                <a class="dolical-booking-person" href="<?php echo dol_escape_htmltag(dolical_booking_public_url($person['id'], 1)); ?>">
                    <span class="dolical-booking-avatar dolical-booking-avatar--<?php echo abs($person['id']) % 12; ?>"><?php echo dol_escape_htmltag($person['initials']); ?></span>
                    <span class="dolical-booking-person__text">
                        <strong><?php echo dol_escape_htmltag($person['name']); ?></strong>
                        <?php if ($person['job'] !== '') : ?>
                            <span><?php echo dol_escape_htmltag($person['job']); ?></span>
                        <?php endif; ?>
                    </span>
                    <i class="fas fa-chevron-right dolical-booking-person__arrow"></i>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
</div>
