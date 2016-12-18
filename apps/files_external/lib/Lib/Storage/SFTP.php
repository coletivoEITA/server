<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Andreas Fischer <bantu@owncloud.com>
 * @author Bart Visscher <bartv@thisnet.nl>
 * @author hkjolhede <hkjolhede@gmail.com>
 * @author Jörn Friedrich Dreyer <jfd@butonic.de>
 * @author Lennart Rosam <lennart.rosam@medien-systempartner.de>
 * @author Lukas Reschke <lukas@statuscode.ch>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Robin Appelman <robin@icewind.nl>
 * @author Robin McCorkell <robin@mccorkell.me.uk>
 * @author Ross Nicoll <jrn@jrn.me.uk>
 * @author SA <stephen@mthosting.net>
 * @author Senorsen <senorsen.zhang@gmail.com>
 * @author Vincent Petry <pvince81@owncloud.com>
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
namespace OCA\Files_External\Lib\Storage;
use Icewind\Streams\IteratorDirectory;

use Icewind\Streams\RetryWrapper;
use OCP\Lock\ILockingProvider;
use phpseclib\Net\SFTP\Stream;

/**
* Uses phpseclib's Net\SFTP class and the Net\SFTP\Stream stream wrapper to
* provide access to SFTP servers.
*/
class SFTP extends \OC\Files\Storage\Common {
	private $host;
	private $user;
	private $root;
	private $port = 22;

	private $auth;

	/**
	 * @var \phpseclib\Net\SFTP
	 */
	protected $client;

	/**
	 * @param string $host protocol://server:port
	 * @return array [$server, $port]
	 */
	private function splitHost($host) {
		$input = $host;
		if (strpos($host, '://') === false) {
			// add a protocol to fix parse_url behavior with ipv6
			$host = 'http://' . $host;
		}

		$parsed = parse_url($host);
		if(is_array($parsed) && isset($parsed['port'])) {
			return [$parsed['host'], $parsed['port']];
		} else if (is_array($parsed)) {
			return [$parsed['host'], 22];
		} else {
			return [$input, 22];
		}
	}

	private function log($msg) {
		\OCP\Util::writeLog(
			'sftp',
			$msg,
			\OCP\Util::ERROR
		);
	}	
	
	/**
	 * {@inheritdoc}
	 */
	public function __construct($params) {
        #$this->log("__construct");
		// Register sftp://
		Stream::register();

		$parsedHost =  $this->splitHost($params['host']);

		$this->host = $parsedHost[0];
		$this->port = $parsedHost[1];

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

		$this->root
			= isset($params['root']) ? $this->cleanPath($params['root']) : '/';

		if ($this->root[0] != '/') {
			 $this->root = '/' . $this->root;
		}

		if (substr($this->root, -1, 1) != '/') {
			$this->root .= '/';
		}
	}

	/**
	 * Returns the connection.
	 *
	 * @return \phpseclib\Net\SFTP connected client instance
	 * @throws \Exception when the connection failed
	 */
	public function getConnection() {
        #$this->log("getConnection");
		if (!is_null($this->client)) {
			return $this->client;
		}

		$hostKeys = $this->readHostKeys();
		$this->client = new \phpseclib\Net\SFTP($this->host, $this->port);

		// The SSH Host Key MUST be verified before login().
		$currentHostKey = $this->client->getServerPublicHostKey();
		if (array_key_exists($this->host, $hostKeys)) {
			if ($hostKeys[$this->host] != $currentHostKey) {
				throw new \Exception('Host public key does not match known key');
			}
		} else {
			$hostKeys[$this->host] = $currentHostKey;
			$this->writeHostKeys($hostKeys);
		}

		if (!$this->client->login($this->user, $this->auth)) {
			throw new \Exception('Login failed');
		}
		return $this->client;
	}

	/**
	 * {@inheritdoc}
	 */
	public function test() {
        $this->log("test");
		if (
			!isset($this->host)
			|| !isset($this->user)
		) {
			return false;
		}
		return $this->getConnection()->nlist() !== false;
	}

	/**
	 * {@inheritdoc}
	 */
	public function getId(){
        #$this->log("getId");
		$id = 'sftp::' . $this->user . '@' . $this->host;
		if ($this->port !== 22) {
			$id .= ':' . $this->port;
		}
		// note: this will double the root slash,
		// we should not change it to keep compatible with
		// old storage ids
		$id .= '/' . $this->root;
		return $id;
	}

	/**
	 * @return string
	 */
	public function getHost() {
        #$this->log("getHost");
		return $this->host;
	}

	/**
	 * @return string
	 */
	public function getRoot() {
        #$this->log("getRoot");
		return $this->root;
	}

	/**
	 * @return mixed
	 */
	public function getUser() {
        #$this->log("getUser");
		return $this->user;
	}

	/**
	 * @param string $path
	 * @return string
	 */
	private function absPath($path) {
        #$this->log("absPath");
		return $this->root . $this->cleanPath($path);
	}

	/**
	 * @return string|false
	 */
	private function hostKeysPath() {
        #$this->log("hostKeysPath");
		try {
			$storage_view = \OCP\Files::getStorage('files_external');
			if ($storage_view) {
				return \OC::$server->getConfig()->getSystemValue('datadirectory') .
					$storage_view->getAbsolutePath('') .
					'ssh_hostKeys';
			}
		} catch (\Exception $e) {
		}
		return false;
	}

	/**
	 * @param $keys
	 * @return bool
	 */
	protected function writeHostKeys($keys) {
        #$this->log("writeHostKeys");
		try {
			$keyPath = $this->hostKeysPath();
			if ($keyPath && file_exists($keyPath)) {
				$fp = fopen($keyPath, 'w');
				foreach ($keys as $host => $key) {
					fwrite($fp, $host . '::' . $key . "\n");
				}
				fclose($fp);
				return true;
			}
		} catch (\Exception $e) {
		}
		return false;
	}

	/**
	 * @return array
	 */
	protected function readHostKeys() {
        #$this->log("readHostKeys");
		try {
			$keyPath = $this->hostKeysPath();
			if (file_exists($keyPath)) {
				$hosts = array();
				$keys = array();
				$lines = file($keyPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
				if ($lines) {
					foreach ($lines as $line) {
						$hostKeyArray = explode("::", $line, 2);
						if (count($hostKeyArray) == 2) {
							$hosts[] = $hostKeyArray[0];
							$keys[] = $hostKeyArray[1];
						}
					}
					return array_combine($hosts, $keys);
				}
			}
		} catch (\Exception $e) {
		}
		return array();
	}

	/**
	 * {@inheritdoc}
	 */
	public function mkdir($path) {
        $this->log("mkdir");
		try {
			return $this->getConnection()->mkdir($this->absPath($path));
		} catch (\Exception $e) {
			return false;
		}
	}

	/**
	 * {@inheritdoc}
	 */
	public function rmdir($path) {
        $this->log("rmdir");
		try {
			$result = $this->getConnection()->delete($this->absPath($path), true);
			// workaround: stray stat cache entry when deleting empty folders
			// see https://github.com/phpseclib/phpseclib/issues/706
			$this->getConnection()->clearStatCache();
			return $result;
		} catch (\Exception $e) {
			return false;
		}
	}

	/**
	 * {@inheritdoc}
	 */
	public function opendir($path) {
		try {
			$list = $this->getConnection()->nlist($this->absPath($path));
			if ($list === false) {
				return false;
			}

			$id = md5('sftp:' . $path);
			$dirStream = array();
			foreach($list as $file) {
				if ($file != '.' && $file != '..') {
					$dirStream[] = $file;
				}
			}
			$this->log("opendir: return=".print_r($dirStream,true));
			return IteratorDirectory::wrap($dirStream);
		} catch(\Exception $e) {
			return false;
		}
	}

	/**
	 * {@inheritdoc}
	 */
	public function filetype($path) {
		try {
			$stat = $this->getConnection()->stat($this->absPath($path));
			if ($stat['type'] == NET_SFTP_TYPE_REGULAR) {
				$this->log('filetype(file): '.print_r($path,true));
				return 'file';
			}

			if ($stat['type'] == NET_SFTP_TYPE_DIRECTORY) {
				$this->log('filetype(dir): '.print_r($path,true));
				return 'dir';
			}
		} catch (\Exception $e) {

		}
		return false;
	}

	/**
	 * {@inheritdoc}
	 */
	public function file_exists($path) {
		$this->log("file_exists ".$path);
		try {
			return $this->getConnection()->stat($this->absPath($path)) !== false;
		} catch (\Exception $e) {
			return false;
		}
	}

	/**
	 * {@inheritdoc}
	 */
	public function unlink($path) {
        $this->log("unlink");
		try {
			return $this->getConnection()->delete($this->absPath($path), true);
		} catch (\Exception $e) {
			return false;
		}
	}

	/**
	 * {@inheritdoc}
	 */
	public function fopen($path, $mode) {
        $this->log("fopen");
		try {
			$absPath = $this->absPath($path);
			switch($mode) {
				case 'r':
				case 'rb':
					if ( !$this->file_exists($path)) {
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
					$context = stream_context_create(array('sftp' => array('session' => $this->getConnection())));
					$handle = fopen($this->constructUrl($path), $mode, false, $context);
					$this->log("RESOURCE: ".print_r($handle,true));

					return RetryWrapper::wrap($handle);
			}
		} catch (\Exception $e) {
		}
		return false;
	}

	/**
	 * {@inheritdoc}
	 */
	public function touch($path, $mtime=null) {
        $this->log("touch");
		try {
			if (!is_null($mtime)) {
				return false;
			}
			if (!$this->file_exists($path)) {
				$this->getConnection()->put($this->absPath($path), '');
			} else {
				return false;
			}
		} catch (\Exception $e) {
			return false;
		}
		return true;
	}

	/**
	 * @param string $path
	 * @param string $target
	 * @throws \Exception
	 */
	public function getFile($path, $target) {
        #$this->log("getFile");
		$this->getConnection()->get($path, $target);
	}

	/**
	 * @param string $path
	 * @param string $target
	 * @throws \Exception
	 */
	public function uploadFile($path, $target) {
        #$this->log("uploadFile");
		$this->getConnection()->put($target, $path, NET_SFTP_LOCAL_FILE);
	}

	/**
	 * {@inheritdoc}
	 */
	public function rename($source, $target) {
        $this->log("rename");
		try {
			if ($this->file_exists($target)) {
				$this->unlink($target);
			}
			return $this->getConnection()->rename(
				$this->absPath($source),
				$this->absPath($target)
			);
		} catch (\Exception $e) {
			return false;
		}
	}

	/**
	 * {@inheritdoc}
	 */
	public function stat($path) {
		try {
			$stat = $this->getConnection()->stat($this->absPath($path));

			$mtime = $stat ? $stat['mtime'] : -1;
			$size = $stat ? $stat['size'] : 0;
			$this->log("stat path=$path mtime=$mtime size=$size");

			return array('mtime' => $mtime, 'size' => $size, 'ctime' => -1);
		} catch (\Exception $e) {
			return false;
		}
	}

	/**
	 * @param string $path
	 * @return string
	 */
	public function constructUrl($path) {
        #$this->log("constructUrl");
		// Do not pass the password here. We want to use the Net_SFTP object
		// supplied via stream context or fail. We only supply username and
		// hostname because this might show up in logs (they are not used).
		$url = 'sftp://' . urlencode($this->user) . '@' . $this->host . ':' . $this->port . $this->root . $path;
		return $url;
	}

	/**
	 * Remove a file or folder
	 *
	 * @param string $path
	 * @return bool
	 */
	protected function remove($path) {
        #$this->log("remove");
		return parent::remove($path); // TODO: Change the autogenerated stub
	}

	public function is_dir($path) {
        #$this->log("is_dir");
		return parent::is_dir($path); // TODO: Change the autogenerated stub
	}

	public function is_file($path) {
        #$this->log("is_file");
		return parent::is_file($path); // TODO: Change the autogenerated stub
	}

	public function filesize($path) {
        #$this->log("filesize");
		return parent::filesize($path); // TODO: Change the autogenerated stub
	}

	public function isReadable($path) {
        #$this->log("isReadable");
		return parent::isReadable($path); // TODO: Change the autogenerated stub
	}

	public function isUpdatable($path) {
        #$this->log("isUpdatable");
		return parent::isUpdatable($path); // TODO: Change the autogenerated stub
	}

	public function isCreatable($path) {
        #$this->log("isCreatable");
		return parent::isCreatable($path); // TODO: Change the autogenerated stub
	}

	public function isDeletable($path) {
        #$this->log("isDeletable");
		return parent::isDeletable($path); // TODO: Change the autogenerated stub
	}

	public function isSharable($path) {
        #$this->log("isSharable");
		return parent::isSharable($path); // TODO: Change the autogenerated stub
	}

	public function getPermissions($path) {
        #$this->log("getPermissions");
		return parent::getPermissions($path); // TODO: Change the autogenerated stub
	}

	public function filemtime($path) {
        #$this->log("filemtime");
		return parent::filemtime($path); // TODO: Change the autogenerated stub
	}

	public function file_get_contents($path) {
        #$this->log("file_get_contents");
		return parent::file_get_contents($path); // TODO: Change the autogenerated stub
	}

	public function file_put_contents($path, $data) {
        #$this->log("file_put_contents");
		return parent::file_put_contents($path, $data); // TODO: Change the autogenerated stub
	}

	public function copy($path1, $path2) {
        #$this->log("copy");
		return parent::copy($path1, $path2); // TODO: Change the autogenerated stub
	}

	public function getMimeType($path) {
        #$this->log("getMimeType");
		return parent::getMimeType($path); // TODO: Change the autogenerated stub
	}

	public function hash($type, $path, $raw = false) {
        #$this->log("hash");
		return parent::hash($type, $path, $raw); // TODO: Change the autogenerated stub
	}

	public function search($query) {
        #$this->log("search");
		return parent::search($query); // TODO: Change the autogenerated stub
	}

	public function getLocalFile($path) {
        #$this->log("getLocalFile");
		return parent::getLocalFile($path); // TODO: Change the autogenerated stub
	}

	/**
	 * @param string $query
	 * @param string $dir
	 * @return array
	 */
	protected function searchInDir($query, $dir = '') {
        #$this->log("searchInDir");
		return parent::searchInDir($query, $dir); // TODO: Change the autogenerated stub
	}

	/**
	 * check if a file or folder has been updated since $time
	 *
	 * The method is only used to check if the cache needs to be updated. Storage backends that don't support checking
	 * the mtime should always return false here. As a result storage implementations that always return false expect
	 * exclusive access to the backend and will not pick up files that have been added in a way that circumvents
	 * ownClouds filesystem.
	 *
	 * @param string $path
	 * @param int $time
	 * @return bool
	 */
	public function hasUpdated($path, $time) {
        #$this->log("hasUpdated");
		return parent::hasUpdated($path, $time); // TODO: Change the autogenerated stub
	}

	public function getCache($path = '', $storage = null) {
        #$this->log("getCache");
		return parent::getCache($path, $storage); // TODO: Change the autogenerated stub
	}

	public function getScanner($path = '', $storage = null) {
        #$this->log("getScanner");
		return parent::getScanner($path, $storage); // TODO: Change the autogenerated stub
	}

	public function getWatcher($path = '', $storage = null) {
        #$this->log("getWatcher");
		return parent::getWatcher($path, $storage); // TODO: Change the autogenerated stub
	}

	/**
	 * get a propagator instance for the cache
	 *
	 * @param \OC\Files\Storage\Storage (optional) the storage to pass to the watcher
	 * @return \OC\Files\Cache\Propagator
	 */
	public function getPropagator($storage = null) {
        #$this->log("getPropagator");
		return parent::getPropagator($storage); // TODO: Change the autogenerated stub
	}

	public function getUpdater($storage = null) {
        #$this->log("getUpdater");
		return parent::getUpdater($storage); // TODO: Change the autogenerated stub
	}

	public function getStorageCache($storage = null) {
        #$this->log("getStorageCache");
		return parent::getStorageCache($storage); // TODO: Change the autogenerated stub
	}

	/**
	 * get the owner of a path
	 *
	 * @param string $path The path to get the owner
	 * @return string|false uid or false
	 */
	public function getOwner($path) {
        #$this->log("getOwner");
		return parent::getOwner($path); // TODO: Change the autogenerated stub
	}

	/**
	 * get the ETag for a file or folder
	 *
	 * @param string $path
	 * @return string
	 */
	public function getETag($path) {
        #$this->log("getETag");
		return parent::getETag($path); // TODO: Change the autogenerated stub
	}

	/**
	 * clean a path, i.e. remove all redundant '.' and '..'
	 * making sure that it can't point to higher than '/'
	 *
	 * @param string $path The path to clean
	 * @return string cleaned path
	 */
	public function cleanPath($path) {
        #$this->log("cleanPath");
		return parent::cleanPath($path); // TODO: Change the autogenerated stub
	}

	/**
	 * get the free space in the storage
	 *
	 * @param string $path
	 * @return int|false
	 */
	public function free_space($path) {
        #$this->log("free_space");
		return parent::free_space($path); // TODO: Change the autogenerated stub
	}

	/**
	 * {@inheritdoc}
	 */
	public function isLocal() {
        #$this->log("isLocal");
		return parent::isLocal(); // TODO: Change the autogenerated stub
	}

	/**
	 * Check if the storage is an instance of $class or is a wrapper for a storage that is an instance of $class
	 *
	 * @param string $class
	 * @return bool
	 */
	public function instanceOfStorage($class) {
        #$this->log("instanceOfStorage");
		return parent::instanceOfStorage($class); // TODO: Change the autogenerated stub
	}

	/**
	 * A custom storage implementation can return an url for direct download of a give file.
	 *
	 * For now the returned array can hold the parameter url - in future more attributes might follow.
	 *
	 * @param string $path
	 * @return array|false
	 */
	public function getDirectDownload($path) {
        #$this->log("getDirectDownload");
		return parent::getDirectDownload($path); // TODO: Change the autogenerated stub
	}

	/**
	 * @inheritdoc
	 */
	public function verifyPath($path, $fileName) {
        #$this->log("verifyPath");
		parent::verifyPath($path, $fileName); // TODO: Change the autogenerated stub
	}

	/**
	 * @param string $fileName
	 * @throws InvalidPathException
	 */
	protected function verifyPosixPath($fileName) {
        #$this->log("verifyPosixPath");
		parent::verifyPosixPath($fileName); // TODO: Change the autogenerated stub
	}

	/**
	 * @param array $options
	 */
	public function setMountOptions(array $options) {
        #$this->log("setMountOptions");
		parent::setMountOptions($options); // TODO: Change the autogenerated stub
	}

	/**
	 * @param string $name
	 * @param mixed $default
	 * @return mixed
	 */
	public function getMountOption($name, $default = null) {
        #$this->log("getMountOption");
		return parent::getMountOption($name, $default); // TODO: Change the autogenerated stub
	}

	/**
	 * @param \OCP\Files\Storage $sourceStorage
	 * @param string $sourceInternalPath
	 * @param string $targetInternalPath
	 * @param bool $preserveMtime
	 * @return bool
	 */
	public function copyFromStorage(\OCP\Files\Storage $sourceStorage, $sourceInternalPath, $targetInternalPath, $preserveMtime = false) {
        #$this->log("copyFromStorage");
		return parent::copyFromStorage($sourceStorage, $sourceInternalPath, $targetInternalPath, $preserveMtime); // TODO: Change the autogenerated stub
	}

	/**
	 * @param \OCP\Files\Storage $sourceStorage
	 * @param string $sourceInternalPath
	 * @param string $targetInternalPath
	 * @return bool
	 */
	public function moveFromStorage(\OCP\Files\Storage $sourceStorage, $sourceInternalPath, $targetInternalPath) {
        #$this->log("moveFromStorage");
		return parent::moveFromStorage($sourceStorage, $sourceInternalPath, $targetInternalPath); // TODO: Change the autogenerated stub
	}

	/**
	 * @inheritdoc
	 */
	public function getMetaData($path) {
        #$this->log("getMetaData");
		return parent::getMetaData($path); // TODO: Change the autogenerated stub
	}

	/**
	 * @param string $path
	 * @param int $type \OCP\Lock\ILockingProvider::LOCK_SHARED or \OCP\Lock\ILockingProvider::LOCK_EXCLUSIVE
	 * @param \OCP\Lock\ILockingProvider $provider
	 * @throws \OCP\Lock\LockedException
	 */
	public function acquireLock($path, $type, ILockingProvider $provider) {
        #$this->log("acquireLock");
		parent::acquireLock($path, $type, $provider); // TODO: Change the autogenerated stub
	}

	/**
	 * @param string $path
	 * @param int $type \OCP\Lock\ILockingProvider::LOCK_SHARED or \OCP\Lock\ILockingProvider::LOCK_EXCLUSIVE
	 * @param \OCP\Lock\ILockingProvider $provider
	 */
	public function releaseLock($path, $type, ILockingProvider $provider) {
        #$this->log("releaseLock");
		parent::releaseLock($path, $type, $provider); // TODO: Change the autogenerated stub
	}

	/**
	 * @param string $path
	 * @param int $type \OCP\Lock\ILockingProvider::LOCK_SHARED or \OCP\Lock\ILockingProvider::LOCK_EXCLUSIVE
	 * @param \OCP\Lock\ILockingProvider $provider
	 */
	public function changeLock($path, $type, ILockingProvider $provider) {
        #$this->log("changeLock");
		parent::changeLock($path, $type, $provider); // TODO: Change the autogenerated stub
	}

	/**
	 * @return array [ available, last_checked ]
	 */
	public function getAvailability() {
        #$this->log("getAvailability");
		return parent::getAvailability(); // TODO: Change the autogenerated stub
	}

	/**
	 * @param bool $isAvailable
	 */
	public function setAvailability($isAvailable) {
        #$this->log("setAvailability");
		parent::setAvailability($isAvailable); // TODO: Change the autogenerated stub
	}
}
