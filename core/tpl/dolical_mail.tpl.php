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
 * \file    core/tpl/dolical_mail.tpl.php
 * \ingroup dolical
 * \brief   Body of the mails of DoliCal : invitation, change, answer received.
 *
 * Rendered by dolical_mail_html() (inherits its scope). Expects: $mail ['title', 'intro', 'rows', 'button',
 * 'secondary', 'note'], $mysoc.
 * The styles are inline on purpose : mail clients drop style sheets, this is the one place of the module where
 * they cannot live in the SCSS.
 */

$company = !empty($mysoc->name) ? $mysoc->name : '';
?>
<div style="margin:0;padding:24px 12px;background:#f5f8fa;font-family:Arial,Helvetica,sans-serif;color:#2d3748;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;margin:0 auto;background:#ffffff;border-radius:12px;border:1px solid #e7edf2;">
        <tr>
            <td style="padding:20px 28px;background:#014751;border-radius:12px 12px 0 0;color:#ffffff;font-size:14px;">
                <?php echo dol_escape_htmltag($company); ?>
            </td>
        </tr>
        <tr>
            <td style="padding:28px;">
                <h1 style="margin:0 0 14px;font-size:22px;line-height:1.3;color:#2d3748;"><?php echo dol_escape_htmltag($mail['title']); ?></h1>
                <p style="margin:0 0 20px;font-size:15px;line-height:1.5;"><?php echo dol_escape_htmltag($mail['intro']); ?></p>
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:0 0 24px;background:#f5f8fa;border-radius:8px;">
                    <?php foreach ($mail['rows'] as $row) : ?>
                        <tr>
                            <td style="padding:10px 14px;width:110px;color:#718096;font-size:13px;vertical-align:top;"><?php echo dol_escape_htmltag($row[0]); ?></td>
                            <td style="padding:10px 14px;font-size:14px;font-weight:bold;"><?php echo dol_escape_htmltag($row[1]); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </table>
                <?php if (!empty($mail['button'])) : ?>
                    <p style="margin:0 0 16px;text-align:center;">
                        <a href="<?php echo dol_escape_htmltag($mail['button'][0]); ?>" style="display:inline-block;padding:12px 28px;background:#63acc9;border-radius:24px;color:#ffffff;font-size:15px;font-weight:bold;text-decoration:none;"><?php echo dol_escape_htmltag($mail['button'][1]); ?></a>
                    </p>
                <?php endif; ?>
                <?php if (!empty($mail['secondary'])) : ?>
                    <p style="margin:0 0 16px;text-align:center;font-size:13px;">
                        <a href="<?php echo dol_escape_htmltag($mail['secondary'][0]); ?>" style="color:#4f9ab8;"><?php echo dol_escape_htmltag($mail['secondary'][1]); ?></a>
                    </p>
                <?php endif; ?>
                <?php if (!empty($mail['note'])) : ?>
                    <p style="margin:16px 0 0;color:#718096;font-size:12px;line-height:1.5;"><?php echo dol_escape_htmltag($mail['note']); ?></p>
                <?php endif; ?>
            </td>
        </tr>
    </table>
</div>
