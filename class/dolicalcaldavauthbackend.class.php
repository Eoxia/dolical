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
 * \file    class/dolicalcaldavauthbackend.class.php
 * \ingroup dolical
 * \brief   Login of the CalDAV server of DoliCal : the person is named after his login stored in Dolibarr, not after
 *          the one typed on the phone.
 */

/**
 * Class DoliCalCalDavAuthBackend
 */
class DoliCalCalDavAuthBackend extends \Sabre\DAV\Auth\Backend\BasicCallBack
{
    /**
     * @var User Person synchronizing, loaded by the callback once his password is checked.
     */
    private $user;

    /**
     * Constructor
     *
     * @param callable $callBack Check of the login and password, loads the person in $user
     * @param User     $user     Person synchronizing, the one the agendas are served to
     */
    public function __construct(callable $callBack, User $user)
    {
        parent::__construct($callBack);
        $this->user = $user;
    }

    /**
     * Login and password of the request. SabreDAV names the principal after the login sent, but the database finds a
     * login whatever its case and the spaces after it, and a phone keyboard often adds a capital or a space :
     * "Oceane" would be let in under principals/Oceane while her agendas are under principals/oceane, a 404 on each
     * of them. The principal takes the login stored in Dolibarr.
     *
     * @param  \Sabre\HTTP\RequestInterface  $request  Request
     * @param  \Sabre\HTTP\ResponseInterface $response Response
     * @return array                                   [true, principal] or [false, reason]
     */
    public function check(\Sabre\HTTP\RequestInterface $request, \Sabre\HTTP\ResponseInterface $response)
    {
        $result = parent::check($request, $response);
        if ($result[0] && (string) $this->user->login !== '') {
            return [true, $this->principalPrefix . $this->user->login];
        }

        return $result;
    }
}
