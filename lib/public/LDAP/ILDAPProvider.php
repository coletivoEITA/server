<?php
/**
 *
 * @copyright Copyright (c) 2016, Roger Szabo (roger.szabo@web.de)
 *
 * @author Roger Szabo <roger.szabo@web.de>
 * @author Vinicius Brand <vinicius@eita.org.br>
 * @author Daniel Tygel <dtygel@eita.org.br>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OCP\LDAP;

/**
 * Interface ILDAPProvider
 *
 * @package OCP\LDAP
 * @since 11.0.0
 */
interface ILDAPProvider {
	/**
	 * Translate a user id to LDAP DN.
	 * @param string $uid user id
	 * @return string
	 * @since 11.0.0
	 */
	public function getUserDN($uid);

	/**
	 * Translate a group id to LDAP DN.
	 * @param string $gid group id
	 * @return string
	 * @since 13.0.0
	 */
	public function getGroupDN($gid);

	/**
	 * Translate a LDAP DN to an internal user name.
	 * @param string $dn LDAP DN
	 * @return string with the internal user name
	 * @throws \Exception if translation was unsuccessful
	 * @since 11.0.0
	 */
	public function getUserName($dn);
	
	/**
	 * Convert a stored DN so it can be used as base parameter for LDAP queries.
	 * @param string $dn the DN
	 * @return string
	 * @since 11.0.0
	 */
	public function DNasBaseParameter($dn);
	
	/**
	 * Sanitize a DN received from the LDAP server.
	 * @param array $dn the DN in question
	 * @return array the sanitized DN
	 * @since 11.0.0
	 */
	public function sanitizeDN($dn);
	
	/**
	 * Return a new LDAP connection resource for the specified user. 
	 * @param string $uid user id
	 * @return resource of the LDAP connection
	 * @since 11.0.0
	 */
	public function getLDAPConnection($uid);
	
	/**
	 * Get the LDAP base for users.
	 * @param string $uid user id
	 * @return string the base for users
	 * @throws \Exception if user id was not found in LDAP
	 * @since 11.0.0
	 */
	public function getLDAPBaseUsers($uid);
	
	/**
	 * Get the LDAP base for groups.
	 * @param string $uid user id
	 * @return string the base for groups
	 * @throws \Exception if user id was not found in LDAP
	 * @since 11.0.0
	 */
	public function getLDAPBaseGroups($uid);
	
	/**
	 * Check whether a LDAP DN exists
	 * @param string $dn LDAP DN
	 * @return bool whether the DN exists
	 * @since 11.0.0
	 */
	public function dnExists($dn);
	
	/**
	 * Clear the cache if a cache is used, otherwise do nothing.
	 * @param string $uid user id
	 * @since 11.0.0
	 */
	public function clearCache($uid);

	/**
	 * Get the LDAP attribute name for the user's display name
	 * @param string $uid user id
	 * @return string the display name field
	 * @throws \Exception if user id was not found in LDAP
	 * @since 12.0.0
	 */
	public function getLDAPDisplayNameField($uid);

	/**
	 * Get the LDAP attribute name for the email
	 * @param string $uid user id
	 * @return string the email field
	 * @throws \Exception if user id was not found in LDAP
	 * @since 12.0.0
	 */
	public function getLDAPEmailField($uid);

}

