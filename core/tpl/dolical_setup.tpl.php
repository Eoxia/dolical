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
 * \file    core/tpl/dolical_setup.tpl.php
 * \ingroup dolical
 * \brief   General setup of DoliCal, as the one of the online booking : a card for each section, the sections listed on
 *          the side, the save button always in sight.
 *
 * Rendered by admin/setup.php (inherits its scope). Expects: $views, $permissionToWrite, $langs.
 */

// The sections : anchor, icon, title
$sections = [
    'display'     => ['fas fa-calendar-week', 'DoliCalSetupSectionDisplay'],
    'invitations' => ['far fa-envelope', 'DoliCalSetupSectionInvitations'],
    'reminders'   => ['far fa-bell', 'DoliCalSetupSectionReminders'],
    'sync'        => ['fas fa-mobile-alt', 'DoliCalSetupSectionSync'],
    'agenda'      => ['far fa-calendar-alt', 'DoliCalSetupSectionAgenda'],
];
$head = function (string $anchor) use ($sections, $langs): string {
    return dolical_setting_card_title($sections[$anchor][0], $langs->trans($sections[$anchor][1]));
};
$switch = function (string $constName, int $default) {
    return dolical_setting_switch($constName, (bool) getDolGlobalInt($constName, $default));
};

$viewSelect = '<select name="DOLICAL_DEFAULT_VIEW" class="dolical-select">';
foreach ($views as $view => $labelKey) {
    $viewSelect .= '<option value="' . $view . '"' . (getDolGlobalString('DOLICAL_DEFAULT_VIEW', 'week') === $view ? ' selected' : '') . '>' . $langs->trans($labelKey) . '</option>';
}
$viewSelect .= '</select>';

$zoneSelect = '<select name="DOLICAL_TIMEZONE" class="dolical-select">';
foreach (timezone_identifiers_list() as $zone) {
    $zoneSelect .= '<option value="' . dol_escape_htmltag($zone) . '"' . (getDolGlobalString('DOLICAL_TIMEZONE', 'Europe/Paris') === $zone ? ' selected' : '') . '>' . dol_escape_htmltag(dolical_timezone_label($zone)) . '</option>';
}
$zoneSelect .= '</select>';

$tokens = '<code>__DOLICAL_EVENT_LABEL__</code>, <code>__DOLICAL_EVENT_DATE__</code>, <code>__DOLICAL_EVENT_LOCATION__</code>, <code>__DOLICAL_EVENT_VISIO__</code>, <code>__DOLICAL_ORGANIZER_NAME__</code>, <code>__DOLICAL_RECIPIENT_NAME__</code>, <code>__DOLICAL_ANSWER_LINK__</code>';
$answerTokens = '<code>__DOLICAL_ANSWER__</code>, <code>__DOLICAL_EVENT_LINK__</code>';
?>
<form method="POST" action="<?php echo dol_escape_htmltag($_SERVER['PHP_SELF']); ?>" class="dolical-setup">
    <input type="hidden" name="token" value="<?php echo newToken(); ?>">
    <input type="hidden" name="action" value="update">

    <nav class="dolical-setup__nav">
        <?php foreach ($sections as $anchor => [$icon, $labelKey]) : ?>
            <a href="#dolical-setup-<?php echo $anchor; ?>" data-section="<?php echo $anchor; ?>"><i class="<?php echo $icon; ?> pictofixedwidth"></i><?php echo $langs->trans($labelKey); ?></a>
        <?php endforeach; ?>
    </nav>

    <div class="dolical-setup__cards">
        <section class="dolical-sync__card dolical-setup__card" id="dolical-setup-display">
            <?php echo $head('display'); ?>
            <?php echo dolical_setting($langs->trans('DoliCalDefaultView'), $langs->trans('DoliCalDefaultViewHelp'), $viewSelect); ?>
            <?php echo dolical_setting($langs->trans('DoliCalWorkingHours'), $langs->trans('DoliCalWorkingHoursHelp'), '<span class="dolical-setting__pair">' . dolical_setting_number('DOLICAL_DAY_START_HOUR', getDolGlobalInt('DOLICAL_DAY_START_HOUR', 8), 0, 23, 'h') . '<i class="fas fa-arrow-right"></i>' . dolical_setting_number('DOLICAL_DAY_END_HOUR', getDolGlobalInt('DOLICAL_DAY_END_HOUR', 19), 1, 24, 'h') . '</span>'); ?>
            <?php echo dolical_setting($langs->trans('DoliCalDefaultDuration'), $langs->trans('DoliCalDefaultDurationHelp'), dolical_setting_number('DOLICAL_DEFAULT_DURATION', getDolGlobalInt('DOLICAL_DEFAULT_DURATION', 60), 5, 1440, $langs->trans('DoliCalBookingMinutes'), 5)); ?>
            <?php echo dolical_setting($langs->trans('DoliCalShowWeekends'), $langs->trans('DoliCalShowWeekendsHelp'), $switch('DOLICAL_SHOW_WEEKENDS', 1)); ?>
            <?php echo dolical_setting($langs->trans('DoliCalHideAutoEvents'), $langs->trans('DoliCalHideAutoEventsHelp'), $switch('DOLICAL_HIDE_AUTO_EVENTS', 1)); ?>
        </section>

        <section class="dolical-sync__card dolical-setup__card" id="dolical-setup-invitations">
            <?php echo $head('invitations'); ?>
            <?php echo dolical_setting($langs->trans('DoliCalNotifyOrganizer'), $langs->trans('DoliCalNotifyOrganizerHelp'), $switch('DOLICAL_NOTIFY_ORGANIZER', 1)); ?>
            <?php echo dolical_setting($langs->trans('DoliCalAutoRemind'), $langs->trans('DoliCalAutoRemindHelp'), $switch('DOLICAL_AUTO_REMIND', 0)); ?>
            <?php echo dolical_setting($langs->trans('DoliCalAutoRemindDays'), $langs->trans('DoliCalAutoRemindDaysHelp'), dolical_setting_number('DOLICAL_AUTO_REMIND_DAYS', getDolGlobalInt('DOLICAL_AUTO_REMIND_DAYS', 2), 1, 30, $langs->trans('Days')), 'inline', 'DOLICAL_AUTO_REMIND'); ?>
            <?php echo dolical_setting($langs->trans('DoliCalTimezone'), $langs->trans('DoliCalTimezoneHelp'), $zoneSelect); ?>
            <?php echo dolical_setting($langs->trans('DoliCalMailTemplates'), $langs->trans('DoliCalMailTemplatesHelp'), '<a class="dolical-btn" href="' . DOL_URL_ROOT . '/admin/mails_templates.php?search_type_template=dolical_invitation"><i class="far fa-edit"></i>' . $langs->trans('DoliCalMailTemplatesLink') . '</a>'); ?>
            <p class="dolical-setup__note"><i class="fas fa-info-circle pictofixedwidth"></i><?php // Without entities : trans() would write the <code> of the tokens as text ?>
                <?php echo $langs->transnoentities('DoliCalMailTemplatesTokens', $tokens, $answerTokens); ?></p>
        </section>

        <section class="dolical-sync__card dolical-setup__card" id="dolical-setup-reminders">
            <?php echo $head('reminders'); ?>
            <?php echo dolical_setting($langs->trans('DoliCalSetupReminderByDefault'), $langs->trans('DoliCalSetupReminderByDefaultHelp'), $switch('DOLICAL_REMINDER_BY_DEFAULT', 1)); ?>
        </section>

        <section class="dolical-sync__card dolical-setup__card" id="dolical-setup-sync">
            <?php echo $head('sync'); ?>
            <?php echo dolical_setting($langs->trans('DoliCalSetupCalDav'), $langs->trans('DoliCalSetupCalDavHelp'), $switch('DOLICAL_CALDAV', 0)); ?>
            <?php echo dolical_setting($langs->trans('DoliCalSetupCalDavHttp'), '<span class="dolical-setting__warning"><i class="fas fa-exclamation-triangle pictofixedwidth"></i>' . $langs->trans('DoliCalSetupCalDavHttpHelp') . '</span>', $switch('DOLICAL_CALDAV_ALLOW_HTTP', 0), 'inline', 'DOLICAL_CALDAV'); ?>
        </section>

        <section class="dolical-sync__card dolical-setup__card" id="dolical-setup-agenda">
            <?php echo $head('agenda'); ?>
            <?php
            // An option of the Agenda module, not of DoliCal (a hidden constant of the core) : it acts on every event
            $linkedHelp = $langs->trans('DoliCalNativeLinkedElementsHelp') . '<br><span class="dolical-setting__warning"><i class="fas fa-exclamation-triangle pictofixedwidth"></i>' . $langs->trans('DoliCalSharedAgendaSetting', 'AGENDA_ENABLE_LINKED_ELEMENTS') . '</span>';
            echo dolical_setting($langs->trans('DoliCalNativeLinkedElements'), $linkedHelp, $switch('AGENDA_ENABLE_LINKED_ELEMENTS', 0));
            $typesOn = (bool) getDolGlobalString('AGENDA_USE_EVENT_TYPE');
            echo dolical_setting($langs->trans('DoliCalEventTypes'), $langs->trans('DoliCalEventTypesHelp'), '<span class="dolical-setting__state' . ($typesOn ? ' is-on' : '') . '">' . $langs->trans($typesOn ? 'Enabled' : 'Disabled') . '</span><a class="dolical-btn dolical-btn--small" href="' . DOL_URL_ROOT . '/admin/agenda_other.php">' . $langs->trans('DoliCalAgendaSetup') . '</a>');
            ?>
            <p class="dolical-setup__note"><i class="fas fa-info-circle pictofixedwidth"></i><?php echo $langs->trans('DoliCalSetupHelp'); ?></p>
        </section>
    </div>

    <?php if ($permissionToWrite) : ?>
        <div class="dolical-setup__savebar">
            <button type="submit" class="dolical-btn dolical-btn--primary"><i class="fas fa-save"></i><?php echo $langs->trans('Save'); ?></button>
        </div>
    <?php endif; ?>
</form>
<div class="dolical-toast"></div>
