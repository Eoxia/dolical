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
 * \file    class/dolicalprincipalbackend.class.php
 * \ingroup dolical
 * \brief   The person synchronizing, the only one the CalDAV server of SabreDAV knows : his email tells the phone which
 *          of the participants he is.
 */

/**
 * Class DoliCalPrincipalBackend
 */
class DoliCalPrincipalBackend extends \Sabre\DAVACL\PrincipalBackend\AbstractBackend
{
    /**
     * @var User Person synchronizing.
     */
    private $user;

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
     * The person, as a principal
     *
     * @return array
     */
    private function principal(): array
    {
        global $langs;

        $principal = [
            'uri'               => 'principals/' . $this->user->login,
            '{DAV:}displayname' => $this->user->getFullName($langs),
        ];
        if (!empty($this->user->email)) {
            $principal['{http://sabredav.org/ns}email-address'] = $this->user->email;
        }

        return $principal;
    }

    /**
     * Principals under a prefix : the person only
     *
     * @param  string $prefixPath Prefix
     * @return array
     */
    public function getPrincipalsByPrefix($prefixPath)
    {
        return $prefixPath === 'principals' ? [$this->principal()] : [];
    }

    /**
     * Principal of a path, when it is the person
     *
     * @param  string     $path Path
     * @return array|null
     */
    public function getPrincipalByPath($path)
    {
        return $path === 'principals/' . $this->user->login ? $this->principal() : null;
    }

    /**
     * The person is changed in Dolibarr, not from a phone
     *
     * @param  string                $path      Path
     * @param  \Sabre\DAV\PropPatch $propPatch Changes
     * @return void
     */
    public function updatePrincipal($path, \Sabre\DAV\PropPatch $propPatch)
    {
    }

    /**
     * Search of principals : the person, when his email is searched
     *
     * @param  string $prefixPath       Prefix
     * @param  array  $searchProperties Properties searched
     * @param  string $test             'allof' or 'anyof'
     * @return array
     */
    public function searchPrincipals($prefixPath, array $searchProperties, $test = 'allof')
    {
        $email = $searchProperties['{http://sabredav.org/ns}email-address'] ?? '';
        if ($prefixPath === 'principals' && $email !== '' && dol_strtolower($email) === dol_strtolower((string) $this->user->email)) {
            return ['principals/' . $this->user->login];
        }

        return [];
    }

    /**
     * No groups
     *
     * @param  string $principal Principal
     * @return array
     */
    public function getGroupMemberSet($principal)
    {
        return [];
    }

    /**
     * No groups
     *
     * @param  string $principal Principal
     * @return array
     */
    public function getGroupMembership($principal)
    {
        return [];
    }

    /**
     * No groups
     *
     * @param  string $principal Principal
     * @param  array  $members   Members
     * @return void
     * @throws \Sabre\DAV\Exception\Forbidden
     */
    public function setGroupMemberSet($principal, array $members)
    {
        throw new \Sabre\DAV\Exception\Forbidden('No groups');
    }
}
