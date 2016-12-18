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

use Icewind\Streams\IteratorDirectory;
use Icewind\Streams\RetryWrapper;
use OCA\CadernoDeCampo\Db\Room;
use OCA\CadernoDeCampo\Db\RoomMapper;
use OCA\Files_External\Lib\PersonalMount;
use OCP\Files\StorageNotAvailableException;


require_once \OC_App::getAppPath('files_external').'/3rdparty/matrixorg_php_client/autoload.php';

class MatrixOrg extends \OC\Files\Storage\Common {

	private $params;
	private $user;
	private $auth;
	private $home_server;
	private $api;
	private $id;
	private $metaData = array();


	private function log($msg) {
		\OCP\Util::writeLog(
			'matrixorg',
			$msg,
			\OCP\Util::ERROR
		);
	}

	public function __construct($params) {
        #$this->log("__construct");
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
			$this->id = 'matrixorg:' . $params['home_server'] ;
			$this->params = $params;

		} else {
			throw new \Exception("$this->id: Creating \OC\Files\Storage\MatrixOrg storage failed");
		}

		$this->home_server = $params['home_server'];
		$this->api = new \MatrixOrg_API($this->home_server);
		//$this->api = new \MatrixOrg_API('http://localhost');
	}

	/**
	 * Returns the path's metadata
	 * @param string $path path for which to return the metadata
	 * @param bool $list if true, also return the directory's contents
	 * @return mixed directory contents if $list is true, file metadata if $list is
	 * false, null if the file doesn't exist or "false" if the operation failed
	 */
	private function getMatrixOrgMetaData($path, $list = false) {
		if (isset($this->metaData[$path])) {
			return $this->metaData[$path][$list?'contents':'meta'];
		}

		//Root folder - gets a list of rooms
		if (empty($path)) {
			$this->metaData[$path] = $this->processMatrixSync($this->api->sync());
		} else { //inside a Room - gets a list of attached files

		}

		if ($list) {
			return $this->metaData[$path]['content'];
		} else {
			return array();
		}
	}


	/**
	 * Separates the file information from the sync
	 * @param $sync - the result of a sync in Matrix.org server
	 */
	private function processMatrixSync($sync) {
		//TODO should get also $sync['rooms']['invite']?
		$rooms = $sync['rooms']['join'];

		$folders = array(
			'content' => array()
		);

		foreach ($rooms as $room_key => $room_info) {
			$room_events = array_merge($room_info['state']['events'],$room_info['timeline']['events']);

			$folder_info = array();

			/*
			Should get the room name and the user permissions: if the user can:
			 * Write in the folder
			 * Rename folder
			 * Delete content in the folder (the folder can not be deleted by nextcloud)
			*/

			//Sort events
			usort($room_events,function($a,$b) {
				if ($a['origin_server_ts'] == $b['origin_server_ts']) return 0;
				return ($a['origin_server_ts'] < $b['origin_server_ts']) ? -1 : 1;
			});

			//Get the room name - last m.room.name message
			$i = 0;
			for ($i = count($room_events)-1; $i>=0; $i--) {
				if ($room_events[$i]['type'] == 'm.room.name') {
					$folder_info['name'] = $room_events[$i]['content']['name'];
					break;
				}
			}

			$folders['content'][] = $folder_info;
		}

		return $folders;
	}

	private function connect() {
		$this->api->login($this->user,$this->auth);
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
        #$this->log("getId");
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
        $this->log("mkdir");
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
        $this->log("rmdir");
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
		$contents = $this->getMatrixOrgMetaData($path,true);
		if ($contents) {
			$files = array();
			foreach ($contents as $file) {
				$files[] = $file['name'];
			}
			return IteratorDirectory::wrap($files);
		}
		return false;
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
		//TODO put the right value...
		$this->log("stat");

		return array('mtime' => time() - 10, 'size' => 4096);
	}

	/**
	 * see http://php.net/manual/en/function.filetype.php
	 *
	 * @param string $path
	 * @return string|false
	 * @since 6.0.0
	 */
	public function filetype($path) {
		$this->log('filetype:'.print_r($path,true));
		if ($path == '' || $path == '/') { //Root folder
			$this->log('dir');
			return 'dir';
		}
		return 'file';
	}




	/**
	 * see http://php.net/manual/en/function.file_exists.php
	 *
	 * @param string $path
	 * @return bool
	 * @since 6.0.0
	 */
	public function file_exists($path) {
		$this->log("file_exists ".$path);

		if (!$this->connect()) {
			$this->log('not connected');
			return false;
		}
		if ($path == '' || $path == '/') { //Root folder
			$this->log('dir');
			return true;
		}


		// TODO: Implement the case where $path is not empty, i.e. a room folder
		return true;
	}

	/**
	 * see http://php.net/manual/en/function.unlink.php
	 *
	 * @param string $path
	 * @return bool
	 * @since 6.0.0
	 */
	public function unlink($path) {
        $this->log("unlink");
		//redact
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
		$this->log("fopen path=".$path." mode=".$mode);
		return false;
		/*
		switch ($mode) {
			case 'r':
			case 'rb':
				try {
					// slashes need to stay
					$encodedPath = str_replace('%2F', '/', rawurlencode(trim($path, '/')));
					$downloadUrl = 'https://api-content.dropbox.com/1/files/auto/' . $encodedPath;
					$headers = $this->oauth->getOAuthHeader($downloadUrl, [], 'GET');

					$client = \OC::$server->getHTTPClientService()->newClient();
					try {
						$response = $client->get($downloadUrl, [
							'headers' => $headers,
							'stream' => true,
						]);
					} catch (RequestException $e) {
						if (!is_null($e->getResponse())) {
							if ($e->getResponse()->getStatusCode() === 404) {
								return false;
							} else {
								throw $e;
							}
						} else {
							throw $e;
						}
					}

					$handle = $response->getBody();
					return RetryWrapper::wrap($handle);
				} catch (\Exception $exception) {
					\OCP\Util::writeLog('files_external', $exception->getMessage(), \OCP\Util::ERROR);
					return false;
				}
			case 'w':
			case 'wb':
			case 'a':
			case 'ab':
			case 'r+':
			case 'w+':
			case 'wb+':
			case 'a+':
			case 'x':
			case 'x+':
			case 'c':
			case 'c+':
				if (strrpos($path, '.') !== false) {
					$ext = substr($path, strrpos($path, '.'));
				} else {
					$ext = '';
				}
				$tmpFile = \OCP\Files::tmpFile($ext);
				\OC\Files\Stream\Close::registerCallback($tmpFile, array($this, 'writeBack'));
				if ($this->file_exists($path)) {
					$source = $this->fopen($path, 'r');
					file_put_contents($tmpFile, $source);
				}
				self::$tempFiles[$tmpFile] = $path;
				return fopen('close://'.$tmpFile, $mode);
		}
		return false;
		*/
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
        $this->log("touch");
		return false;
	}

	/**
	 * Test a storage for availability
	 *
	 * @return bool
	 */
	public function test() {
		$this->log("test");

		try {
			$this->connect();
		} catch (\MatrixOrg_Exception $e) {
			$this->log($e->getMessage());
			return false;
		}

		return true;
	}

}
