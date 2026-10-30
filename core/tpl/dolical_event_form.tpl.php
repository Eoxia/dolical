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
 * \file    core/tpl/dolical_event_form.tpl.php
 * \ingroup dolical
 * \brief   Form of an event : creation, edition and duplication, inside the modal of the calendar.
 *
 * Rendered by ajax/event.php (inherits its scope). Expects: $event (null for a new event), $isNew, $formValues,
 * $calendarUsers, $eventTypes, $canAssignAll, $selectedContacts, $selectedThirdparty, $selectedProject,
 * $selectedElements, $eventFiles, $recurrenceChoices, $seriesSize, $reminderTypes, $reminder, $langs.
 * Dates are written in the format of the HTML date inputs, the JavaScript sends them back as timestamps.
 */

$untilDay      = dol_print_date($formValues['recurUntil'], '%Y-%m-%d', 'tzuserrel');
$reminderUnits = ['i' => 'DoliCalReminderMinutes', 'h' => 'DoliCalReminderHours', 'd' => 'DoliCalReminderDays', 'w' => 'DoliCalReminderWeeks'];

$startValue = dol_print_date($formValues['start'], '%Y-%m-%dT%H:%M', 'tzuserrel');
$endValue   = dol_print_date($formValues['end'], '%Y-%m-%dT%H:%M', 'tzuserrel');
$startDay   = dol_print_date($formValues['start'], '%Y-%m-%d', 'tzuserrel');
$endDay     = dol_print_date($formValues['end'], '%Y-%m-%d', 'tzuserrel');

$percentOptions = [-1 => $langs->trans('StatusNotApplicable'), 0 => $langs->trans('StatusActionToDo'), 50 => $langs->trans('StatusActionInProcess'), 100 => $langs->trans('StatusActionDone')];
if (!isset($percentOptions[$formValues['percent']])) {
    $percentOptions[$formValues['percent']] = $langs->trans('StatusActionInProcess') . ' (' . $formValues['percent'] . ' %)';
}

$title = $isNew ? $langs->trans('DoliCalNewEvent') : $langs->trans('DoliCalEditEvent');
?>
<div class="modal-header">
    <h2 class="modal-title"><i class="far fa-calendar-plus pictofixedwidth"></i><?php echo $title; ?></h2>
    <div class="modal-close"><i class="fas fa-times"></i></div>
</div>
<div class="modal-content">
    <form class="dolical-form" data-id="<?php echo $isNew ? 0 : (int) $event->id; ?>" data-series="<?php echo (int) $seriesSize; ?>" data-recurrence="<?php echo dol_escape_htmltag($formValues['recurrence']); ?>" data-until="<?php echo $untilDay; ?>" autocomplete="off" novalidate>
        <input type="text" name="label" class="dolical-form__label" maxlength="255" placeholder="<?php echo dol_escape_htmltag($langs->trans('DoliCalAddTitle')); ?>" value="<?php echo dol_escape_htmltag($formValues['label']); ?>">

        <div class="dolical-form__grid">
            <div class="dolical-field dolical-field--wide dolical-form__when">
                <i class="far fa-clock dolical-field__icon"></i>
                <div class="dolical-form__dates">
                    <input type="datetime-local" name="start_at" class="dolical-form__time" value="<?php echo $startValue; ?>">
                    <input type="date" name="start_day" class="dolical-form__day" value="<?php echo $startDay; ?>">
                    <span class="dolical-form__arrow"><i class="fas fa-long-arrow-alt-right"></i></span>
                    <input type="datetime-local" name="end_at" class="dolical-form__time" value="<?php echo $endValue; ?>">
                    <input type="date" name="end_day" class="dolical-form__day" value="<?php echo $endDay; ?>">
                    <label class="dolical-check">
                        <input type="checkbox" name="fullday" value="1"<?php echo $formValues['fullday'] ? ' checked' : ''; ?>>
                        <span><?php echo $langs->trans('DoliCalAllDay'); ?></span>
                    </label>
                </div>
            </div>

            <div class="dolical-field dolical-field--wide dolical-form__repeat<?php echo $formValues['recurrence'] !== '' ? ' is-repeating' : ''; ?>">
                <i class="fas fa-redo-alt dolical-field__icon" title="<?php echo dol_escape_htmltag($langs->trans('DoliCalRepeat')); ?>"></i>
                <div class="dolical-form__repeat-line">
                    <select name="recurrence" class="dolical-select dolical-form__recurrence" aria-label="<?php echo dol_escape_htmltag($langs->trans('DoliCalRepeat')); ?>">
                        <?php foreach ($recurrenceChoices as $kind => $label) : ?>
                            <option value="<?php echo dol_escape_htmltag($kind); ?>"<?php echo $kind === $formValues['recurrence'] ? ' selected' : ''; ?>><?php echo dol_escape_htmltag($label); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <label class="dolical-form__until">
                        <span><?php echo $langs->trans('DoliCalRepeatUntilLabel'); ?></span>
                        <input type="date" name="recur_until_day" value="<?php echo $untilDay; ?>">
                    </label>
                </div>
                <?php if ($seriesSize > 1) : ?>
                    <span class="dolical-field__help"><i class="fas fa-info-circle"></i> <?php echo $langs->trans('DoliCalPartOfSeries', $seriesSize); ?></span>
                <?php endif; ?>
            </div>

            <?php if (!empty($eventTypes)) : ?>
                <div class="dolical-field">
                    <label for="dolical-type"><?php echo $langs->trans('Type'); ?></label>
                    <select id="dolical-type" name="type_code" class="dolical-select">
                        <?php foreach ($eventTypes as $type) : ?>
                            <option value="<?php echo dol_escape_htmltag($type['code']); ?>"<?php echo $type['code'] === $formValues['type_code'] ? ' selected' : ''; ?>><?php echo dol_escape_htmltag($type['label']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php endif; ?>

            <div class="dolical-field">
                <label for="dolical-percent"><?php echo $langs->trans('Status'); ?></label>
                <select id="dolical-percent" name="percent" class="dolical-select">
                    <?php foreach ($percentOptions as $value => $label) : ?>
                        <option value="<?php echo (int) $value; ?>"<?php echo (int) $value === (int) $formValues['percent'] ? ' selected' : ''; ?>><?php echo dol_escape_htmltag($label); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <?php if (!getDolGlobalString('AGENDA_DISABLE_LOCATION')) : ?>
                <div class="dolical-field dolical-field--wide">
                    <label for="dolical-location"><i class="fas fa-map-marker-alt pictofixedwidth"></i><?php echo $langs->trans('Location'); ?></label>
                    <input type="text" id="dolical-location" name="location" maxlength="255" value="<?php echo dol_escape_htmltag($formValues['location']); ?>">
                </div>
            <?php endif; ?>

            <div class="dolical-field">
                <label for="dolical-owner"><i class="fas fa-crown pictofixedwidth"></i><?php echo $langs->trans('DoliCalOrganizer'); ?></label>
                <select id="dolical-owner" name="owner_id" class="dolical-select"<?php echo $canAssignAll ? '' : ' disabled'; ?>>
                    <?php foreach ($calendarUsers as $calendarUser) : ?>
                        <option value="<?php echo $calendarUser['id']; ?>"<?php echo $calendarUser['id'] === (int) $formValues['owner_id'] ? ' selected' : ''; ?>><?php echo dol_escape_htmltag($calendarUser['name']); ?></option>
                    <?php endforeach; ?>
                    <?php if (!isset($calendarUsers[(int) $formValues['owner_id']]) && $formValues['owner_id'] > 0) : ?>
                        <option value="<?php echo (int) $formValues['owner_id']; ?>" selected>#<?php echo (int) $formValues['owner_id']; ?></option>
                    <?php endif; ?>
                </select>
            </div>

            <div class="dolical-field dolical-field--check">
                <label class="dolical-check">
                    <input type="checkbox" name="busy" value="1"<?php echo $formValues['busy'] ? ' checked' : ''; ?>>
                    <span><?php echo $langs->trans('DoliCalBusy'); ?></span>
                </label>
            </div>

            <div class="dolical-field dolical-field--wide">
                <label for="dolical-users"><i class="fas fa-user-friends pictofixedwidth"></i><?php echo $langs->trans('DoliCalInvitedUsers'); ?></label>
                <select id="dolical-users" name="user_ids[]" class="dolical-select2" multiple data-placeholder="<?php echo dol_escape_htmltag($langs->trans('DoliCalAddPeople')); ?>">
                    <?php foreach ($calendarUsers as $calendarUser) : ?>
                        <option value="<?php echo $calendarUser['id']; ?>"<?php echo in_array($calendarUser['id'], $formValues['user_ids']) ? ' selected' : ''; ?>><?php echo dol_escape_htmltag($calendarUser['name'] . ($calendarUser['email'] !== '' ? ' <' . $calendarUser['email'] . '>' : '')); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="dolical-field dolical-field--wide dolical-form__conflicts" aria-live="polite"></div>

            <div class="dolical-field">
                <label for="dolical-thirdparty"><i class="far fa-building pictofixedwidth"></i><?php echo $langs->trans('ThirdParty'); ?></label>
                <select id="dolical-thirdparty" name="socid" class="dolical-select2" data-ajax-type="thirdparty" data-allow-clear="1" data-placeholder="<?php echo dol_escape_htmltag($langs->trans('DoliCalSearchThirdparty')); ?>">
                    <option value=""></option>
                    <?php if ($selectedThirdparty !== '') : ?>
                        <option value="<?php echo (int) $formValues['socid']; ?>" selected><?php echo dol_escape_htmltag($selectedThirdparty); ?></option>
                    <?php endif; ?>
                </select>
            </div>

            <?php if (isModEnabled('project')) : ?>
                <div class="dolical-field">
                    <label for="dolical-project"><i class="fas fa-project-diagram pictofixedwidth"></i><?php echo $langs->trans('Project'); ?></label>
                    <select id="dolical-project" name="project_id" class="dolical-select2" data-ajax-type="project" data-allow-clear="1" data-placeholder="<?php echo dol_escape_htmltag($langs->trans('DoliCalSearchProject')); ?>">
                        <option value=""></option>
                        <?php if ($selectedProject !== '') : ?>
                            <option value="<?php echo (int) $formValues['project_id']; ?>" selected><?php echo dol_escape_htmltag($selectedProject); ?></option>
                        <?php endif; ?>
                    </select>
                </div>
            <?php endif; ?>

            <div class="dolical-field dolical-field--wide">
                <label for="dolical-contacts"><i class="far fa-address-book pictofixedwidth"></i><?php echo $langs->trans('DoliCalContacts'); ?></label>
                <select id="dolical-contacts" name="contact_ids[]" class="dolical-select2" data-ajax-type="contact" data-tags="email" multiple data-placeholder="<?php echo dol_escape_htmltag($langs->trans('DoliCalSearchContacts')); ?>" data-new-label="<?php echo dol_escape_htmltag($langs->trans('DoliCalNewContactByEmail', '%EMAIL%')); ?>">
                    <?php foreach ($selectedContacts as $contactId => $text) : ?>
                        <option value="<?php echo (int) $contactId; ?>" selected><?php echo dol_escape_htmltag($text); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="dolical-field dolical-field--wide">
                <label for="dolical-elements"><i class="fas fa-link pictofixedwidth"></i><?php echo $langs->trans('DoliCalLinkedElements'); ?></label>
                <select id="dolical-elements" name="elements[]" class="dolical-select2" data-ajax-type="element" multiple data-placeholder="<?php echo dol_escape_htmltag($langs->trans('DoliCalSearchElements')); ?>">
                    <?php foreach ($selectedElements as $key => $text) : ?>
                        <option value="<?php echo dol_escape_htmltag($key); ?>" selected><?php echo dol_escape_htmltag($text); ?></option>
                    <?php endforeach; ?>
                </select>
                <span class="dolical-field__help"><?php echo $langs->trans('DoliCalLinkedElementsHelp'); ?></span>
            </div>

            <div class="dolical-field dolical-field--wide">
                <label for="dolical-note"><i class="fas fa-align-left pictofixedwidth"></i><?php echo $langs->trans('Description'); ?></label>
                <textarea id="dolical-note" name="note" rows="4"><?php echo dol_escape_htmltag(dol_textishtml($formValues['note']) ? dol_string_nohtmltag($formValues['note'], 0) : $formValues['note'], 0, 1); ?></textarea>
            </div>

            <div class="dolical-field dolical-field--wide dolical-files" data-id="<?php echo $isNew ? 0 : (int) $event->id; ?>">
                <label><i class="fas fa-paperclip pictofixedwidth"></i><?php echo $langs->trans('DoliCalAttachments'); ?></label>
                <?php if (!empty($eventFiles)) : ?>
                    <ul class="dolical-files__list">
                        <?php foreach ($eventFiles as $file) : ?>
                            <li class="dolical-file">
                                <span class="dolical-file__icon"><?php echo $file['icon']; ?></span>
                                <div class="dolical-file__body">
                                    <a class="dolical-file__name" href="<?php echo dol_escape_htmltag($file['url']); ?>" target="_blank" rel="noopener"><?php echo dol_escape_htmltag($file['name']); ?></a>
                                    <span class="dolical-card__muted"><?php echo dol_escape_htmltag($file['size']); ?></span>
                                </div>
                                <button type="button" class="dolical-icon-btn dolical-file__delete" data-file="<?php echo dol_escape_htmltag($file['name']); ?>" title="<?php echo dol_escape_htmltag($langs->trans('Delete')); ?>"><i class="far fa-trash-alt"></i></button>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
                <label class="dolical-dropzone dolical-dropzone--form">
                    <input type="file" name="files[]" class="dolical-form__files" multiple>
                    <i class="fas fa-cloud-upload-alt"></i>
                    <span class="dolical-dropzone__text" data-empty="<?php echo dol_escape_htmltag($langs->trans('DoliCalDropFiles')); ?>"><?php echo $langs->trans('DoliCalDropFiles'); ?></span>
                </label>
            </div>

            <?php if (!empty($reminderTypes)) : ?>
                <div class="dolical-field dolical-field--wide dolical-form__reminder<?php echo $reminder['enabled'] ? ' is-on' : ''; ?>">
                    <label class="dolical-switch">
                        <input type="checkbox" name="reminder" value="1"<?php echo $reminder['enabled'] ? ' checked' : ''; ?>>
                        <span class="dolical-switch__track"></span>
                        <i class="far fa-bell"></i>
                        <span><?php echo $langs->trans('DoliCalReminderSwitch'); ?></span>
                    </label>
                    <div class="dolical-form__reminder-options">
                        <input type="number" name="reminder_offset" min="0" max="999" value="<?php echo (int) $reminder['offset']; ?>" aria-label="<?php echo dol_escape_htmltag($langs->trans('DoliCalReminderDelay')); ?>">
                        <select name="reminder_unit" class="dolical-select">
                            <?php foreach ($reminderUnits as $unit => $labelKey) : ?>
                                <option value="<?php echo $unit; ?>"<?php echo $unit === $reminder['unit'] ? ' selected' : ''; ?>><?php echo dol_strtolower($langs->trans($labelKey)); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <span><?php echo $langs->trans('DoliCalReminderBeforeBy'); ?></span>
                        <?php foreach ($reminderTypes as $type => $label) : ?>
                            <label class="dolical-check">
                                <input type="checkbox" name="reminder_types[]" value="<?php echo $type; ?>"<?php echo in_array($type, $reminder['types'], true) ? ' checked' : ''; ?>>
                                <i class="<?php echo $type === 'email' ? 'far fa-envelope' : 'far fa-window-maximize'; ?>"></i>
                                <span><?php echo dol_escape_htmltag($label); ?></span>
                            </label>
                        <?php endforeach; ?>
                        <label class="dolical-check">
                            <input type="checkbox" name="reminder_everyone" value="1"<?php echo !empty($reminder['everyone']) ? ' checked' : ''; ?>>
                            <span><?php echo $langs->trans('DoliCalReminderEveryone'); ?></span>
                        </label>
                    </div>
                    <span class="dolical-field__help dolical-form__notif-blocked"><i class="fas fa-ban"></i> <?php echo $langs->trans('DoliCalReminderBlocked'); ?></span>
                </div>
            <?php endif; ?>

            <div class="dolical-field dolical-field--wide dolical-form__notify">
                <label class="dolical-check">
                    <input type="checkbox" name="notify" value="1">
                    <i class="far fa-paper-plane"></i>
                    <span><?php echo $langs->trans('DoliCalNotifyParticipants'); ?></span>
                </label>
                <span class="dolical-field__help"><?php echo $langs->trans('DoliCalNotifyParticipantsHelp'); ?></span>
            </div>
        </div>
        <input type="hidden" name="note_changed" value="0">
    </form>
</div>
<div class="modal-footer">
    <span class="dolical-form__error"></span>
    <button type="button" class="dolical-btn modal-close"><?php echo $langs->trans('Cancel'); ?></button>
    <button type="button" class="dolical-btn dolical-btn--primary dolical-form__submit"><i class="fas fa-check"></i><?php echo $langs->trans('Save'); ?></button>
</div>
