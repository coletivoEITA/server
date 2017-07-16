<?php
/**
 * Created by PhpStorm.
 * User: vinicius
 * Date: 09/06/17
 * Time: 12:10
 */

namespace OCA\User_LDAP\Tests;


use OCA\User_LDAP\ILDAPUserPlugin;

class LDAPUserPluginDummy implements ILDAPUserPlugin {

	public function respondToActions() {
		return null;
	}

	public function createUser($username, $password) {
		return null;
	}

	public function setPassword($uid, $password) {
		return null;
	}

	public function checkPassword($uid, $password) {
		return null;
	}

	public function getHome($uid) {
		return null;
	}

	public function getDisplayName($uid) {
		return null;
	}

	public function setDisplayName($uid, $displayName) {
		return null;
	}

	public function canChangeAvatar($uid) {
		return null;
	}

	public function countUsers() {
		return null;
	}
}