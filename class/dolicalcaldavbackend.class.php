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
 * \file    class/dolicalcaldavbackend.class.php
 * \ingroup dolical
 * \brief   Agendas of DoliCal for the CalDAV server of SabreDAV : the agenda of the person, written, and the ones of
 *          the colleagues he chose, read only. Each agenda has its list of reminders beside it (VTODO), with the same
 *          rights. Every object is a native event. The id of an agenda is the id of its person, the one of a list of
 *          reminders its opposite.
 */

require_once __DIR__ . '/../lib/dolical_caldav.lib.php';

/**
 * Class DoliCalCalDavBackend
 */
class DoliCalCalDavBackend extends \Sabre\CalDAV\Backend\AbstractBackend
{
    /**
     * @var User Person synchronizing.
     */
    private $user;

    /**
     * @var array Objects of each agenda, built once per request : the tag of the agenda, its list and its objects
     *            all read the same build.
     */
    private $cache = [];

    /**
     * Constructor
     *
     * @param User $user Person synchronizing, authenticated
     */
    public function __construct(User $user)
    {
        $this->user = $user;
    }

    /**
     * Agendas of a person
     *
     * @param  string $principalUri Principal, principals/<login>
     * @return array
     */
    public function getCalendarsForUser($principalUri)
    {
        global $db, $langs;

        if ($principalUri !== 'principals/' . $this->user->login) {
            return [];
        }

        $calendars = [];
        $people    = dolical_caldav_calendar_users($this->user);
        foreach ($people as $order => $userId) {
            $owner = new User($db);
            if ($owner->fetch($userId) <= 0) {
                continue;
            }
            $mine        = $userId === (int) $this->user->id;
            $color       = strtoupper(dolical_user_color($userId, (string) $owner->color)) . 'FF';
            // The reminders of the person, in their own list : the reminders application of the phone finds it and
            // makes none of its own, refused here. The ones of the colleagues only when asked for
            if (dolical_caldav_reminders_listed($this->user, $userId)) {
                $calendars[] = [
                    'id'                                                                 => -$userId,
                    'uri'                                                                => $mine ? 'reminders' : 'reminders-' . $userId,
                    'principaluri'                                                       => $principalUri,
                    '{DAV:}displayname'                                                  => $langs->transnoentities('DoliCalCalDavRemindersOf', $owner->getFullName($langs)),
                    '{http://apple.com/ns/ical/}calendar-color'                          => $color,
                    '{http://apple.com/ns/ical/}calendar-order'                          => count($people) + $order + 1,
                    '{' . \Sabre\CalDAV\Plugin::NS_CALENDARSERVER . '}getctag'           => $this->ctag(-$userId),
                    '{' . \Sabre\CalDAV\Plugin::NS_CALDAV . '}supported-calendar-component-set' => new \Sabre\CalDAV\Xml\Property\SupportedCalendarComponentSet(['VTODO']),
                    '{http://sabredav.org/ns}read-only'                                  => !$mine,
                ];
            }
            $calendars[] = [
                'id'                                                                 => $userId,
                'uri'                                                                => $mine ? 'agenda' : 'agenda-' . $userId,
                'principaluri'                                                       => $principalUri,
                '{DAV:}displayname'                                                  => $langs->transnoentities('DoliCalCalDavCalendarOf', $owner->getFullName($langs)),
                '{http://apple.com/ns/ical/}calendar-color'                          => $color,
                '{http://apple.com/ns/ical/}calendar-order'                          => $order + 1,
                '{' . \Sabre\CalDAV\Plugin::NS_CALENDARSERVER . '}getctag'           => $this->ctag($userId),
                '{' . \Sabre\CalDAV\Plugin::NS_CALDAV . '}supported-calendar-component-set' => new \Sabre\CalDAV\Xml\Property\SupportedCalendarComponentSet(['VEVENT']),
                '{' . \Sabre\CalDAV\Plugin::NS_CALDAV . '}calendar-timezone'         => null,
                '{http://sabredav.org/ns}read-only'                                  => !$mine,
            ];
        }

        return array_map(function ($calendar) {
            return array_filter($calendar, function ($value) {
                return $value !== null;
            });
        }, $calendars);
    }

    /**
     * Objects of an agenda or of a list of reminders, built once per request
     *
     * @param  int   $calendarId Person whose agenda it is, the opposite for his reminders
     * @return array             [uri => object]
     */
    private function objects($calendarId): array
    {
        $calendarId = (int) $calendarId;
        if (!in_array(abs($calendarId), dolical_caldav_calendar_users($this->user), true)) {
            return [];
        }
        // The list of reminders of a colleague, not asked for : nothing, even by its direct address
        if ($calendarId < 0 && !dolical_caldav_reminders_listed($this->user, -$calendarId)) {
            return [];
        }
        if (!isset($this->cache[$calendarId])) {
            $this->cache[$calendarId] = $calendarId < 0 ? dolical_caldav_todo_objects($this->user, -$calendarId) : dolical_caldav_objects($this->user, $calendarId);
        }

        return $this->cache[$calendarId];
    }

    /**
     * Tag of an agenda : changes as soon as one of its objects does
     *
     * @param  int    $calendarId Agenda
     * @return string
     */
    private function ctag($calendarId): string
    {
        return md5(implode(',', array_column($this->objects($calendarId), 'etag')));
    }

    /**
     * The agendas are the ones of the people : none is made from a phone
     *
     * @param  string $principalUri Principal
     * @param  string $calendarUri  Name of the agenda
     * @param  array  $properties   Properties
     * @return void
     * @throws \Sabre\DAV\Exception\Forbidden
     */
    public function createCalendar($principalUri, $calendarUri, array $properties)
    {
        throw new \Sabre\DAV\Exception\Forbidden('The agendas follow the people of Dolibarr');
    }

    /**
     * Name and color set on the phone : accepted, kept on the phone only
     *
     * @param  mixed                 $calendarId Agenda
     * @param  \Sabre\DAV\PropPatch $propPatch  Changes
     * @return void
     */
    public function updateCalendar($calendarId, \Sabre\DAV\PropPatch $propPatch)
    {
        $propPatch->handleRemaining(function () {
            return true;
        });
    }

    /**
     * The agendas are the ones of the people : none is deleted from a phone
     *
     * @param  mixed $calendarId Agenda
     * @return void
     * @throws \Sabre\DAV\Exception\Forbidden
     */
    public function deleteCalendar($calendarId)
    {
        throw new \Sabre\DAV\Exception\Forbidden('The agendas follow the people of Dolibarr');
    }

    /**
     * Objects of an agenda, their data included
     *
     * @param  mixed $calendarId Agenda
     * @return array
     */
    public function getCalendarObjects($calendarId)
    {
        return array_values($this->objects($calendarId));
    }

    /**
     * One object of an agenda
     *
     * @param  mixed  $calendarId Agenda
     * @param  string $objectUri  File name
     * @return array|null
     */
    public function getCalendarObject($calendarId, $objectUri)
    {
        return $this->objects($calendarId)[$objectUri] ?? null;
    }

    /**
     * Event of the agenda of the person behind a file name, loaded
     *
     * @param  mixed  $calendarId Agenda
     * @param  string $objectUri  File name
     * @return array              [ActionComm, served data]
     * @throws \Sabre\DAV\Exception\NotFound
     */
    private function load($calendarId, $objectUri): array
    {
        global $db;

        $object = $this->getCalendarObject($calendarId, $objectUri);
        $event  = new ActionComm($db);
        if ($object === null || $event->fetch((int) $object['event']) <= 0) {
            throw new \Sabre\DAV\Exception\NotFound('No such event');
        }

        return [$event, $object['calendardata']];
    }

    /**
     * Only the agenda of the person and his list of reminders take changes
     *
     * @param  mixed $calendarId Agenda, or list of reminders
     * @return void
     * @throws \Sabre\DAV\Exception\Forbidden
     */
    private function checkWritable($calendarId): void
    {
        if (abs((int) $calendarId) !== (int) $this->user->id) {
            throw new \Sabre\DAV\Exception\Forbidden('This agenda is read only');
        }
    }

    /**
     * Event created on the phone
     *
     * @param  mixed  $calendarId   Agenda
     * @param  string $objectUri    File name
     * @param  string $calendarData iCalendar object
     * @return null                 No tag : the phone reads the event back as Dolibarr stored it
     * @throws \Sabre\DAV\Exception
     */
    public function createCalendarObject($calendarId, $objectUri, $calendarData)
    {
        global $db;

        $this->checkWritable($calendarId);
        if (!dolical_can_create($this->user)) {
            throw new \Sabre\DAV\Exception\Forbidden('Not allowed to create events');
        }
        // dolical-<id>.ics names the events made in Dolibarr, and a "|" would cut the reference stored in ref_ext
        if (preg_match('/^dolical-\d+\.ics$/', (string) $objectUri) || preg_match('/[|\x00-\x1F\x7F]/', (string) $objectUri)) {
            throw new \Sabre\DAV\Exception\Forbidden('Reserved file name');
        }
        if ((int) $calendarId < 0) {
            // A reminder : an event of the type of the reminders in the list of the person
            $read           = dolical_caldav_read_todo($calendarData);
            $event          = new ActionComm($db);
            $event->ref_ext = dol_trunc('dav:' . $objectUri . '|' . $read['uid'], 255, 'right', 'UTF-8', 1);
            if (dolical_caldav_save_todo($event, $read, null, $this->user, $this->user) <= 0) {
                throw new \Sabre\DAV\Exception('The reminder could not be saved');
            }
            $this->cache = [];

            return null;
        }
        $read = dolical_caldav_read($calendarData, (string) $this->user->email);
        if (dolical_caldav_create($this->user, $objectUri, $read) <= 0) {
            throw new \Sabre\DAV\Exception('The event could not be saved');
        }
        $this->cache = [];

        return null;
    }

    /**
     * Event changed on the phone : moved, renamed, answered, its alarm changed
     *
     * @param  mixed  $calendarId   Agenda
     * @param  string $objectUri    File name
     * @param  string $calendarData iCalendar object
     * @return null                 No tag : the phone reads the event back as Dolibarr stored it
     * @throws \Sabre\DAV\Exception
     */
    public function updateCalendarObject($calendarId, $objectUri, $calendarData)
    {
        $this->checkWritable($calendarId);
        [$event, $served] = $this->load($calendarId, $objectUri);
        if ((int) $calendarId < 0) {
            // A reminder ticked, renamed, given a due date : only what the phone changed
            if (!dolical_can_edit_event($this->user, $event)) {
                throw new \Sabre\DAV\Exception\Forbidden('Not allowed to change this reminder');
            }
            if (dolical_caldav_save_todo($event, dolical_caldav_read_todo($calendarData), dolical_caldav_read_todo($served), $this->user, $this->user) <= 0) {
                throw new \Sabre\DAV\Exception('The reminder could not be saved');
            }
            $this->cache = [];

            return null;
        }
        $read             = dolical_caldav_read($calendarData, (string) $this->user->email);
        if (dolical_caldav_update($this->user, $event, $served, $read) <= 0) {
            throw new \Sabre\DAV\Exception('The event could not be saved');
        }
        $this->cache = [];

        return null;
    }

    /**
     * Event removed from the phone
     *
     * @param  mixed  $calendarId Agenda
     * @param  string $objectUri  File name
     * @return void
     * @throws \Sabre\DAV\Exception
     */
    public function deleteCalendarObject($calendarId, $objectUri)
    {
        $this->checkWritable($calendarId);
        [$event] = $this->load($calendarId, $objectUri);
        if (!dolical_caldav_delete($this->user, $event)) {
            throw new \Sabre\DAV\Exception('The event could not be deleted');
        }
        $this->cache = [];
    }
}
