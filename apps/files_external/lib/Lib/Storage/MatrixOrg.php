<?php

/**
 * Matrix.org external storage backend
 *
 * @author Vinicius Cubas Brand <vinicius@eita.org.br>
 * @copyright 2016 Cooperativa EITA (eita.org.br)
 *
 * This library is free software; you can redistribute it and/or
 * modify it under the terms of the GNU AFFERO GENERAL PUBLIC LICENSE
 * License as published by the Free Software Foundation; either
 * version 3 of the License, or any later version.
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU AFFERO GENERAL PUBLIC LICENSE for more details.
 *
 * You should have received a copy of the GNU Affero General Public
 * License along with this library.  If not, see <http://www.gnu.org/licenses/>.
 */

namespace OCA\Files_External\Lib\Storage;


class MatrixOrg extends \OC\Files\Storage\Common {

	private $params;
	private $user;
	private $auth;
	private $home_server;


	public function __construct($params) {
		if (!isset($params['user'])) {
			throw new \UnexpectedValueException('no authentication parameters specified');
		}
		$this->user = $params['user'];

		if (isset($params['public_key_auth'])) {
			$this->auth = $params['public_key_auth'];
		} elseif (isset($params['password'])) {
			$this->auth = $params['password'];
		} else {
			throw new \UnexpectedValueException('no authentication parameters specified');
		}


		if (!empty($params['home_server']))
		{
			$this->id = 'matrixorg:' . $this->user . '@' . $params['home_server'] ;
			$this->params = $params;

		} else {
			throw new \Exception('cadernodecampo: Creating \OC\Files\Storage\MatrixOrg storage failed');
		}

		$this->home_server = $params['home_server'];




	}

	/**
	 * Get the identifier for the storage,
	 * the returned id should be the same for every storage object that is created with the same parameters
	 * and two storage objects with the same id should refer to two storages that display the same files.
	 *
	 * @return string
	 * @since 6.0.0
	 */
	public function getId() {
		return $this->id;
	}

	/**
	 * see http://php.net/manual/en/function.mkdir.php
	 * implementations need to implement a recursive mkdir
	 *
	 * @param string $path
	 * @return bool
	 * @since 6.0.0
	 */
	public function mkdir($path) {
		// TODO: Implement mkdir() method.
	}

	/**
	 * see http://php.net/manual/en/function.rmdir.php
	 *
	 * @param string $path
	 * @return bool
	 * @since 6.0.0
	 */
	public function rmdir($path) {
		// TODO: Implement rmdir() method.
	}

	/**
	 * see http://php.net/manual/en/function.opendir.php
	 *
	 * @param string $path
	 * @return resource|false
	 * @since 6.0.0
	 */
	public function opendir($path) {
		// TODO: Implement opendir() method.
	}

	/**
	 * see http://php.net/manual/en/function.stat.php
	 * only the following keys are required in the result: size and mtime
	 *
	 * @param string $path
	 * @return array|false
	 * @since 6.0.0
	 */
	public function stat($path) {
		// TODO: Implement stat() method.
	}

	/**
	 * see http://php.net/manual/en/function.filetype.php
	 *
	 * @param string $path
	 * @return string|false
	 * @since 6.0.0
	 */
	public function filetype($path) {
		// TODO: Implement filetype() method.
	}

	/**
	 * see http://php.net/manual/en/function.file_exists.php
	 *
	 * @param string $path
	 * @return bool
	 * @since 6.0.0
	 */
	public function file_exists($path) {
		// TODO: Implement file_exists() method.
	}

	/**
	 * see http://php.net/manual/en/function.unlink.php
	 *
	 * @param string $path
	 * @return bool
	 * @since 6.0.0
	 */
	public function unlink($path) {
		// TODO: Implement unlink() method.
	}

	/**
	 * see http://php.net/manual/en/function.fopen.php
	 *
	 * @param string $path
	 * @param string $mode
	 * @return resource|false
	 * @since 6.0.0
	 */
	public function fopen($path, $mode) {
		// TODO: Implement fopen() method.
	}

	/**
	 * see http://php.net/manual/en/function.touch.php
	 * If the backend does not support the operation, false should be returned
	 *
	 * @param string $path
	 * @param int $mtime
	 * @return bool
	 * @since 6.0.0
	 */
	public function touch($path, $mtime = null) {
		// TODO: Implement touch() method.
	}

	/**
	 * Test a storage for availability
	 *
	 * @return bool
	 */
	public function test() {
		/*
		#$url = $this->home_server.'/_matrix/client/r0/login';
		$url='http://localhost/index.php';
		$data = array('type' => 'm.login.password', 'user' => $this->user, 'password' => $this->auth);

		// use key 'http' even if you send the request to https://...
		$options = array(
			'http' => array(
				'header'  => "Content-type: application/json",
				'method'  => 'POST',
				'content' => http_build_query($data)
			)
		);
		$context  = stream_context_create($options);
		$result = file_get_contents($url, false, $context);

		\OCP\Util::writeLog(
			'files_external',
			'RESULT: '.print_r($result,true),
			\OCP\Util::ERROR
		);

		if ($result === FALSE) { return false; }

		return true;
		*/

		$url = $this->home_server.'/_matrix/client/r0/login';
		$fields = array(
			'type' => 'm.login.password',
			'user' => $this->user,
			'password' => $this->auth
		);

		//url-ify the data for the POST
		$fields_string = http_build_query($fields);

		//open connection
		$ch = curl_init();

		//set the url, number of POST vars, POST data
		curl_setopt($ch,CURLOPT_URL,$url);
		curl_setopt($ch,CURLOPT_POST,count($fields));
		curl_setopt($ch,CURLOPT_POSTFIELDS,json_encode($fields));
		curl_setopt($ch,CURLOPT_RETURNTRANSFER,true);
		curl_setopt($ch,CURLOPT_HEADER,'Content-Type: application/json');

		//execute post
		$result = curl_exec($ch);

		\OCP\Util::writeLog(
			'files_external',
			'RESULT: '.$result,
			\OCP\Util::ERROR
		);

		if ($result === FALSE) { return false; }

		return true;

	}
}
