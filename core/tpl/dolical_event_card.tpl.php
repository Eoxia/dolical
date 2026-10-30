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
 * \file    core/tpl/dolical_event_card.tpl.php
 * \ingroup dolical
 * \brief   Card of an event, shown when it is clicked in the calendar.
 *
 * Rendered by ajax/event.php (inherits its scope). Expects: $card from dolical_get_event_card(), $langs.
 * Colors travel in data-color attributes, the JavaScript turns them into a CSS variable.
 */

$answerLabels = [
    1  => ['icon' => 'fas fa-check-circle', 'class' => 'accepted', 'label' => $langs->trans('DoliCalAnswerAccepted')],
    2  => ['icon' => 'fas fa-question-circle', 'class' => 'tentative', 'label' => $langs->trans('DoliCalAnswerTentative')],
    -1 => ['icon' => 'fas fa-times-circle', 'class' => 'declined', 'label' => $langs->trans('DoliCalAnswerDeclined')],
    0  => ['icon' => 'far fa-clock', 'class' => 'pending', 'label' => $langs->trans('DoliCalAnswerPending')],
];

$participants  = $card['participants'];
$otherObjects  = array_filter($card['objects'], function ($object) {
    return !in_array($object['element'], ['propal', 'facture']);
});
$billing       = $card['billing'];
$billingIcons  = ['paid' => 'fas fa-check-circle', 'unpaid' => 'fas fa-hourglass-half', 'late' => 'fas fa-exclamation-triangle', 'signed' => 'fas fa-file-signature', 'pending' => 'far fa-file-alt'];
?>
<div class="dolical-card" data-id="<?php echo $card['id']; ?>" data-can-edit="<?php echo $card['canEdit'] ? 1 : 0; ?>" data-invited="<?php echo (int) ($card['invitedCount'] ?? 0); ?>" data-series="<?php echo (int) $card['seriesSize']; ?>">
    <div class="dolical-card__header">
        <span class="dolical-card__calendar">
            <span class="dolical-dot" data-color="<?php echo dol_escape_htmltag($card['owner']['color']); ?>"></span>
            <?php echo dol_escape_htmltag($card['owner']['name']); ?>
        </span>
        <div class="dolical-card__tools">
            <div class="dolical-card__menu-wrap">
                <button type="button" class="dolical-icon-btn dolical-card__menu-toggle" title="<?php echo dol_escape_htmltag($langs->trans('DoliCalMoreActions')); ?>"><i class="fas fa-ellipsis-h"></i></button>
                <div class="dolical-card__menu">
                    <?php if ($card['canEdit'] && $card['percent'] >= 0) : ?>
                        <?php if ($card['percent'] < 100) : ?>
                            <button type="button" class="dolical-card__action" data-action="setpercent" data-percent="100"><i class="fas fa-check pictofixedwidth"></i><?php echo $langs->trans('DoliCalMarkDone'); ?></button>
                        <?php else : ?>
                            <button type="button" class="dolical-card__action" data-action="setpercent" data-percent="0"><i class="fas fa-undo pictofixedwidth"></i><?php echo $langs->trans('DoliCalReopen'); ?></button>
                        <?php endif; ?>
                    <?php endif; ?>
                    <?php if ($card['canReport'] && $card['report'] === '' && !$card['reportDue']) : ?>
                        <button type="button" class="dolical-card__action" data-action="report"><i class="far fa-file-alt pictofixedwidth"></i><?php echo $langs->trans('DoliCalReportAdd'); ?></button>
                    <?php endif; ?>
                    <button type="button" class="dolical-card__action" data-action="clone"><i class="far fa-clone pictofixedwidth"></i><?php echo $langs->trans('DoliCalDuplicate'); ?></button>
                    <button type="button" class="dolical-card__action" data-action="copylink" data-copy="<?php echo dol_escape_htmltag(dol_buildpath('/dolical/dolicalindex.php', 2) . '?event=' . $card['id']); ?>"><i class="fas fa-link pictofixedwidth"></i><?php echo $langs->trans('DoliCalCopyLink'); ?></button>
                    <a class="dolical-card__action" href="<?php echo dol_escape_htmltag($card['nativeUrl']); ?>"><i class="fas fa-external-link-alt pictofixedwidth"></i><?php echo $langs->trans('DoliCalOpenNativeCard'); ?></a>
                    <?php if ($card['canDelete']) : ?>
                        <button type="button" class="dolical-card__action dolical-card__action--danger" data-action="delete"><i class="far fa-trash-alt pictofixedwidth"></i><?php echo $langs->trans('Delete'); ?></button>
                    <?php endif; ?>
                </div>
            </div>
            <button type="button" class="dolical-icon-btn dolical-card__close" title="<?php echo dol_escape_htmltag($langs->trans('DoliCalClose')); ?>"><i class="fas fa-times"></i></button>
        </div>
    </div>

    <div class="dolical-card__body">
        <h3 class="dolical-card__title<?php echo $card['percent'] == 100 ? ' dolical-card__title--done' : ''; ?>"><?php echo dol_escape_htmltag($card['label']); ?></h3>

        <div class="dolical-card__chips">
            <span class="dolical-chip" data-color="<?php echo dol_escape_htmltag($card['typeColor']); ?>"><i class="<?php echo dol_escape_htmltag($card['typeIcon']); ?>"></i><?php echo dol_escape_htmltag($card['typeLabel']); ?></span>
            <?php if ($card['percent'] >= 0) : ?>
                <span class="dolical-chip dolical-chip--status"><?php echo $card['status']; ?></span>
            <?php endif; ?>
            <?php if ($card['isPast']) : ?>
                <span class="dolical-chip dolical-chip--late"><i class="fas fa-exclamation-circle"></i><?php echo $langs->trans('DoliCalLate'); ?></span>
            <?php endif; ?>
            <?php foreach ($card['tags'] as $tag) : ?>
                <span class="dolical-chip dolical-chip--tag" data-color="<?php echo dol_escape_htmltag($tag['color']); ?>"><i class="fas fa-tag"></i><?php echo dol_escape_htmltag($tag['label']); ?></span>
            <?php endforeach; ?>
        </div>

        <div class="dolical-card__row">
            <i class="far fa-calendar dolical-card__row-icon"></i>
            <div>
                <div><?php echo dol_escape_htmltag($card['dateLines'][0]); ?></div>
                <div class="dolical-card__muted">
                    <?php echo dol_escape_htmltag($card['dateLines'][1]); ?>
                    <?php if ($card['duration'] !== '') : ?>
                        · <?php echo dol_escape_htmltag($card['duration']); ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <?php if ($card['recurrence'] !== '') : ?>
            <div class="dolical-card__row">
                <i class="fas fa-redo-alt dolical-card__row-icon"></i>
                <div class="dolical-card__grow">
                    <?php echo dol_escape_htmltag($card['recurrence']); ?>
                    <?php if ($card['seriesSize'] > 1) : ?>
                        <span class="dolical-card__muted">· <?php echo $langs->trans('DoliCalSeriesDates', $card['seriesSize']); ?></span>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($card['reminder'] !== '') : ?>
            <div class="dolical-card__row">
                <i class="far fa-bell dolical-card__row-icon"></i>
                <div class="dolical-card__grow"><?php echo dol_escape_htmltag($card['reminder']); ?></div>
            </div>
        <?php endif; ?>

        <?php if ($card['location'] !== '') : ?>
            <div class="dolical-card__row">
                <i class="fas fa-map-marker-alt dolical-card__row-icon"></i>
                <div class="dolical-card__grow"><?php echo dol_escape_htmltag($card['location']); ?></div>
                <button type="button" class="dolical-icon-btn dolical-copy" data-copy="<?php echo dol_escape_htmltag($card['location']); ?>" title="<?php echo dol_escape_htmltag($langs->trans('DoliCalCopy')); ?>"><i class="far fa-copy"></i></button>
                <a class="dolical-icon-btn" href="https://www.openstreetmap.org/search?query=<?php echo urlencode($card['location']); ?>" target="_blank" rel="noopener" title="<?php echo dol_escape_htmltag($langs->trans('DoliCalShowOnMap')); ?>"><i class="fas fa-map"></i></a>
            </div>
        <?php endif; ?>

        <?php if ($card['isMine'] && $card['owner']['id'] != $user->id) : ?>
            <div class="dolical-card__answer">
                <span><?php echo $langs->trans('DoliCalWillYouAttend'); ?></span>
                <?php foreach ([1 => 'Yes', 2 => 'DoliCalMaybe', -1 => 'No'] as $status => $labelKey) : ?>
                    <button type="button" class="dolical-btn dolical-btn--small<?php echo $card['myStatus'] === $status ? ' dolical-btn--on' : ''; ?>" data-action="answer" data-status="<?php echo $status; ?>"><?php echo $langs->trans($labelKey); ?></button>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if ($card['report'] !== '') : ?>
            <div class="dolical-card__section dolical-card__report">
                <div class="dolical-card__section-title">
                    <i class="far fa-file-alt dolical-card__row-icon"></i>
                    <span><?php echo $langs->trans('DoliCalReport'); ?></span>
                    <?php if ($card['canReport']) : ?>
                        <button type="button" class="dolical-icon-btn dolical-card__report-edit" data-action="report" title="<?php echo dol_escape_htmltag($langs->trans('DoliCalReportEdit')); ?>"><i class="fas fa-pen"></i></button>
                    <?php endif; ?>
                </div>
                <div class="dolical-card__note dolical-card__indent"><?php echo dol_nl2br(dol_escape_htmltag($card['report'], 0, 1)); ?></div>
                <button type="button" class="dolical-link dolical-card__note-toggle dolical-card__indent"><?php echo $langs->trans('DoliCalShowMore'); ?></button>
            </div>
        <?php elseif ($card['reportDue']) : ?>
            <div class="dolical-card__prompt">
                <div class="dolical-card__prompt-text">
                    <i class="far fa-comment-dots"></i>
                    <span><?php echo $langs->trans('DoliCalReportPrompt'); ?></span>
                </div>
                <div class="dolical-card__prompt-actions">
                    <button type="button" class="dolical-btn dolical-btn--small dolical-btn--primary" data-action="report"><i class="fas fa-pen"></i><?php echo $langs->trans('DoliCalReportWrite'); ?></button>
                    <button type="button" class="dolical-btn dolical-btn--small" data-action="dictate"><i class="fas fa-microphone"></i><?php echo $langs->trans('DoliCalReportDictate'); ?></button>
                </div>
            </div>
        <?php endif; ?>

        <?php if (!empty($participants['people'])) : ?>
            <div class="dolical-card__section">
                <div class="dolical-card__section-title">
                    <i class="fas fa-user-friends dolical-card__row-icon"></i>
                    <span><?php echo $langs->trans('DoliCalParticipants'); ?></span>
                    <span class="dolical-badge"><?php echo count($participants['people']); ?></span>
                    <?php if (!empty($participants['emails'])) : ?>
                        <button type="button" class="dolical-icon-btn dolical-copy" data-copy="<?php echo dol_escape_htmltag(implode(', ', $participants['emails'])); ?>" title="<?php echo dol_escape_htmltag($langs->trans('DoliCalCopyAllEmails')); ?>"><i class="far fa-copy"></i></button>
                        <a class="dolical-icon-btn" href="mailto:<?php echo dol_escape_htmltag(implode(',', $participants['emails'])); ?>?subject=<?php echo rawurlencode($card['label']); ?>" title="<?php echo dol_escape_htmltag($langs->trans('DoliCalMailEveryone')); ?>"><i class="far fa-envelope"></i></a>
                    <?php endif; ?>
                    <?php if (!empty($card['pendingCount'])) : ?>
                        <button type="button" class="dolical-btn dolical-btn--small dolical-card__invite-all" data-action="remind" title="<?php echo dol_escape_htmltag($langs->trans('DoliCalRemindHelp')); ?>"><i class="far fa-bell"></i><?php echo $langs->trans('DoliCalRemind', $card['pendingCount']); ?></button>
                    <?php endif; ?>
                    <?php if (!empty($card['canInviteAll'])) : ?>
                        <button type="button" class="dolical-btn dolical-btn--small<?php echo empty($card['pendingCount']) ? ' dolical-card__invite-all' : ''; ?>" data-action="invite"><i class="far fa-paper-plane"></i><?php echo $langs->trans('DoliCalSendInvitations'); ?></button>
                    <?php endif; ?>
                </div>
                <div class="dolical-card__muted dolical-card__indent">
                    <?php echo $langs->trans('DoliCalAnswersSummary', $participants['confirmed'], $participants['pending']); ?>
                    <?php if ($participants['declined'] > 0) : ?>
                        , <?php echo $langs->trans('DoliCalAnswersDeclined', $participants['declined']); ?>
                    <?php endif; ?>
                </div>
                <ul class="dolical-people">
                    <?php foreach ($participants['people'] as $person) : ?>
                        <?php $answer = $person['owner'] ? ['icon' => 'fas fa-crown', 'class' => 'owner', 'label' => $langs->trans('DoliCalOrganizer')] : $answerLabels[$person['status']] ?? $answerLabels[0]; ?>
                        <li class="dolical-person dolical-person--<?php echo $person['kind']; ?>">
                            <span class="dolical-avatar" data-color="<?php echo dol_escape_htmltag($person['color']); ?>" title="<?php echo dol_escape_htmltag($person['kind'] === 'user' ? $langs->trans('User') : $langs->trans('Contact')); ?>"><?php echo dol_escape_htmltag($person['initials']); ?></span>
                            <div class="dolical-person__body">
                                <div class="dolical-person__line">
                                    <span class="dolical-person__name"><?php echo $person['nomUrl']; ?></span>
                                    <?php if ($person['email'] !== '') : ?>
                                        <span class="dolical-person__email"><?php echo dol_escape_htmltag($person['email']); ?></span>
                                    <?php endif; ?>
                                </div>
                                <div class="dolical-person__status dolical-person__status--<?php echo $answer['class']; ?>">
                                    <i class="<?php echo $answer['icon']; ?>"></i>
                                    <?php echo dol_escape_htmltag($answer['label']); ?>
                                    <?php if ($person['company'] !== '') : ?>
                                        <span class="dolical-card__muted">· <?php echo dol_escape_htmltag($person['company']); ?></span>
                                    <?php endif; ?>
                                    <?php if (!empty($person['invitedAt'])) : ?>
                                        <span class="dolical-card__muted dolical-person__invited" title="<?php echo dol_escape_htmltag($langs->trans('DoliCalInvitedOn', $person['invitedAt'])); ?>">· <i class="far fa-paper-plane"></i><?php echo dol_escape_htmltag($person['invitedAt']); ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="dolical-person__tools">
                                <?php if (!empty($person['canInvite'])) : ?>
                                    <button type="button" class="dolical-icon-btn" data-action="invite" data-kind="<?php echo $person['kind'] === 'user' ? 'u' : 'c'; ?>" data-person="<?php echo (int) $person['id']; ?>" title="<?php echo dol_escape_htmltag($langs->trans(empty($person['invitedAt']) ? 'DoliCalSendInvitation' : 'DoliCalSendInvitationAgain')); ?>"><i class="far fa-paper-plane"></i></button>
                                <?php endif; ?>
                                <?php if ($person['email'] !== '') : ?>
                                    <button type="button" class="dolical-icon-btn dolical-copy" data-copy="<?php echo dol_escape_htmltag($person['email']); ?>" title="<?php echo dol_escape_htmltag($langs->trans('DoliCalCopyEmail')); ?>"><i class="far fa-copy"></i></button>
                                    <a class="dolical-icon-btn" href="mailto:<?php echo dol_escape_htmltag($person['email']); ?>" title="<?php echo dol_escape_htmltag($langs->trans('SendMail')); ?>"><i class="far fa-envelope"></i></a>
                                <?php endif; ?>
                                <?php if ($person['phone'] !== '') : ?>
                                    <a class="dolical-icon-btn" href="tel:<?php echo dol_escape_htmltag(preg_replace('/[^0-9+]/', '', $person['phone'])); ?>" title="<?php echo dol_escape_htmltag($person['phone']); ?>"><i class="fas fa-phone-alt"></i></a>
                                <?php endif; ?>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <?php if ($card['thirdparty'] !== null || $card['project'] !== null) : ?>
            <div class="dolical-card__section">
                <?php if ($card['thirdparty'] !== null) : ?>
                    <div class="dolical-card__row">
                        <i class="far fa-building dolical-card__row-icon"></i>
                        <div class="dolical-card__grow">
                            <?php echo $card['thirdparty']['nomUrl']; ?>
                            <?php if ($card['thirdparty']['town'] !== '') : ?>
                                <span class="dolical-card__muted">· <?php echo dol_escape_htmltag($card['thirdparty']['town']); ?></span>
                            <?php endif; ?>
                        </div>
                        <?php if ($card['thirdparty']['phone'] !== '') : ?>
                            <a class="dolical-icon-btn" href="tel:<?php echo dol_escape_htmltag(preg_replace('/[^0-9+]/', '', $card['thirdparty']['phone'])); ?>" title="<?php echo dol_escape_htmltag($card['thirdparty']['phone']); ?>"><i class="fas fa-phone-alt"></i></a>
                        <?php endif; ?>
                        <?php if ($card['thirdparty']['email'] !== '') : ?>
                            <a class="dolical-icon-btn" href="mailto:<?php echo dol_escape_htmltag($card['thirdparty']['email']); ?>" title="<?php echo dol_escape_htmltag($card['thirdparty']['email']); ?>"><i class="far fa-envelope"></i></a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
                <?php if ($card['project'] !== null) : ?>
                    <div class="dolical-card__row">
                        <i class="fas fa-project-diagram dolical-card__row-icon"></i>
                        <div class="dolical-card__grow">
                            <?php echo $card['project']['nomUrl']; ?>
                            <div class="dolical-card__muted">
                                <?php echo $card['project']['status']; ?>
                                <?php if ($card['project']['amount'] !== '') : ?>
                                    · <?php echo $langs->trans('OpportunityAmountShort'); ?> <?php echo dol_escape_htmltag($card['project']['amount']); ?>
                                <?php endif; ?>
                                <?php if ($card['project']['percent'] !== '') : ?>
                                    (<?php echo dol_escape_htmltag($card['project']['percent']); ?>)
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php if ($billing['state'] !== 'none') : ?>
            <div class="dolical-card__section">
                <div class="dolical-card__section-title">
                    <i class="fas fa-euro-sign dolical-card__row-icon"></i>
                    <span><?php echo $langs->trans('DoliCalBilling'); ?></span>
                    <?php if ($billing['fromProject']) : ?>
                        <span class="dolical-card__muted dolical-card__hint"><?php echo $langs->trans('DoliCalBillingFromProject'); ?></span>
                    <?php endif; ?>
                </div>
                <div class="dolical-billing dolical-billing--<?php echo $billing['state']; ?>">
                    <i class="<?php echo $billingIcons[$billing['state']]; ?>"></i>
                    <span><?php echo dol_escape_htmltag($billing['label']); ?></span>
                </div>
                <ul class="dolical-objects">
                    <?php foreach (array_merge($billing['proposals'], $billing['invoices']) as $object) : ?>
                        <li class="dolical-object">
                            <div class="dolical-object__ref"><?php echo $object['nomUrl']; ?></div>
                            <div class="dolical-object__amount"><?php echo dol_escape_htmltag($object['amountText']); ?></div>
                            <div class="dolical-object__status">
                                <?php echo $object['status']; ?>
                                <?php if ($object['paid'] === false && $object['remain'] !== null && $object['remain'] > 0) : ?>
                                    <span class="dolical-object__remain<?php echo $object['late'] ? ' dolical-object__remain--late' : ''; ?>"><?php echo $langs->trans('DoliCalRemainToPay', price($object['remain'], 0, $langs, 1, -1, -1, $conf->currency)); ?></span>
                                <?php endif; ?>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <?php if (!empty($otherObjects)) : ?>
            <div class="dolical-card__section">
                <div class="dolical-card__section-title">
                    <i class="fas fa-link dolical-card__row-icon"></i>
                    <span><?php echo $langs->trans('DoliCalLinkedElements'); ?></span>
                    <span class="dolical-badge"><?php echo count($otherObjects); ?></span>
                </div>
                <ul class="dolical-objects">
                    <?php foreach ($otherObjects as $object) : ?>
                        <li class="dolical-object">
                            <div class="dolical-object__ref"><?php echo $object['nomUrl']; ?>
                                <?php if ($object['extra'] !== '') : ?>
                                    <span class="dolical-card__muted">· <?php echo dol_escape_htmltag($object['extra']); ?></span>
                                <?php endif; ?>
                            </div>
                            <div class="dolical-object__amount"><?php echo dol_escape_htmltag($object['amountText']); ?></div>
                            <div class="dolical-object__status"><?php echo $object['status']; ?></div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <div class="dolical-card__section dolical-files" data-id="<?php echo $card['id']; ?>">
            <div class="dolical-card__section-title">
                <i class="fas fa-paperclip dolical-card__row-icon"></i>
                <span><?php echo $langs->trans('DoliCalAttachments'); ?></span>
                <span class="dolical-badge"><?php echo count($card['files']); ?></span>
            </div>
            <?php if (!empty($card['files'])) : ?>
                <ul class="dolical-files__list">
                    <?php foreach ($card['files'] as $file) : ?>
                        <li class="dolical-file">
                            <?php if ($file['image']) : ?>
                                <a class="dolical-file__thumb" href="<?php echo dol_escape_htmltag($file['preview']); ?>" target="_blank" rel="noopener"><img src="<?php echo dol_escape_htmltag($file['preview']); ?>" alt="" loading="lazy"></a>
                            <?php else : ?>
                                <span class="dolical-file__icon"><?php echo $file['icon']; ?></span>
                            <?php endif; ?>
                            <div class="dolical-file__body">
                                <a class="dolical-file__name" href="<?php echo dol_escape_htmltag($file['url']); ?>" title="<?php echo dol_escape_htmltag($file['name']); ?>"><?php echo dol_escape_htmltag($file['name']); ?></a>
                                <span class="dolical-card__muted"><?php echo dol_escape_htmltag($file['size'] . ' · ' . $file['date']); ?></span>
                            </div>
                            <a class="dolical-icon-btn" href="<?php echo dol_escape_htmltag(str_replace('attachment=0', 'attachment=1', $file['url'])); ?>" title="<?php echo dol_escape_htmltag($langs->trans('Download')); ?>"><i class="fas fa-download"></i></a>
                            <?php if ($card['canEdit']) : ?>
                                <button type="button" class="dolical-icon-btn dolical-file__delete" data-file="<?php echo dol_escape_htmltag($file['name']); ?>" title="<?php echo dol_escape_htmltag($langs->trans('Delete')); ?>"><i class="far fa-trash-alt"></i></button>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
            <?php if ($card['canEdit']) : ?>
                <label class="dolical-dropzone">
                    <input type="file" class="dolical-dropzone__input" multiple>
                    <i class="fas fa-cloud-upload-alt"></i>
                    <span><?php echo $langs->trans('DoliCalDropFiles'); ?></span>
                </label>
            <?php endif; ?>
        </div>

        <?php if ($card['email'] !== null) : ?>
            <div class="dolical-card__section">
                <div class="dolical-card__section-title">
                    <i class="far fa-envelope-open dolical-card__row-icon"></i>
                    <span><?php echo dol_escape_htmltag($card['email']['subject'] !== '' ? $card['email']['subject'] : $langs->trans('Email')); ?></span>
                </div>
                <div class="dolical-card__muted dolical-card__indent"><?php echo $langs->trans('MailFrom'); ?> : <?php echo dol_escape_htmltag($card['email']['from']); ?></div>
                <div class="dolical-card__muted dolical-card__indent"><?php echo $langs->trans('MailTo'); ?> : <?php echo dol_escape_htmltag($card['email']['to']); ?></div>
            </div>
        <?php endif; ?>

        <?php if ($card['note'] !== '') : ?>
            <div class="dolical-card__section">
                <div class="dolical-card__section-title">
                    <i class="fas fa-align-left dolical-card__row-icon"></i>
                    <span><?php echo $langs->trans('Description'); ?></span>
                </div>
                <div class="dolical-card__note dolical-card__indent"><?php echo $card['note']; ?></div>
                <button type="button" class="dolical-link dolical-card__note-toggle dolical-card__indent"><?php echo $langs->trans('DoliCalShowMore'); ?></button>
            </div>
        <?php endif; ?>

        <?php if (!empty($card['meta'])) : ?>
            <div class="dolical-card__meta"><?php echo dol_escape_htmltag(implode(' · ', $card['meta'])); ?></div>
        <?php endif; ?>
    </div>

    <div class="dolical-card__footer">
        <a class="dolical-btn" href="<?php echo dol_escape_htmltag($card['nativeUrl']); ?>"><?php echo $langs->trans('DoliCalMoreDetails'); ?></a>
        <?php if ($card['canEdit']) : ?>
            <button type="button" class="dolical-btn dolical-btn--primary" data-action="edit"><i class="fas fa-pen"></i><?php echo $langs->trans('Modify'); ?></button>
        <?php endif; ?>
    </div>
</div>
