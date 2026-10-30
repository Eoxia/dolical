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
 * \file    core/tpl/dolical_public_invitation.tpl.php
 * \ingroup dolical
 * \brief   Public page of an invitation : the event, the current answer and the three buttons.
 *
 * Rendered by public/invitation.php (inherits its scope). Expects: $valid, $event, $title, $personName, $status,
 * $organizerName, $isPast, $linkParams, $pageUrl, $langs.
 */

$answers = [
    1  => ['icon' => 'fas fa-check', 'class' => 'accepted', 'button' => 'DoliCalAnswerYes', 'label' => 'DoliCalAnswerAccepted'],
    2  => ['icon' => 'fas fa-question', 'class' => 'tentative', 'button' => 'DoliCalMaybe', 'label' => 'DoliCalAnswerTentative'],
    -1 => ['icon' => 'fas fa-times', 'class' => 'declined', 'button' => 'DoliCalAnswerNo', 'label' => 'DoliCalAnswerDeclined'],
];
?>
<div class="dolical-public-page">
    <?php if (!$valid) : ?>
        <div class="dolical-public-card dolical-public-card--invalid">
            <i class="far fa-calendar-times dolical-public-card__big-icon"></i>
            <h1><?php echo $langs->trans('DoliCalInvitationInvalid'); ?></h1>
            <p><?php echo $langs->trans('DoliCalInvitationInvalidHelp'); ?></p>
        </div>
    <?php else : ?>
        <div class="dolical-public-card">
            <div class="dolical-public-card__kicker"><i class="far fa-envelope-open pictofixedwidth"></i><?php echo $langs->trans('DoliCalInvitationFor', dol_escape_htmltag($personName)); ?></div>
            <h1><?php echo dol_escape_htmltag($title); ?></h1>

            <div class="dolical-public-card__rows">
                <div class="dolical-public-card__row"><i class="far fa-calendar"></i><span><?php echo dol_escape_htmltag(!empty($event->recurid) ? dolical_series_when($event) : dolical_event_when($event)); ?></span></div>
                <?php if (!empty($event->location)) : ?>
                    <div class="dolical-public-card__row"><i class="fas fa-map-marker-alt"></i><span><?php echo dol_escape_htmltag($event->location); ?></span></div>
                <?php endif; ?>
                <?php if ($organizerName !== '') : ?>
                    <div class="dolical-public-card__row"><i class="fas fa-crown"></i><span><?php echo $langs->trans('DoliCalOrganizedBy', dol_escape_htmltag($organizerName)); ?></span></div>
                <?php endif; ?>
            </div>

            <?php if (GETPOSTINT('done')) : ?>
                <div class="dolical-public-notice dolical-public-notice--ok"><i class="fas fa-check-circle"></i><?php echo $langs->trans('DoliCalInvitationThanks'); ?></div>
            <?php elseif (GETPOSTINT('failed')) : ?>
                <div class="dolical-public-notice dolical-public-notice--error"><i class="fas fa-exclamation-circle"></i><?php echo $langs->trans('DoliCalInvitationFailed'); ?></div>
            <?php endif; ?>

            <div class="dolical-public-card__status dolical-public-card__status--<?php echo isset($answers[$status]) ? $answers[$status]['class'] : 'pending'; ?>">
                <?php echo $langs->trans('DoliCalYourAnswer'); ?>
                <strong><?php echo $langs->trans(isset($answers[$status]) ? $answers[$status]['label'] : 'DoliCalAnswerPending'); ?></strong>
            </div>

            <?php if ($isPast) : ?>
                <p class="dolical-public-card__muted"><?php echo $langs->trans('DoliCalInvitationPast'); ?></p>
            <?php else : ?>
                <form method="POST" action="<?php echo dol_escape_htmltag($pageUrl); ?>" class="dolical-public-answer">
                    <input type="hidden" name="action" value="answer">
                    <?php foreach ($linkParams as $name => $value) : ?>
                        <input type="hidden" name="<?php echo dol_escape_htmltag($name); ?>" value="<?php echo dol_escape_htmltag((string) $value); ?>">
                    <?php endforeach; ?>
                    <?php foreach ($answers as $value => $answer) : ?>
                        <button type="submit" name="status" value="<?php echo $value; ?>" class="dolical-public-answer__button dolical-public-answer__button--<?php echo $answer['class']; ?><?php echo $status === $value ? ' is-current' : ''; ?>">
                            <i class="<?php echo $answer['icon']; ?>"></i><?php echo $langs->trans($answer['button']); ?>
                        </button>
                    <?php endforeach; ?>
                </form>
            <?php endif; ?>

            <a class="dolical-public-card__ics" href="<?php echo dol_escape_htmltag($pageUrl . '&ics=1'); ?>"><i class="far fa-calendar-plus pictofixedwidth"></i><?php echo $langs->trans('DoliCalAddToCalendar'); ?></a>
        </div>
    <?php endif; ?>
</div>
