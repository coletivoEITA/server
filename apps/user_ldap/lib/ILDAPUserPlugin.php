<?php
/**
 * @copyright Copyright (c) 2017 EITA Cooperative (eita.org.br)
 *
 * @author Vinicius Brand <vinicius@eita.org.br>
 * @author Daniel Tygel <dtygel@eita.org.br>
 *
 * @license AGPL-3.0
 *
 * This code is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License, version 3,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License, version 3,
 * along with this program.  If not, see <http://www.gnu.org/licenses/>
 *
 */

namespace OCA\User_LDAP;


interface ILDAPUserPlugin {

	/**
	 * Check if plugin implements actions
	 * @param int $actions bitwise-or'ed actions
	 * @return boolean
	 *
	 * Returns the supported actions as int to be
	 * compared with OC_USER_BACKEND_CREATE_USER etc.
	 */
	public function respondToActions();

	/**
	 * Create a new user in LDAP Backend
	 *
	 * @param string $uid The username of the user to create
	 * @param string $password The password of the new user
	 * @return bool
	 *
	 */
	public function createUser($username, $password);

	/**
	 * Set password
	 *
	 * @param string $uid The username
	 * @param string $password The new password
	 * @return bool
	 *
	 * Change the password of a user
	 */
	public function setPassword($uid, $password);

	/**
	 * get the user's home directory
	 * @param string $uid the username
	 * @return boolean
	 */
	public function getHome($uid);

	/**
	 * get display name of the user
	 * @param string $uid user ID of the user
	 * @return string display name
	 */
	public function getDisplayName($uid);

	/**
	 * set display name of the user
	 * @param string $uid user ID of the user
	 * @param string $displayName new user's display name
	 * @return string display name
	 */
	public function setDisplayName($uid, $displayName);

	/**
	 * checks whether the user is allowed to change his avatar in Nextcloud
	 * @param string $uid the Nextcloud user name
	 * @return boolean either the user can or cannot
	 */
	public function canChangeAvatar($uid);

	/**
	 * Count the number of users
	 * @return int|bool
	 */
	public function countUsers();

}
