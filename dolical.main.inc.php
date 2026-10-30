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
 * \file    dolical.main.inc.php
 * \ingroup dolical
 * \brief   Load the Dolibarr environment for the pages of DoliCal, wherever the module is installed
 */

// The module lives in htdocs/custom/dolical or in htdocs/dolical : main.inc.php is one or two levels up
$res = 0;
if (!empty($_SERVER['CONTEXT_DOCUMENT_ROOT'])) {
    $res = @include $_SERVER['CONTEXT_DOCUMENT_ROOT'] . '/main.inc.php';
}
if (!$res && file_exists(__DIR__ . '/../main.inc.php')) {
    $res = @include __DIR__ . '/../main.inc.php';
}
if (!$res && file_exists(__DIR__ . '/../../main.inc.php')) {
    $res = @include __DIR__ . '/../../main.inc.php';
}
if (!$res) {
    die('Include of main fails');
}

require_once __DIR__ . '/lib/dolical.lib.php';

if (!isModEnabled('dolical')) {
    if (defined('NOLOGIN') || defined('NOREQUIREHTML')) {
        httponly_accessforbidden('Module DoliCal not enabled');
    }
    accessforbidden('Module DoliCal not enabled');
}
