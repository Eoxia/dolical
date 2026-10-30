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
 * \file    core/tpl/dolical_sync.tpl.php
 * \ingroup dolical
 * \brief   Page of the synchronization : address and login, steps for each kind of device, agendas of the colleagues.
 *
 * Rendered by view/dolical_sync.php (inherits its scope). Expects: $syncUrl, $calendarUrl, $isSecure, $canShare,
 * $calendarUsers, $shared, $user, $langs.
 */

$devices = [
    'android'     => ['icon' => 'fab fa-android', 'title' => 'DoliCalSyncAndroid', 'steps' => ['DoliCalSyncAndroid1', 'DoliCalSyncAndroid2', 'DoliCalSyncAndroid3', 'DoliCalSyncAndroid4']],
    'apple'       => ['icon' => 'fab fa-apple', 'title' => 'DoliCalSyncApple', 'steps' => ['DoliCalSyncApple1', 'DoliCalSyncApple2', 'DoliCalSyncApple3', 'DoliCalSyncApple4']],
    'thunderbird' => ['icon' => 'fas fa-envelope-open-text', 'title' => 'DoliCalSyncThunderbird', 'steps' => ['DoliCalSyncThunderbird1', 'DoliCalSyncThunderbird2', 'DoliCalSyncThunderbird3']],
];
?>
<div class="dolical-sync" data-config="<?php echo dol_escape_htmltag(json_encode(['i18n' => ['copied' => $langs->transnoentities('DoliCalCopied')]])); ?>">
    <div class="dolical-sync__intro">
        <h1><i class="fas fa-mobile-alt pictofixedwidth"></i><?php echo $langs->trans('DoliCalSyncPhone'); ?></h1>
        <p><?php echo $langs->trans('DoliCalSyncIntro'); ?></p>
    </div>

    <?php if (!dolical_caldav_enabled()) : ?>
        <div class="dolical-sync__notice dolical-sync__notice--warning"><i class="fas fa-ban"></i><?php echo $langs->trans('DoliCalSyncDisabled'); ?></div>
    <?php else : ?>
        <?php if (!$isSecure) : ?>
            <div class="dolical-sync__notice dolical-sync__notice--warning"><i class="fas fa-unlock"></i><?php echo $langs->trans('DoliCalSyncNotSecure'); ?></div>
        <?php endif; ?>

        <div class="dolical-sync__card">
            <div class="dolical-sync__field">
                <span class="dolical-sync__label"><?php echo $langs->trans('DoliCalSyncServer'); ?></span>
                <code class="dolical-sync__value"><?php echo dol_escape_htmltag($syncUrl); ?></code>
                <button type="button" class="dolical-icon-btn dolical-copy" data-copy="<?php echo dol_escape_htmltag($syncUrl); ?>" title="<?php echo dol_escape_htmltag($langs->trans('DoliCalCopy')); ?>"><i class="far fa-copy"></i></button>
            </div>
            <div class="dolical-sync__field">
                <span class="dolical-sync__label"><?php echo $langs->trans('Login'); ?></span>
                <code class="dolical-sync__value"><?php echo dol_escape_htmltag($user->login); ?></code>
                <button type="button" class="dolical-icon-btn dolical-copy" data-copy="<?php echo dol_escape_htmltag($user->login); ?>" title="<?php echo dol_escape_htmltag($langs->trans('DoliCalCopy')); ?>"><i class="far fa-copy"></i></button>
            </div>
            <div class="dolical-sync__field">
                <span class="dolical-sync__label"><?php echo $langs->trans('Password'); ?></span>
                <span class="dolical-sync__value"><?php echo $langs->trans('DoliCalSyncPassword'); ?></span>
            </div>
            <div class="dolical-sync__field dolical-sync__field--secondary">
                <span class="dolical-sync__label"><?php echo $langs->trans('DoliCalSyncCalendarUrl'); ?></span>
                <code class="dolical-sync__value"><?php echo dol_escape_htmltag($calendarUrl); ?></code>
                <button type="button" class="dolical-icon-btn dolical-copy" data-copy="<?php echo dol_escape_htmltag($calendarUrl); ?>" title="<?php echo dol_escape_htmltag($langs->trans('DoliCalCopy')); ?>"><i class="far fa-copy"></i></button>
            </div>
        </div>

        <div class="dolical-sync__devices">
            <?php foreach ($devices as $key => $device) : ?>
                <section class="dolical-sync__device">
                    <h2><i class="<?php echo $device['icon']; ?> pictofixedwidth"></i><?php echo $langs->trans($device['title']); ?></h2>
                    <ol>
                        <?php foreach ($device['steps'] as $step) : ?>
                            <li><?php echo $langs->trans($step); ?></li>
                        <?php endforeach; ?>
                    </ol>
                </section>
            <?php endforeach; ?>
        </div>

        <div class="dolical-sync__card">
            <h2><i class="fas fa-sync-alt pictofixedwidth"></i><?php echo $langs->trans('DoliCalSyncWhat'); ?></h2>
            <ul class="dolical-sync__list">
                <li><?php echo $langs->trans('DoliCalSyncWhat1'); ?></li>
                <li><?php echo $langs->trans('DoliCalSyncWhat2'); ?></li>
                <li><?php echo $langs->trans('DoliCalSyncWhat3'); ?></li>
                <li><?php echo $langs->trans('DoliCalSyncWhat4', DOLICAL_CALDAV_PAST_DAYS); ?></li>
            </ul>
        </div>

        <?php if ($canShare && count($calendarUsers) > 1) : ?>
            <form class="dolical-sync__card" method="POST" action="<?php echo dol_escape_htmltag($_SERVER['PHP_SELF']); ?>">
                <input type="hidden" name="token" value="<?php echo newToken(); ?>">
                <input type="hidden" name="action" value="saveshared">
                <h2><i class="fas fa-users pictofixedwidth"></i><?php echo $langs->trans('DoliCalSyncShared'); ?></h2>
                <p class="dolical-card__muted"><?php echo $langs->trans('DoliCalSyncSharedHelp'); ?></p>
                <div class="dolical-sync__people">
                    <?php foreach ($calendarUsers as $calendarUser) : ?>
                        <?php if ($calendarUser['id'] === (int) $user->id) {
                            continue;
                        } ?>
                        <label class="dolical-check">
                            <input type="checkbox" name="shared[]" value="<?php echo $calendarUser['id']; ?>"<?php echo in_array($calendarUser['id'], $shared, true) ? ' checked' : ''; ?>>
                            <span class="dolical-dot" data-color="<?php echo dol_escape_htmltag($calendarUser['color']); ?>"></span>
                            <span><?php echo dol_escape_htmltag($calendarUser['name']); ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
                <button type="submit" class="dolical-btn dolical-btn--primary"><i class="fas fa-check"></i><?php echo $langs->trans('Save'); ?></button>
            </form>
        <?php endif; ?>
    <?php endif; ?>
</div>
<div class="dolical-toast"></div>
