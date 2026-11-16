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
 * \file    core/tpl/dolical_public_header.tpl.php
 * \ingroup dolical
 * \brief   Header of the public pages : the logo of the company, nothing without one. Neither the logo of Dolibarr nor
 *          its « Powered by » : the customer is on the page of the company.
 *
 * Rendered by public/booking.php and public/invitation.php (inherits their scope). Expects: $mysoc.
 */

$logoUrl = dolical_company_logo_url();
?>
<?php if ($logoUrl !== '') : ?>
    <header class="dolical-public-header">
        <img class="dolical-public-header__logo" src="<?php echo dol_escape_htmltag($logoUrl); ?>" alt="<?php echo dol_escape_htmltag((string) $mysoc->name); ?>">
    </header>
<?php endif; ?>
