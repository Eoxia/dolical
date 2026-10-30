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
 * \file    class/dolicalcron.class.php
 * \ingroup dolical
 * \brief   Scheduled jobs of DoliCal
 */

require_once __DIR__ . '/../lib/dolical_invitation.lib.php';

/**
 * Class DoliCalCron
 */
class DoliCalCron
{
    /**
     * @var DoliDB Database handler
     */
    public DoliDB $db;

    /**
     * @var string Result shown in the list of the scheduled jobs
     */
    public string $output = '';

    /**
     * @var string Error
     */
    public string $error = '';

    /**
     * Constructor
     *
     * @param DoliDB $db Database handler
     */
    public function __construct(DoliDB $db)
    {
        $this->db = $db;
    }

    /**
     * Remind the guests who have not answered, once, a few days before the event
     *
     * @return int 0 when done, < 0 on error
     */
    public function remindPendingInvitations(): int
    {
        global $langs;

        $langs->load('dolical@dolical');

        if (!getDolGlobalInt('DOLICAL_AUTO_REMIND')) {
            $this->output = $langs->transnoentities('DoliCalAutoRemindDisabled');
            return 0;
        }

        $summary      = dolical_send_due_reminders(getDolGlobalInt('DOLICAL_AUTO_REMIND_DAYS', 2));
        $this->output = $langs->transnoentities('DoliCalAutoRemindDone', (string) $summary['sent'], (string) $summary['events']);
        if (!empty($summary['errors'])) {
            $this->error = implode(' ', $summary['errors']);
            return -1;
        }

        return 0;
    }
}
