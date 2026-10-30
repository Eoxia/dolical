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
 * \file    class/actions_dolical.class.php
 * \ingroup dolical
 * \brief   Hooks of DoliCal : a button on the cards of Dolibarr to plan an event already linked to them
 */

/**
 * Class ActionsDoliCal
 */
class ActionsDoliCal
{
    /**
     * @var DoliDB Database handler
     */
    public DoliDB $db;

    /**
     * @var string Error code (or message)
     */
    public string $error = '';

    /**
     * @var array Errors
     */
    public array $errors = [];

    /**
     * @var array Hook results. Propagated to $hookmanager->resArray for later reuse
     */
    public array $results = [];

    /**
     * @var string|null String displayed by executeHook() immediately after return
     */
    public ?string $resprints;

    /**
     * Element of each card, as the calendar links it, by context of hook
     *
     * @var array
     */
    private const CARD_ELEMENTS = [
        'propalcard'            => 'propal',
        'ordercard'             => 'commande',
        'invoicecard'           => 'facture',
        'contractcard'          => 'contrat',
        'interventioncard'      => 'fichinter',
        'ticketcard'            => 'ticket',
        'expeditioncard'        => 'shipping',
        'supplier_proposalcard' => 'supplier_proposal',
        'ordersuppliercard'     => 'order_supplier',
        'invoicesuppliercard'   => 'invoice_supplier',
        'thirdpartycard'        => '',
        'thirdpartycomm'        => '',
        'projectcard'           => '',
        'contactcard'           => '',
    ];

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
     * Types of the email templates of the module, so that the administrator can write them in the setup of the
     * email templates : invitation, change of an event, answer received by the organizer
     *
     * @param  array $parameters Hook metadatas (context, etc...)
     * @return int               0 < on error, 0 on success, 1 to replace standard code
     */
    public function emailElementlist(array $parameters): int
    {
        global $langs;

        if (strpos($parameters['context'], 'emailtemplates') === false || !isModEnabled('dolical')) {
            return 0;
        }

        $langs->load('dolical@dolical');

        $picto         = img_picto('', 'dolical_color@dolical', 'class="pictofixedwidth"');
        $this->results = [
            'dolical_invitation' => $picto . dol_escape_htmltag($langs->trans('DoliCalTemplateInvitation')),
            'dolical_update'     => $picto . dol_escape_htmltag($langs->trans('DoliCalTemplateUpdate')),
            'dolical_reminder'   => $picto . dol_escape_htmltag($langs->trans('DoliCalTemplateReminder')),
            'dolical_cancel'     => $picto . dol_escape_htmltag($langs->trans('DoliCalTemplateCancel')),
            'dolical_answer'     => $picto . dol_escape_htmltag($langs->trans('DoliCalTemplateAnswer')),
        ];

        return 0;
    }

    /**
     * Button "Plan in DoliCal" on the cards : the form opens with the third party, the project, the contact
     * and the document of the card already filled in
     *
     * @param  array        $parameters Hook metadatas (context, etc...)
     * @param  CommonObject $object     Current object
     * @param  string       $action     Current action
     * @return int                      0 < on error, 0 on success, 1 to replace standard code
     */
    public function addMoreActionsButtons(array $parameters, $object, string $action): int
    {
        global $langs, $user;

        $contexts = explode(':', $parameters['context']);
        $context  = '';
        foreach ($contexts as $candidate) {
            if (array_key_exists($candidate, self::CARD_ELEMENTS)) {
                $context = $candidate;
                break;
            }
        }
        if ($context === '' || empty($object->id) || !isModEnabled('dolical') || !$user->hasRight('dolical', 'read')
            || !($user->hasRight('agenda', 'myactions', 'create') || $user->hasRight('agenda', 'allactions', 'create'))) {
            return 0;
        }

        $langs->load('dolical@dolical');

        $params = ['action' => 'create'];
        if (in_array($context, ['thirdpartycard', 'thirdpartycomm'])) {
            $params['socid'] = $object->id;
        } elseif ($context === 'contactcard') {
            $params['contactids'] = $object->id;
            $params['socid']      = $object->socid;
        } elseif ($context === 'projectcard') {
            $params['projectid'] = $object->id;
            $params['socid']     = $object->socid;
        } else {
            $params['elements'] = self::CARD_ELEMENTS[$context] . ':' . $object->id;
            $params['socid']    = $object->socid ?? 0;
            $params['projectid'] = $object->fk_project ?? 0;
        }
        $url = dol_buildpath('/dolical/dolicalindex.php', 1) . '?' . http_build_query(array_filter($params));

        // The cards print the buttons inline and ignore resPrint for this hook
        print dolGetButtonAction('', '<i class="far fa-calendar-plus pictofixedwidth"></i>' . $langs->trans('DoliCalPlanEvent'), 'default', $url, 'dolical-plan-event');

        return 0;
    }
}
