<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Björn Schießle <bjoern@schiessle.org>
 * @author Joas Schilling <coding@schilljs.com>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Robin Appelman <robin@icewind.nl>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
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

namespace OCA\Files_Sharing;

use OC\Files\Filesystem;
use OC\Files\Mount\MountPoint;
use OC\Files\Mount\MoveableMount;
use OC\Files\View;

/**
 * Shared mount points can be moved by the user
 */
class SharedMount extends MountPoint implements MoveableMount {
	/**
	 * @var \OCA\Files_Sharing\SharedStorage $storage
	 */
	protected $storage = null;

	/**
	 * @var \OC\Files\View
	 */
	private $recipientView;

	/**
	 * @var string
	 */
	private $user;

	/** @var \OCP\Share\IShare */
	private $superShare;

	/** @var \OCP\Share\IShare[] */
	private $groupedShares;

	/**
	 * @param string $storage
	 * @param SharedMount[] $mountpoints
	 * @param array|null $arguments
	 * @param \OCP\Files\Storage\IStorageFactory $loader
	 */
	public function __construct($storage, array $mountpoints, $arguments = null, $loader = null) {
		$this->user = $arguments['user'];
		$this->recipientView = new View('/' . $this->user . '/files');

		$this->superShare = $arguments['superShare'];
		$this->groupedShares = $arguments['groupedShares'];

		$newMountPoint = $this->verifyMountPoint($this->superShare, $mountpoints);
		$absMountPoint = '/' . $this->user . '/files' . $newMountPoint;
		$arguments['ownerView'] = new View('/' . $this->superShare->getShareOwner() . '/files');
		parent::__construct($storage, $absMountPoint, $arguments, $loader);
	}

	/**
	 * check if the parent folder exists otherwise move the mount point up
	 *
	 * @param \OCP\Share\IShare $share
	 * @param SharedMount[] $mountpoints
	 * @return string
	 */
	private function verifyMountPoint(\OCP\Share\IShare $share, array $mountpoints) {

		$mountPoint = basename($share->getTarget());
		$parent = dirname($share->getTarget());

		if (!$this->recipientView->is_dir($parent)) {
			$parent = Helper::getShareFolder($this->recipientView);
		}

		$newMountPoint = $this->generateUniqueTarget(
			\OC\Files\Filesystem::normalizePath($parent . '/' . $mountPoint),
			$this->recipientView,
			$mountpoints
		);

		if ($newMountPoint !== $share->getTarget()) {
			$this->updateFileTarget($newMountPoint, $share);
		}

		return $newMountPoint;
	}

	/**
	 * update fileTarget in the database if the mount point changed
	 *
	 * @param string $newPath
	 * @param \OCP\Share\IShare $share
	 * @return bool
	 */
	private function updateFileTarget($newPath, &$share) {
		$share->setTarget($newPath);

		foreach ($this->groupedShares as $tmpShare) {
			$tmpShare->setTarget($newPath);
			\OC::$server->getShareManager()->moveShare($tmpShare, $this->user);
		}
	}


	/**
	 * @param string $path
	 * @param View $view
	 * @param SharedMount[] $mountpoints
	 * @return mixed
	 */
	private function generateUniqueTarget($path, $view, array $mountpoints) {
		$pathinfo = pathinfo($path);
		$ext = (isset($pathinfo['extension'])) ? '.' . $pathinfo['extension'] : '';
		$name = $pathinfo['filename'];
		$dir = $pathinfo['dirname'];

		// Helper function to find existing mount points
		$mountpointExists = function ($path) use ($mountpoints) {
			foreach ($mountpoints as $mountpoint) {
				if ($mountpoint->getShare()->getTarget() === $path) {
					return true;
				}
			}
			return false;
		};

		$i = 2;
		while ($view->file_exists($path) || $mountpointExists($path)) {
			$path = Filesystem::normalizePath($dir . '/' . $name . ' (' . $i . ')' . $ext);
			$i++;
		}

		return $path;
	}

	/**
	 * Format a path to be relative to the /user/files/ directory
	 *
	 * @param string $path the absolute path
	 * @return string e.g. turns '/admin/files/test.txt' into '/test.txt'
	 * @throws \OCA\Files_Sharing\Exceptions\BrokenPath
	 */
	protected function stripUserFilesPath($path) {
		$trimmed = ltrim($path, '/');
		$split = explode('/', $trimmed);

		// it is not a file relative to data/user/files
		if (count($split) < 3 || $split[1] !== 'files') {
			\OCP\Util::writeLog('file sharing',
				'Can not strip userid and "files/" from path: ' . $path,
				\OCP\Util::ERROR);
			throw new \OCA\Files_Sharing\Exceptions\BrokenPath('Path does not start with /user/files', 10);
		}

		// skip 'user' and 'files'
		$sliced = array_slice($split, 2);
		$relPath = implode('/', $sliced);

		return '/' . $relPath;
	}

	/**
	 * Move the mount point to $target
	 *
	 * @param string $target the target mount point
	 * @return bool
	 */
	public function moveMount($target) {

		$relTargetPath = $this->stripUserFilesPath($target);
		$share = $this->storage->getShare();

		$result = true;

		try {
			// Gets mount for destination target. If it is another share, will delete this share and move the file
			$targetMount = Filesystem::getMountManager()->find($target);
			if (is_a($targetMount, 'OCA\Files_Sharing\SharedMount')) {
				$thisNode = \OC::$server->getRootFolder()->getById($this->getStorageRootId());
				$thisShares = \OC::$server->getShareManager()->getSharesByPath($thisNode[0]);

				$targetNode = \OC::$server->getRootFolder()->getById($targetMount->getStorageRootId());
				$targetShares = \OC::$server->getShareManager()->getSharesByPath($targetNode[0]);

				//Must have exactly the same shares
				if (count($thisShares) == count($targetShares)) {
					//checks for each share if there is correspondence
					$sameShares = true;
					foreach ($thisShares as $thisShare) {
						foreach ($targetShares as $targetShare) {
							if ($targetShare->getSharedWith() == $thisShare->getSharedWith() and
								$targetShare->getShareType() == $thisShare->getShareType() and
								($thisShare->getNodeType() === 'file' or
									$targetShare->getPermissions() == $thisShare->getPermissions())) {
								continue 2;
							}
						}
						$sameShares = false;
					}
					if ($sameShares) {
						//Deletes share
						foreach ($thisShares as $thisShare) {
							\OC::$server->getShareManager()->deleteShare($thisShare);
						}

						//Gets the mount for the original file
						$srcPath = $thisNode[0]->getPath();
						$destPath = $targetNode[0]->getPath(). '/' . basename($internalPath1);
						$srcMount = Filesystem::getMountManager()->find($srcPath);
						$destMount = Filesystem::getMountManager()->find($destPath);
						$storage1 = $srcMount->getStorage();
						$storage2 = $destMount->getStorage();
						$internalPath1 = $srcMount->getInternalPath($srcPath);
						$internalPath2 = $srcMount->getInternalPath($destPath). '/' . basename($internalPath1);

						//Moves file into target
						if ($storage1 === $storage2) {
							if ($storage1) {
								$result = $storage1->rename($internalPath1, $internalPath2);
							} else {
								$result = false;
							}
							// moving a file/folder between storages (from $storage1 to $storage2)
						} else {
							$result = $storage2->moveFromStorage($storage1, $internalPath1, $internalPath2);
						}
					}

				} else {
					$result = false;
				}
				
			} else {
				$this->updateFileTarget($relTargetPath, $share);
				$this->setMountPoint($target);
				$this->storage->setMountPoint($relTargetPath);
			}
		} catch (\Exception $e) {
			\OCP\Util::writeLog('file sharing',
				'Could not rename mount point for shared folder "' . $this->getMountPoint() . '" to "' . $target . '"',
				\OCP\Util::ERROR);
		}

		return $result;
	}

	/**
	 * Remove the mount points
	 *
	 * @return bool
	 */
	public function removeMount() {
		$mountManager = \OC\Files\Filesystem::getMountManager();
		/** @var $storage \OCA\Files_Sharing\SharedStorage */
		$storage = $this->getStorage();
		$result = $storage->unshareStorage();
		$mountManager->removeMount($this->mountPoint);

		return $result;
	}

	/**
	 * @return \OCP\Share\IShare
	 */
	public function getShare() {
		return $this->superShare;
	}

	/**
	 * Get the file id of the root of the storage
	 *
	 * @return int
	 */
	public function getStorageRootId() {
		return $this->getShare()->getNodeId();
	}

	/**
	 * @return int
	 */
	public function getNumericStorageId() {
		if (!is_null($this->getShare()->getNodeCacheEntry())) {
			return $this->getShare()->getNodeCacheEntry()->getStorageId();
		} else {
			$builder = \OC::$server->getDatabaseConnection()->getQueryBuilder();

			$query = $builder->select('storage')
				->from('filecache')
				->where($builder->expr()->eq('fileid', $builder->createNamedParameter($this->getStorageRootId())));

			$result = $query->execute();
			$row = $result->fetch();
			$result->closeCursor();
			if ($row) {
				return (int)$row['storage'];
			}
			return -1;
		}
	}

	public function getMountType() {
		return 'shared';
	}
}
