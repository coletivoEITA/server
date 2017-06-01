<?php
/**
 * @copyright Copyright (c) 2017 EITA Cooperative (eita.org.br)
 *
 * @author Vinicius Brand <vinicius@eita.org.br>
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

namespace OCA\User_LDAP\Tests;


use OC\User\Backend;
use OCA\DAV\CalDAV\Plugin;
use OCA\User_LDAP\PluginManager;

class LDAPPluginTest extends \Test\TestCase {

	protected function setUp() {
		parent::setUp();
	}

	public function testCreateUser() {
		$plugin = $this->getMockBuilder('OCA\User_LDAP\Tests\LDAPPluginDummy')
			->setMethods(['respondToActions', 'createUser'])
			->getMock();

		$plugin->expects($this->any())
			->method('respondToActions')
			->willReturn(Backend::CREATE_USER);

		$plugin->expects($this->once())
			->method('createUser')
			->with(
				$this->equalTo('user'),
				$this->equalTo('password')
			);

		PluginManager::register($plugin);
		PluginManager::createUser('user', 'password');
	}

	public function testSetPassword() {
		$plugin = $this->getMockBuilder('OCA\User_LDAP\Tests\LDAPPluginDummy')
			->setMethods(['respondToActions', 'setPassword'])
			->getMock();

		$plugin->expects($this->any())
			->method('respondToActions')
			->willReturn(Backend::SET_PASSWORD);

		$plugin->expects($this->once())
			->method('setPassword')
			->with(
				$this->equalTo('user'),
				$this->equalTo('password')
			);

		PluginManager::register($plugin);
		PluginManager::setPassword('user', 'password');
	}

	public function testCheckPassword() {
		$plugin = $this->getMockBuilder('OCA\User_LDAP\Tests\LDAPPluginDummy')
			->setMethods(['respondToActions', 'checkPassword'])
			->getMock();

		$plugin->expects($this->any())
			->method('respondToActions')
			->willReturn(Backend::CHECK_PASSWORD);

		$plugin->expects($this->once())
			->method('checkPassword')
			->with(
				$this->equalTo('user'),
				$this->equalTo('password')
			);

		PluginManager::register($plugin);
		PluginManager::checkPassword('user', 'password');
	}

	public function testGetHome() {
		$plugin = $this->getMockBuilder('OCA\User_LDAP\Tests\LDAPPluginDummy')
			->setMethods(['respondToActions', 'getHome'])
			->getMock();

		$plugin->expects($this->any())
			->method('respondToActions')
			->willReturn(Backend::GET_HOME);

		$plugin->expects($this->once())
			->method('getHome')
			->with(
				$this->equalTo('uid')
			);

		PluginManager::register($plugin);
		PluginManager::getHome('uid');
	}

	public function testGetDisplayName() {
		$plugin = $this->getMockBuilder('OCA\User_LDAP\Tests\LDAPPluginDummy')
			->setMethods(['respondToActions', 'getDisplayName'])
			->getMock();

		$plugin->expects($this->any())
			->method('respondToActions')
			->willReturn(Backend::GET_DISPLAYNAME);

		$plugin->expects($this->once())
			->method('getDisplayName')
			->with(
				$this->equalTo('uid')
			);

		PluginManager::register($plugin);
		PluginManager::getDisplayName('uid');
	}

	public function testSetDisplayName() {
		$plugin = $this->getMockBuilder('OCA\User_LDAP\Tests\LDAPPluginDummy')
			->setMethods(['respondToActions', 'setDisplayName'])
			->getMock();

		$plugin->expects($this->any())
			->method('respondToActions')
			->willReturn(Backend::SET_DISPLAYNAME);

		$plugin->expects($this->once())
			->method('setDisplayName')
			->with(
				$this->equalTo('user'),
				$this->equalTo('password')
			);

		PluginManager::register($plugin);
		PluginManager::setDisplayName('user', 'password');		}

	public function testCanChangeAvatar() {
		$plugin = $this->getMockBuilder('OCA\User_LDAP\Tests\LDAPPluginDummy')
			->setMethods(['respondToActions', 'canChangeAvatar'])
			->getMock();

		$plugin->expects($this->any())
			->method('respondToActions')
			->willReturn(Backend::PROVIDE_AVATAR);

		$plugin->expects($this->once())
			->method('canChangeAvatar')
			->with(
				$this->equalTo('uid')
			);

		PluginManager::register($plugin);
		PluginManager::canChangeAvatar('uid');
	}

	public function testCountUsers() {
		$plugin = $this->getMockBuilder('OCA\User_LDAP\Tests\LDAPPluginDummy')
			->setMethods(['respondToActions', 'countUsers'])
			->getMock();

		$plugin->expects($this->any())
			->method('respondToActions')
			->willReturn(Backend::COUNT_USERS);

		$plugin->expects($this->once())
			->method('countUsers');

		PluginManager::register($plugin);
		PluginManager::countUsers();
	}

	public function testDeleteUser() {
		$plugin = $this->getMockBuilder('OCA\User_LDAP\Tests\LDAPPluginDummy')
			->setMethods(['respondToActions', 'canDeleteUser','deleteUser'])
			->getMock();

		$plugin->expects($this->any())
			->method('respondToActions')
			->willReturn(0);

		$plugin->expects($this->any())
			->method('canDeleteUser')
			->willReturn(true);

		$plugin->expects($this->once())
			->method('deleteUser')
			->with(
				$this->equalTo('uid')
			);

		PluginManager::register($plugin);
		PluginManager::deleteUser('uid');
	}

	protected function tearDown() {
		$res =  parent::tearDown();
		PluginManager::reset();
		return $res;
	}
}