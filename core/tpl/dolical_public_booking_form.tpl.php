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
 * \file    core/tpl/dolical_public_booking_form.tpl.php
 * \ingroup dolical
 * \brief   Public booking page, last step : the slot chosen, the name and the email of the customer.
 *
 * Rendered by public/booking.php (inherits its scope). Expects: $person, $userId, $week, $slot, $formToken, $visitor,
 * $errors, $captchaObject, $conf, $langs.
 */

$duration  = dolical_booking_duration();
$slotsUrl  = dolical_booking_public_url($userId, 1) . '&w=' . $week;
$pageTitle = dolical_booking_text('DOLICAL_BOOKING_TITLE', $userId);
?>
<div class="dolical-public-page">
    <div class="dolical-public-card">
        <a class="dolical-booking-back" href="<?php echo dol_escape_htmltag($slotsUrl); ?>"><i class="fas fa-chevron-left pictofixedwidth"></i><?php echo $langs->trans('DoliCalBookingOtherSlot'); ?></a>

        <div class="dolical-public-card__kicker"><?php echo $pageTitle !== '' ? dol_escape_htmltag($pageTitle) : $langs->trans('DoliCalBookingWithName', dol_escape_htmltag($person['name'])); ?></div>
        <h1><?php echo dol_escape_htmltag(dolical_booking_day($slot)); ?></h1>

        <div class="dolical-public-card__rows">
            <?php if ($pageTitle !== '') : ?>
                <div class="dolical-public-card__row"><i class="far fa-user"></i><span><?php echo $langs->trans('DoliCalBookingWithName', dol_escape_htmltag($person['name'])); ?></span></div>
            <?php endif; ?>
            <div class="dolical-public-card__row"><i class="far fa-clock"></i><span><?php echo dolical_booking_hour($slot) . ' – ' . dolical_booking_hour($slot + $duration * 60); ?> <span class="dolical-public-card__muted">(<?php echo dol_escape_htmltag(dolical_format_duration($duration * 60)); ?>)</span></span></div>
            <div class="dolical-public-card__row"><i class="fas fa-globe-europe"></i><span><?php echo $langs->trans('DoliCalBookingTimezone', dol_escape_htmltag(dolical_booking_timezone())); ?></span></div>
        </div>

        <?php foreach ($errors as $error) : ?>
            <div class="dolical-public-notice dolical-public-notice--error"><i class="fas fa-exclamation-circle"></i><?php echo $error; ?></div>
        <?php endforeach; ?>

        <form method="POST" action="<?php echo dol_escape_htmltag($_SERVER['PHP_SELF']); ?>" class="dolical-booking-form">
            <input type="hidden" name="action" value="book">
            <input type="hidden" name="entity" value="<?php echo (int) $conf->entity; ?>">
            <input type="hidden" name="p" value="<?php echo dol_escape_htmltag(dolical_booking_page_key($userId)); ?>">
            <input type="hidden" name="w" value="<?php echo (int) $week; ?>">
            <input type="hidden" name="t" value="<?php echo (int) $slot; ?>">
            <input type="hidden" name="ft" value="<?php echo dol_escape_htmltag($formToken); ?>">

            <!-- Left empty by the people : the robots fill every field -->
            <div class="dolical-booking-form__trap" aria-hidden="true">
                <label for="dolical-booking-website">Website</label>
                <input type="text" id="dolical-booking-website" name="website" value="" tabindex="-1" autocomplete="off">
            </div>

            <div class="dolical-booking-form__grid">
                <label class="dolical-booking-field">
                    <span><?php echo $langs->trans('DoliCalBookingName'); ?> <em>*</em></span>
                    <input type="text" name="name" required maxlength="100" autocomplete="name" value="<?php echo dol_escape_htmltag($visitor['name']); ?>">
                </label>
                <label class="dolical-booking-field">
                    <span><?php echo $langs->trans('Email'); ?> <em>*</em></span>
                    <input type="email" name="email" required maxlength="128" autocomplete="email" value="<?php echo dol_escape_htmltag($visitor['email']); ?>">
                </label>
                <label class="dolical-booking-field">
                    <span><?php echo $langs->trans('Phone'); ?></span>
                    <input type="tel" name="phone" maxlength="30" autocomplete="tel" value="<?php echo dol_escape_htmltag($visitor['phone']); ?>">
                </label>
                <label class="dolical-booking-field">
                    <span><?php echo $langs->trans('Company'); ?></span>
                    <input type="text" name="company" maxlength="100" autocomplete="organization" value="<?php echo dol_escape_htmltag($visitor['company']); ?>">
                </label>
            </div>
            <label class="dolical-booking-field">
                <span><?php echo $langs->trans('DoliCalBookingMessage'); ?></span>
                <textarea name="message" rows="4" maxlength="2000" placeholder="<?php echo dol_escape_htmltag($langs->trans('DoliCalBookingMessageHelp')); ?>"><?php echo dol_escape_htmltag($visitor['message']); ?></textarea>
            </label>

            <?php if (is_object($captchaObject) && method_exists($captchaObject, 'getCaptchaCodeForForm')) : ?>
                <div class="dolical-booking-captcha"><?php echo $captchaObject->getCaptchaCodeForForm(''); ?></div>
            <?php endif; ?>

            <button type="submit" class="dolical-booking-submit"><i class="fas fa-check pictofixedwidth"></i><?php echo $langs->trans('DoliCalBookingConfirm'); ?></button>
            <p class="dolical-public-card__muted dolical-booking-form__note"><?php echo $langs->trans('DoliCalBookingFormNote'); ?></p>
        </form>
    </div>
</div>
