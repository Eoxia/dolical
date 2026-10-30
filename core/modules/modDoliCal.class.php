<?php
/* Copyright (C) 2026 EVARISK <technique@evarisk.com>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

/**
 * \defgroup dolical Module DoliCal
 * \brief    DoliCal module descriptor
 *
 * \file    core/modules/modDoliCal.class.php
 * \ingroup dolical
 * \brief   Description and activation file for module DoliCal
 */

// Load Dolibarr libraries
require_once DOL_DOCUMENT_ROOT . '/core/modules/DolibarrModules.class.php';

/**
 * Description and activation class for module DoliCal
 */
class modDoliCal extends DolibarrModules
{
    /**
     * Constructor. Define names, constants, directories, boxes, permissions
     *
     * @param DoliDB $db Database handler
     */
    public function __construct($db)
    {
        global $langs, $conf;

        parent::__construct($db);

        $langs->load('dolical@dolical');

        // ID for module (must be unique)
        $this->numero = 436352;

        // Key text used to identify module (for permissions, menus, etc...)
        $this->rights_class = 'dolical';

        // Family can be 'base' (core modules),'crm','financial','hr','projects','products','ecm','technic' (transverse modules),'interface' (link with external tools),'other', 'etc.'
        // It is used to group modules by family in module setup page
        $this->family = '';

        // Module position in the family on 2 digits ('01', '10', '20', ...)
        $this->module_position = '';

        // Gives the possibility for the module, to provide his own family info and position of this family (Overwrite $this->family and $this->module_position. Avoid this)
        $this->familyinfo = ['Eoxia' => ['position' => '01', 'label' => 'Eoxia']];
        // Module label (no space allowed), used if translation string 'ModuleDoliCalName' not found (DoliCal is name of module)
        $this->name = preg_replace('/^mod/i', '', get_class($this));

        // DESCRIPTION_FLAG
        // Module description, used if translation string 'ModuleDoliCalDesc' not found (DoliCal is name of module)
        $this->description = $langs->transnoentities('DoliCalDescription');
        // Used only if file README.md and README-LL.md not found
        $this->descriptionlong = $langs->transnoentities('DoliCalDescription');

        // Author
        $this->editor_name = 'Eoxia';
        $this->editor_url  = 'https://www.eoxia.com';

        // Possible values for version are: 'development', 'experimental', 'dolibarr', 'dolibarr_deprecated' or a version string like 'x.y.z'
        $this->version = '1.1.0';

        // Key used in llx_const table to save module status enabled/disabled (where DOLICAL is value of property name of module in uppercase)
        $this->const_name = 'MAIN_MODULE_' . strtoupper($this->name);

        // Name of image file used for this module
        // If file is in module/img directory under name object_pictovalue.png, use this->picto='pictovalue@module'
        $this->picto = 'dolical_color@dolical';

        // Define some features supported by module (triggers, login, substitutions, menus, css, etc...)
        $this->module_parts = [
            // Set this to 1 if module has its own trigger directory (core/triggers)
            'triggers' => 0,
            // Set this to 1 if module has its own login method file (core/login)
            'login' => 0,
            // Set this to 1 if module has its own substitution function file (core/substitutions)
            'substitutions' => 0,
            // Set this to 1 if module has its own menus handler directory (core/menus)
            'menus' => 0,
            // Set this to 1 if module overwrite template dir (core/tpl)
            'tpl' => 0,
            // Set this to 1 if module has its own barcode directory (core/modules/barcode)
            'barcode' => 0,
            // Set this to 1 if module has its own models' directory (core/modules/xxx)
            'models' => 0,
            // Set this to 1 if module has its own printing directory (core/modules/printing)
            'printing' => 0,
            // Set this to 1 if module has its own theme directory (theme)
            'theme' => 0,
            // Set this to relative path of css file if module has its own css file : the colors of the icon of the top menu
            'css' => ['/dolical/css/dolical_menu.css'],
            // Set this to relative path of js file if module must load a js on all pages
            'js' => [],
            // Set here all hooks context managed by module : the cards that get the button "Plan in DoliCal"
            'hooks' => [
                'propalcard',
                'ordercard',
                'invoicecard',
                'contractcard',
                'interventioncard',
                'ticketcard',
                'expeditioncard',
                'supplier_proposalcard',
                'ordersuppliercard',
                'invoicesuppliercard',
                'thirdpartycard',
                'thirdpartycomm',
                'projectcard',
                'contactcard',
                // The setup of the email templates lists the types of the module
                'emailtemplates'
            ],
            // Set this to 1 if features of module are opened to external users
            'moduleforexternal' => 0,
            // Set this to 1 if the module provides a website template into doctemplates/websites/website_template-mytemplate
            'websitetemplates' => 0,
            // Set this to 1 if the module provides a captcha driver
            'captcha' => 0
        ];

        // Data directories to create when module is enabled
        $this->dirs = ['/dolical/temp'];

        // Config pages. Put here list of php page, stored into dolical/admin directory, to use to set up module
        $this->config_page_url = ['setup.php@dolical'];

        // Dependencies
        // A condition to hide module
        $this->hidden = getDolGlobalInt('MODULE_DOLICAL_DISABLED');
        // The agenda is made of native events only : the Agenda module owns them
        $this->depends      = ['modAgenda', 'modSociete'];
        $this->requiredby   = [];
        $this->conflictwith = [];

        // The language file dedicated to your module
        $this->langfiles = ['dolical@dolical'];

        // Prerequisites
        $this->phpmin                = [7, 4];
        $this->need_dolibarr_version = [23, 0];
        $this->max_dolibarr_version  = [24, 0];
        $this->need_javascript_ajax  = 1;

        // Messages at activation
        $this->warnings_activation     = [];
        $this->warnings_activation_ext = [];

        // Constants
        // List of particular constants to add when module is enabled (key, 'chaine', value, desc, visible, 'current' or 'allentities', deleteonunactive)
        $i           = 0;
        $this->const = [
            // CONST CALENDAR
            $i++ => ['DOLICAL_DEFAULT_VIEW', 'chaine', 'week', '', 0, 'current'],
            $i++ => ['DOLICAL_DAY_START_HOUR', 'integer', 8, '', 0, 'current'],
            $i++ => ['DOLICAL_DAY_END_HOUR', 'integer', 19, '', 0, 'current'],
            $i++ => ['DOLICAL_SHOW_WEEKENDS', 'integer', 1, '', 0, 'current'],
            $i++ => ['DOLICAL_HIDE_AUTO_EVENTS', 'integer', 1, '', 0, 'current'],
            $i++ => ['DOLICAL_DEFAULT_DURATION', 'integer', 60, '', 0, 'current'],

            // CONST INVITATION
            $i++ => ['DOLICAL_NOTIFY_ORGANIZER', 'integer', 1, '', 0, 'current'],
            $i++ => ['DOLICAL_TIMEZONE', 'chaine', 'Europe/Paris', '', 0, 'current'],
            // Off until the administrator chooses it : the reminders go to people outside the company
            $i++ => ['DOLICAL_AUTO_REMIND', 'integer', 0, '', 0, 'current'],
            $i++ => ['DOLICAL_AUTO_REMIND_DAYS', 'integer', 2, '', 0, 'current'],

            // CONST REMINDER, REPORT AND SYNCHRONIZATION
            $i++ => ['DOLICAL_REMINDER_BY_DEFAULT', 'integer', 1, '', 0, 'current'],
            $i++ => ['DOLICAL_CALDAV', 'integer', 1, '', 0, 'current'],

            // CONST MODULE
            $i++ => ['DOLICAL_VERSION', 'chaine', $this->version, '', 0, 'current'],
            $i   => ['DOLICAL_DB_VERSION', 'chaine', $this->version, '', 0, 'current']
        ];

        if (!isModEnabled('dolical')) {
            $conf->dolical          = new stdClass();
            $conf->dolical->enabled = 0;
        }

        // Array to add new pages in new tabs
        $this->tabs = [];

        // Dictionaries
        $this->dictionaries = [];

        // Boxes/Widgets
        $this->boxes = [];

        // Cronjobs
        $this->cronjobs = [
            0 => [
                'label'         => $langs->transnoentities('DoliCalCronRemind'),
                'jobtype'       => 'method',
                'class'         => '/dolical/class/dolicalcron.class.php',
                'objectname'    => 'DoliCalCron',
                'method'        => 'remindPendingInvitations',
                'parameters'    => '',
                'comment'       => $langs->transnoentities('DoliCalCronRemindComment'),
                'frequency'     => 1,
                'unitfrequency' => 86400,
                'status'        => 1,
                'test'          => 'isModEnabled(\'dolical\')',
                'priority'      => 50
            ],
        ];

        // Permissions provided by this module
        $this->rights = [];
        $r            = 0;

        /* DOLICAL PERMISSIONS */
        $this->rights[$r][0] = $this->numero . sprintf('%02d', $r + 1);
        $this->rights[$r][1] = $langs->transnoentities('ReadModule', $this->name);
        $this->rights[$r][4] = 'read';
        $r++;

        /* ADMINPAGE PANEL ACCESS PERMISSIONS */
        $this->rights[$r][0] = $this->numero . sprintf('%02d', $r + 1);
        $this->rights[$r][1] = $langs->transnoentities('ReadAdminPage', $this->name);
        $this->rights[$r][4] = 'adminpage';
        $this->rights[$r][5] = 'read';

        // Main menu entries to add
        // The left menu is a flat list sorted on 'position'
        $this->menu = [];
        $r          = 0;

        $this->menu[$r++] = [
            'fk_menu'  => 'fk_mainmenu=dolical',
            'type'     => 'top',
            'titre'    => 'DoliCal',
            'prefix'   => '<i class="far fa-calendar-alt pictofixedwidth"></i>',
            'mainmenu' => 'dolical',
            'leftmenu' => '',
            'url'      => '/dolical/dolicalindex.php',
            'langs'    => 'dolical@dolical',
            'position' => 1000 + $r,
            'enabled'  => 'isModEnabled(\'dolical\')',
            'perms'    => '$user->hasRight(\'dolical\', \'read\') && $user->hasRight(\'agenda\', \'myactions\', \'read\')',
            'target'   => '',
            'user'     => 0,
        ];

        $this->menu[$r++] = [
            'fk_menu'  => 'fk_mainmenu=dolical',
            'type'     => 'left',
            'titre'    => 'DoliCalCalendar',
            'prefix'   => '<i class="far fa-calendar-alt pictofixedwidth"></i>',
            'mainmenu' => 'dolical',
            'leftmenu' => 'dolical_calendar',
            'url'      => '/dolical/dolicalindex.php',
            'langs'    => 'dolical@dolical',
            'position' => 1000 + $r,
            'enabled'  => 'isModEnabled(\'dolical\')',
            'perms'    => '$user->hasRight(\'dolical\', \'read\') && $user->hasRight(\'agenda\', \'myactions\', \'read\')',
            'target'   => '',
            'user'     => 0,
        ];

        $this->menu[$r++] = [
            'fk_menu'  => 'fk_mainmenu=dolical',
            'type'     => 'left',
            'titre'    => 'DoliCalNewEvent',
            'prefix'   => '<i class="fas fa-plus-circle pictofixedwidth"></i>',
            'mainmenu' => 'dolical',
            'leftmenu' => 'dolical_newevent',
            'url'      => '/dolical/dolicalindex.php?action=create',
            'langs'    => 'dolical@dolical',
            'position' => 1000 + $r,
            'enabled'  => 'isModEnabled(\'dolical\')',
            'perms'    => '$user->hasRight(\'dolical\', \'read\') && ($user->hasRight(\'agenda\', \'myactions\', \'create\') || $user->hasRight(\'agenda\', \'allactions\', \'create\'))',
            'target'   => '',
            'user'     => 0,
        ];

        $this->menu[$r++] = [
            'fk_menu'  => 'fk_mainmenu=dolical',
            'type'     => 'left',
            'titre'    => 'DoliCalNativeList',
            'prefix'   => '<i class="fas fa-list pictofixedwidth"></i>',
            'mainmenu' => 'dolical',
            'leftmenu' => 'dolical_nativelist',
            'url'      => '/comm/action/list.php?mainmenu=agenda',
            'langs'    => 'dolical@dolical',
            'position' => 1000 + $r,
            'enabled'  => 'isModEnabled(\'dolical\')',
            'perms'    => '$user->hasRight(\'dolical\', \'read\') && $user->hasRight(\'agenda\', \'myactions\', \'read\')',
            'target'   => '',
            'user'     => 0,
        ];

        $this->menu[$r++] = [
            'fk_menu'  => 'fk_mainmenu=dolical',
            'type'     => 'left',
            'titre'    => 'DoliCalSyncPhone',
            'prefix'   => '<i class="fas fa-mobile-alt pictofixedwidth"></i>',
            'mainmenu' => 'dolical',
            'leftmenu' => 'dolical_sync',
            'url'      => '/dolical/view/dolical_sync.php',
            'langs'    => 'dolical@dolical',
            'position' => 1000 + $r,
            'enabled'  => 'isModEnabled(\'dolical\') && getDolGlobalInt(\'DOLICAL_CALDAV\', 1)',
            'perms'    => '$user->hasRight(\'dolical\', \'read\') && $user->hasRight(\'agenda\', \'myactions\', \'read\')',
            'target'   => '',
            'user'     => 0,
        ];

        $this->menu[$r++] = [
            'fk_menu'  => 'fk_mainmenu=dolical',
            'type'     => 'left',
            'titre'    => 'DoliCalSetup',
            'prefix'   => '<i class="fas fa-cog pictofixedwidth"></i>',
            'mainmenu' => 'dolical',
            'leftmenu' => 'dolical_setup',
            'url'      => '/dolical/admin/setup.php',
            'langs'    => 'dolical@dolical',
            'position' => 1900 + $r,
            'enabled'  => 'isModEnabled(\'dolical\')',
            'perms'    => '$user->hasRight(\'dolical\', \'adminpage\', \'read\')',
            'target'   => '',
            'user'     => 0,
        ];

        // The native Agenda menu leads to the new calendar too : that is where the users look for it
        $this->menu[$r++] = [
            'fk_menu'  => 'fk_mainmenu=agenda',
            'type'     => 'left',
            'titre'    => 'DoliCalTeamCalendar',
            'prefix'   => '<i class="far fa-calendar-alt pictofixedwidth"></i>',
            'mainmenu' => 'agenda',
            'leftmenu' => 'dolical_agenda',
            'url'      => '/dolical/dolicalindex.php',
            'langs'    => 'dolical@dolical',
            'position' => 50 + $r,
            'enabled'  => 'isModEnabled(\'dolical\')',
            'perms'    => '$user->hasRight(\'dolical\', \'read\') && $user->hasRight(\'agenda\', \'myactions\', \'read\')',
            'target'   => '',
            'user'     => 0,
        ];
    }

    /**
     * Function called when module is enabled
     * The init function add constants, boxes, permissions and menus (defined in constructor) into Dolibarr database
     * It also creates data directories
     *
     * @param  string $options Options when enabling module ('', 'noboxes')
     * @return int             1 if OK, 0 if KO
     * @throws Exception
     */
    public function init($options = ''): int
    {
        global $conf, $langs;

        dolibarr_set_const($this->db, 'DOLICAL_VERSION', $this->version, 'chaine', 0, '', $conf->entity);
        dolibarr_set_const($this->db, 'DOLICAL_DB_VERSION', $this->version, 'chaine', 0, '', $conf->entity);

        // The report of a meeting is a field of the native event : the agenda of Dolibarr shows it too. Already there
        // when the module is enabled again, the field is kept with its content
        require_once DOL_DOCUMENT_ROOT . '/core/class/extrafields.class.php';
        $extrafields = new ExtraFields($this->db);
        $result      = $extrafields->addExtraField('dolical_report', 'DoliCalReport', 'text', (int) ($this->numero . '10'), '2000', 'actioncomm', 0, 0, '', '', 1, '', '1', '', '', '0', 'dolical@dolical', "isModEnabled('dolical')");
        if ($result < 0) {
            $this->error = $extrafields->error;
            return 0;
        }

        // Permissions
        $this->remove($options);

        return $this->_init([], $options);
    }

    /**
     * Function called when module is disabled
     * Remove from database constants, boxes and permissions from Dolibarr database
     * Data directories are not deleted
     *
     * @param  string $options Options when enabling module ('', 'noboxes')
     * @return int             1 if OK, 0 if KO
     */
    public function remove($options = ''): int
    {
        return $this->_remove([], $options);
    }
}
