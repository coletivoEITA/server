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

use OC\User\Backend;

class UserPluginManager {

	public $test = false;

	private $respondToActions = 0;

	private $which = array(
		Backend::CREATE_USER => null,
		Backend::SET_PASSWORD => null,
		Backend::GET_HOME => null,
		Backend::GET_DISPLAYNAME => null,
		Backend::SET_DISPLAYNAME => null,
		Backend::PROVIDE_AVATAR => null,
		Backend::COUNT_USERS => null,
		'deleteUser' => null
	);

	public function getImplementedActions() {
		return $this->respondToActions;
	}

	public function register(ILDAPUserPlugin $plugin) {
		$respondToActions = $plugin->respondToActions();
		$this->respondToActions |= $respondToActions;

		foreach($this->which as $action => $v) {
			if ((bool)($respondToActions & $action)) {
				$this->which[$action] = $plugin;
				\OC::$server->getLogger()->debug("Registered action ".$action." to plugin ".get_class($plugin), ['app' => 'user_ldap']);
			}
		}
		if (method_exists($plugin,'deleteUser')) {
			$this->which['deleteUser'] = $plugin;
		}
	}

	public function implementsActions($actions) {
		return (bool) ($actions & $this->respondToActions);
	}

	public function createUser($username, $password) {
		$plugin = $this->which[Backend::CREATE_USER];

		if ($plugin) {
			return $plugin->createUser($username,$password);
		}
		throw new \Exception('No plugin implements createUser in this LDAP Backend.');
	}

	public function setPassword($uid, $password) {
		$plugin = $this->which[Backend::SET_PASSWORD];

		if ($plugin) {
			return $plugin->setPassword($uid,$password);
		}
		throw new \Exception('No plugin implements setPassword in this LDAP Backend.');
	}

	public function canChangeAvatar($uid) {
		$plugin = $this->which[Backend::PROVIDE_AVATAR];

		if ($plugin) {
			return $plugin->canChangeAvatar($uid);
		}
		throw new \Exception('No plugin implements canChangeAvatar in this LDAP Backend.');
	}

	public function getHome($uid) {
		$plugin = $this->which[Backend::GET_HOME];

		if ($plugin) {
			return $plugin->getHome($uid);
		}
		throw new \Exception('No plugin implements getHome in this LDAP Backend.');
	}

	public function getDisplayName($uid) {
		$plugin = $this->which[Backend::GET_DISPLAYNAME];

		if ($plugin) {
			return $plugin->getDisplayName($uid);
		}
		throw new \Exception('No plugin implements getDisplayName in this LDAP Backend.');
	}

	public function setDisplayName($uid, $displayName) {
		$plugin = $this->which[Backend::SET_DISPLAYNAME];

		if ($plugin) {
			return $plugin->setDisplayName($uid, $displayName);
		}
		throw new \Exception('No plugin implements setDisplayName in this LDAP Backend.');
	}

	public function countUsers() {
		$plugin = $this->which[Backend::COUNT_USERS];

		if ($plugin) {
			return $plugin->countUsers();
		}
		throw new \Exception('No plugin implements countUsers in this LDAP Backend.');
	}

	/**
	 * @return bool
	 */
	public function canDeleteUser() {
		return $this->which['deleteUser'] !== null;
	}

	/**
	 * @param $uid
	 * @return bool
	 */
	public function deleteUser($uid) {
		$plugin = $this->which['deleteUser'];
		if ($plugin) {
			return $plugin->deleteUser($uid);
		}
		throw new \Exception('No plugin implements deleteUser in this LDAP Backend.');
	}
}

