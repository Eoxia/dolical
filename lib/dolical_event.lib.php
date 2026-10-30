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
 * \file    lib/dolical_event.lib.php
 * \ingroup dolical
 * \brief   Native events (llx_actioncomm) seen and written by the calendar : list of a period, detail of the
 *          card, people, documents and linked elements. Nothing is stored outside the native tables :
 *          participants in llx_actioncomm_resources, elements in fk_element and llx_element_element,
 *          attachments in the documents directory of the agenda.
 */

require_once DOL_DOCUMENT_ROOT . '/comm/action/class/actioncomm.class.php';
require_once DOL_DOCUMENT_ROOT . '/comm/action/class/cactioncomm.class.php';
require_once DOL_DOCUMENT_ROOT . '/contact/class/contact.class.php';
// The links to the cards of the people use Form : not loaded by master.inc.php, the scheduled jobs need it
require_once DOL_DOCUMENT_ROOT . '/core/class/html.form.class.php';
require_once DOL_DOCUMENT_ROOT . '/societe/class/societe.class.php';
require_once DOL_DOCUMENT_ROOT . '/core/lib/files.lib.php';
require_once DOL_DOCUMENT_ROOT . '/core/lib/images.lib.php';
require_once DOL_DOCUMENT_ROOT . '/core/lib/date.lib.php';

require_once __DIR__ . '/dolical.lib.php';
require_once __DIR__ . '/dolical_recurrence.lib.php';
require_once __DIR__ . '/dolical_reminder.lib.php';
require_once __DIR__ . '/dolical_report.lib.php';

/**
 * Where a phone keeps an event it created : the name of its file in the calendar and its identifier, kept in the
 * external reference of the native event (« dav:<file>|<uid> »)
 *
 * @param  ActionComm $event Event
 * @return array             ['uri' => string, 'uid' => string], empty strings for an event created in Dolibarr
 */
function dolical_dav_reference(ActionComm $event): array
{
    $reference = (string) $event->ref_ext;
    if (strpos($reference, 'dav:') !== 0) {
        return ['uri' => '', 'uid' => ''];
    }
    $parts = explode('|', substr($reference, 4), 2);

    return ['uri' => (string) $parts[0], 'uid' => (string) ($parts[1] ?? '')];
}

/**
 * Elements an event can be linked to, keyed by the element code used in llx_element_element. Only the
 * types whose module is enabled and that the user may read are returned
 *
 * @param  User  $user Current user
 * @return array       [element => ['label', 'picto', 'table', 'entity', 'labelfield', 'amountfield', 'amountlabel']]
 */
function dolical_get_linkable_types(User $user): array
{
    global $langs;

    $langs->loadLangs(['propal', 'orders', 'bills', 'contracts', 'interventions', 'sendings', 'supplier_proposal', 'other']);

    $catalogue = [
        'propal'            => ['module' => 'propal',            'right' => $user->hasRight('propal', 'lire'),                                                     'label' => 'Proposal',         'picto' => 'propal',           'table' => 'propal',               'entity' => 'propal',            'labelfield' => 'ref_client',   'amountfield' => 'total_ht',  'amountlabel' => 'AmountHT'],
        'commande'          => ['module' => 'order',             'right' => $user->hasRight('commande', 'lire'),                                                   'label' => 'Order',            'picto' => 'order',            'table' => 'commande',             'entity' => 'commande',          'labelfield' => 'ref_client',   'amountfield' => 'total_ht',  'amountlabel' => 'AmountHT'],
        'facture'           => ['module' => 'invoice',           'right' => $user->hasRight('facture', 'lire'),                                                    'label' => 'Invoice',          'picto' => 'bill',             'table' => 'facture',              'entity' => 'invoice',           'labelfield' => 'ref_client',   'amountfield' => 'total_ttc', 'amountlabel' => 'AmountTTC'],
        'contrat'           => ['module' => 'contract',          'right' => $user->hasRight('contrat', 'lire'),                                                    'label' => 'Contract',         'picto' => 'contract',         'table' => 'contrat',              'entity' => 'contract',          'labelfield' => 'ref_customer', 'amountfield' => '',          'amountlabel' => ''],
        'fichinter'         => ['module' => 'intervention',      'right' => $user->hasRight('ficheinter', 'lire'),                                                 'label' => 'Intervention',     'picto' => 'intervention',     'table' => 'fichinter',            'entity' => 'intervention',      'labelfield' => 'ref_client',   'amountfield' => '',          'amountlabel' => ''],
        'ticket'            => ['module' => 'ticket',            'right' => $user->hasRight('ticket', 'read'),                                                     'label' => 'Ticket',           'picto' => 'ticket',           'table' => 'ticket',               'entity' => 'ticket',            'labelfield' => 'subject',      'amountfield' => '',          'amountlabel' => ''],
        'shipping'          => ['module' => 'shipping',          'right' => $user->hasRight('expedition', 'lire'),                                                 'label' => 'Shipment',         'picto' => 'dolly',            'table' => 'expedition',           'entity' => 'expedition',        'labelfield' => 'ref_customer', 'amountfield' => '',          'amountlabel' => ''],
        'supplier_proposal' => ['module' => 'supplier_proposal', 'right' => $user->hasRight('supplier_proposal', 'lire'),                                          'label' => 'SupplierProposal', 'picto' => 'supplier_proposal', 'table' => 'supplier_proposal',    'entity' => 'supplier_proposal', 'labelfield' => '',             'amountfield' => 'total_ht',  'amountlabel' => 'AmountHT'],
        'order_supplier'    => ['module' => 'supplier_order',    'right' => $user->hasRight('fournisseur', 'commande', 'lire') || $user->hasRight('supplier_order', 'lire'),   'label' => 'SupplierOrder',    'picto' => 'supplier_order',   'table' => 'commande_fournisseur', 'entity' => 'supplier_order',    'labelfield' => 'ref_supplier', 'amountfield' => 'total_ht',  'amountlabel' => 'AmountHT'],
        'invoice_supplier'  => ['module' => 'supplier_invoice',  'right' => $user->hasRight('fournisseur', 'facture', 'lire') || $user->hasRight('supplier_invoice', 'lire'), 'label' => 'SupplierInvoice',  'picto' => 'supplier_invoice', 'table' => 'facture_fourn',        'entity' => 'supplier_invoice',  'labelfield' => 'ref_supplier', 'amountfield' => 'total_ttc', 'amountlabel' => 'AmountTTC'],
    ];

    $types = [];
    foreach ($catalogue as $element => $type) {
        if (isModEnabled($type['module']) && $type['right']) {
            $type['label']    = $langs->trans($type['label']);
            $types[$element]  = $type;
        }
    }

    return $types;
}

/**
 * Whether the user may see an object : the read right of its module, then the check its own card makes through
 * restrictedArea() (entity, third party of an external user, customers of a sales representative, private
 * project). An id coming from the browser is never loaded without it
 *
 * @param  User   $user    Current user
 * @param  string $element societe, contact, project, or a linkable element (propal, facture...)
 * @param  int    $id      Id of the object
 * @return bool
 */
function dolical_can_see_object(User $user, string $element, int $id): bool
{
    global $db;

    if ($id <= 0) {
        return false;
    }

    // Module of the object, then the arguments its card gives to restrictedArea()
    $checks = [
        'societe'           => [$user->hasRight('societe', 'lire'), 'societe', '&societe', ''],
        'contact'           => [$user->hasRight('societe', 'contact', 'lire'), 'contact', 'socpeople&societe', ''],
        'project'           => [isModEnabled('project') && $user->hasRight('projet', 'lire'), 'projet', 'projet&project', ''],
        'propal'            => [null, 'propal', '', ''],
        'commande'          => [null, 'commande', '', ''],
        'facture'           => [null, 'facture', '', ''],
        'contrat'           => [null, 'contrat', '', ''],
        'fichinter'         => [null, 'ficheinter', 'fichinter', ''],
        'ticket'            => [null, 'ticket', '', ''],
        'shipping'          => [null, 'expedition', '', ''],
        'supplier_proposal' => [null, 'supplier_proposal', '', ''],
        'order_supplier'    => [null, 'fournisseur', 'commande_fournisseur', 'commande'],
        'invoice_supplier'  => [null, 'fournisseur', 'facture_fourn', 'facture'],
    ];
    if (!isset($checks[$element])) {
        return false;
    }
    [$right, $feature, $tableAndShare, $feature2] = $checks[$element];
    // The linkable elements : their module is enabled and the user reads it
    if ($right === null) {
        $right = isset(dolical_get_linkable_types($user)[$element]);
    }
    if (!$right) {
        return false;
    }

    require_once DOL_DOCUMENT_ROOT . '/core/lib/security.lib.php';
    if (!checkUserAccessToObject($user, [$feature], $id, $tableAndShare, $feature2, 'fk_soc', 'rowid')) {
        return false;
    }

    // A private contact is seen by whoever created it only
    if ($element === 'contact') {
        $resql = $db->query('SELECT priv, fk_user_creat FROM ' . MAIN_DB_PREFIX . 'socpeople WHERE rowid = ' . ((int) $id));
        $obj   = $resql ? $db->fetch_object($resql) : null;
        if (!$obj || (!empty($obj->priv) && (int) $obj->fk_user_creat !== (int) $user->id)) {
            return false;
        }
    }

    return true;
}

/**
 * Element code of llx_element_element from the value of llx_actioncomm.elementtype : ActionComm::create()
 * renames facture, commande and contrat on the way in
 *
 * @param  string $elementType Value of llx_actioncomm.elementtype
 * @return string              Element code
 */
function dolical_element_from_actioncomm(string $elementType): string
{
    $renamed = ['invoice' => 'facture', 'order' => 'commande', 'contract' => 'contrat', 'propale' => 'propal', 'invoice_supplier' => 'invoice_supplier', 'order_supplier' => 'order_supplier'];

    return $renamed[$elementType] ?? $elementType;
}

/**
 * Value of llx_actioncomm.elementtype for an element code, as ActionComm::create() stores it. update()
 * does not rename, the value has to be given already renamed
 *
 * @param  string $element Element code
 * @return string          Value of llx_actioncomm.elementtype
 */
function dolical_element_to_actioncomm(string $element): string
{
    $renamed = ['facture' => 'invoice', 'commande' => 'order', 'contrat' => 'contract'];

    return $renamed[$element] ?? $element;
}

/**
 * Font Awesome icon of an event type, close to the pictos of ActionComm::getTypePicto()
 *
 * @param  string $typeCode  Code of the type (AC_RDV, AC_TEL...)
 * @param  string $type      Kind of the type ('system', 'systemauto'...)
 * @param  string $typePicto Picto stored in the dictionary
 * @param  string $code      Code of the event itself (AC_PROPAL_SENTBYMAIL...)
 * @return string            Font Awesome classes
 */
function dolical_type_icon(string $typeCode, string $type = '', string $typePicto = '', string $code = ''): string
{
    if (preg_match('/^fa-[a-z0-9-]+$/', $typePicto)) {
        return 'fas ' . $typePicto;
    }
    if ($typeCode === 'AC_RDV') {
        return 'fas fa-handshake';
    }
    if ($typeCode === 'AC_TEL') {
        return 'fas fa-phone-alt';
    }
    if ($typeCode === 'AC_FAX') {
        return 'fas fa-fax';
    }
    if (preg_match('/^AC_EMAIL/', $typeCode) || preg_match('/_SENTBYMAIL$/', $code)) {
        return 'fas fa-envelope';
    }
    if ($typeCode === 'AC_INT') {
        return 'fas fa-tools';
    }
    if (preg_match('/^TICKET_MSG/', $code)) {
        return 'fas fa-comments';
    }

    return $type === 'systemauto' ? 'fas fa-cog' : 'far fa-calendar-check';
}

/**
 * Events of a period for a set of people, ready to be drawn by the calendar
 *
 * An event belongs to a person when he owns it or is one of the users assigned to it, as in the native
 * agenda. The events of the other people are only given to a user allowed to read them.
 *
 * @param  User  $user    Current user
 * @param  int   $start   Start of the period, timestamp included
 * @param  int   $end     End of the period, timestamp excluded
 * @param  int[] $userIds People whose agenda is displayed
 * @param  array $filters ['hideauto' => bool, 'status' => 'all'|'todo'|'done', 'types' => codes, 'socid' => int, 'projectid' => int, 'search' => string]
 * @return array          List of events
 */
function dolical_fetch_events(User $user, int $start, int $end, array $userIds, array $filters = []): array
{
    global $db;

    if (!dolical_can_read_all($user)) {
        $userIds = [(int) $user->id];
    }
    $userIds = array_values(array_filter(array_map('intval', $userIds)));
    if (empty($userIds) || $end <= $start) {
        return [];
    }
    $userList = implode(',', $userIds);

    $sql  = 'SELECT a.id, a.label, a.datep, a.datep2, a.fulldayevent, a.percent, a.location, a.code, a.transparency,';
    $sql .= ' a.fk_user_action, a.fk_user_author, a.fk_soc, a.fk_project, a.recurid,';
    $sql .= ' ca.code as type_code, ca.libelle as type_label, ca.color as type_color, ca.type as type_type, ca.picto as type_picto,';
    $sql .= ' s.nom as socname, p.ref as project_ref';
    $sql .= ' FROM ' . MAIN_DB_PREFIX . 'actioncomm as a';
    $sql .= ' LEFT JOIN ' . MAIN_DB_PREFIX . 'c_actioncomm as ca ON ca.id = a.fk_action';
    $sql .= ' LEFT JOIN ' . MAIN_DB_PREFIX . 'societe as s ON s.rowid = a.fk_soc';
    $sql .= ' LEFT JOIN ' . MAIN_DB_PREFIX . 'projet as p ON p.rowid = a.fk_project';
    $sql .= ' WHERE a.entity IN (' . getEntity('agenda') . ')';
    // Overlaps the period : starts before its end, ends (or starts, without end) after its start
    $sql .= " AND a.datep < '" . $db->idate($end) . "'";
    $sql .= " AND COALESCE(a.datep2, a.datep) >= '" . $db->idate($start) . "'";
    $sql .= ' AND (a.fk_user_action IN (' . $db->sanitize($userList) . ')';
    $sql .= ' OR EXISTS (SELECT ar.fk_actioncomm FROM ' . MAIN_DB_PREFIX . "actioncomm_resources as ar WHERE ar.fk_actioncomm = a.id AND ar.element_type = 'user' AND ar.fk_element IN (" . $db->sanitize($userList) . ')))';
    if ($user->socid > 0) {
        $sql .= ' AND a.fk_soc = ' . ((int) $user->socid);
    }
    if (!empty($filters['hideauto'])) {
        $sql .= " AND (ca.type IS NULL OR ca.type <> 'systemauto')";
    }
    if (($filters['status'] ?? 'all') === 'todo') {
        $sql .= ' AND a.percent >= 0 AND a.percent < 100';
    } elseif (($filters['status'] ?? 'all') === 'done') {
        $sql .= ' AND a.percent = 100';
    }
    if (!empty($filters['types'])) {
        $codes = array_map(function ($code) use ($db) {
            return "'" . $db->escape($code) . "'";
        }, array_filter($filters['types'], function ($code) {
            return preg_match('/^[A-Z0-9_]+$/', $code);
        }));
        if (!empty($codes)) {
            $sql .= ' AND ca.code IN (' . implode(',', $codes) . ')';
        }
    }
    if (!empty($filters['socid'])) {
        $sql .= ' AND a.fk_soc = ' . ((int) $filters['socid']);
    }
    if (!empty($filters['projectid'])) {
        $sql .= ' AND a.fk_project = ' . ((int) $filters['projectid']);
    }
    if (!empty($filters['search'])) {
        $search = $db->escape($db->escapeforlike($filters['search']));
        $sql   .= " AND (a.label LIKE '%" . $search . "%' OR a.location LIKE '%" . $search . "%' OR s.nom LIKE '%" . $search . "%' OR p.ref LIKE '%" . $search . "%' OR p.title LIKE '%" . $search . "%')";
    }
    $sql .= ' ORDER BY a.datep ASC, a.id ASC';
    // A busy month of a whole team stays far below, the cap only protects the browser
    $sql .= $db->plimit(3000);

    $events = [];
    $resql  = $db->query($sql);
    if (!$resql) {
        dol_syslog(__FUNCTION__ . ' ' . $db->lasterror(), LOG_ERR);
        return [];
    }
    while ($obj = $db->fetch_object($resql)) {
        $datep  = (int) $db->jdate($obj->datep);
        $datef  = $obj->datep2 ? (int) $db->jdate($obj->datep2) : $datep;
        // Some modules store their labels HTML encoded : the browser escapes plain text, it must get plain text
        $events[(int) $obj->id] = [
            'id'        => (int) $obj->id,
            'label'     => dol_html_entity_decode((string) $obj->label, ENT_QUOTES | ENT_HTML5),
            'start'     => $datep,
            'end'       => max($datep, $datef),
            'allDay'    => !empty($obj->fulldayevent),
            'percent'   => (int) $obj->percent,
            'location'  => dol_html_entity_decode((string) $obj->location, ENT_QUOTES | ENT_HTML5),
            'busy'      => !empty($obj->transparency),
            'ownerId'   => (int) $obj->fk_user_action,
            'authorId'  => (int) $obj->fk_user_author,
            'userIds'   => $obj->fk_user_action > 0 ? [(int) $obj->fk_user_action] : [],
            'typeCode'  => (string) $obj->type_code,
            'typeLabel' => (string) $obj->type_label,
            'typeColor' => $obj->type_color ? '#' . ltrim($obj->type_color, '#') : '',
            'typeIcon'  => dolical_type_icon((string) $obj->type_code, (string) $obj->type_type, (string) $obj->type_picto, (string) $obj->code),
            'auto'      => $obj->type_type === 'systemauto',
            'socName'   => dol_html_entity_decode((string) $obj->socname, ENT_QUOTES | ENT_HTML5),
            'project'   => (string) $obj->project_ref,
            'recurring' => !empty($obj->recurid),
            'report'    => false,
        ];
    }
    $db->free($resql);

    if (empty($events)) {
        return [];
    }

    // Everyone assigned, with their answer, in one query : the calendar shows the event in the column of each of
    // them, struck out where it was declined, with the count of the answers of the guests
    $sql   = 'SELECT fk_actioncomm, element_type, fk_element, answer_status FROM ' . MAIN_DB_PREFIX . 'actioncomm_resources';
    $sql  .= " WHERE element_type IN ('user', 'socpeople') AND fk_actioncomm IN (" . $db->sanitize(implode(',', array_keys($events))) . ')';
    $resql = $db->query($sql);
    if ($resql) {
        while ($obj = $db->fetch_object($resql)) {
            $id     = (int) $obj->fk_actioncomm;
            $status = (int) $obj->answer_status;
            if (!isset($events[$id]['tally'])) {
                $events[$id]['tally']   = ['yes' => 0, 'maybe' => 0, 'no' => 0, 'pending' => 0];
                $events[$id]['answers'] = [];
            }
            if ($obj->element_type === 'user' && $obj->fk_element > 0) {
                if (!in_array((int) $obj->fk_element, $events[$id]['userIds'])) {
                    $events[$id]['userIds'][] = (int) $obj->fk_element;
                }
                $events[$id]['answers'][(int) $obj->fk_element] = $status;
                // The organizer does not answer his own invitation
                if ((int) $obj->fk_element === $events[$id]['ownerId']) {
                    continue;
                }
            } elseif ($obj->element_type === 'socpeople') {
                $events[$id]['contacts'] = ($events[$id]['contacts'] ?? 0) + 1;
            }
            $key = $status === 1 ? 'yes' : ($status === 2 ? 'maybe' : ($status === -1 ? 'no' : 'pending'));
            $events[$id]['tally'][$key]++;
        }
        $db->free($resql);
    }

    // The meetings already written up : the calendar tells them from the ones whose report is awaited
    foreach (dolical_events_with_report(array_keys($events)) as $id) {
        $events[$id]['report'] = true;
    }

    $canEditAll = $user->hasRight('agenda', 'allactions', 'create');
    $canEditMy  = $user->hasRight('agenda', 'myactions', 'create');
    foreach ($events as $id => $event) {
        $owned                       = $event['ownerId'] == $user->id || $event['authorId'] == $user->id;
        $events[$id]['editable']     = $canEditAll || ($canEditMy && $owned);
        $events[$id]['contacts']     = $event['contacts'] ?? 0;
        $events[$id]['tally']        = $event['tally'] ?? ['yes' => 0, 'maybe' => 0, 'no' => 0, 'pending' => 0];
        // An object even when empty : the browser reads it as a map of the answers by user
        $events[$id]['answers']      = (object) ($event['answers'] ?? []);
        unset($events[$id]['authorId']);
    }

    return array_values($events);
}

/**
 * Colleagues already taken during a slot : their events that overlap it, the automatic ones, the declined
 * ones and the all day events marked free left out
 *
 * @param  User  $user      Current user
 * @param  int   $start     Start of the slot
 * @param  int   $end       End of the slot
 * @param  int[] $userIds   People invited
 * @param  int   $excludeId Event being edited, not a conflict with itself
 * @return array            [['userId', 'name', 'label', 'start', 'end', 'allDay']]
 */
function dolical_find_conflicts(User $user, int $start, int $end, array $userIds, int $excludeId = 0): array
{
    global $db, $langs;

    $canReadAll = dolical_can_read_all($user);
    $userIds    = array_values(array_filter(array_map('intval', $userIds)));
    if (!$canReadAll) {
        $userIds = array_values(array_intersect($userIds, [(int) $user->id]));
    }
    if (empty($userIds) || $end <= $start) {
        return [];
    }

    $sql  = 'SELECT a.id, a.label, a.datep, a.datep2, a.fulldayevent, r.fk_element as user_id';
    $sql .= ' FROM ' . MAIN_DB_PREFIX . 'actioncomm as a';
    $sql .= ' INNER JOIN ' . MAIN_DB_PREFIX . "actioncomm_resources as r ON r.fk_actioncomm = a.id AND r.element_type = 'user'";
    $sql .= ' LEFT JOIN ' . MAIN_DB_PREFIX . 'c_actioncomm as ca ON ca.id = a.fk_action';
    $sql .= ' WHERE a.entity IN (' . getEntity('agenda') . ')';
    $sql .= ' AND r.fk_element IN (' . $db->sanitize(implode(',', $userIds)) . ')';
    $sql .= ' AND a.id <> ' . ((int) $excludeId);
    $sql .= " AND a.datep < '" . $db->idate($end) . "' AND COALESCE(a.datep2, a.datep) > '" . $db->idate($start) . "'";
    $sql .= " AND (ca.type IS NULL OR ca.type <> 'systemauto')";
    $sql .= ' AND COALESCE(r.answer_status, 0) <> -1';
    // A day of remote work marked free does not take the slot, a fair marked busy does
    $sql .= ' AND (a.fulldayevent = 0 OR a.transparency = 1)';
    $sql .= ' ORDER BY a.datep ASC';
    $sql .= $db->plimit(20);

    $names     = array_column(dolical_get_calendar_users($user), 'name', 'id');
    $conflicts = [];
    $resql     = $db->query($sql);
    if ($resql) {
        while ($obj = $db->fetch_object($resql)) {
            $datep       = (int) $db->jdate($obj->datep);
            $conflicts[] = [
                'userId' => (int) $obj->user_id,
                'name'   => $names[(int) $obj->user_id] ?? ('#' . $obj->user_id),
                'label'  => dol_html_entity_decode((string) $obj->label, ENT_QUOTES | ENT_HTML5),
                'start'  => $datep,
                'end'    => $obj->datep2 ? (int) $db->jdate($obj->datep2) : $datep,
                'allDay' => !empty($obj->fulldayevent),
            ];
        }
        $db->free($resql);
    }

    return $conflicts;
}

/**
 * Load an event with everything the calendar needs, or null when it does not exist or may not be read
 *
 * @param  int             $id   Id of the event
 * @param  User            $user Current user
 * @return ActionComm|null
 */
function dolical_load_event(int $id, User $user): ?ActionComm
{
    global $db;

    $event = new ActionComm($db);
    if ($id <= 0 || $event->fetch($id) <= 0) {
        return null;
    }
    if (!in_array((int) $event->entity, array_map('intval', explode(',', getEntity('agenda'))))) {
        return null;
    }

    return dolical_can_read_event($user, $event) ? $event : null;
}

/**
 * Elements linked to an event : the native one (fk_element) first, then the links of llx_element_element
 *
 * @param  ActionComm $event Event
 * @return array             ['element:id' => ['element', 'id', 'primary' => bool, 'linkid' => rowid of llx_element_element]]
 */
function dolical_get_event_links(ActionComm $event): array
{
    global $db;

    $links = [];
    if ($event->fk_element > 0 && !empty($event->elementtype)) {
        $element = dolical_element_from_actioncomm($event->elementtype);
        $links[$element . ':' . ((int) $event->fk_element)] = ['element' => $element, 'id' => (int) $event->fk_element, 'primary' => true, 'linkid' => 0];
    }

    $sql  = 'SELECT rowid, fk_source, sourcetype, fk_target, targettype FROM ' . MAIN_DB_PREFIX . 'element_element';
    $sql .= ' WHERE (fk_target = ' . ((int) $event->id) . " AND targettype = 'action')";
    $sql .= ' OR (fk_source = ' . ((int) $event->id) . " AND sourcetype = 'action')";
    $sql .= ' ORDER BY rowid';

    $resql = $db->query($sql);
    if ($resql) {
        while ($obj = $db->fetch_object($resql)) {
            $isTarget = ($obj->targettype === 'action' && (int) $obj->fk_target === (int) $event->id);
            $element  = $isTarget ? $obj->sourcetype : $obj->targettype;
            $id       = (int) ($isTarget ? $obj->fk_source : $obj->fk_target);
            $key      = $element . ':' . $id;
            if (!isset($links[$key])) {
                $links[$key] = ['element' => $element, 'id' => $id, 'primary' => false, 'linkid' => (int) $obj->rowid];
            }
        }
        $db->free($resql);
    }

    return $links;
}

/**
 * Load the linked elements the user may read, described for the card and the form
 *
 * @param  ActionComm $event Event
 * @param  User       $user  Current user
 * @return array             ['element:id' => description from dolical_describe_object()]
 */
function dolical_get_linked_objects(ActionComm $event, User $user): array
{
    $types   = dolical_get_linkable_types($user);
    $objects = [];
    foreach (dolical_get_event_links($event) as $key => $link) {
        // A link to an element of a disabled module, of a forbidden type or that the user may not see is kept in
        // base, only not shown
        if (!isset($types[$link['element']]) || !dolical_can_see_object($user, $link['element'], (int) $link['id'])) {
            continue;
        }
        $object = fetchObjectByElement($link['id'], $link['element']);
        if (!is_object($object) || empty($object->id)) {
            continue;
        }
        $objects[$key]            = dolical_describe_object($object, $link['element'], $types[$link['element']]);
        $objects[$key]['primary'] = $link['primary'];
    }

    return $objects;
}

/**
 * Description of a commercial document for the card : reference, amount, status, and for an invoice
 * whether it is paid
 *
 * @param  CommonObject $object  Loaded object
 * @param  string       $element Element code
 * @param  array        $type    Entry of dolical_get_linkable_types()
 * @return array
 */
function dolical_describe_object(CommonObject $object, string $element, array $type): array
{
    global $conf, $langs;

    $amountField = $type['amountfield'];
    $amount      = ($amountField !== '' && isset($object->$amountField)) ? (float) $object->$amountField : null;
    $labelField  = $type['labelfield'];

    $description = [
        'key'         => $element . ':' . $object->id,
        'element'     => $element,
        'id'          => (int) $object->id,
        'ref'         => (string) $object->ref,
        'typeLabel'   => $type['label'],
        'picto'       => $type['picto'],
        'nomUrl'      => $object->getNomUrl(1),
        'status'      => method_exists($object, 'getLibStatut') ? $object->getLibStatut(5) : '',
        'amount'      => $amount,
        'amountText'  => $amount !== null ? price($amount, 0, $langs, 1, -1, -1, $conf->currency) . ' ' . $langs->trans($type['amountlabel']) : '',
        'extra'       => ($labelField !== '' && !empty($object->$labelField)) ? (string) $object->$labelField : '',
        'socid'       => (int) ($object->socid ?? 0),
        'paid'        => null,
        'remain'      => null,
        'late'        => false,
    ];

    // The question asked of an event linked to an invoice : is it paid
    if ($element === 'facture' || $element === 'invoice_supplier') {
        $description['paid']   = !empty($object->paye) || !empty($object->paid);
        $description['remain'] = method_exists($object, 'getRemainToPay') ? (float) $object->getRemainToPay() : null;
        $description['late']   = !$description['paid'] && method_exists($object, 'hasDelay') && $object->hasDelay();
    }

    return $description;
}

/**
 * Billing of what the event is about : linked invoices, invoices of the linked proposals and orders, and
 * failing that the invoices and proposals of its project. Answers "is the invoice OK" at a glance
 *
 * @param  ActionComm $event   Event
 * @param  array      $objects Linked elements, from dolical_get_linked_objects()
 * @param  User       $user    Current user
 * @return array               ['state' => 'paid'|'unpaid'|'late'|'signed'|'pending'|'none', 'label', 'invoices' => [...], 'proposals' => [...], 'remain', 'total']
 */
function dolical_get_billing_summary(ActionComm $event, array $objects, User $user): array
{
    global $conf, $db, $langs;

    $types     = dolical_get_linkable_types($user);
    $invoices  = [];
    $proposals = [];

    foreach ($objects as $key => $object) {
        if ($object['element'] === 'facture') {
            $invoices[$key] = $object;
        } elseif ($object['element'] === 'propal') {
            $proposals[$key] = $object;
        }
    }

    // Invoices made from the linked proposals and orders
    if (isset($types['facture'])) {
        foreach ($objects as $object) {
            if (!in_array($object['element'], ['propal', 'commande'])) {
                continue;
            }
            $sql  = 'SELECT fk_source, sourcetype, fk_target, targettype FROM ' . MAIN_DB_PREFIX . 'element_element';
            $sql .= ' WHERE (fk_source = ' . ((int) $object['id']) . " AND sourcetype = '" . $db->escape($object['element']) . "' AND targettype = 'facture')";
            $sql .= ' OR (fk_target = ' . ((int) $object['id']) . " AND targettype = '" . $db->escape($object['element']) . "' AND sourcetype = 'facture')";
            $resql = $db->query($sql);
            if (!$resql) {
                continue;
            }
            while ($obj = $db->fetch_object($resql)) {
                $invoiceId = (int) ($obj->targettype === 'facture' ? $obj->fk_target : $obj->fk_source);
                if (isset($invoices['facture:' . $invoiceId]) || !dolical_can_see_object($user, 'facture', $invoiceId)) {
                    continue;
                }
                $invoice = fetchObjectByElement($invoiceId, 'facture');
                if (is_object($invoice) && $invoice->id > 0) {
                    $invoices['facture:' . $invoiceId] = dolical_describe_object($invoice, 'facture', $types['facture']);
                }
            }
            $db->free($resql);
        }
    }

    // Nothing linked directly : the project tells what was proposed and billed for it
    $fromProject = false;
    if (empty($invoices) && empty($proposals) && $event->fk_project > 0) {
        $fromProject = true;
        foreach (['facture' => 'fk_projet', 'propal' => 'fk_projet'] as $element => $field) {
            if (!isset($types[$element])) {
                continue;
            }
            $sql  = 'SELECT rowid FROM ' . MAIN_DB_PREFIX . $types[$element]['table'];
            $sql .= ' WHERE ' . $field . ' = ' . ((int) $event->fk_project);
            $sql .= ' AND entity IN (' . getEntity($types[$element]['entity']) . ')';
            $sql .= ' AND fk_statut > 0';
            $sql .= ' ORDER BY rowid DESC';
            $sql .= $db->plimit(6);
            $resql = $db->query($sql);
            if (!$resql) {
                continue;
            }
            while ($obj = $db->fetch_object($resql)) {
                if (!dolical_can_see_object($user, $element, (int) $obj->rowid)) {
                    continue;
                }
                $object = fetchObjectByElement((int) $obj->rowid, $element);
                if (is_object($object) && $object->id > 0) {
                    $description = dolical_describe_object($object, $element, $types[$element]);
                    if ($element === 'facture') {
                        $invoices[$description['key']] = $description;
                    } else {
                        $proposals[$description['key']] = $description;
                    }
                }
            }
            $db->free($resql);
        }
    }

    $summary = ['state' => 'none', 'label' => '', 'invoices' => array_values($invoices), 'proposals' => array_values($proposals), 'remain' => 0.0, 'total' => 0.0, 'fromProject' => $fromProject];

    if (!empty($invoices)) {
        $late = false;
        foreach ($invoices as $invoice) {
            $summary['total']  += (float) $invoice['amount'];
            $summary['remain'] += max(0, (float) $invoice['remain']);
            $late               = $late || $invoice['late'];
        }
        $remainText = price($summary['remain'], 0, $langs, 1, -1, -1, $conf->currency);
        if ($summary['remain'] < 0.01) {
            $summary['state'] = 'paid';
            $summary['label'] = $langs->trans('DoliCalBillingPaid');
        } elseif ($late) {
            $summary['state'] = 'late';
            $summary['label'] = $langs->trans('DoliCalBillingLate', $remainText);
        } else {
            $summary['state'] = 'unpaid';
            $summary['label'] = $langs->trans('DoliCalBillingUnpaid', $remainText);
        }
    } elseif (!empty($proposals)) {
        // Propal statuses : 2 signed, 4 billed
        $signed = false;
        foreach ($proposals as $proposal) {
            $object = fetchObjectByElement($proposal['id'], 'propal', '', 1);
            $signed = $signed || (is_object($object) && in_array((int) $object->status, [2, 4]));
        }
        $summary['state'] = $signed ? 'signed' : 'pending';
        $summary['label'] = $langs->trans($signed ? 'DoliCalBillingSignedNotBilled' : 'DoliCalBillingProposalPending');
    }

    return $summary;
}

/**
 * Users and contacts taking part in the event, the owner first
 *
 * @param  ActionComm $event Event, resources loaded
 * @return array             ['people' => [...], 'confirmed' => int, 'pending' => int, 'declined' => int, 'emails' => [...]]
 */
function dolical_get_event_participants(ActionComm $event): array
{
    global $db, $langs;

    $people = [];
    $emails = [];

    $userIds = array_keys($event->userassigned);
    if ($event->userownerid > 0 && !in_array($event->userownerid, $userIds)) {
        array_unshift($userIds, $event->userownerid);
    }
    foreach ($userIds as $userId) {
        $tmpUser = new User($db);
        if ($tmpUser->fetch((int) $userId) <= 0) {
            continue;
        }
        $isOwner = ((int) $userId === (int) $event->userownerid);
        $status  = (int) ($event->userassigned[$userId]['answer_status'] ?? 0);
        // The organizer does not answer his own invitation
        if ($isOwner && $status === 0) {
            $status = 1;
        }
        $name     = $tmpUser->getFullName($langs);
        $people[] = [
            'kind'     => 'user',
            'id'       => (int) $tmpUser->id,
            'name'     => $name,
            'initials' => dolical_initials($name),
            'color'    => dolical_user_color((int) $tmpUser->id, (string) $tmpUser->color),
            'email'    => (string) $tmpUser->email,
            'phone'    => (string) ($tmpUser->user_mobile ?: $tmpUser->office_phone),
            'company'  => '',
            'nomUrl'   => $tmpUser->getNomUrl(-1, '', 0, 0, 24, 0, '', 'valignmiddle'),
            'owner'    => $isOwner,
            'status'   => $status,
            'busy'     => !empty($event->userassigned[$userId]['transparency']),
        ];
        if (!empty($tmpUser->email)) {
            $emails[] = $tmpUser->email;
        }
    }

    $contactIds = array_keys($event->socpeopleassigned);
    if ($event->contact_id > 0 && !in_array($event->contact_id, $contactIds)) {
        array_unshift($contactIds, $event->contact_id);
    }
    $companies = [];
    foreach ($contactIds as $contactId) {
        $contact = new Contact($db);
        if ($contact->fetch((int) $contactId) <= 0) {
            continue;
        }
        if ($contact->socid > 0 && !isset($companies[$contact->socid])) {
            $company = new Societe($db);
            $companies[$contact->socid] = $company->fetch($contact->socid) > 0 ? $company->name : '';
        }
        $name     = $contact->getFullName($langs);
        $people[] = [
            'kind'     => 'contact',
            'id'       => (int) $contact->id,
            'name'     => $name,
            'initials' => dolical_initials($name),
            'color'    => '#94a3b8',
            'email'    => (string) $contact->email,
            'phone'    => (string) ($contact->phone_mobile ?: $contact->phone_pro),
            'company'  => $contact->socid > 0 ? $companies[$contact->socid] : '',
            'nomUrl'   => $contact->getNomUrl(1, '', 0, '', 0, 0, 'valignmiddle'),
            'owner'    => false,
            'status'   => (int) ($event->socpeopleassigned[$contactId]['answer_status'] ?? 0),
            'busy'     => false,
        ];
        if (!empty($contact->email)) {
            $emails[] = $contact->email;
        }
    }

    $counts = ['confirmed' => 0, 'pending' => 0, 'declined' => 0];
    foreach ($people as $person) {
        if ($person['status'] > 0) {
            $counts['confirmed']++;
        } elseif ($person['status'] < 0) {
            $counts['declined']++;
        } else {
            $counts['pending']++;
        }
    }

    return array_merge(['people' => $people, 'emails' => array_values(array_unique($emails))], $counts);
}

/**
 * Directory of the documents of an event, the one the native card uses
 *
 * @param  ActionComm $event Event
 * @return string
 */
function dolical_get_event_dir(ActionComm $event): string
{
    global $conf;

    $entity = !empty($event->entity) ? (int) $event->entity : (int) $conf->entity;
    $root   = $conf->agenda->multidir_output[$entity] ?? $conf->agenda->dir_output;

    return $root . '/' . ((int) $event->id);
}

/**
 * Remove the documents of an event and their lines of the ECM index
 *
 * The index is cleaned by directory, not file by file : dol_delete_file() looks every file up by its name, which
 * fails on an accented name when the table is still in latin1 while the connection speaks utf8
 *
 * @param  ActionComm $event Event
 * @return void
 */
function dolical_delete_event_dir(ActionComm $event): void
{
    global $db;

    $dir = dolical_get_event_dir($event);
    if (!is_dir($dir)) {
        return;
    }

    $relativeDir = trim(preg_replace('/^' . preg_quote(DOL_DATA_ROOT, '/') . '/', '', $dir), '/\\');
    $db->query('DELETE FROM ' . MAIN_DB_PREFIX . "ecm_files WHERE filepath = '" . $db->escape($relativeDir) . "'");

    $count = 0;
    dol_delete_dir_recursive($dir, 0, 0, 0, $count, 0);
}

/**
 * Attachments of an event
 *
 * @param  ActionComm $event Event
 * @return array             [['name', 'size', 'date', 'url', 'preview', 'icon', 'image' => bool]]
 */
function dolical_get_event_files(ActionComm $event): array
{
    $entity = !empty($event->entity) ? (int) $event->entity : 1;
    $files  = [];
    // Mode 1 : the size is only read on demand
    foreach (dol_dir_list(dolical_get_event_dir($event), 'files', 0, '', '(\.meta|_preview.*\.png)$', 'date', SORT_DESC, 1) as $file) {
        $relative = ((int) $event->id) . '/' . $file['name'];
        $isImage  = image_format_supported($file['name']) >= 0;
        $files[]  = [
            'name'    => $file['name'],
            'size'    => dol_print_size($file['size'], 1, 1),
            'date'    => dol_print_date($file['date'], 'dayhour', 'tzuserrel'),
            'url'     => DOL_URL_ROOT . '/document.php?modulepart=actions&attachment=0&file=' . urlencode($relative) . '&entity=' . $entity,
            'preview' => $isImage ? DOL_URL_ROOT . '/viewimage.php?modulepart=actions&file=' . urlencode($relative) . '&entity=' . $entity : '',
            'icon'    => img_mime($file['name'], '', 'pictofixedwidth'),
            'image'   => $isImage,
        ];
    }

    return $files;
}

/**
 * Everything the card of an event shows
 *
 * @param  ActionComm $event Event, resources loaded
 * @param  User       $user  Current user
 * @return array
 */
function dolical_get_event_card(ActionComm $event, User $user): array
{
    global $db, $langs;

    $langs->loadLangs(['agenda', 'companies', 'projects', 'bills', 'propal']);

    $owner = new User($db);
    if ($event->userownerid > 0) {
        $owner->fetch($event->userownerid);
    }

    $card = [
        'id'        => (int) $event->id,
        'label'     => (string) $event->label,
        'owner'     => ['id' => (int) $owner->id, 'name' => $owner->id > 0 ? $owner->getFullName($langs) : '', 'color' => dolical_user_color((int) $owner->id, (string) $owner->color)],
        'typeLabel' => $event->getTypeLabel(),
        'typeIcon'  => dolical_type_icon((string) $event->type_code, (string) $event->type, (string) $event->type_picto, (string) $event->code),
        'typeColor' => $event->type_color ? '#' . ltrim($event->type_color, '#') : '',
        'status'    => $event->getLibStatut(4),
        'percent'   => (int) $event->percentage,
        'location'  => (string) $event->location,
        'note'      => trim((string) $event->note_private) !== '' ? dolPrintHTML($event->note_private) : '',
        'canEdit'   => dolical_can_edit_event($user, $event),
        'canDelete' => dolical_can_delete_event($user, $event),
        'isMine'    => isset($event->userassigned[$user->id]) || $event->userownerid == $user->id,
        'myStatus'  => (int) ($event->userassigned[$user->id]['answer_status'] ?? 0),
        'nativeUrl' => DOL_URL_ROOT . '/comm/action/card.php?id=' . ((int) $event->id),
    ];

    // Dates : one line when the event fits in a day, two otherwise
    $start   = (int) $event->datep;
    $end     = $event->datef ? (int) $event->datef : $start;
    $sameDay = dol_print_date($start, '%Y%m%d', 'tzuserrel') === dol_print_date($end, '%Y%m%d', 'tzuserrel');
    if ($event->fulldayevent) {
        $card['dateLines'] = $sameDay
            ? [dol_print_date($start, 'daytext', 'tzuserrel'), $langs->trans('DoliCalAllDay')]
            : [dol_print_date($start, 'daytext', 'tzuserrel') . ' → ' . dol_print_date($end, 'daytext', 'tzuserrel'), $langs->trans('DoliCalAllDay')];
    } elseif ($sameDay) {
        $card['dateLines'] = [dol_print_date($start, 'daytext', 'tzuserrel'), dol_print_date($start, 'hour', 'tzuserrel') . ' – ' . dol_print_date($end, 'hour', 'tzuserrel')];
    } else {
        $card['dateLines'] = [dol_print_date($start, 'dayhourtext', 'tzuserrel'), dol_print_date($end, 'dayhourtext', 'tzuserrel')];
    }
    $card['duration'] = (!$event->fulldayevent && $sameDay && $end > $start) ? dolical_format_duration($end - $start) : '';
    $card['isPast']   = $end < dol_now() && $event->percentage >= 0 && $event->percentage < 100;

    // Repetition, the reminder of the user, and the report once the meeting is over
    $card['recurrence'] = !empty($event->recurid) ? dolical_recurrence_label((string) $event->recurrule, $start, !empty($event->recurdateend) ? (int) $event->recurdateend : 0) : '';
    $card['seriesSize'] = !empty($event->recurid) ? count(dolical_series_occurrences($event)) : 0;
    $card['reminder']   = dolical_reminder_label(dolical_get_reminders($event, (int) $user->id));
    $card['report']     = dolical_get_report($event);
    $card['reportDue']  = dolical_report_due($event, $user);
    $card['canReport']  = dolical_can_report($user, $event);

    // Third party and project : their name as on the native card, their details for whoever may see them
    $card['thirdparty'] = null;
    if ($event->socid > 0) {
        $thirdparty = new Societe($db);
        if ($thirdparty->fetch($event->socid) > 0) {
            $seen               = dolical_can_see_object($user, 'societe', (int) $event->socid);
            $card['thirdparty'] = [
                'nomUrl' => $thirdparty->getNomUrl(0, '', 0, 0, -1, 0, '', 'valignmiddle'),
                'town'   => $seen ? trim($thirdparty->zip . ' ' . $thirdparty->town) : '',
                'phone'  => $seen ? (string) $thirdparty->phone : '',
                'email'  => $seen ? (string) $thirdparty->email : '',
            ];
        }
    }
    $card['project'] = null;
    if ($event->fk_project > 0 && isModEnabled('project')) {
        require_once DOL_DOCUMENT_ROOT . '/projet/class/project.class.php';
        $project = new Project($db);
        if ($project->fetch($event->fk_project) > 0) {
            $seen            = dolical_can_see_object($user, 'project', (int) $event->fk_project);
            $card['project'] = [
                'nomUrl'  => $project->getNomUrl(0, '', 1),
                'status'  => $project->getLibStatut(5),
                'amount'  => $seen && $project->opp_amount > 0 ? price($project->opp_amount, 0, $langs, 1, -1, -1, 'auto') : '',
                'percent' => $seen && $project->opp_percent !== '' && $project->opp_percent !== null ? round((float) $project->opp_percent) . ' %' : '',
            ];
        }
    }

    $card['participants'] = dolical_get_event_participants($event);
    $card['objects']      = dolical_get_linked_objects($event, $user);
    $card['billing']      = dolical_get_billing_summary($event, $card['objects'], $user);
    $card['files']        = dolical_get_event_files($event);

    // Email thread of an event born from a sent or received email
    $card['email'] = null;
    if (!empty($event->email_from) || !empty($event->email_to)) {
        $card['email'] = ['subject' => (string) $event->email_subject, 'from' => (string) $event->email_from, 'to' => (string) $event->email_to];
    }

    // Tags of the event
    $card['tags'] = [];
    if (isModEnabled('category')) {
        require_once DOL_DOCUMENT_ROOT . '/categories/class/categorie.class.php';
        $category   = new Categorie($db);
        $categories = $category->containing($event->id, Categorie::TYPE_ACTIONCOMM);
        if (is_array($categories)) {
            foreach ($categories as $tag) {
                $card['tags'][] = ['label' => $tag->label, 'color' => $tag->color ? '#' . ltrim($tag->color, '#') : ''];
            }
        }
    }

    // Who created and changed it
    $author = new User($db);
    $card['meta'] = [];
    if ($event->authorid > 0 && $author->fetch($event->authorid) > 0) {
        $card['meta'][] = $langs->trans('DoliCalCreatedBy', $author->getFullName($langs), dol_print_date($event->datec, 'dayhour', 'tzuserrel'));
    }
    if ($event->usermodid > 0 && !empty($event->datem) && $event->datem != $event->datec) {
        $modifier = new User($db);
        if ($modifier->fetch($event->usermodid) > 0) {
            $card['meta'][] = $langs->trans('DoliCalModifiedBy', $modifier->getFullName($langs), dol_print_date($event->datem, 'dayhour', 'tzuserrel'));
        }
    }

    return $card;
}

/**
 * Search the documents an event can be linked to, grouped by type for select2
 *
 * @param  User   $user   Current user
 * @param  string $search Searched text, on the reference, the customer reference and the third party
 * @param  int    $socid  Third party of the event, its documents come first
 * @return array          [['text' => type label, 'children' => [['id' => 'element:id', 'text' => ...]]]]
 */
function dolical_search_elements(User $user, string $search, int $socid = 0): array
{
    global $conf, $db, $langs;

    $search = trim($search);
    if ($search === '' && $socid <= 0) {
        return [];
    }

    $groups = [];
    foreach (dolical_get_linkable_types($user) as $element => $type) {
        $fields  = 't.rowid, t.ref, s.nom as socname';
        $fields .= $type['labelfield'] !== '' ? ', t.' . $type['labelfield'] . ' as extra' : ", '' as extra";
        $fields .= $type['amountfield'] !== '' ? ', t.' . $type['amountfield'] . ' as amount' : ', NULL as amount';

        $sql  = 'SELECT ' . $fields;
        $sql .= ' FROM ' . MAIN_DB_PREFIX . $type['table'] . ' as t';
        $sql .= ' LEFT JOIN ' . MAIN_DB_PREFIX . 'societe as s ON s.rowid = t.fk_soc';
        $sql .= ' WHERE t.entity IN (' . getEntity($type['entity']) . ')';
        if ($search !== '') {
            $like = $db->escape($db->escapeforlike($search));
            $sql .= " AND (t.ref LIKE '%" . $like . "%' OR s.nom LIKE '%" . $like . "%'";
            $sql .= $type['labelfield'] !== '' ? ' OR t.' . $type['labelfield'] . " LIKE '%" . $like . "%'" : '';
            $sql .= ')';
        } else {
            $sql .= ' AND t.fk_soc = ' . ((int) $socid);
        }
        if ($user->socid > 0) {
            $sql .= ' AND t.fk_soc = ' . ((int) $user->socid);
        }
        // Same restriction as the native lists for a user limited to his own customers
        if (!$user->hasRight('societe', 'client', 'voir')) {
            $sql .= ' AND EXISTS (SELECT sc.fk_soc FROM ' . MAIN_DB_PREFIX . 'societe_commerciaux as sc WHERE sc.fk_soc = t.fk_soc AND sc.fk_user = ' . ((int) $user->id) . ')';
        }
        $sql .= ' ORDER BY ' . ($socid > 0 ? '(t.fk_soc = ' . ((int) $socid) . ') DESC, ' : '') . 't.rowid DESC';
        $sql .= $db->plimit(8);

        $resql = $db->query($sql);
        if (!$resql) {
            continue;
        }
        $children = [];
        while ($obj = $db->fetch_object($resql)) {
            $text = $obj->ref;
            if (!empty($obj->extra)) {
                $text .= ' · ' . $obj->extra;
            }
            if (!empty($obj->socname)) {
                $text .= ' — ' . $obj->socname;
            }
            if ($obj->amount !== null && $type['amountfield'] !== '') {
                $text .= ' (' . price((float) $obj->amount, 0, $langs, 1, -1, -1, $conf->currency) . ')';
            }
            $children[] = ['id' => $element . ':' . ((int) $obj->rowid), 'text' => $text];
        }
        $db->free($resql);

        if (!empty($children)) {
            $groups[] = ['text' => $type['label'], 'children' => $children];
        }
    }

    return $groups;
}

/**
 * Search the contacts that can take part in an event
 *
 * @param  User   $user   Current user
 * @param  string $search Searched text, on the name, the email and the third party
 * @param  int    $socid  Third party of the event, its contacts come first
 * @return array          [['id', 'text']]
 */
function dolical_search_contacts(User $user, string $search, int $socid = 0): array
{
    global $db, $langs;

    $search = trim($search);
    if (!$user->hasRight('societe', 'contact', 'lire') || ($search === '' && $socid <= 0)) {
        return [];
    }

    $sql  = 'SELECT sp.rowid, sp.lastname, sp.firstname, sp.email, s.nom as socname';
    $sql .= ' FROM ' . MAIN_DB_PREFIX . 'socpeople as sp';
    $sql .= ' LEFT JOIN ' . MAIN_DB_PREFIX . 'societe as s ON s.rowid = sp.fk_soc';
    $sql .= ' WHERE sp.entity IN (' . getEntity('contact') . ')';
    $sql .= ' AND sp.statut = 1';
    $sql .= ' AND (sp.priv = 0 OR sp.fk_user_creat = ' . ((int) $user->id) . ')';
    if ($search !== '') {
        $like = $db->escape($db->escapeforlike($search));
        $sql .= " AND (sp.lastname LIKE '%" . $like . "%' OR sp.firstname LIKE '%" . $like . "%' OR sp.email LIKE '%" . $like . "%' OR s.nom LIKE '%" . $like . "%'";
        $sql .= " OR CONCAT(sp.firstname, ' ', sp.lastname) LIKE '%" . $like . "%')";
    } else {
        $sql .= ' AND sp.fk_soc = ' . ((int) $socid);
    }
    if ($user->socid > 0) {
        $sql .= ' AND sp.fk_soc = ' . ((int) $user->socid);
    }
    // Same restriction as the native lists for a user limited to his own customers : a contact without third party stays
    if (!$user->hasRight('societe', 'client', 'voir')) {
        $sql .= ' AND (sp.fk_soc IS NULL OR sp.fk_soc = 0 OR EXISTS (SELECT sc.fk_soc FROM ' . MAIN_DB_PREFIX . 'societe_commerciaux as sc WHERE sc.fk_soc = sp.fk_soc AND sc.fk_user = ' . ((int) $user->id) . '))';
    }
    $sql .= ' ORDER BY ' . ($socid > 0 ? '(sp.fk_soc = ' . ((int) $socid) . ') DESC, ' : '') . 'sp.lastname, sp.firstname';
    $sql .= $db->plimit(25);

    $results = [];
    $resql   = $db->query($sql);
    if ($resql) {
        while ($obj = $db->fetch_object($resql)) {
            $text = dolGetFirstLastname($obj->firstname, $obj->lastname);
            if (!empty($obj->socname)) {
                $text .= ' — ' . $obj->socname;
            }
            if (!empty($obj->email)) {
                $text .= ' <' . $obj->email . '>';
            }
            $results[] = ['id' => (int) $obj->rowid, 'text' => $text];
        }
        $db->free($resql);
    }

    return $results;
}

/**
 * Search the third parties an event can be about
 *
 * @param  User   $user   Current user
 * @param  string $search Searched text, on the name, the alias and the customer code
 * @return array          [['id', 'text']]
 */
function dolical_search_thirdparties(User $user, string $search): array
{
    global $db;

    $search = trim($search);
    if ($search === '' || !$user->hasRight('societe', 'lire')) {
        return [];
    }

    $like = $db->escape($db->escapeforlike($search));
    $sql  = 'SELECT s.rowid, s.nom, s.name_alias, s.town';
    $sql .= ' FROM ' . MAIN_DB_PREFIX . 'societe as s';
    $sql .= ' WHERE s.entity IN (' . getEntity('societe') . ')';
    $sql .= ' AND s.status = 1';
    $sql .= " AND (s.nom LIKE '%" . $like . "%' OR s.name_alias LIKE '%" . $like . "%' OR s.code_client LIKE '%" . $like . "%' OR s.code_fournisseur LIKE '%" . $like . "%')";
    // Same restriction as the native lists for a user limited to his own customers
    if (!$user->hasRight('societe', 'client', 'voir')) {
        $sql .= ' AND EXISTS (SELECT sc.fk_soc FROM ' . MAIN_DB_PREFIX . 'societe_commerciaux as sc WHERE sc.fk_soc = s.rowid AND sc.fk_user = ' . ((int) $user->id) . ')';
    }
    if ($user->socid > 0) {
        $sql .= ' AND s.rowid = ' . ((int) $user->socid);
    }
    $sql .= ' ORDER BY s.nom';
    $sql .= $db->plimit(25);

    $results = [];
    $resql   = $db->query($sql);
    if ($resql) {
        while ($obj = $db->fetch_object($resql)) {
            $text = $obj->nom;
            if (!empty($obj->name_alias)) {
                $text .= ' (' . $obj->name_alias . ')';
            }
            if (!empty($obj->town)) {
                $text .= ' — ' . $obj->town;
            }
            $results[] = ['id' => (int) $obj->rowid, 'text' => $text];
        }
        $db->free($resql);
    }

    return $results;
}

/**
 * Search the projects an event can belong to, among the ones the user may see
 *
 * @param  User   $user   Current user
 * @param  string $search Searched text, on the reference and the title
 * @param  int    $socid  Third party of the event, its projects come first
 * @return array          [['id', 'text']]
 */
function dolical_search_projects(User $user, string $search, int $socid = 0): array
{
    global $db;

    $search = trim($search);
    if (!isModEnabled('project') || !$user->hasRight('projet', 'lire') || ($search === '' && $socid <= 0)) {
        return [];
    }

    require_once DOL_DOCUMENT_ROOT . '/projet/class/project.class.php';
    $project    = new Project($db);
    $authorized = $project->getProjectsAuthorizedForUser($user, 0, 1);

    $sql  = 'SELECT p.rowid, p.ref, p.title, s.nom as socname';
    $sql .= ' FROM ' . MAIN_DB_PREFIX . 'projet as p';
    $sql .= ' LEFT JOIN ' . MAIN_DB_PREFIX . 'societe as s ON s.rowid = p.fk_soc';
    $sql .= ' WHERE p.entity IN (' . getEntity('project') . ')';
    $sql .= ' AND p.fk_statut IN (0, 1)';
    $sql .= ' AND p.rowid IN (' . $db->sanitize($authorized !== '' ? $authorized : '0') . ')';
    if ($search !== '') {
        $like = $db->escape($db->escapeforlike($search));
        $sql .= " AND (p.ref LIKE '%" . $like . "%' OR p.title LIKE '%" . $like . "%' OR s.nom LIKE '%" . $like . "%')";
    } else {
        $sql .= ' AND p.fk_soc = ' . ((int) $socid);
    }
    $sql .= ' ORDER BY ' . ($socid > 0 ? '(p.fk_soc = ' . ((int) $socid) . ') DESC, ' : '') . 'p.rowid DESC';
    $sql .= $db->plimit(25);

    $results = [];
    $resql   = $db->query($sql);
    if ($resql) {
        while ($obj = $db->fetch_object($resql)) {
            $text = $obj->ref . ' · ' . $obj->title;
            if (!empty($obj->socname)) {
                $text .= ' — ' . $obj->socname;
            }
            $results[] = ['id' => (int) $obj->rowid, 'text' => $text];
        }
        $db->free($resql);
    }

    return $results;
}

/**
 * Create or update an event from the form of the calendar, with its people and its linked elements
 *
 * @param  ActionComm $event Event, new or loaded with its resources
 * @param  array      $data  Values of the form, already read with GETPOST
 * @param  User       $user  Current user
 * @return int               Id of the event, < 0 on error ($event->error is set)
 */
function dolical_save_event(ActionComm $event, array $data, User $user): int
{
    global $db, $langs;

    $isNew = empty($event->id);
    if (!$isNew) {
        $event->oldcopy = clone $event;
    }

    // Whoever writes the event (form, phone, series), its text is read as the form reads it : no HTML in the title
    // nor the place, and no line break, they end in mail subjects and iCalendar lines
    $label    = trim(preg_replace('/\s*[\r\n]+\s*/', ' ', sanitizeVal((string) $data['label'], 'alphanohtml')));
    $location = trim(preg_replace('/\s*[\r\n]+\s*/', ' ', sanitizeVal((string) $data['location'], 'alphanohtml')));

    if ($label === '') {
        $event->error = $langs->trans('ErrorFieldRequired', $langs->transnoentities('Title'));
        return -1;
    }
    if (empty($data['start'])) {
        $event->error = $langs->trans('ErrorFieldRequired', $langs->transnoentities('DateStart'));
        return -1;
    }

    // Type : the dictionary when the agenda uses it, the generic "Other" otherwise
    $typeCode = 'AC_OTH';
    if (getDolGlobalString('AGENDA_USE_EVENT_TYPE')) {
        $types = dolical_get_event_types();
        if (isset($types[$data['type_code']])) {
            $typeCode = $data['type_code'];
        } elseif (!$isNew && !empty($event->type_code)) {
            $typeCode = $event->type_code;
        }
    }
    // An automatic event keeps its type, the form never offers it
    if ($isNew || $event->type !== 'systemauto') {
        $cactioncomm = new CActionComm($db);
        if ($cactioncomm->fetch($typeCode) > 0) {
            $event->type_id   = $cactioncomm->id;
            $event->type_code = $cactioncomm->code;
        }
    }

    // A third party or a project chosen for the event must be one the user may see ; the ones already there stay
    $socid     = max(0, (int) $data['socid']);
    $projectId = max(0, (int) $data['project_id']);
    if (($socid > 0 && $socid !== (int) $event->socid && !dolical_can_see_object($user, 'societe', $socid))
        || ($projectId > 0 && $projectId !== (int) $event->fk_project && !dolical_can_see_object($user, 'project', $projectId))) {
        $event->error = $langs->trans('NotEnoughPermissions');
        return -1;
    }

    $event->label        = dol_trunc($label, 255, 'right', 'UTF-8', 1);
    $event->fulldayevent = !empty($data['fullday']) ? 1 : 0;
    $event->datep        = (int) $data['start'];
    $event->datef        = max((int) $data['start'], (int) ($data['end'] ?: $data['start']));
    $event->location     = dol_trunc($location, 255, 'right', 'UTF-8', 1);
    $event->percentage   = max(-1, min(100, (int) $data['percent']));
    $event->transparency = !empty($data['busy']) ? 1 : 0;
    $event->socid        = $socid;
    $event->fk_project   = $projectId;
    if ($isNew || !empty($data['note_changed'])) {
        $event->note_private = sanitizeVal((string) $data['note'], 'restricthtml');
    }

    // People : the owner first, the answers already given are kept
    $ownerId = (int) $data['owner_id'] > 0 && dolical_filter_active_users([(int) $data['owner_id']]) ? (int) $data['owner_id'] : (int) $user->id;
    if (!$user->hasRight('agenda', 'allactions', 'create')) {
        // Without the right on the events of others, an event stays mine
        $ownerId = (int) ($isNew ? $user->id : ($event->userownerid ?: $user->id));
    }
    $previousUsers       = $event->userassigned;
    $event->userownerid  = $ownerId;
    $event->userassigned = [];
    foreach (array_merge([$ownerId], dolical_filter_active_users((array) $data['user_ids'])) as $userId) {
        if ($userId <= 0 || isset($event->userassigned[$userId])) {
            continue;
        }
        $event->userassigned[$userId] = [
            'id'            => $userId,
            'mandatory'     => (int) ($previousUsers[$userId]['mandatory'] ?? 0),
            'answer_status' => (int) ($previousUsers[$userId]['answer_status'] ?? 0),
            'transparency'  => $userId === $ownerId ? $event->transparency : (int) ($previousUsers[$userId]['transparency'] ?? $event->transparency),
        ];
    }

    // Contacts : the ones already invited stay, a new one must be a contact the user may see
    $previousContacts         = $event->socpeopleassigned;
    $event->socpeopleassigned = [];
    $contactIds               = array_values(array_filter(array_map('intval', (array) $data['contact_ids']), function ($contactId) use ($previousContacts, $user) {
        return $contactId > 0 && (isset($previousContacts[$contactId]) || dolical_can_see_object($user, 'contact', $contactId));
    }));
    foreach ($contactIds as $contactId) {
        $event->socpeopleassigned[$contactId] = ['id' => $contactId, 'mandatory' => 0, 'transparency' => 0, 'answer_status' => (int) ($previousContacts[$contactId]['answer_status'] ?? 0)];
    }
    $event->contact_id = !empty($contactIds) ? $contactIds[0] : 0;
    // A contact always belongs to a third party : without one chosen, the event takes the third party of the first contact
    if ($event->socid <= 0 && $event->contact_id > 0) {
        $contact = new Contact($db);
        if ($contact->fetch($event->contact_id) > 0 && $contact->socid > 0) {
            $event->socid = (int) $contact->socid;
        }
    }

    // Linked elements : the native link (fk_element) stays the first one, the others go to llx_element_element
    $types    = dolical_get_linkable_types($user);
    $wanted   = [];
    $existing = $isNew ? [] : dolical_get_event_links($event);
    foreach ((array) $data['elements'] as $value) {
        if (!preg_match('/^([a-z_]+):(\d+)$/', (string) $value, $matches) || !isset($types[$matches[1]]) || $matches[2] <= 0) {
            continue;
        }
        $key = $matches[1] . ':' . $matches[2];
        // A new link must point to a document the user may see
        if (!isset($existing[$key]) && !dolical_can_see_object($user, $matches[1], (int) $matches[2])) {
            continue;
        }
        $wanted[$key] = ['element' => $matches[1], 'id' => (int) $matches[2]];
    }
    $primaryKey   = '';
    $clearPrimary = false;
    foreach ($existing as $key => $link) {
        if ($link['primary']) {
            $primaryKey = $key;
            // An element of a type the user cannot see is not in the form, it must not be lost
            if (!isset($wanted[$key]) && isset($types[$link['element']])) {
                $clearPrimary = true;
                $primaryKey   = '';
            }
        }
    }
    if ($primaryKey === '' && !empty($wanted)) {
        $primaryKey         = array_key_first($wanted);
        $event->fk_element  = $wanted[$primaryKey]['id'];
        $event->elementtype = dolical_element_to_actioncomm($wanted[$primaryKey]['element']);
        $clearPrimary       = false;
    }

    $db->begin();

    $result = $isNew ? $event->create($user) : $event->update($user);
    if ($result <= 0) {
        $db->rollback();
        if (empty($event->error) && !empty($event->errors)) {
            $event->error = implode(', ', $event->errors);
        }
        return -1;
    }

    $error = 0;
    if ($clearPrimary) {
        $sql = 'UPDATE ' . MAIN_DB_PREFIX . 'actioncomm SET fk_element = NULL, elementtype = NULL WHERE id = ' . ((int) $event->id);
        $error += $db->query($sql) ? 0 : 1;
    }

    // ActionComm::update() writes the contacts back without their answer
    foreach ($event->socpeopleassigned as $contactId => $contact) {
        if (!empty($contact['answer_status'])) {
            $sql    = 'UPDATE ' . MAIN_DB_PREFIX . 'actioncomm_resources SET answer_status = ' . ((int) $contact['answer_status']);
            $sql   .= ' WHERE fk_actioncomm = ' . ((int) $event->id) . " AND element_type = 'socpeople' AND fk_element = " . ((int) $contactId);
            $error += $db->query($sql) ? 0 : 1;
        }
    }

    foreach ($existing as $key => $link) {
        if (!$link['primary'] && !isset($wanted[$key]) && isset($types[$link['element']])) {
            $error += $event->deleteObjectLinked(null, '', null, '', $link['linkid']) > 0 ? 0 : 1;
        }
    }
    foreach ($wanted as $key => $link) {
        if ($key !== $primaryKey && !isset($existing[$key])) {
            $error += $event->add_object_linked($link['element'], $link['id']) > 0 ? 0 : 1;
        }
    }

    if ($error) {
        // The database tells the log, not the browser
        dol_syslog(__FUNCTION__ . ' ' . $db->lasterror() . ' ' . $event->error, LOG_ERR);
        $db->rollback();
        $event->error = $langs->trans('Error');
        return -1;
    }

    $db->commit();

    return (int) $event->id;
}

/**
 * Move an event in time, and from a person to another when it is dropped in the column of someone else
 *
 * @param  ActionComm $event    Event, resources loaded
 * @param  int        $start    New start
 * @param  int        $end      New end
 * @param  bool       $allDay   Whether the event now lasts all day
 * @param  int        $fromUser Person whose column the event left, 0 when unchanged
 * @param  int        $toUser   Person whose column the event reached, 0 when unchanged
 * @param  User       $user     Current user
 * @return int                  > 0 when done
 */
function dolical_move_event(ActionComm $event, int $start, int $end, bool $allDay, int $fromUser, int $toUser, User $user): int
{
    global $langs;

    // Without the right on the events of others, an event stays its owner's, as in the form
    if ($fromUser > 0 && $toUser > 0 && $fromUser !== $toUser && (int) $event->userownerid === $fromUser && !$user->hasRight('agenda', 'allactions', 'create')) {
        $event->error = $langs->trans('NotEnoughPermissions');
        return -1;
    }

    $event->oldcopy      = clone $event;
    $event->datep        = $start;
    $event->datef        = max($start, $end);
    $event->fulldayevent = $allDay ? 1 : 0;

    if ($fromUser > 0 && $toUser > 0 && $fromUser !== $toUser) {
        $assigned = [];
        foreach ($event->userassigned as $userId => $values) {
            $values = is_array($values) ? $values : ['id' => $values];
            if ((int) $userId === $fromUser) {
                $values['id']            = $toUser;
                $values['answer_status'] = 0;
                $userId                  = $toUser;
            }
            $assigned[(int) $userId] = $values;
        }
        if ((int) $event->userownerid === $fromUser) {
            $event->userownerid = $toUser;
        }
        if (!isset($assigned[$event->userownerid])) {
            $assigned = [$event->userownerid => ['id' => $event->userownerid]] + $assigned;
        }
        $event->userassigned = $assigned;
    }

    return $event->update($user);
}
