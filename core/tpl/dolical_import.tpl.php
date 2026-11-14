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
 * \file    core/tpl/dolical_import.tpl.php
 * \ingroup dolical
 * \brief   Page of the import of an agenda : the result of the last import, the file and the agenda it goes to, where
 *          to find the file in the other agendas, what the import does.
 *
 * Rendered by view/dolical_import.php (inherits its scope). Expects: $result (array|null), $owners, $maxSize (Ko),
 * $user, $langs.
 */

$sources = [
    'nextcloud' => ['icon' => 'fas fa-cloud', 'title' => 'DoliCalImportNextcloud', 'steps' => ['DoliCalImportNextcloud1', 'DoliCalImportNextcloud2', 'DoliCalImportNextcloud3']],
    'google'    => ['icon' => 'fab fa-google', 'title' => 'DoliCalImportGoogle', 'steps' => ['DoliCalImportGoogle1', 'DoliCalImportGoogle2']],
    'outlook'   => ['icon' => 'fab fa-microsoft', 'title' => 'DoliCalImportOutlook', 'steps' => ['DoliCalImportOutlook1', 'DoliCalImportOutlook2']],
];
$config = ['maxSize' => $maxSize, 'i18n' => ['tooLarge' => $langs->transnoentities('DoliCalImportFileTooLarge', price(round($maxSize / 1024, 1), 0, $langs, 0, 0) . ' ' . $langs->transnoentities('Mb'))]];
?>
<div class="dolical-import" data-config="<?php echo dol_escape_htmltag(json_encode($config)); ?>">
    <div class="dolical-import__intro">
        <h1><i class="fas fa-file-upload pictofixedwidth"></i><?php echo $langs->trans('DoliCalImport'); ?></h1>
        <p><?php echo $langs->trans('DoliCalImportIntro'); ?></p>
    </div>

    <?php if (is_array($result)) : ?>
        <div class="dolical-import__card dolical-import__result">
            <h2><i class="fas fa-check-circle pictofixedwidth"></i><?php echo dol_escape_htmltag($langs->transnoentities('DoliCalImportDone', $result['file'])); ?></h2>
            <?php if ($result['events'] + $result['duplicates'] + $result['ignored'] + count($result['failed']) === 0) : ?>
                <p><?php echo $langs->trans('DoliCalImportNothing'); ?></p>
            <?php else : ?>
                <ul class="dolical-import__list">
                    <li><?php echo dol_escape_htmltag($langs->transnoentities('DoliCalImportCreated', $result['owner'], $result['events']) . ($result['series'] > 0 ? $langs->transnoentities('DoliCalImportSeries', $result['series'], $result['dates']) : '')); ?></li>
                    <?php if ($result['duplicates'] > 0) : ?>
                        <li><?php echo $langs->trans('DoliCalImportDuplicates', $result['duplicates']); ?></li>
                    <?php endif; ?>
                    <?php if (!empty($result['linked'])) : ?>
                        <li><?php echo $langs->trans('DoliCalImportLinked', $result['linked']); ?></li>
                    <?php endif; ?>
                    <?php if ($result['ignored'] > 0) : ?>
                        <li><?php echo $langs->trans('DoliCalImportIgnored', $result['ignored']); ?></li>
                    <?php endif; ?>
                    <?php if (!empty($result['failed'])) : ?>
                        <li class="dolical-import__failed">
                            <?php echo $langs->trans('DoliCalImportFailed', count($result['failed'])); ?>
                            <ul>
                                <?php foreach (array_slice($result['failed'], 0, 20) as $failed) : ?>
                                    <li><?php echo dol_escape_htmltag($failed); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </li>
                    <?php endif; ?>
                    <?php if (!empty($result['truncated'])) : ?>
                        <li class="dolical-import__failed"><?php echo $langs->trans('DoliCalImportTruncated', (int) $result['truncated']); ?></li>
                    <?php endif; ?>
                    <?php if (!empty($result['unknown'])) : ?>
                        <li class="dolical-import__unknown">
                            <?php echo $langs->trans('DoliCalImportUnknown', count($result['unknown'])); ?>
                            <ul>
                                <?php foreach (array_slice($result['unknown'], 0, 30, true) as $email => $count) : ?>
                                    <li><?php echo dol_escape_htmltag($email); ?> <span class="opacitymedium">(<?php echo (int) $count; ?>)</span></li>
                                <?php endforeach; ?>
                                <?php if (count($result['unknown']) > 30) : ?>
                                    <li>…</li>
                                <?php endif; ?>
                            </ul>
                        </li>
                    <?php endif; ?>
                </ul>
            <?php endif; ?>
            <a class="dolical-btn" href="<?php echo dol_buildpath('/dolical/dolicalindex.php', 1); ?>"><i class="far fa-calendar-alt"></i><?php echo $langs->trans('DoliCalImportSeeCalendar'); ?></a>
        </div>
    <?php endif; ?>

    <form class="dolical-import__card dolical-import__form" method="POST" enctype="multipart/form-data" action="<?php echo dol_escape_htmltag($_SERVER['PHP_SELF']); ?>?uploadform=1">
        <input type="hidden" name="token" value="<?php echo newToken(); ?>">
        <input type="hidden" name="action" value="import">
        <div class="dolical-field">
            <label for="dolical-import-file"><?php echo $langs->trans('DoliCalImportFile'); ?></label>
            <input type="file" id="dolical-import-file" name="icsfile" accept=".ics,.ical,.icalendar,text/calendar" required>
        </div>
        <?php if (count($owners) > 1) : ?>
            <div class="dolical-field">
                <label for="dolical-import-owner"><?php echo $langs->trans('DoliCalImportOwner'); ?></label>
                <select id="dolical-import-owner" name="owner_id">
                    <?php foreach ($owners as $owner) : ?>
                        <option value="<?php echo $owner['id']; ?>"<?php echo $owner['id'] === (int) $user->id ? ' selected' : ''; ?>><?php echo dol_escape_htmltag($owner['name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        <?php endif; ?>
        <button type="submit" class="dolical-btn dolical-btn--primary" data-running="<?php echo dol_escape_htmltag($langs->transnoentities('DoliCalImportRunning')); ?>"><i class="fas fa-file-upload"></i><?php echo $langs->trans('DoliCalImportSubmit'); ?></button>
    </form>

    <?php if (!empty($imports)) : ?>
        <div class="dolical-import__card dolical-import__history">
            <h2><i class="fas fa-history pictofixedwidth"></i><?php echo $langs->trans('DoliCalImportHistory'); ?></h2>
            <p class="opacitymedium"><?php echo $langs->trans('DoliCalImportHistoryHelp'); ?></p>
            <ul>
                <?php foreach ($imports as $import) : ?>
                    <li class="dolical-import__entry">
                        <div>
                            <strong><?php echo dol_print_date($import['date'], 'dayhour', 'tzuser'); ?></strong>
                            · <?php echo dol_escape_htmltag($langs->transnoentities('DoliCalImportHistoryAgenda', $people[$import['owner']] ?? '')); ?>
                            <div class="opacitymedium">
                                <?php echo dol_escape_htmltag($langs->transnoentities('DoliCalImportHistoryCount', $import['events'], $import['dates'], dol_print_date($import['first'], 'day', 'tzuser'), dol_print_date($import['last'], 'day', 'tzuser'))); ?>
                                <?php if ($import['author'] !== $import['owner']) : ?>
                                    · <?php echo dol_escape_htmltag($langs->transnoentities('DoliCalImportHistoryBy', $people[$import['author']] ?? '')); ?>
                                <?php endif; ?>
                            </div>
                        </div>
                        <form class="dolical-import__undo" method="POST" action="<?php echo dol_escape_htmltag($_SERVER['PHP_SELF']); ?>"
                              data-heading="<?php echo dol_escape_htmltag($langs->transnoentities('DoliCalImportUndoHeading', dol_print_date($import['date'], 'dayhour', 'tzuser'))); ?>"
                              data-text="<?php echo dol_escape_htmltag($langs->transnoentities('DoliCalImportUndoText', $import['dates'], $import['events'], $people[$import['owner']] ?? '', dol_print_date($import['first'], 'day', 'tzuser'), dol_print_date($import['last'], 'day', 'tzuser'))); ?>"
                              data-button="<?php echo dol_escape_htmltag($langs->transnoentities('DoliCalImportUndoButton', $import['dates'])); ?>">
                            <input type="hidden" name="token" value="<?php echo newToken(); ?>">
                            <input type="hidden" name="action" value="undo">
                            <input type="hidden" name="import_key" value="<?php echo dol_escape_htmltag($import['key']); ?>">
                            <input type="hidden" name="import_author" value="<?php echo $import['author']; ?>">
                            <button type="submit" class="dolical-btn dolical-btn--danger"><i class="fas fa-trash-alt"></i><?php echo $langs->trans('DoliCalImportUndo'); ?></button>
                        </form>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>

        <div class="dolical-modal dolical-confirm-modal" id="dolical-import-undo-modal" role="dialog" aria-modal="true" aria-labelledby="dolical-import-undo-title">
            <div class="modal-container">
                <div class="modal-header">
                    <h2 class="modal-title" id="dolical-import-undo-title"><?php echo $langs->trans('DoliCalImportUndoTitle'); ?></h2>
                    <div class="modal-close" title="<?php echo dol_escape_htmltag($langs->trans('Cancel')); ?>"><i class="fas fa-times"></i></div>
                </div>
                <div class="modal-content">
                    <div class="dolical-confirm">
                        <div class="dolical-confirm__icon"><i class="fas fa-trash-alt"></i></div>
                        <div class="dolical-confirm__body">
                            <p class="dolical-confirm__heading"></p>
                            <p class="dolical-confirm__text"></p>
                            <p class="dolical-confirm__warning"><i class="fas fa-exclamation-triangle"></i><span><?php echo $langs->trans('DoliCalImportUndoWarning'); ?></span></p>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="dolical-btn modal-close"><?php echo $langs->trans('Cancel'); ?></button>
                    <button type="button" class="dolical-btn dolical-btn--destructive dolical-import__undo-confirm"><i class="fas fa-trash-alt"></i><span class="dolical-import__undo-label"></span></button>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <div class="dolical-import__sources">
        <?php foreach ($sources as $source) : ?>
            <section class="dolical-import__source">
                <h2><i class="<?php echo $source['icon']; ?> pictofixedwidth"></i><?php echo $langs->trans($source['title']); ?></h2>
                <ol>
                    <?php foreach ($source['steps'] as $step) : ?>
                        <li><?php echo $langs->trans($step); ?></li>
                    <?php endforeach; ?>
                </ol>
            </section>
        <?php endforeach; ?>
    </div>

    <div class="dolical-import__card">
        <h2><i class="fas fa-info-circle pictofixedwidth"></i><?php echo $langs->trans('DoliCalImportWhat'); ?></h2>
        <ul class="dolical-import__list">
            <li><?php echo $langs->trans('DoliCalImportWhat1'); ?></li>
            <li><?php echo $langs->trans('DoliCalImportWhat2', DOLICAL_RECURRENCE_MAX); ?></li>
            <li><?php echo $langs->trans('DoliCalImportWhat3'); ?></li>
            <li><?php echo $langs->trans('DoliCalImportWhat4'); ?></li>
        </ul>
    </div>
</div>
<div class="dolical-toast"></div>
