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
 * \file    core/tpl/dolical_public_booking_slots.tpl.php
 * \ingroup dolical
 * \brief   Public booking page, second step : the free slots of the person, one week at a time.
 *
 * Rendered by public/booking.php (inherits its scope). Expects: $person, $people, $userId, $days, $week, $weekCount,
 * $weekStart, $hasSlot, $openDays, $firstOpen, $errors, $langs.
 */

$zone       = dolical_booking_timezone();
$baseUrl    = dolical_booking_public_url($userId, 1);
$weekEnd    = $weekStart->modify('+6 days')->getTimestamp();
$sameMonth  = dolical_print_date($weekStart->getTimestamp(), '%Y%m', $zone) === dolical_print_date($weekEnd, '%Y%m', $zone);
$weekLabel  = ((int) dolical_print_date($weekStart->getTimestamp(), '%d', $zone)) . ($sameMonth ? '' : ' ' . dolical_print_date($weekStart->getTimestamp(), '%b', $zone));
$weekLabel .= ' – ' . ((int) dolical_print_date($weekEnd, '%d', $zone)) . ' ' . dolical_print_date($weekEnd, '%B %Y', $zone);
if (strpos((string) $langs->defaultlang, 'fr') === 0) {
    $weekLabel = dol_strtolower($weekLabel);
}
$dayList   = array_values($days);
$pageTitle = dolical_booking_text('DOLICAL_BOOKING_TITLE', $userId);
$intro     = dolical_booking_text('DOLICAL_BOOKING_INTRO', $userId);
$formTitle = dolical_booking_text('DOLICAL_BOOKING_FORM_TITLE', $userId);
?>
<div class="dolical-public-page dolical-public-page--wide">
    <?php if (GETPOSTINT('cancelled')) : ?>
        <div class="dolical-public-notice dolical-public-notice--ok"><i class="fas fa-check-circle"></i><?php echo $langs->trans('DoliCalBookingCancelledNotice'); ?></div>
    <?php endif; ?>
    <div class="dolical-public-card">
        <?php if (count($people) > 1 && dolical_booking_hub_enabled()) : ?>
            <a class="dolical-booking-back" href="<?php echo dol_escape_htmltag(dolical_booking_public_url(0, 1)); ?>"><i class="fas fa-chevron-left pictofixedwidth"></i><?php echo $langs->trans('DoliCalBookingOtherPerson'); ?></a>
        <?php endif; ?>

        <?php if ($pageTitle !== '') : ?>
            <h1 class="dolical-booking-title"><?php echo dol_escape_htmltag($pageTitle); ?></h1>
            <?php if ($intro !== '') : ?>
                <div class="dolical-booking-intro"><?php echo dolPrintHTML($intro); ?></div>
            <?php endif; ?>
        <?php endif; ?>

        <div class="dolical-booking-who<?php echo $pageTitle !== '' ? ' dolical-booking-who--compact' : ''; ?>">
            <span class="dolical-booking-avatar<?php echo $pageTitle !== '' ? '' : ' dolical-booking-avatar--large'; ?> dolical-booking-avatar--<?php echo abs($person['id']) % 12; ?>"><?php echo dol_escape_htmltag($person['initials']); ?></span>
            <div>
                <?php if ($pageTitle !== '') : ?>
                    <div class="dolical-booking-who__name"><?php echo str_replace('%NAME%', '<strong>' . dol_escape_htmltag($person['name']) . '</strong>', dol_escape_htmltag($langs->transnoentities('DoliCalBookingWithName', '%NAME%'))) . ($person['job'] !== '' ? ' <span class="dolical-booking-who__job">· ' . dol_escape_htmltag($person['job']) . '</span>' : ''); ?></div>
                <?php else : ?>
                    <div class="dolical-public-card__kicker"><?php echo $langs->trans('DoliCalBookingWithPerson'); ?></div>
                    <h1><?php echo dol_escape_htmltag($person['name']); ?></h1>
                <?php endif; ?>
                <div class="dolical-booking-who__meta">
                    <span><i class="far fa-clock pictofixedwidth"></i><?php echo dol_escape_htmltag(dolical_format_duration(dolical_booking_duration() * 60)); ?></span>
                    <span><i class="fas fa-globe-europe pictofixedwidth"></i><?php echo dol_escape_htmltag($langs->transnoentities('DoliCalBookingTimezone', $zone)); ?></span>
                </div>
            </div>
        </div>

        <?php if ($pageTitle === '' && $intro !== '') : ?>
            <div class="dolical-booking-intro"><?php echo dolPrintHTML($intro); ?></div>
        <?php endif; ?>
        <?php if ($formTitle !== '') : ?>
            <h2 class="dolical-booking-form-title"><?php echo dol_escape_htmltag($formTitle); ?></h2>
        <?php endif; ?>

        <?php foreach ($errors as $error) : ?>
            <div class="dolical-public-notice dolical-public-notice--error"><i class="fas fa-exclamation-circle"></i><?php echo $error; ?></div>
        <?php endforeach; ?>

        <div class="dolical-booking-week">
            <?php if ($week > 0) : ?>
                <a class="dolical-booking-week__nav" href="<?php echo dol_escape_htmltag($baseUrl . '&w=' . ($week - 1)); ?>" title="<?php echo dol_escape_htmltag($langs->trans('DoliCalBookingPreviousWeek')); ?>"><i class="fas fa-chevron-left"></i></a>
            <?php else : ?>
                <span class="dolical-booking-week__nav is-disabled"><i class="fas fa-chevron-left"></i></span>
            <?php endif; ?>
            <span class="dolical-booking-week__label"><?php echo dol_escape_htmltag($weekLabel); ?></span>
            <?php if ($week < $weekCount - 1) : ?>
                <a class="dolical-booking-week__nav" href="<?php echo dol_escape_htmltag($baseUrl . '&w=' . ($week + 1)); ?>" title="<?php echo dol_escape_htmltag($langs->trans('DoliCalBookingNextWeek')); ?>"><i class="fas fa-chevron-right"></i></a>
            <?php else : ?>
                <span class="dolical-booking-week__nav is-disabled"><i class="fas fa-chevron-right"></i></span>
            <?php endif; ?>
        </div>

        <div class="dolical-booking-days">
            <?php foreach ($dayList as $index => $day) : ?>
                <?php
                if (!in_array($index + 1, $openDays, true)) {
                    continue;
                }
                $isPastDay = $day['date'] + 86400 <= $firstOpen;
                ?>
                <div class="dolical-booking-day<?php echo $isPastDay ? ' is-past' : ''; ?>">
                    <div class="dolical-booking-day__head">
                        <span class="dolical-booking-day__name"><?php echo dol_escape_htmltag(dolical_print_date($day['date'], '%A', $zone)); ?></span>
                        <span class="dolical-booking-day__date"><?php echo dol_escape_htmltag(((int) dolical_print_date($day['date'], '%d', $zone)) . ' ' . dolical_print_date($day['date'], '%b', $zone)); ?></span>
                    </div>
                    <div class="dolical-booking-day__slots">
                        <?php foreach ($day['slots'] as $start) : ?>
                            <a class="dolical-booking-slot" href="<?php echo dol_escape_htmltag($baseUrl . '&w=' . $week . '&t=' . $start); ?>"><?php echo dolical_booking_hour($start); ?></a>
                        <?php endforeach; ?>
                        <?php if (empty($day['slots'])) : ?>
                            <span class="dolical-booking-day__empty"><?php echo $isPastDay ? '—' : $langs->trans($day['open'] ? 'DoliCalBookingDayFull' : 'DoliCalBookingDayClosed'); ?></span>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <?php if (!$hasSlot) : ?>
            <p class="dolical-public-card__muted dolical-booking-none"><?php echo $langs->trans($week < $weekCount - 1 ? 'DoliCalBookingNoSlotWeek' : 'DoliCalBookingNoSlot'); ?></p>
        <?php endif; ?>
    </div>
</div>
