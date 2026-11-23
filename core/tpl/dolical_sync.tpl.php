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
 * Rendered by view/dolical_sync.php (inherits its scope). Expects: $syncUrl, $calendarUrl, $isSecure, $httpAllowed,
 * $newPassword, $syncPassword, $davx5Link, $profileUrl, $canShare, $sharedWrite, $calendarUsers, $shared, $onPhone,
 * $phoneDevice, $user, $langs.
 */

// The names the phone shows : the agenda and the reminders of the person, named like the ones of the colleagues
$calendarName  = $langs->transnoentities('DoliCalCalDavCalendarOf', $user->getFullName($langs));
$remindersName = $langs->transnoentities('DoliCalCalDavRemindersOf', $user->getFullName($langs));

// A step is a translation key, or a key and its value
$devices = [
    'android'     => ['icon' => 'fab fa-android', 'title' => 'DoliCalSyncAndroid', 'steps' => ['DoliCalSyncAndroid1', 'DoliCalSyncAndroid2', 'DoliCalSyncAndroid3', ['DoliCalSyncAndroid4', $calendarName]]],
    'apple'       => ['icon' => 'fab fa-apple', 'title' => 'DoliCalSyncApple', 'steps' => ['DoliCalSyncApple1', 'DoliCalSyncApple2', 'DoliCalSyncApple3', 'DoliCalSyncApple4']],
    'thunderbird' => ['icon' => 'fas fa-envelope-open-text', 'title' => 'DoliCalSyncThunderbird', 'steps' => ['DoliCalSyncThunderbird1', 'DoliCalSyncThunderbird2', 'DoliCalSyncThunderbird3']],
];
// On a phone, the steps of that phone come first, the other devices folded below them
$ownDevice    = isset($devices[$phoneDevice]) ? [$phoneDevice => $devices[$phoneDevice]] : [];
$otherDevices = array_diff_key($devices, $ownDevice);

// A value to type in the device : its label, the value, and a button to copy it
$field = function (string $label, string $value, string $more = '') use ($langs): string {
    $secret = strpos($more, 'dolical-sync__field--password') !== false;

    return '<div class="dolical-sync__field' . $more . '"><span class="dolical-sync__label">' . $label . '</span>'
        . '<code class="dolical-sync__value' . ($secret ? ' dolical-sync__secret' : '') . '">' . dol_escape_htmltag($value) . '</code>'
        . '<button type="button" class="dolical-icon-btn dolical-copy" data-copy="' . dol_escape_htmltag($value) . '" title="' . dol_escape_htmltag($langs->trans('DoliCalCopy')) . '"><i class="far fa-copy"></i></button></div>';
};
// The phone set up in one scan : a QR code on a computer, a button on the phone itself (its own kind only when known)
$scans = [];
if ($newPassword !== '') {
    $scans['android'] = ['icon' => 'fab fa-android', 'title' => 'DoliCalSyncAndroid', 'link' => $davx5Link, 'button' => 'DoliCalSyncScanOpenDavx5', 'help' => $onPhone ? 'DoliCalSyncScanAndroidPhone' : 'DoliCalSyncScanAndroid'];
    $scans['apple']   = ['icon' => 'fab fa-apple', 'title' => 'DoliCalSyncApple', 'link' => $profileUrl, 'button' => 'DoliCalSyncScanInstallProfile', 'help' => $onPhone ? 'DoliCalSyncScanApplePhone' : 'DoliCalSyncScanApple'];
    if ($davx5Link === '') {
        unset($scans['android']);
    }
    if (isset($scans[$phoneDevice])) {
        $scans = [$phoneDevice => $scans[$phoneDevice]];
    }
}
// The scan of one kind of device
$scan = function (array $scan) use ($langs, $onPhone): string {
    $html = '<div class="dolical-sync__scan"><h3><i class="' . $scan['icon'] . ' pictofixedwidth"></i>' . $langs->trans($scan['title']) . '</h3>';
    if ($scan['link'] === '') {
        // No profile over plain HTTP : the steps below remain
        return $html . '<p class="dolical-card__muted">' . $langs->trans('DoliCalSyncScanAppleHttps') . '</p></div>';
    }
    if ($onPhone) {
        $html .= '<a class="dolical-btn dolical-btn--primary" href="' . dol_escape_htmltag($scan['link']) . '" rel="noopener noreferrer"><i class="' . $scan['icon'] . '"></i>' . $langs->trans($scan['button']) . '</a>';
    } else {
        $html .= dolical_qrcode_svg($scan['link'], $langs->transnoentities($scan['title']));
    }

    return $html . '<p class="dolical-card__muted">' . $langs->trans($scan['help'], (int) (DOLICAL_CALDAV_PROFILE_TTL / 60)) . '</p></div>';
};
$scansHtml = '';
if (!empty($scans)) {
    $scansHtml = '<div class="dolical-sync__scans' . ($onPhone ? ' dolical-sync__scans--first' : '') . '"><h2><i class="fas fa-qrcode pictofixedwidth"></i>'
        . $langs->trans($onPhone ? 'DoliCalSyncScanTitlePhone' : 'DoliCalSyncScanTitle') . '</h2><div class="dolical-sync__scans-list">'
        . implode('', array_map($scan, $scans)) . '</div></div>';
}
// The steps of one kind of device
$device = function (array $device) use ($langs): string {
    $html = '<section class="dolical-sync__device"><h2><i class="' . $device['icon'] . ' pictofixedwidth"></i>' . $langs->trans($device['title']) . '</h2><ol>';
    foreach ($device['steps'] as $step) {
        $html .= '<li>' . $langs->trans(...(array) $step) . '</li>';
    }

    return $html . '</ol></section>';
};
?>
<div class="dolical-sync<?php echo $onPhone ? ' dolical-sync--phone' : ''; ?>" data-config="<?php echo dol_escape_htmltag(json_encode(['i18n' => ['copied' => $langs->transnoentities('DoliCalCopied')]])); ?>">
    <div class="dolical-sync__intro">
        <h1><i class="fas fa-mobile-alt pictofixedwidth"></i><?php echo $langs->trans('DoliCalSyncPhone'); ?></h1>
        <p><?php echo $langs->trans('DoliCalSyncIntro'); ?></p>
    </div>

    <?php if (!dolical_caldav_enabled()) : ?>
        <div class="dolical-sync__notice dolical-sync__notice--warning"><i class="fas fa-ban"></i><?php echo $langs->trans('DoliCalSyncDisabled'); ?></div>
    <?php else : ?>
        <?php if (!$isSecure) : ?>
            <div class="dolical-sync__notice dolical-sync__notice--warning"><i class="fas fa-unlock"></i><?php echo $langs->trans($httpAllowed ? 'DoliCalSyncNotSecure' : 'DoliCalSyncHttpRefused'); ?></div>
        <?php endif; ?>

        <div class="dolical-sync__card dolical-sync__password">
            <h2><i class="fas fa-key pictofixedwidth"></i><?php echo $langs->trans('DoliCalSyncPasswordTitle'); ?></h2>
            <?php if ($newPassword !== '') : ?>
                <div class="dolical-sync__notice dolical-sync__notice--success"><i class="fas fa-exclamation-circle"></i><span><?php echo $langs->trans('DoliCalSyncPasswordShownOnce'); ?><?php echo $onPhone ? ' ' . $langs->trans('DoliCalSyncPasswordOnPhone') : ''; ?></span></div>
                <?php
                // On the phone, its button first : the values to copy are the way when it does not work
                echo $onPhone ? $scansHtml : '';
                // Everything the device asks for, together and in the order of its form : nothing to look for further down
                echo $field($langs->trans('DoliCalSyncServer'), $syncUrl);
                echo $field($langs->trans('Login'), (string) $user->login);
                echo $field($langs->trans('Password'), $newPassword, ' dolical-sync__field--password');
                echo $onPhone ? '' : $scansHtml;
                ?>
            <?php elseif ($syncPassword['hash'] !== '') : ?>
                <p><?php echo $langs->trans('DoliCalSyncPasswordActive', dol_print_date($syncPassword['date'], 'dayhour', 'tzuserrel')); ?></p>
            <?php else : ?>
                <p><?php echo $langs->trans('DoliCalSyncPasswordNone'); ?></p>
            <?php endif; ?>
            <p class="dolical-card__muted"><?php echo $langs->trans('DoliCalSyncPasswordHelp'); ?></p>
            <div class="dolical-sync__actions">
                <form method="POST" action="<?php echo dol_escape_htmltag($_SERVER['PHP_SELF']); ?>"<?php echo $syncPassword['hash'] !== '' ? ' data-confirm="' . dol_escape_htmltag($langs->transnoentities('DoliCalSyncPasswordConfirmNew')) . '"' : ''; ?>>
                    <input type="hidden" name="token" value="<?php echo newToken(); ?>">
                    <input type="hidden" name="action" value="caldavpassword">
                    <button type="submit" class="dolical-btn dolical-btn--primary"><i class="fas fa-key"></i><?php echo $langs->trans($syncPassword['hash'] !== '' ? 'DoliCalSyncPasswordRenew' : 'DoliCalSyncPasswordCreate'); ?></button>
                </form>
                <?php if ($syncPassword['hash'] !== '') : ?>
                    <form method="POST" action="<?php echo dol_escape_htmltag($_SERVER['PHP_SELF']); ?>" data-confirm="<?php echo dol_escape_htmltag($langs->transnoentities('DoliCalSyncPasswordConfirmRevoke')); ?>">
                        <input type="hidden" name="token" value="<?php echo newToken(); ?>">
                        <input type="hidden" name="action" value="caldavrevoke">
                        <button type="submit" class="dolical-btn"><i class="fas fa-ban"></i><?php echo $langs->trans('DoliCalSyncPasswordRevoke'); ?></button>
                    </form>
                <?php endif; ?>
            </div>
        </div>

        <div class="dolical-sync__card">
            <?php if ($newPassword === '') : ?>
                <?php echo $field($langs->trans('DoliCalSyncServer'), $syncUrl); ?>
                <?php echo $field($langs->trans('Login'), (string) $user->login); ?>
                <div class="dolical-sync__field">
                    <span class="dolical-sync__label"><?php echo $langs->trans('Password'); ?></span>
                    <span class="dolical-sync__value"><?php echo $langs->trans('DoliCalSyncPassword'); ?></span>
                </div>
            <?php endif; ?>
            <?php echo $field($langs->trans('DoliCalSyncCalendarUrl'), $calendarUrl, $newPassword === '' ? ' dolical-sync__field--secondary' : ''); ?>
        </div>

        <?php if (!empty($ownDevice)) : ?>
            <div class="dolical-sync__devices dolical-sync__devices--own">
                <?php echo $device(reset($ownDevice)); ?>
            </div>
            <details class="dolical-sync__others">
                <summary><?php echo $langs->trans('DoliCalSyncOtherDevices'); ?></summary>
                <div class="dolical-sync__devices">
                    <?php foreach ($otherDevices as $otherDevice) : ?>
                        <?php echo $device($otherDevice); ?>
                    <?php endforeach; ?>
                </div>
            </details>
        <?php else : ?>
            <div class="dolical-sync__devices">
                <?php foreach ($devices as $otherDevice) : ?>
                    <?php echo $device($otherDevice); ?>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <div class="dolical-sync__card">
            <h2><i class="fas fa-sync-alt pictofixedwidth"></i><?php echo $langs->trans('DoliCalSyncWhat'); ?></h2>
            <ul class="dolical-sync__list">
                <li><?php echo $langs->trans('DoliCalSyncWhat1'); ?></li>
                <li><?php echo $langs->trans('DoliCalSyncWhat2'); ?></li>
                <li><?php echo $langs->trans('DoliCalSyncWhat3'); ?></li>
                <li><?php echo $langs->trans('DoliCalSyncWhat4', DOLICAL_CALDAV_PAST_DAYS); ?></li>
                <li><?php echo $langs->trans('DoliCalSyncWhat5', $remindersName); ?></li>
            </ul>
        </div>

        <?php if ($canShare && count($calendarUsers) > 1) : ?>
            <form class="dolical-sync__card" method="POST" action="<?php echo dol_escape_htmltag($_SERVER['PHP_SELF']); ?>">
                <input type="hidden" name="token" value="<?php echo newToken(); ?>">
                <input type="hidden" name="action" value="saveshared">
                <h2><i class="fas fa-users pictofixedwidth"></i><?php echo $langs->trans('DoliCalSyncShared'); ?></h2>
                <p class="dolical-card__muted"><?php echo $langs->trans($sharedWrite ? 'DoliCalSyncSharedHelpWrite' : 'DoliCalSyncSharedHelp'); ?></p>
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
                <label class="dolical-check dolical-sync__shared-reminders">
                    <input type="checkbox" name="sharedreminders" value="1"<?php echo !empty($user->conf->DOLICAL_CALDAV_SHARED_REMINDERS) ? ' checked' : ''; ?>>
                    <span><?php echo $langs->trans($sharedWrite ? 'DoliCalSyncSharedRemindersWrite' : 'DoliCalSyncSharedReminders'); ?></span>
                </label>
                <button type="submit" class="dolical-btn dolical-btn--primary"><i class="fas fa-check"></i><?php echo $langs->trans('Save'); ?></button>
            </form>
        <?php endif; ?>
    <?php endif; ?>
</div>
<div class="dolical-toast"></div>
