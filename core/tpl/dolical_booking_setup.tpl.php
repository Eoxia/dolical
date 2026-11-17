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
 * \file    core/tpl/dolical_booking_setup.tpl.php
 * \ingroup dolical
 * \brief   Setup of the online booking : a card for each section of the appointments of Nextcloud, the sections listed
 *          on the side, the save button always in sight.
 *
 * Rendered by admin/booking.php (inherits its scope). Expects: $switches, $numbers, $form, $slotCount, $slotDays,
 * $links, $permissionToWrite, $langs.
 */

// The lines, switches and numbers of the setup pages (lib/dolical.lib.php), from keys of translation and constants
$setting = function (string $labelKey, string $helpHtml, string $field, string $layout = 'inline', string $depends = '') use ($langs): string {
    return dolical_setting($langs->trans($labelKey), $helpHtml, $field, $layout, $depends);
};
$switch = function (string $constName, string $labelKey = '') use ($langs, $switches): string {
    return dolical_setting_switch($constName, (bool) getDolGlobalInt($constName, $switches[$constName] ?? 0), $labelKey !== '' ? $langs->trans($labelKey) : '');
};
$number = function (string $constName, string $unitKey = '') use ($langs, $numbers): string {
    [$default, $min, $max] = $numbers[$constName];
    return dolical_setting_number($constName, getDolGlobalInt($constName, $default), $min, $max, $unitKey !== '' ? $langs->trans($unitKey) : '');
};

/**
 * A text of one line
 *
 * @param  string $constName      Constant
 * @param  string $placeholderKey Key of translation of the example ('' for none)
 * @return string
 */
$text = function (string $constName, string $placeholderKey = '') use ($langs): string {
    $max = DOLICAL_BOOKING_SETTING_TEXTS[$constName] ?? DOLICAL_BOOKING_TEXTS[$constName] ?? 255;
    return '<input type="text" name="' . $constName . '" maxlength="' . $max . '" placeholder="' . dol_escape_htmltag($placeholderKey !== '' ? $langs->trans($placeholderKey) : '') . '" value="' . dol_escape_htmltag(dolical_booking_text($constName)) . '">';
};

/**
 * A text of several lines : its lines kept, without keepn the escape writes them as « \n » and a save would store that
 *
 * @param  string $constName      Constant
 * @param  string $placeholderKey Key of translation of the example ('' for none)
 * @return string
 */
$textarea = function (string $constName, string $placeholderKey = '') use ($langs): string {
    return '<textarea name="' . $constName . '" rows="3" maxlength="' . DOLICAL_BOOKING_SETTING_TEXTS[$constName] . '" placeholder="' . dol_escape_htmltag($placeholderKey !== '' ? $langs->trans($placeholderKey) : '') . '">' . dol_escape_htmltag(dolical_booking_text($constName), 0, 1) . '</textarea>';
};

// The sections, in the order of Nextcloud : anchor, icon, title
$sections = [
    'planning'  => ['far fa-calendar-alt', 'DoliCalBookingSectionPlanning'],
    'contact'   => ['far fa-address-card', 'DoliCalBookingSectionContact'],
    'mails'     => ['far fa-envelope', 'DoliCalBookingSectionMails'],
    'form'      => ['fas fa-list-ul', 'DoliCalBookingSectionForm'],
    'security'  => ['fas fa-shield-alt', 'DoliCalBookingSectionSecurity'],
    'reminders' => ['far fa-bell', 'DoliCalBookingSectionReminders'],
    'visio'     => ['fas fa-video', 'DoliCalBookingSectionVisio'],
    'advanced'  => ['fas fa-sliders-h', 'DoliCalBookingSectionAdvanced'],
];
if (!empty($links)) {
    $sections['links'] = ['fas fa-link', 'DoliCalBookingLinks'];
}

$head = function (string $anchor) use ($sections, $langs): string {
    return dolical_setting_card_title($sections[$anchor][0], $langs->trans($sections[$anchor][1]));
};

$phoneModes = ['optional' => 'DoliCalBookingPhoneOptional', 'required' => 'DoliCalBookingPhoneRequired', 'hidden' => 'DoliCalBookingPhoneHidden'];
?>
<form method="POST" action="<?php echo dol_escape_htmltag($_SERVER['PHP_SELF']); ?>" class="dolical-setup" data-config="<?php echo dol_escape_htmltag(json_encode(['i18n' => ['copied' => $langs->transnoentities('DoliCalCopied')]])); ?>">
    <input type="hidden" name="token" value="<?php echo newToken(); ?>">
    <input type="hidden" name="action" value="update">

    <nav class="dolical-setup__nav">
        <?php foreach ($sections as $anchor => [$icon, $labelKey]) : ?>
            <a href="#dolical-setup-<?php echo $anchor; ?>" data-section="<?php echo $anchor; ?>"><i class="<?php echo $icon; ?> pictofixedwidth"></i><?php echo $langs->trans($labelKey); ?></a>
        <?php endforeach; ?>
    </nav>

    <div class="dolical-setup__cards">
        <section class="dolical-sync__card dolical-setup__card" id="dolical-setup-planning">
            <?php echo $head('planning'); ?>
            <?php echo $setting('DoliCalBookingEnable', $langs->trans('DoliCalBookingEnableHelp'), $switch('DOLICAL_BOOKING')); ?>
            <?php echo $setting('DoliCalBookingPeople', $langs->trans('DoliCalBookingPeopleHelp'), $form->multiselectarray('DOLICAL_BOOKING_USERS', dolical_booking_candidates(), dolical_booking_user_ids(), 0, 0, 'centpercent', 0, 0, '', '', $langs->trans('DoliCalBookingPeoplePlaceholder')), 'stacked'); ?>
            <?php echo $setting('DoliCalBookingConflicts', $langs->trans('DoliCalBookingConflictsHelp'), $form->multiselectarray('DOLICAL_BOOKING_CONFLICT_USERS', dolical_booking_candidates(), dolical_booking_conflict_user_ids(), 0, 0, 'centpercent', 0, 0, '', '', $langs->trans('DoliCalBookingConflictsPlaceholder')), 'stacked'); ?>
            <?php
            $summary = $slotCount > 0 ? dol_escape_htmltag($langs->transnoentities('DoliCalBookingTemplateSummary', $slotCount, $slotDays)) : '<span class="dolical-setting__warning">' . $langs->trans('DoliCalBookingTemplateEmpty') . '</span>';
            echo $setting('DoliCalTemplate', $summary, '<a class="dolical-btn" href="' . dol_buildpath('/dolical/admin/booking_template.php', 1) . '"><i class="far fa-calendar-alt"></i>' . $langs->trans('DoliCalBookingTemplateEdit') . '</a>');
            ?>
            <?php echo $setting('DoliCalBookingDuration', $langs->trans('DoliCalBookingDurationHelp'), $number('DOLICAL_BOOKING_DURATION', 'DoliCalBookingMinutes')); ?>
            <?php echo $setting('DoliCalBookingBuffers', $langs->trans('DoliCalBookingBuffersHelp'), '<span class="dolical-setting__pair"><span>' . $langs->trans('DoliCalBookingBufferBefore') . '</span>' . $number('DOLICAL_BOOKING_BUFFER_BEFORE', 'DoliCalBookingMinutes') . '<span>' . $langs->trans('DoliCalBookingBufferAfter') . '</span>' . $number('DOLICAL_BOOKING_BUFFER_AFTER', 'DoliCalBookingMinutes') . '</span>'); ?>
            <?php echo $setting('DoliCalBookingNotice', $langs->trans('DoliCalBookingNoticeHelp'), $number('DOLICAL_BOOKING_NOTICE_HOURS', 'DoliCalBookingNoticeUnit')); ?>
            <?php echo $setting('DoliCalBookingHorizon', dol_escape_htmltag($langs->transnoentities('DoliCalBookingHorizonHelp', dolical_booking_day(dolical_booking_window()[1] - 1))), $number('DOLICAL_BOOKING_HORIZON_WEEKS', 'DoliCalBookingHorizonUnit')); ?>
        </section>

        <section class="dolical-sync__card dolical-setup__card" id="dolical-setup-contact">
            <?php echo $head('contact'); ?>
            <div class="dolical-setup__grid">
                <?php echo $setting('DoliCalBookingPageTitle', $langs->trans('DoliCalBookingPageTitleHelp'), $text('DOLICAL_BOOKING_TITLE', 'DoliCalBookingPageTitleExample'), 'stacked'); ?>
                <?php echo $setting('DoliCalBookingEmail', $langs->trans('DoliCalBookingEmailHelp'), $text('DOLICAL_BOOKING_EMAIL', 'DoliCalBookingEmailExample'), 'stacked'); ?>
                <?php echo $setting('DoliCalBookingLocation', $langs->trans('DoliCalBookingLocationHelp'), $text('DOLICAL_BOOKING_LOCATION'), 'stacked'); ?>
                <?php echo $setting('DoliCalBookingPhone', $langs->trans('DoliCalBookingPhoneHelp'), $text('DOLICAL_BOOKING_PHONE'), 'stacked'); ?>
            </div>
            <?php // Not a label : a click in the toolbar of the editor would go to its hidden field ?>
            <?php echo $setting('DoliCalBookingIntro', $langs->trans('DoliCalBookingIntroHelp'), dolical_booking_intro_editor('DOLICAL_BOOKING_INTRO', dolical_booking_text('DOLICAL_BOOKING_INTRO')), 'stacked'); ?>
            <p class="dolical-setup__note"><i class="fas fa-info-circle pictofixedwidth"></i><?php echo $langs->trans('DoliCalBookingOwnTextsNote'); ?></p>
        </section>

        <section class="dolical-sync__card dolical-setup__card" id="dolical-setup-mails">
            <?php echo $head('mails'); ?>
            <?php echo $setting('DoliCalBookingCustomerMails', $langs->trans('DoliCalBookingCustomerMailsHelp'), '<span class="dolical-setting__pair">' . $switch('DOLICAL_BOOKING_MAIL_CONFIRM', 'DoliCalBookingMailConfirmed') . $switch('DOLICAL_BOOKING_MAIL_CANCEL', 'DoliCalBookingMailCancelled') . '</span>'); ?>
            <?php echo $setting('DoliCalBookingNotifyMe', $langs->trans('DoliCalBookingNotifyMeHelp'), '<span class="dolical-setting__pair">' . $switch('DOLICAL_BOOKING_NOTIFY_NEW', 'DoliCalBookingNotifyNew') . $switch('DOLICAL_BOOKING_NOTIFY_CANCEL', 'DoliCalBookingNotifyCancel') . '</span>'); ?>
            <div class="dolical-setup__grid">
                <?php echo $setting('DoliCalBookingConfirmText', $langs->trans('DoliCalBookingConfirmTextHelp'), $textarea('DOLICAL_BOOKING_CONFIRM_TEXT'), 'stacked'); ?>
                <?php echo $setting('DoliCalBookingIcsText', $langs->trans('DoliCalBookingIcsTextHelp'), $textarea('DOLICAL_BOOKING_ICS_TEXT'), 'stacked'); ?>
            </div>
            <?php
            // The template of each mail, opened in the editor of Dolibarr : made from the mail when there is none. The
            // buttons belong to the form under this one, a form cannot hold another
            $templateButtons = '';
            foreach (['dolical_booking' => 'DoliCalBookingMailConfirmed', 'dolical_booking_reminder' => 'DoliCalBookingMailReminder'] as $templateType => $templateLabel) {
                $templateButtons .= '<button type="submit" form="dolical-mailtemplate-form" name="type" value="' . $templateType . '" class="dolical-btn"><i class="far fa-edit"></i>' . $langs->trans($templateLabel) . '</button>';
            }
            // The tokens of the texts of this page (confirmation, calendar file, reminders), and the two more of the templates
            $code = function (array $tokens): string {
                return implode(', ', array_map(function ($token) {
                    return '<code>' . $token . '</code>';
                }, $tokens));
            };
            $textTokens     = $code(['__DOLICAL_RECIPIENT_NAME__', '__DOLICAL_EVENT_DATE__', '__DOLICAL_ORGANIZER_NAME__', '__DOLICAL_EVENT_LABEL__', '__DOLICAL_EVENT_LOCATION__', '__DOLICAL_EVENT_VISIO__', '__DOLICAL_ANSWER_LINK__', '__DOLICAL_ICS_LINK__']);
            $templateTokens = $code(['__DOLICAL_EVENT_DETAILS__', '__DOLICAL_BOOKING_TEXT__']);
            ?>
            <?php if ($permissionToWrite) : ?>
                <?php echo $setting('DoliCalBookingMailTemplates', $langs->trans('DoliCalBookingMailTemplatesHelp'), '<span class="dolical-setting__pair">' . $templateButtons . '</span>'); ?>
            <?php endif; ?>
            <?php // Without entities : trans() would write the <code> of the tokens as text ?>
            <p class="dolical-setup__note"><i class="fas fa-info-circle pictofixedwidth"></i><?php echo $langs->transnoentities('DoliCalBookingMailTemplatesTokens', $textTokens, $templateTokens); ?></p>
        </section>

        <section class="dolical-sync__card dolical-setup__card" id="dolical-setup-form">
            <?php echo $head('form'); ?>
            <?php echo $setting('DoliCalBookingFormTitle', $langs->trans('DoliCalBookingFormTitleHelp'), $text('DOLICAL_BOOKING_FORM_TITLE', 'DoliCalBookingFormTitleExample'), 'stacked'); ?>
            <?php
            $phoneSelect = '<select name="DOLICAL_BOOKING_PHONE_FIELD" class="dolical-select">';
            foreach ($phoneModes as $mode => $labelKey) {
                $phoneSelect .= '<option value="' . $mode . '"' . (dolical_booking_phone_field() === $mode ? ' selected' : '') . '>' . $langs->trans($labelKey) . '</option>';
            }
            $phoneSelect .= '</select>';
            echo $setting('DoliCalBookingPhoneField', $langs->trans('DoliCalBookingPhoneFieldHelp'), $phoneSelect);
            ?>
            <?php echo $setting('DoliCalBookingConsent', $langs->trans('DoliCalBookingConsentHelp'), $textarea('DOLICAL_BOOKING_CONSENT', 'DoliCalBookingConsentExample'), 'stacked'); ?>
        </section>

        <section class="dolical-sync__card dolical-setup__card" id="dolical-setup-security">
            <?php echo $head('security'); ?>
            <?php echo $setting('DoliCalBookingCaptcha', $langs->trans('DoliCalBookingCaptchaHelp'), $switch('DOLICAL_BOOKING_CAPTCHA')); ?>
            <?php echo $setting('DoliCalBookingMaxPerEmail', $langs->trans('DoliCalBookingMaxPerEmailHelp'), $number('DOLICAL_BOOKING_MAX_PER_EMAIL')); ?>
            <?php echo $setting('DoliCalBookingMaxPerIp', $langs->trans('DoliCalBookingMaxPerIpHelp'), $number('DOLICAL_BOOKING_MAX_PER_IP')); ?>
            <?php echo $setting('DoliCalBookingBlocked', $langs->trans('DoliCalBookingBlockedHelp'), $textarea('DOLICAL_BOOKING_BLOCKED', 'DoliCalBookingBlockedExample'), 'stacked'); ?>
        </section>

        <section class="dolical-sync__card dolical-setup__card" id="dolical-setup-reminders">
            <?php echo $head('reminders'); ?>
            <?php
            $reminderHelp = $langs->trans('DoliCalBookingRemindersHelp');
            if (!isModEnabled('cron')) {
                $reminderHelp .= '<br><span class="dolical-setting__warning"><i class="fas fa-exclamation-triangle pictofixedwidth"></i>' . $langs->trans('DoliCalBookingRemindersNoCron') . '</span>';
            }
            $reminderChips = '<span class="dolical-setup__chips">';
            foreach (DOLICAL_BOOKING_REMINDER_DELAYS as $delay => $labelKey) {
                $reminderChips .= '<label class="dolical-setup__chip"><input type="checkbox" name="DOLICAL_BOOKING_REMINDERS[]" value="' . $delay . '"' . (in_array($delay, dolical_booking_reminder_delays(), true) ? ' checked' : '') . '><span>' . $langs->trans($labelKey) . '</span></label>';
            }
            $reminderChips .= '</span>';
            echo $setting('DoliCalBookingReminders', $reminderHelp, $reminderChips, 'stacked');
            ?>
            <?php echo $setting('DoliCalBookingReminderText', $langs->trans('DoliCalBookingReminderTextHelp'), $textarea('DOLICAL_BOOKING_REMINDER_TEXT'), 'stacked'); ?>
        </section>

        <section class="dolical-sync__card dolical-setup__card" id="dolical-setup-visio">
            <?php echo $head('visio'); ?>
            <?php echo $setting('DoliCalBookingVisioRooms', $langs->trans('DoliCalBookingVisioRoomsHelp'), $switch('DOLICAL_BOOKING_VISIO_ROOMS')); ?>
            <?php echo $setting('DoliCalBookingVisioServer', $langs->trans('DoliCalBookingVisioServerHelp'), $text('DOLICAL_BOOKING_VISIO_SERVER', 'DoliCalBookingVisioServerExample'), 'stacked', 'DOLICAL_BOOKING_VISIO_ROOMS'); ?>
            <?php echo $setting('DoliCalBookingVisioCommon', $langs->trans('DoliCalBookingVisioCommonHelp'), $text('DOLICAL_BOOKING_VISIO', 'DoliCalVisioPlaceholder'), 'stacked', '!DOLICAL_BOOKING_VISIO_ROOMS'); ?>
        </section>

        <section class="dolical-sync__card dolical-setup__card" id="dolical-setup-advanced">
            <?php echo $head('advanced'); ?>
            <div class="dolical-setup__grid">
                <?php echo $setting('DoliCalBookingEventLabelModel', $langs->trans('DoliCalBookingEventLabelModelHelp'), $text('DOLICAL_BOOKING_EVENT_LABEL', 'DoliCalBookingEventLabelModelExample'), 'stacked'); ?>
                <?php echo $setting('DoliCalBookingRedirect', $langs->trans('DoliCalBookingRedirectHelp'), $text('DOLICAL_BOOKING_REDIRECT_URL', 'DoliCalBookingRedirectExample'), 'stacked'); ?>
            </div>
            <?php echo $setting('DoliCalBookingTimezoneLabel', $langs->trans('DoliCalBookingTimezoneHelp'), '<span class="dolical-setting__value">' . dol_escape_htmltag(dolical_timezone_label(dolical_booking_timezone())) . '</span><a class="dolical-btn dolical-btn--small" href="' . dol_buildpath('/dolical/admin/setup.php', 1) . '">' . $langs->trans('DoliCalBookingTimezoneLink') . '</a>'); ?>
        </section>

        <?php if (!empty($links)) : ?>
            <section class="dolical-sync__card dolical-setup__card" id="dolical-setup-links">
                <?php echo $head('links'); ?>
                <p class="dolical-card__muted"><?php echo $langs->trans('DoliCalBookingLinksHelp'); ?></p>
                <?php foreach ($links as $link) : ?>
                    <div class="dolical-sync__field">
                        <span class="dolical-sync__label"><?php echo dol_escape_htmltag($link['name']); ?></span>
                        <code class="dolical-sync__value"><?php echo dol_escape_htmltag($link['url']); ?></code>
                        <span class="nowraponall">
                            <button type="button" class="dolical-icon-btn dolical-copy" data-copy="<?php echo dol_escape_htmltag($link['url']); ?>" title="<?php echo dol_escape_htmltag($langs->trans('DoliCalCopy')); ?>"><i class="far fa-copy"></i></button>
                            <a class="dolical-icon-btn" href="<?php echo dol_escape_htmltag($link['url']); ?>" target="_blank" rel="noopener" title="<?php echo dol_escape_htmltag($langs->trans('DoliCalBookingMineOpen')); ?>"><i class="fas fa-external-link-alt"></i></a>
                            <?php if ($link['edit'] !== '') : ?>
                                <a class="dolical-icon-btn" href="<?php echo dol_escape_htmltag($link['edit']); ?>" title="<?php echo dol_escape_htmltag($langs->trans('DoliCalBookingEditTexts')); ?>"><i class="fas fa-pen"></i></a>
                            <?php endif; ?>
                        </span>
                    </div>
                <?php endforeach; ?>
            </section>
        <?php endif; ?>
    </div>

    <?php if ($permissionToWrite) : ?>
        <div class="dolical-setup__savebar">
            <button type="submit" class="dolical-btn dolical-btn--primary"><i class="fas fa-save"></i><?php echo $langs->trans('Save'); ?></button>
        </div>
    <?php endif; ?>
</form>
<?php if ($permissionToWrite) : ?>
    <form method="POST" action="<?php echo dol_escape_htmltag($_SERVER['PHP_SELF']); ?>" id="dolical-mailtemplate-form">
        <input type="hidden" name="token" value="<?php echo newToken(); ?>">
        <input type="hidden" name="action" value="mailtemplate">
    </form>
<?php endif; ?>
<div class="dolical-toast"></div>
