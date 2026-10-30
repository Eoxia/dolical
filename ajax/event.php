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
 * \file    ajax/event.php
 * \ingroup dolical
 * \brief   One native event seen from the calendar : its card and its form as HTML (GET), and every change made
 *          from them as JSON (POST) : save, move, answer, progress, delete, attachments
 */

if (!defined('NOTOKENRENEWAL')) {
    define('NOTOKENRENEWAL', '1');
}
if (!defined('NOREQUIREMENU')) {
    define('NOREQUIREMENU', '1');
}

if (file_exists('../dolical.main.inc.php')) {
    require_once __DIR__ . '/../dolical.main.inc.php';
} elseif (file_exists('../../dolical.main.inc.php')) {
    require_once __DIR__ . '/../../dolical.main.inc.php';
} else {
    die('Include of dolical main fails');
}

require_once __DIR__ . '/../lib/dolical_event.lib.php';
require_once __DIR__ . '/../lib/dolical_invitation.lib.php';
require_once __DIR__ . '/../lib/dolical_series.lib.php';
require_once __DIR__ . '/../class/dolicalvoicereport.class.php';

global $conf, $db, $langs, $user;

// commercial carries the labels of the progress of an event (StatusActionToDo...)
saturne_load_langs(['agenda', 'commercial', 'companies', 'projects', 'other']);

$action = GETPOST('action', 'aZ09');
$id     = GETPOSTINT('id');

/**
 * Answer the JavaScript and stop
 *
 * @param  array $payload Answer
 * @param  int   $code    HTTP code
 * @return void
 */
function dolical_json_exit(array $payload, int $code = 200): void
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload);
    exit;
}

if (!$user->hasRight('dolical', 'read') || !$user->hasRight('agenda', 'myactions', 'read')) {
    dolical_json_exit(['success' => false, 'error' => $langs->trans('NotEnoughPermissions')], 403);
}

$event = null;
if ($id > 0) {
    $event = dolical_load_event($id, $user);
    if ($event === null) {
        dolical_json_exit(['success' => false, 'error' => $langs->trans('ErrorRecordNotFound')], 404);
    }
}

/*
 * Views : HTML fragments drawn by the templates
 */

if ($action === 'card' && $event !== null) {
    $card = dolical_get_event_card($event, $user);

    // When each one was invited, and who can still be : the organizer is not, nor a person without email
    $history = dolical_invitation_history($event);
    foreach ($card['participants']['people'] as $index => $person) {
        $email = dol_strtolower($person['email']);
        $card['participants']['people'][$index]['invitedAt'] = isset($history[$email]) ? dol_print_date($history[$email], '%d/%m %H:%M', 'tzuserrel') : '';
        $card['participants']['people'][$index]['canInvite'] = $card['canEdit'] && !$person['owner'] && $person['email'] !== '' && isValidEmail($person['email']);
    }
    $card['canInviteAll'] = !empty(array_filter(array_column($card['participants']['people'], 'canInvite')));
    // Who to remind, and who would be told of a cancellation
    $card['pendingCount'] = $card['canEdit'] ? count(dolical_pending_recipients($event)) : 0;
    $card['invitedCount'] = count(dolical_invited_only(dolical_invitation_recipients($event), $history));
    header('Content-Type: text/html; charset=utf-8');
    require __DIR__ . '/../core/tpl/dolical_event_card.tpl.php';
    exit;
}

if ($action === 'form') {
    $cloneId = GETPOSTINT('clone');
    $source  = $event;
    if ($event === null && $cloneId > 0) {
        $source = dolical_load_event($cloneId, $user);
    }

    if ($event !== null ? !dolical_can_edit_event($user, $event) : !dolical_can_create($user)) {
        dolical_json_exit(['success' => false, 'error' => $langs->trans('NotEnoughPermissions')], 403);
    }

    $calendarUsers = dolical_get_calendar_users($user);
    $eventTypes    = getDolGlobalString('AGENDA_USE_EVENT_TYPE') ? dolical_get_event_types() : [];
    $isNew         = ($event === null);
    $canAssignAll  = $user->hasRight('agenda', 'allactions', 'create');

    // Values of the form : the event, its clone, or what the calendar was clicked on
    if ($source !== null) {
        $formValues = [
            'label'      => $source->label,
            'type_code'  => $source->type_code,
            'fullday'    => (int) $source->fulldayevent,
            'start'      => (int) $source->datep,
            'end'        => (int) ($source->datef ?: $source->datep),
            'location'   => $source->location,
            'percent'    => (int) $source->percentage,
            'busy'       => (int) $source->transparency,
            'owner_id'   => (int) $source->userownerid,
            'user_ids'   => array_values(array_diff(array_map('intval', array_keys($source->userassigned)), [(int) $source->userownerid])),
            'socid'      => (int) $source->socid,
            'project_id' => (int) $source->fk_project,
            'contacts'   => array_values(array_unique(array_merge($source->contact_id > 0 ? [(int) $source->contact_id] : [], array_map('intval', array_keys($source->socpeopleassigned))))),
            'elements'   => array_keys(dolical_get_event_links($source)),
            'note'       => (string) $source->note_private,
        ];
        // A clone is a new event : it starts as to do
        if ($isNew) {
            $formValues['percent'] = $formValues['percent'] < 0 ? -1 : 0;
        }
    } else {
        $start = GETPOSTINT('start');
        if ($start <= 0) {
            // Next round hour
            $start = (int) (ceil(dol_now() / 3600) * 3600);
        }
        $end = GETPOSTINT('end');
        if ($end <= $start) {
            $end = $start + 60 * getDolGlobalInt('DOLICAL_DEFAULT_DURATION', 60);
        }
        $ownerId = GETPOSTINT('user');
        if ($ownerId <= 0 || !$canAssignAll || !isset($calendarUsers[$ownerId])) {
            $ownerId = (int) $user->id;
        }
        $formValues = [
            'label'      => '',
            'type_code'  => getDolGlobalString('AGENDA_USE_EVENT_TYPE_DEFAULT', 'AC_RDV') != '-1' ? getDolGlobalString('AGENDA_USE_EVENT_TYPE_DEFAULT', 'AC_RDV') : 'AC_RDV',
            'fullday'    => GETPOSTINT('allday') ? 1 : 0,
            'start'      => $start,
            'end'        => $end,
            'location'   => '',
            'percent'    => 0,
            'busy'       => 1,
            'owner_id'   => $ownerId,
            'user_ids'   => [],
            'socid'      => GETPOSTINT('socid'),
            'project_id' => GETPOSTINT('projectid'),
            'contacts'   => array_filter(array_map('intval', explode(',', GETPOST('contactids', 'intcomma')))),
            'elements'   => array_filter(explode(',', GETPOST('elements', 'alphanohtml'))),
            'note'       => '',
        ];
    }

    // Labels of what is already chosen, the selects only search the rest
    $selectedContacts = [];
    foreach ($formValues['contacts'] as $contactId) {
        $contact = new Contact($db);
        if ($contact->fetch($contactId) > 0) {
            $text = $contact->getFullName($langs);
            if ($contact->socid > 0 && $contact->fetch_thirdparty() > 0) {
                $text .= ' — ' . $contact->thirdparty->name;
            }
            $selectedContacts[$contactId] = $text . (!empty($contact->email) ? ' <' . $contact->email . '>' : '');
        }
    }
    $selectedThirdparty = '';
    if ($formValues['socid'] > 0) {
        $thirdparty = new Societe($db);
        if ($thirdparty->fetch($formValues['socid']) > 0) {
            $selectedThirdparty = $thirdparty->name;
        }
    }
    $selectedProject = '';
    if ($formValues['project_id'] > 0 && isModEnabled('project')) {
        require_once DOL_DOCUMENT_ROOT . '/projet/class/project.class.php';
        $project = new Project($db);
        if ($project->fetch($formValues['project_id']) > 0) {
            $selectedProject = $project->ref . ' · ' . $project->title;
        }
    }
    $selectedElements = [];
    $linkableTypes    = dolical_get_linkable_types($user);
    foreach ($formValues['elements'] as $key) {
        if (!preg_match('/^([a-z_]+):(\d+)$/', $key, $matches) || !isset($linkableTypes[$matches[1]])) {
            continue;
        }
        $object = fetchObjectByElement((int) $matches[2], $matches[1]);
        if (is_object($object) && $object->id > 0) {
            $description            = dolical_describe_object($object, $matches[1], $linkableTypes[$matches[1]]);
            $selectedElements[$key] = $description['typeLabel'] . ' ' . $description['ref'] . ($description['amountText'] !== '' ? ' (' . $description['amountText'] . ')' : '');
        }
    }
    $eventFiles = $event !== null ? dolical_get_event_files($event) : [];

    // Repetition : the kind of the series of the event, with its end ; a clone starts a series of its own
    $recurrenceChoices        = dolical_recurrence_choices((int) $formValues['start']);
    $formValues['recurrence'] = $source !== null ? dolical_recurrence_kind((string) $source->recurrule, (int) $source->datep) : '';
    $formValues['recurUntil'] = ($source !== null && !empty($source->recurdateend)) ? (int) $source->recurdateend : dolical_recurrence_default_until('weekly', (int) $formValues['start']);
    if ($formValues['recurrence'] === 'custom') {
        $recurrenceChoices['custom'] = dolical_recurrence_label((string) $source->recurrule, (int) $source->datep);
    }
    $seriesSize = ($event !== null && !empty($event->recurid)) ? count(dolical_series_occurrences($event)) : 0;

    // Reminder : the one of the user on this event, else the one a new event gets
    $reminderTypes = dolical_reminder_types();
    $reminder      = $source !== null ? dolical_get_reminders($source, (int) $user->id) : ['enabled' => false];
    if (!$reminder['enabled']) {
        $reminder = dolical_reminder_default((string) $formValues['type_code']);
        // An event already saved without reminder stays without : the switch is off
        if ($event !== null) {
            $reminder['enabled'] = false;
        }
    }

    header('Content-Type: text/html; charset=utf-8');
    require __DIR__ . '/../core/tpl/dolical_event_form.tpl.php';
    exit;
}

if ($action === 'report' && $event !== null) {
    // Report of a meeting : its text, and the media block for photos, documents and voice notes
    $card = dolical_get_event_card($event, $user);
    if (!$card['canReport']) {
        dolical_json_exit(['success' => false, 'error' => $langs->trans('NotEnoughPermissions')], 403);
    }
    $canDictate = DoliCalVoiceReport::isAvailable();
    $mediaBlock = dolical_report_media_block($event, true);

    header('Content-Type: text/html; charset=utf-8');
    require __DIR__ . '/../core/tpl/dolical_event_report.tpl.php';
    exit;
}

if ($action === 'mediablock' && $event !== null) {
    // The media block of the report drawn again, once files were dropped next to it
    if (!dolical_can_report($user, $event)) {
        dolical_json_exit(['success' => false, 'error' => $langs->trans('NotEnoughPermissions')], 403);
    }
    header('Content-Type: text/html; charset=utf-8');
    echo dolical_report_media_block($event, true);
    exit;
}

if ($action === 'availability') {
    // Read only : who among the people of the form is already taken during the slot
    if (!dolical_can_create($user)) {
        dolical_json_exit(['success' => false, 'conflicts' => []], 403);
    }
    $conflicts = dolical_find_conflicts($user, GETPOSTINT('start'), GETPOSTINT('end'), array_map('intval', explode(',', GETPOST('users', 'intcomma'))), $id);
    dolical_json_exit(['success' => true, 'conflicts' => $conflicts]);
}

/*
 * Actions : JSON answers
 */

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    dolical_json_exit(['success' => false, 'error' => 'BadMethod'], 405);
}

// main.inc.php only checks the token when MAIN_SECURITY_CSRF_WITH_TOKEN asks for it, or when one is sent : every
// change made here requires the token of the session, whatever the setup
$token = GETPOST('token', 'alpha');
if ($token === '' || !in_array($token, [currentToken(), newToken()], true)) {
    dolical_json_exit(['success' => false, 'error' => $langs->trans('SecurityTokenHasExpiredSoActionHasBeenCanceledPleaseRetry')], 403);
}

if ($action === 'save') {
    if ($event !== null ? !dolical_can_edit_event($user, $event) : !dolical_can_create($user)) {
        dolical_json_exit(['success' => false, 'error' => $langs->trans('NotEnoughPermissions')], 403);
    }
    if ($event === null) {
        $event = new ActionComm($db);
    }

    // People typed as an email become contacts, found or created
    $contactErrors = [];
    $contactIds    = dolical_resolve_contacts(GETPOST('contact_ids', 'array:alphanohtml'), GETPOSTINT('socid'), $user, $contactErrors);
    if (!empty($contactErrors)) {
        dolical_json_exit(['success' => false, 'error' => implode(' ', $contactErrors)]);
    }

    // What the invitations compare with : who was already there, and when and where it took place
    $isNew          = empty($event->id);
    $previousPeople = [];
    foreach (array_keys($event->userassigned) as $userId) {
        $previousPeople['u' . $userId] = true;
    }
    foreach (array_keys($event->socpeopleassigned) as $contactId) {
        $previousPeople['c' . $contactId] = true;
    }
    $previousPlace = [(int) $event->datep, (int) $event->datef, (int) $event->fulldayevent, trim((string) $event->location)];
    // The people invited by email before the change : the ones removed have to be told
    $previouslyInvited = $isNew ? [] : dolical_invited_only(dolical_invitation_recipients($event), dolical_invitation_history($event));
    $previousRecurid   = (string) $event->recurid;

    $data = [
        'label'        => GETPOST('label', 'alphanohtml'),
        'type_code'    => GETPOST('type_code', 'aZ09'),
        'fullday'      => GETPOSTINT('fullday'),
        'start'        => GETPOSTINT('start'),
        'end'          => GETPOSTINT('end'),
        'location'     => GETPOST('location', 'alphanohtml'),
        'percent'      => GETPOSTINT('percent'),
        'busy'         => GETPOSTINT('busy'),
        'owner_id'     => GETPOSTINT('owner_id'),
        'user_ids'     => GETPOST('user_ids', 'array:int'),
        'socid'        => GETPOSTINT('socid'),
        'contact_ids'  => $contactIds,
        'project_id'   => GETPOSTINT('project_id'),
        'elements'     => GETPOST('elements', 'array:alphanohtml'),
        'note'         => GETPOST('note', 'restricthtml'),
        'note_changed' => GETPOSTINT('note_changed'),
        'recurrence'   => GETPOST('recurrence', 'aZ09'),
        'recur_until'  => GETPOSTINT('recur_until'),
        'recur_scope'  => GETPOST('recur_scope', 'aZ09'),
        // Untouched when the form had no reminder part : the reminders of the event stay
        'reminder'     => GETPOSTISSET('reminder_offset') ? [
            'enabled'  => GETPOSTINT('reminder'),
            'offset'   => GETPOSTINT('reminder_offset'),
            'unit'     => GETPOST('reminder_unit', 'aZ09'),
            'types'    => GETPOST('reminder_types', 'array:aZ09'),
            'everyone' => GETPOSTINT('reminder_everyone'),
        ] : null,
    ];

    $saved  = dolical_save_event_series($event, $data, $user);
    $result = $saved['id'];
    if ($result <= 0) {
        dolical_json_exit(['success' => false, 'error' => $saved['error'] ?: $langs->trans('Error')]);
    }

    // Attachments chosen in the form go with it, into the documents of the event
    $uploadErrors = [];
    if (!empty($_FILES['files']['name'][0])) {
        $event->fetch($result);
        dol_add_file_process(dolical_get_event_dir($event), 0, 1, 'files', '', null, '', 1, $event);
        $uploadErrors = dolical_pull_event_messages();
    }

    // Mails : the newcomers are invited ; when the date or the place changed, the people already invited get the
    // change ; the people removed after their invitation get the cancellation, the event leaves their calendar
    $mail = ['sent' => 0, 'cancelled' => 0, 'errors' => []];
    if (GETPOSTINT('notify')) {
        $event->fetch($result);
        // A series is sent once, from its first date, with its rule ; one date of it as an exception of the series
        $mailEvent   = $event;
        $mailOptions = [];
        if (!empty($event->recurid) && $saved['scope'] !== 'one') {
            $first = new ActionComm($db);
            if ($first->fetch((int) array_key_first(dolical_series_occurrences($event))) > 0) {
                $mailEvent = $first;
            }
            $mailOptions = ['series' => true];
        } elseif (!empty($event->recurid) && $previousRecurid !== '') {
            $mailOptions = ['recurrenceId' => $saved['oldStart']];
        }
        $changed     = !$isNew && ($saved['ruleChanged'] || $previousPlace !== [(int) $event->datep, (int) $event->datef, (int) $event->fulldayevent, trim((string) $event->location)]);
        $current     = dolical_invitation_recipients($mailEvent);
        $currentKeys = array_map(function ($recipient) {
            return $recipient['kind'] . $recipient['id'];
        }, $current);
        $invitedKeys = array_map(function ($recipient) {
            return $recipient['kind'] . $recipient['id'];
        }, $previouslyInvited);

        $toUpdate = $changed ? array_values(array_filter($current, function ($recipient) use ($invitedKeys) {
            return in_array($recipient['kind'] . $recipient['id'], $invitedKeys, true);
        })) : [];
        $toInvite = array_values(array_filter($current, function ($recipient) use ($changed, $invitedKeys, $previousPeople) {
            $key = $recipient['kind'] . $recipient['id'];
            return $changed ? !in_array($key, $invitedKeys, true) : !isset($previousPeople[$key]);
        }));
        $toCancel = array_values(array_filter($previouslyInvited, function ($recipient) use ($currentKeys) {
            return !in_array($recipient['kind'] . $recipient['id'], $currentKeys, true);
        }));

        foreach (['update' => $toUpdate, 'invitation' => $toInvite, 'cancel' => $toCancel] as $mode => $recipients) {
            $sending           = dolical_send_invitations($mailEvent, $user, $recipients, $mode, $mailOptions);
            $mail[$mode === 'cancel' ? 'cancelled' : 'sent'] += $sending['sent'];
            $mail['errors']    = array_merge($mail['errors'], $sending['errors']);
        }

        // Cut in two : the dates before keep the old series, which now ends sooner
        if ($saved['oldSeriesFirst'] > 0) {
            $oldSeries = new ActionComm($db);
            if ($oldSeries->fetch($saved['oldSeriesFirst']) > 0) {
                $sending        = dolical_send_invitations($oldSeries, $user, dolical_invited_only(dolical_invitation_recipients($oldSeries), dolical_invitation_history($oldSeries)), 'update', ['series' => true]);
                $mail['errors'] = array_merge($mail['errors'], $sending['errors']);
            }
        }
    }

    dolical_json_exit([
        'success'   => true,
        'id'        => $result,
        'warnings'  => array_merge($uploadErrors, $mail['errors']),
        'sent'      => $mail['sent'],
        'cancelled' => $mail['cancelled'],
        'created'   => $saved['created'],
        'removed'   => $saved['removed'],
        'truncated' => $saved['truncated'],
    ]);
}

if ($event === null) {
    dolical_json_exit(['success' => false, 'error' => $langs->trans('ErrorRecordNotFound')], 404);
}

switch ($action) {
    case 'move':
        if (!dolical_can_edit_event($user, $event)) {
            dolical_json_exit(['success' => false, 'error' => $langs->trans('NotEnoughPermissions')], 403);
        }
        $start  = GETPOSTINT('start');
        $toUser = GETPOSTINT('to_user');
        if ($start <= 0) {
            dolical_json_exit(['success' => false, 'error' => 'BadDate']);
        }
        // Dropped on the column of someone else : a person whose agenda the user may display
        if ($toUser > 0 && !isset(dolical_get_calendar_users($user)[$toUser])) {
            dolical_json_exit(['success' => false, 'error' => $langs->trans('NotEnoughPermissions')], 403);
        }
        $result = dolical_move_event($event, $start, GETPOSTINT('end'), (bool) GETPOSTINT('allday'), GETPOSTINT('from_user'), $toUser, $user);
        if ($result > 0) {
            // The reminders go with the event ; a date of a series moves alone, the series stays
            dolical_shift_reminders($event);
        }
        dolical_json_exit(['success' => $result > 0, 'error' => $result > 0 ? '' : $event->error, 'occurrence' => !empty($event->recurid)]);
        break;

    case 'invite':
        // Send the invitation again, to one person or to all the participants
        if (!dolical_can_edit_event($user, $event)) {
            dolical_json_exit(['success' => false, 'error' => $langs->trans('NotEnoughPermissions')], 403);
        }
        $kind       = GETPOST('kind', 'aZ09');
        $recipients = dolical_invitation_recipients($event, in_array($kind, ['u', 'c'], true) ? $kind : '', GETPOSTINT('person'));
        if (empty($recipients)) {
            dolical_json_exit(['success' => false, 'error' => $langs->trans('DoliCalNobodyToInvite')]);
        }
        $mail = dolical_send_invitations($event, $user, $recipients, 'invitation');
        dolical_json_exit(['success' => $mail['sent'] > 0, 'sent' => $mail['sent'], 'error' => implode(' ', $mail['errors'])]);
        break;

    case 'remind':
        // Remind the people invited who have not answered yet
        if (!dolical_can_edit_event($user, $event)) {
            dolical_json_exit(['success' => false, 'error' => $langs->trans('NotEnoughPermissions')], 403);
        }
        $recipients = dolical_pending_recipients($event);
        if (empty($recipients)) {
            dolical_json_exit(['success' => false, 'error' => $langs->trans('DoliCalNobodyToRemind')]);
        }
        $mail = dolical_send_invitations($event, $user, $recipients, 'reminder');
        dolical_json_exit(['success' => $mail['sent'] > 0, 'sent' => $mail['sent'], 'error' => implode(' ', $mail['errors'])]);
        break;

    case 'setpercent':
        if (!dolical_can_edit_event($user, $event)) {
            dolical_json_exit(['success' => false, 'error' => $langs->trans('NotEnoughPermissions')], 403);
        }
        $event->oldcopy    = clone $event;
        $event->percentage = max(-1, min(100, GETPOSTINT('percent')));
        $result            = $event->update($user);
        dolical_json_exit(['success' => $result > 0, 'error' => $result > 0 ? '' : $event->error]);
        break;

    case 'answer':
        // Only a person invited to the event answers, for himself
        $status = GETPOSTINT('status');
        if (!in_array($status, [-1, 0, 1, 2], true) || !(isset($event->userassigned[$user->id]) || $event->userownerid == $user->id)) {
            dolical_json_exit(['success' => false, 'error' => $langs->trans('NotEnoughPermissions')], 403);
        }
        $sql   = 'UPDATE ' . MAIN_DB_PREFIX . 'actioncomm_resources SET answer_status = ' . ((int) $status);
        $sql  .= ' WHERE fk_actioncomm = ' . ((int) $event->id) . " AND element_type = 'user' AND fk_element = " . ((int) $user->id);
        $resql = $db->query($sql);
        if ($resql && $db->affected_rows($resql) == 0 && !isset($event->userassigned[$user->id])) {
            $sql   = 'INSERT INTO ' . MAIN_DB_PREFIX . 'actioncomm_resources (fk_actioncomm, element_type, fk_element, mandatory, transparency, answer_status)';
            $sql  .= ' VALUES (' . ((int) $event->id) . ", 'user', " . ((int) $user->id) . ', 0, ' . ((int) $event->transparency) . ', ' . ((int) $status) . ')';
            $resql = $db->query($sql);
        }
        dolical_json_exit(['success' => (bool) $resql, 'error' => $resql ? '' : $db->lasterror()]);
        break;

    case 'delete':
        if (!dolical_can_delete_event($user, $event)) {
            dolical_json_exit(['success' => false, 'error' => $langs->trans('NotEnoughPermissions')], 403);
        }
        // This date, this one and the following, or the whole series ; the people invited by email are told, the
        // documents of the events go with them (the native deletion leaves them behind, out of reach)
        $scope   = GETPOST('scope', 'aZ09');
        $deleted = dolical_delete_event_series($event, in_array($scope, ['one', 'following', 'all'], true) ? $scope : 'one', $user);
        dolical_json_exit(['success' => $deleted['success'], 'error' => $deleted['success'] ? implode(' ', $deleted['errors']) : (implode(' ', $deleted['errors']) ?: $langs->trans('Error')), 'cancelled' => $deleted['cancelled'], 'deleted' => $deleted['deleted']]);
        break;

    case 'savereport':
        // The report of a meeting : whoever took part in it, or may change it
        if (!dolical_can_report($user, $event)) {
            dolical_json_exit(['success' => false, 'error' => $langs->trans('NotEnoughPermissions')], 403);
        }
        $done   = GETPOSTINT('done') && dolical_can_edit_event($user, $event);
        $result = dolical_save_report($event, GETPOST('report', 'restricthtml'), $done, $user);
        dolical_json_exit(['success' => $result > 0, 'error' => $result > 0 ? '' : ($event->error ?: $langs->trans('Error'))]);
        break;

    case 'dictate':
        // The voice note just recorded in the media block, written up as a report by the AI module
        if (!dolical_can_report($user, $event)) {
            dolical_json_exit(['success' => false, 'error' => $langs->trans('NotEnoughPermissions')], 403);
        }
        if (!DoliCalVoiceReport::isAvailable()) {
            dolical_json_exit(['success' => false, 'error' => $langs->transnoentities('DoliCalVoiceUnavailable')]);
        }
        $voiceNote = dolical_latest_voice_note($event);
        if ($voiceNote === '') {
            dolical_json_exit(['success' => false, 'error' => $langs->transnoentities('DoliCalVoiceNoRecording')]);
        }
        $participants = dolical_get_event_participants($event);
        $context      = [
            'label'      => dol_html_entity_decode((string) $event->label, ENT_QUOTES | ENT_HTML5),
            'date'       => dolical_event_when($event),
            'people'     => implode(', ', array_column($participants['people'], 'name')),
            'thirdparty' => $event->socid > 0 && $event->fetch_thirdparty() > 0 ? $event->thirdparty->name : '',
            'notes'      => dol_string_nohtmltag((string) $event->note_private),
        ];
        $writer = new DoliCalVoiceReport($db);
        $report = $writer->write($voiceNote, $context);
        dolical_json_exit(['success' => $report !== null, 'report' => (string) $report, 'transcript' => $writer->transcript, 'error' => $report === null ? $writer->error : '']);
        break;

    case 'upload':
        // Whoever writes the report of a meeting adds its photos and documents too
        if (!dolical_can_edit_event($user, $event) && !dolical_can_report($user, $event)) {
            dolical_json_exit(['success' => false, 'error' => $langs->trans('NotEnoughPermissions')], 403);
        }
        $result = dol_add_file_process(dolical_get_event_dir($event), 0, 1, 'files', '', null, '', 1, $event);
        $errors = dolical_pull_event_messages();
        dolical_json_exit(['success' => $result > 0, 'error' => implode(' ', $errors), 'files' => dolical_get_event_files($event)]);
        break;

    case 'deletefile':
        if (!dolical_can_edit_event($user, $event)) {
            dolical_json_exit(['success' => false, 'error' => $langs->trans('NotEnoughPermissions')], 403);
        }
        // The name must be one of the files of the event : nothing outside its directory can be reached
        $name = GETPOST('file', 'alphanohtml');
        $dir  = dolical_get_event_dir($event);
        $done = false;
        foreach (dol_dir_list($dir, 'files') as $file) {
            if ($file['name'] === $name) {
                try {
                    $done = dol_delete_file($dir . '/' . $file['name'], 0, 0, 0, $event);
                } catch (Throwable $exception) {
                    // Accented name and ECM table still in latin1 : the lookup of the index fails, the file still goes
                    dol_syslog('DoliCal deletefile ' . $exception->getMessage(), LOG_WARNING);
                    $done = dol_delete_file($dir . '/' . $file['name'], 0, 0, 0, null, false, 0);
                }
                if ($done && image_format_supported($file['name']) >= 0) {
                    dol_delete_file($dir . '/thumbs/' . getImageFileNameForSize($file['name'], '_small'), 0, 0, 0, null, false, 0);
                    dol_delete_file($dir . '/thumbs/' . getImageFileNameForSize($file['name'], '_mini'), 0, 0, 0, null, false, 0);
                }
                break;
            }
        }
        dolical_json_exit(['success' => (bool) $done, 'files' => dolical_get_event_files($event)]);
        break;
}

dolical_json_exit(['success' => false, 'error' => 'UnknownAction'], 400);

/**
 * Messages that dol_add_file_process() left in the session for the next page : the calendar shows them
 * itself, they must not pop up later on another page
 *
 * @return string[] Error and warning messages
 */
function dolical_pull_event_messages(): array
{
    $messages = [];
    foreach (['errors', 'warnings'] as $level) {
        if (!empty($_SESSION['dol_events'][$level])) {
            $messages = array_merge($messages, (array) $_SESSION['dol_events'][$level]);
        }
    }
    unset($_SESSION['dol_events']);

    // Dolibarr writes them for HTML, with entities : the calendar shows them as plain text
    return array_map(function ($message) {
        return dol_html_entity_decode(strip_tags($message), ENT_QUOTES | ENT_HTML5);
    }, $messages);
}
