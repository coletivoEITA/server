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

use OC\Group\Backend;

class GroupPluginManager {

	private $respondToActions = 0;

	private $which = array(
		Backend::CREATE_GROUP => null,
		Backend::DELETE_GROUP => null,
		Backend::ADD_TO_GROUP => null,
		Backend::REMOVE_FROM_GROUP => null,
		Backend::COUNT_USERS => null,
		Backend::GROUP_DETAILS => null
	);

	public function getImplementedActions() {
		return $this->respondToActions;
	}

	public function register(ILDAPGroupPlugin $plugin) {
		$respondToActions = $plugin->respondToActions();
		$this->respondToActions |= $respondToActions;

		foreach($this->which as $action => $v) {
			if ((bool)($respondToActions & $action)) {
				$this->which[$action] = $plugin;
				\OC::$server->getLogger()->debug("Registered action ".$action." to plugin ".get_class($plugin), ['app' => 'user_ldap']);
			}
		}
	}

	public function implementsActions($actions) {
		return (bool) ($actions & $this->respondToActions);
	}

	public function createGroup($gid) {
		$plugin = $this->which[Backend::CREATE_GROUP];

		if ($plugin) {
			return $plugin->createGroup($gid);
		}

		return false;
	}

	public function deleteGroup($gid) {
		$plugin = $this->which[Backend::DELETE_GROUP];

		if ($plugin) {
			return $plugin->deleteGroup($gid);
		}

		return false;
	}

	public function addToGroup($uid, $gid) {
		$plugin = $this->which[Backend::ADD_TO_GROUP];

		if ($plugin) {
			return $plugin->addToGroup($uid, $gid);
		}

		return false;
	}

	public function removeFromGroup($uid, $gid) {
		$plugin = $this->which[Backend::REMOVE_FROM_GROUP];

		if ($plugin) {
			return $plugin->removeFromGroup($uid, $gid);
		}

		return false;
	}

	public function countUsersInGroup($gid, $search = '') {
		$plugin = $this->which[Backend::COUNT_USERS];

		if ($plugin) {
			return $plugin->countUsersInGroup($gid,$search);
		}

		return false;
	}

	public function getGroupDetails($gid) {
		$plugin = $this->which[Backend::GROUP_DETAILS];

		if ($plugin) {
			return $plugin->getGroupDetails($gid);
		}

		return false;
	}
}
