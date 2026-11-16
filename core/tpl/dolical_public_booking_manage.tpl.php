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
 * \file    core/tpl/dolical_public_booking_manage.tpl.php
 * \ingroup dolical
 * \brief   Page of an appointment booked online, from the link of its confirmation : the appointment, the file for the
 *          calendar of the customer, and its cancellation.
 *
 * Rendered by public/booking.php (inherits its scope). Expects: $valid, $event, $owner, $isPast, $manageUrl,
 * $bookingId, $contactId, $signature, $conf, $langs.
 */
?>
<div class="dolical-public-page">
    <?php if (!$valid) : ?>
        <div class="dolical-public-card dolical-public-card--invalid">
            <i class="far fa-calendar-times dolical-public-card__big-icon"></i>
            <h1><?php echo $langs->trans('DoliCalBookingLinkInvalid'); ?></h1>
            <p><?php echo $langs->trans('DoliCalBookingLinkInvalidHelp'); ?></p>
            <?php if (dolical_booking_hub_enabled()) : ?>
                <a class="dolical-booking-submit dolical-booking-submit--inline" href="<?php echo dol_escape_htmltag(dolical_booking_public_url(0, 1)); ?>"><i class="far fa-calendar-plus pictofixedwidth"></i><?php echo $langs->trans('DoliCalBookingBookAgain'); ?></a>
            <?php endif; ?>
        </div>
    <?php else : ?>
        <?php
        $contact = new Contact($db);
        $contact->fetch($contactId);
        $start = (int) $event->datep;
        $end   = (int) ($event->datef ?: $event->datep);
        ?>
        <div class="dolical-public-card">
            <div class="dolical-public-card__kicker"><i class="far fa-calendar-check pictofixedwidth"></i><?php echo $langs->trans('DoliCalBookingYours'); ?></div>
            <h1><?php echo dol_escape_htmltag(dolical_booking_customer_view($event, $owner)->label); ?></h1>

            <?php if (GETPOSTINT('done')) : ?>
                <div class="dolical-public-notice dolical-public-notice--ok"><i class="fas fa-check-circle"></i><span><?php echo dol_escape_htmltag($langs->transnoentities('DoliCalBookingDone', (string) $contact->email)); ?></span></div>
            <?php elseif (GETPOSTINT('failed')) : ?>
                <div class="dolical-public-notice dolical-public-notice--error"><i class="fas fa-exclamation-circle"></i><?php echo $langs->trans('DoliCalBookingCancelFailed'); ?></div>
            <?php endif; ?>

            <div class="dolical-public-card__rows">
                <div class="dolical-public-card__row"><i class="far fa-calendar"></i><span><?php echo dol_escape_htmltag(dolical_booking_when($start, $end)); ?></span></div>
                <?php if (!empty($event->location)) : ?>
                    <div class="dolical-public-card__row"><i class="fas fa-map-marker-alt"></i><span><?php echo dol_escape_htmltag($event->location); ?></span></div>
                <?php endif; ?>
                <?php if (!empty($owner->job) || dolical_booking_text('DOLICAL_BOOKING_TITLE', (int) $owner->id) !== '') : ?>
                    <div class="dolical-public-card__row"><i class="far fa-user"></i><span><?php echo dol_escape_htmltag($owner->getFullName($langs) . (!empty($owner->job) ? ', ' . $owner->job : '')); ?></span></div>
                <?php endif; ?>
                <div class="dolical-public-card__row"><i class="fas fa-globe-europe"></i><span><?php echo dol_escape_htmltag($langs->transnoentities('DoliCalBookingTimezone', dolical_booking_timezone())); ?></span></div>
            </div>

            <?php if ($isPast) : ?>
                <p class="dolical-public-card__muted"><?php echo $langs->trans('DoliCalBookingPast'); ?></p>
            <?php elseif (GETPOSTINT('confirmcancel')) : ?>
                <form method="POST" action="<?php echo dol_escape_htmltag($_SERVER['PHP_SELF']); ?>" class="dolical-booking-cancel">
                    <input type="hidden" name="action" value="cancel">
                    <input type="hidden" name="entity" value="<?php echo (int) $conf->entity; ?>">
                    <input type="hidden" name="b" value="<?php echo (int) $bookingId; ?>">
                    <input type="hidden" name="c" value="<?php echo (int) $contactId; ?>">
                    <input type="hidden" name="s" value="<?php echo dol_escape_htmltag($signature); ?>">
                    <p><strong><?php echo $langs->trans('DoliCalBookingCancelQuestion'); ?></strong></p>
                    <div class="dolical-booking-actions">
                        <button type="submit" class="dolical-booking-button dolical-booking-button--danger"><i class="fas fa-times pictofixedwidth"></i><?php echo $langs->trans('DoliCalBookingCancelYes'); ?></button>
                        <a class="dolical-booking-button" href="<?php echo dol_escape_htmltag($manageUrl); ?>"><?php echo $langs->trans('DoliCalBookingCancelNo'); ?></a>
                    </div>
                </form>
            <?php else : ?>
                <div class="dolical-booking-actions">
                    <a class="dolical-booking-button" href="<?php echo dol_escape_htmltag($manageUrl . '&ics=1'); ?>"><i class="far fa-calendar-plus pictofixedwidth"></i><?php echo $langs->trans('DoliCalAddToCalendar'); ?></a>
                    <a class="dolical-booking-button dolical-booking-button--danger" href="<?php echo dol_escape_htmltag($manageUrl . '&confirmcancel=1'); ?>"><i class="fas fa-times pictofixedwidth"></i><?php echo $langs->trans('DoliCalBookingCancel'); ?></a>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>
