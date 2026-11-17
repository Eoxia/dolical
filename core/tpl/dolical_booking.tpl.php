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
 * \file    core/tpl/dolical_booking.tpl.php
 * \ingroup dolical
 * \brief   Page of a person for the online booking : his link, and his texts. Same layout as the page of the
 *          synchronization, whose script copies the link.
 *
 * Rendered by view/dolical_booking.php (inherits its scope). Expects: $title, $isSelf, $fromSetup, $isOpen, $isOffered,
 * $publicUrl, $shortUrl, $ownTexts, $ownVisio, $fields, $personId, $person, $candidates, $ownConflicts, $globalConflicts,
 * $form, $user, $langs.
 */
?>
<div class="dolical-sync dolical-booking-mine" data-config="<?php echo dol_escape_htmltag(json_encode(['i18n' => ['copied' => $langs->transnoentities('DoliCalCopied')]])); ?>">
    <?php // Back where the page was opened from : the setup of the online booking (its links, one of them or a colleague's), else the calendar ?>
    <?php $backToSetup = $fromSetup || !$isSelf; ?>
    <a class="dolical-booking-mine__back" href="<?php echo dol_buildpath($backToSetup ? '/dolical/admin/booking.php' : '/dolical/dolicalindex.php', 1) . ($backToSetup ? '#dolical-setup-links' : ''); ?>"><i class="fas fa-arrow-left pictofixedwidth"></i><?php echo $langs->trans($backToSetup ? 'DoliCalBackToSetup' : 'DoliCalBackToCalendar'); ?></a>

    <div class="dolical-sync__intro">
        <h1><i class="far fa-calendar-check pictofixedwidth"></i><?php echo dol_escape_htmltag($title); ?></h1>
        <p><?php echo $langs->trans($isSelf ? 'DoliCalBookingMineIntro' : 'DoliCalBookingPageOfIntro'); ?></p>
    </div>

    <?php if (!$isOpen || !$isOffered) : ?>
        <div class="dolical-sync__notice dolical-sync__notice--warning">
            <i class="fas fa-ban"></i>
            <span>
                <?php echo $langs->trans(!$isOpen ? 'DoliCalBookingMineClosed' : 'DoliCalBookingMineNotOffered'); ?>
                <?php if ($user->admin) : ?>
                    <a href="<?php echo dol_buildpath('/dolical/admin/booking.php', 1); ?>"><?php echo $langs->trans('DoliCalSetup'); ?></a>
                <?php endif; ?>
            </span>
        </div>
    <?php else : ?>
        <div class="dolical-sync__card">
            <h2><i class="fas fa-link pictofixedwidth"></i><?php echo $langs->trans($isSelf ? 'DoliCalBookingMineLink' : 'DoliCalBookingLinks'); ?></h2>
            <?php
            // The short link first when Saturne makes one, the full one under it
            $links = $shortUrl !== '' ? ['DoliCalBookingShortLink' => $shortUrl, 'DoliCalBookingFullLink' => $publicUrl] : ['Link' => $publicUrl];
            ?>
            <?php foreach ($links as $labelKey => $url) : ?>
                <div class="dolical-sync__field<?php echo $labelKey === 'DoliCalBookingFullLink' ? ' dolical-sync__field--secondary' : ''; ?>">
                    <span class="dolical-sync__label"><?php echo $langs->trans($labelKey); ?></span>
                    <code class="dolical-sync__value"><?php echo dol_escape_htmltag($url); ?></code>
                    <span class="nowraponall">
                        <button type="button" class="dolical-icon-btn dolical-copy" data-copy="<?php echo dol_escape_htmltag($url); ?>" title="<?php echo dol_escape_htmltag($langs->trans('DoliCalCopy')); ?>"><i class="far fa-copy"></i></button>
                        <a class="dolical-icon-btn" href="<?php echo dol_escape_htmltag($url); ?>" target="_blank" rel="noopener" title="<?php echo dol_escape_htmltag($langs->trans('DoliCalBookingMineOpen')); ?>"><i class="fas fa-external-link-alt"></i></a>
                    </span>
                </div>
            <?php endforeach; ?>
            <p class="dolical-card__muted"><?php echo $langs->trans('DoliCalBookingMineLinkHelp'); ?></p>
        </div>
    <?php endif; ?>

    <?php if (dolical_visio_available()) : ?>
        <div class="dolical-sync__card">
            <h2><i class="fas fa-video pictofixedwidth"></i><?php echo $langs->trans('DoliCalBookingVisio'); ?></h2>
            <p class="dolical-card__muted"><?php echo $langs->trans('DoliCalBookingVisioHelp'); ?></p>
            <form method="POST" action="<?php echo dol_escape_htmltag($_SERVER['PHP_SELF']); ?>" class="dolical-booking-texts">
                <input type="hidden" name="token" value="<?php echo newToken(); ?>">
                <input type="hidden" name="action" value="savevisio">
                <?php if (!$isSelf) : ?>
                    <input type="hidden" name="id" value="<?php echo (int) $personId; ?>">
                <?php endif; ?>
                <?php if ($fromSetup) : ?>
                    <input type="hidden" name="from" value="setup">
                <?php endif; ?>
                <label class="dolical-booking-texts__field">
                    <span><?php echo $langs->trans('DoliCalVisio'); ?></span>
                    <input type="text" inputmode="url" name="DOLICAL_BOOKING_VISIO" maxlength="<?php echo DOLICAL_VISIO_MAX; ?>" placeholder="<?php echo dol_escape_htmltag($langs->trans('DoliCalVisioPlaceholder')); ?>" value="<?php echo dol_escape_htmltag($ownVisio); ?>">
                </label>
                <div class="dolical-sync__actions">
                    <button type="submit" class="dolical-btn dolical-btn--primary"><i class="fas fa-save"></i><?php echo $langs->trans('Save'); ?></button>
                </div>
            </form>
        </div>
    <?php endif; ?>

    <div class="dolical-sync__card">
        <h2><i class="far fa-calendar-times pictofixedwidth"></i><?php echo $langs->trans('DoliCalBookingPersonConflicts'); ?></h2>
        <p class="dolical-card__muted"><?php echo $langs->trans('DoliCalBookingPersonConflictsHelp'); ?></p>
        <form method="POST" action="<?php echo dol_escape_htmltag($_SERVER['PHP_SELF']); ?>" class="dolical-booking-texts">
            <input type="hidden" name="token" value="<?php echo newToken(); ?>">
            <input type="hidden" name="action" value="saveconflicts">
            <?php if (!$isSelf) : ?>
                <input type="hidden" name="id" value="<?php echo (int) $personId; ?>">
            <?php endif; ?>
            <?php if ($fromSetup) : ?>
                <input type="hidden" name="from" value="setup">
            <?php endif; ?>
            <div class="dolical-booking-texts__field">
                <span><?php echo $langs->trans('DoliCalBookingPersonConflictsField'); ?></span>
                <?php echo $form->multiselectarray('DOLICAL_BOOKING_CONFLICT_USERS', $candidates, $ownConflicts, 0, 0, 'centpercent', 0, 0, '', '', $langs->trans('DoliCalBookingConflictsPlaceholder')); ?>
            </div>
            <?php if (!empty($globalConflicts)) : ?>
                <?php // The agendas of the setup take every page already : told, not chosen here ?>
                <p class="dolical-card__muted"><?php echo dol_escape_htmltag($langs->transnoentities('DoliCalBookingConflictsGlobal', implode(', ', $globalConflicts))); ?></p>
            <?php endif; ?>
            <div class="dolical-sync__actions">
                <button type="submit" class="dolical-btn dolical-btn--primary"><i class="fas fa-save"></i><?php echo $langs->trans('Save'); ?></button>
            </div>
        </form>
    </div>

    <div class="dolical-sync__card">
        <h2><i class="fas fa-pen pictofixedwidth"></i><?php echo $langs->trans('DoliCalBookingMineTexts'); ?></h2>
        <p class="dolical-card__muted"><?php echo $langs->trans('DoliCalBookingMineTextsHelp'); ?></p>
        <form method="POST" action="<?php echo dol_escape_htmltag($_SERVER['PHP_SELF']); ?>" class="dolical-booking-texts">
            <input type="hidden" name="token" value="<?php echo newToken(); ?>">
            <input type="hidden" name="action" value="savetexts">
            <?php if (!$isSelf) : ?>
                <input type="hidden" name="id" value="<?php echo (int) $personId; ?>">
            <?php endif; ?>
            <?php if ($fromSetup) : ?>
                <input type="hidden" name="from" value="setup">
            <?php endif; ?>
            <?php foreach ($fields as $constName => $labelKey) : ?>
                <?php
                // The common text shows in grey : what the customers read while the field is empty
                $common      = dolical_booking_text($constName);
                $placeholder = $common !== '' ? $common : $langs->transnoentities($labelKey . 'Example');
                ?>
                <?php if ($constName === 'DOLICAL_BOOKING_INTRO') : ?>
                    <?php // Not a label : a click in the toolbar of the editor would go to its hidden field ?>
                    <div class="dolical-booking-texts__field">
                        <span><?php echo $langs->trans($labelKey); ?></span>
                        <?php echo dolical_booking_intro_editor($constName, $ownTexts[$constName] ?? ''); ?>
                        <?php if (empty($ownTexts[$constName]) && $common !== '') : ?>
                            <div class="dolical-booking-texts__common">
                                <span class="dolical-card__muted"><?php echo $langs->trans('DoliCalBookingCommonText'); ?></span>
                                <div class="dolical-booking-intro"><?php echo dolPrintHTML($common); ?></div>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php else : ?>
                    <label class="dolical-booking-texts__field">
                        <span><?php echo $langs->trans($labelKey); ?></span>
                        <input type="text" name="<?php echo $constName; ?>" maxlength="<?php echo DOLICAL_BOOKING_TEXTS[$constName]; ?>" placeholder="<?php echo dol_escape_htmltag($placeholder); ?>" value="<?php echo dol_escape_htmltag($ownTexts[$constName] ?? ''); ?>">
                    </label>
                <?php endif; ?>
            <?php endforeach; ?>
            <div class="dolical-sync__actions">
                <button type="submit" class="dolical-btn dolical-btn--primary"><i class="fas fa-save"></i><?php echo $langs->trans('Save'); ?></button>
            </div>
        </form>
    </div>
</div>
