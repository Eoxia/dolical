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
 * \file    core/tpl/dolical_calendar.tpl.php
 * \ingroup dolical
 * \brief   Frame of the calendar : people and filters on the side, toolbar and grid, card and form shells.
 *
 * Rendered by dolicalindex.php (inherits its scope). Expects: $calendarConfig, $calendarUsers,
 * $calendarGroups, $eventTypes, $views, $langs, $user.
 * The grid itself is drawn by js/modules/calendar.js from the events of ajax/events.php.
 */
?>
<div class="dolical-app" data-config="<?php echo dol_escape_htmltag(json_encode($calendarConfig)); ?>">
    <aside class="dolical-sidebar">
        <?php if ($calendarConfig['canCreate']) : ?>
            <button type="button" class="dolical-btn dolical-btn--primary dolical-btn--create" data-action="create"><i class="fas fa-plus"></i><?php echo $langs->trans('DoliCalNewEvent'); ?></button>
        <?php endif; ?>

        <div class="dolical-minical"></div>

        <div class="dolical-sidebar__block dolical-picker">
            <div class="dolical-sidebar__title">
                <span><i class="fas fa-users pictofixedwidth"></i><?php echo $langs->trans('DoliCalCalendars'); ?></span>
                <span class="dolical-badge dolical-picker__count">0</span>
            </div>
            <?php if (count($calendarUsers) > 1) : ?>
                <input type="search" class="dolical-picker__search" placeholder="<?php echo dol_escape_htmltag($langs->trans('DoliCalSearchPerson')); ?>">
                <div class="dolical-picker__shortcuts">
                    <button type="button" class="dolical-link" data-pick="me"><?php echo $langs->trans('DoliCalOnlyMe'); ?></button>
                    <button type="button" class="dolical-link" data-pick="none"><?php echo $langs->trans('DoliCalNobody'); ?></button>
                    <?php if (!empty($calendarGroups)) : ?>
                        <select class="dolical-picker__group">
                            <option value=""><?php echo $langs->trans('DoliCalAddGroup'); ?></option>
                            <?php foreach ($calendarGroups as $group) : ?>
                                <option value="<?php echo $group['id']; ?>"><?php echo dol_escape_htmltag($group['name'] . ' (' . count($group['members']) . ')'); ?></option>
                            <?php endforeach; ?>
                        </select>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            <ul class="dolical-picker__list">
                <?php foreach ($calendarUsers as $calendarUser) : ?>
                    <li class="dolical-picker__item" data-name="<?php echo dol_escape_htmltag(dol_strtolower($calendarUser['name'] . ' ' . $calendarUser['login'])); ?>">
                        <label>
                            <input type="checkbox" class="dolical-picker__check" value="<?php echo $calendarUser['id']; ?>">
                            <span class="dolical-picker__swatch" data-color="<?php echo dol_escape_htmltag($calendarUser['color']); ?>"></span>
                            <span class="dolical-picker__name"><?php echo dol_escape_htmltag($calendarUser['name']); ?><?php echo $calendarUser['id'] === (int) $user->id ? ' <span class="dolical-card__muted">(' . $langs->trans('DoliCalMe') . ')</span>' : ''; ?></span>
                        </label>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>

        <div class="dolical-sidebar__block dolical-filters">
            <div class="dolical-sidebar__title"><span><i class="fas fa-filter pictofixedwidth"></i><?php echo $langs->trans('Filters'); ?></span></div>
            <label class="dolical-check">
                <input type="checkbox" class="dolical-filter" data-filter="hideauto" value="1"<?php echo $calendarConfig['hideAuto'] ? ' checked' : ''; ?>>
                <span><?php echo $langs->trans('DoliCalHideAutoEvents'); ?></span>
            </label>
            <select class="dolical-filter dolical-select" data-filter="status">
                <option value="all"><?php echo $langs->trans('DoliCalAllStatuses'); ?></option>
                <option value="todo"><?php echo $langs->trans('DoliCalOnlyTodo'); ?></option>
                <option value="done"><?php echo $langs->trans('DoliCalOnlyDone'); ?></option>
            </select>
            <?php if (getDolGlobalString('AGENDA_USE_EVENT_TYPE') && !empty($eventTypes)) : ?>
                <select class="dolical-filter dolical-select" data-filter="types">
                    <option value=""><?php echo $langs->trans('DoliCalAllTypes'); ?></option>
                    <?php foreach ($eventTypes as $type) : ?>
                        <option value="<?php echo dol_escape_htmltag($type['code']); ?>"><?php echo dol_escape_htmltag($type['label']); ?></option>
                    <?php endforeach; ?>
                </select>
            <?php endif; ?>
        </div>

        <?php if (getDolGlobalInt('DOLICAL_CALDAV', 1)) : ?>
            <a class="dolical-sidebar__sync" href="<?php echo dol_buildpath('/dolical/view/dolical_sync.php', 1); ?>"><i class="fas fa-mobile-alt pictofixedwidth"></i><?php echo $langs->trans('DoliCalSyncPhone'); ?></a>
        <?php endif; ?>
    </aside>

    <section class="dolical-main">
        <header class="dolical-toolbar">
            <button type="button" class="dolical-btn dolical-btn--icon dolical-sidebar-toggle" title="<?php echo dol_escape_htmltag($langs->trans('DoliCalCalendars')); ?>"><i class="fas fa-bars"></i></button>
            <button type="button" class="dolical-btn" data-nav="today"><?php echo $langs->trans('Today'); ?></button>
            <div class="dolical-btn-group">
                <button type="button" class="dolical-btn dolical-btn--icon" data-nav="prev" title="<?php echo dol_escape_htmltag($langs->trans('Previous')); ?>"><i class="fas fa-chevron-left"></i></button>
                <button type="button" class="dolical-btn dolical-btn--icon" data-nav="next" title="<?php echo dol_escape_htmltag($langs->trans('Next')); ?>"><i class="fas fa-chevron-right"></i></button>
            </div>
            <h2 class="dolical-toolbar__period"></h2>
            <div class="dolical-toolbar__spacer"></div>
            <div class="dolical-toolbar__search">
                <i class="fas fa-search"></i>
                <input type="search" class="dolical-filter" data-filter="search" placeholder="<?php echo dol_escape_htmltag($langs->trans('DoliCalSearchEvents')); ?>">
            </div>
            <div class="dolical-btn-group dolical-views">
                <?php foreach ($views as $view => $labelKey) : ?>
                    <button type="button" class="dolical-btn" data-view="<?php echo $view; ?>"><?php echo $langs->trans($labelKey); ?></button>
                <?php endforeach; ?>
            </div>
            <button type="button" class="dolical-btn dolical-btn--icon" data-nav="refresh" title="<?php echo dol_escape_htmltag($langs->trans('Refresh')); ?>"><i class="fas fa-sync-alt"></i></button>
        </header>
        <div class="dolical-calendar"></div>
        <div class="dolical-loader"><i class="fas fa-circle-notch fa-spin"></i></div>
    </section>
</div>

<div class="dolical-popover"></div>
<div class="dolical-toast"></div>

<div class="dolical-nudge" role="status">
    <i class="far fa-comment-dots dolical-nudge__icon"></i>
    <div class="dolical-nudge__body">
        <strong class="dolical-nudge__title"><?php echo $langs->trans('DoliCalReportNudgeTitle'); ?></strong>
        <span class="dolical-nudge__text"></span>
        <div class="dolical-nudge__actions">
            <button type="button" class="dolical-btn dolical-btn--small dolical-btn--primary" data-nudge="write"><i class="fas fa-pen"></i><?php echo $langs->trans('DoliCalReportWrite'); ?></button>
            <button type="button" class="dolical-link" data-nudge="later"><?php echo $langs->trans('DoliCalLater'); ?></button>
        </div>
    </div>
</div>

<div class="dolical-modal" id="dolical-event-modal">
    <div class="modal-container"></div>
</div>

<div class="dolical-modal" id="dolical-report-modal">
    <div class="modal-container"></div>
</div>

<div class="dolical-modal dolical-scope-modal" id="dolical-scope-modal">
    <div class="modal-container">
        <div class="modal-header">
            <h2 class="modal-title"><i class="fas fa-redo-alt pictofixedwidth"></i><span class="dolical-scope__title" data-edit="<?php echo dol_escape_htmltag($langs->trans('DoliCalScopeEditTitle')); ?>" data-delete="<?php echo dol_escape_htmltag($langs->trans('DoliCalScopeDeleteTitle')); ?>"></span></h2>
            <div class="modal-close"><i class="fas fa-times"></i></div>
        </div>
        <div class="modal-content">
            <?php foreach (['one' => 'DoliCalScopeOne', 'following' => 'DoliCalScopeFollowing', 'all' => 'DoliCalScopeAll'] as $scope => $labelKey) : ?>
                <label class="dolical-scope__choice" data-scope="<?php echo $scope; ?>">
                    <input type="radio" name="dolical_scope" value="<?php echo $scope; ?>"<?php echo $scope === 'one' ? ' checked' : ''; ?>>
                    <span><?php echo $langs->trans($labelKey); ?></span>
                </label>
            <?php endforeach; ?>
        </div>
        <div class="modal-footer">
            <button type="button" class="dolical-btn modal-close"><?php echo $langs->trans('Cancel'); ?></button>
            <button type="button" class="dolical-btn dolical-btn--primary dolical-scope__confirm"><i class="fas fa-check"></i><?php echo $langs->trans('Validate'); ?></button>
        </div>
    </div>
</div>
