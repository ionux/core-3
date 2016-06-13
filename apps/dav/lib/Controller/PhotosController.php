<?php
/**
 * @author Georg Ehrke <georg@ownCloud.com>
 *
 * @copyright Copyright (c) 2016, ownCloud, Inc.
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

namespace OCA\DAV\Controller;

use OCA\DAV\CardDAV\CardDavBackend;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\NotFoundResponse;
use OCP\ILogger;
use OCP\IRequest;
use OCP\IUserSession;
use Sabre\VObject\Reader;

class PhotosController extends Controller {

	/**
	 * @var CardDavBackend
	 */
	protected $backend;

	/**
	 * @var IUserSession
	 */
	protected $userSession;

	/**
	 * @var ILogger
	 */
	protected $logger;

	/**
	 * @param string $appName
	 * @param IRequest $request an instance of the request
	 * @param IUserSession $userSession
	 * @param CardDavBackend $backend
	 * @param ILogger $logger
	 */
	public function __construct($appName, IRequest $request,
								IUserSession $userSession,
								CardDavBackend $backend,
								ILogger $logger) {
		parent::__construct($appName, $request);
		$this->backend = $backend;
		$this->userSession = $userSession;
		$this->logger = $logger;
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 *
	 * @param integer $addressbookId
	 * @param string $contactUri
	 * @return \OCP\AppFramework\Http\Response|null
	 */
	public function get($addressbookId, $contactUri) {
		$user = $this->userSession->getUser();
		if (!$user) {
			return new NotFoundResponse();
		}

		if (!$this->doesAddressBookBelongToUser($addressbookId, $user->getUID())) {
			return new NotFoundResponse();
		}

		$card = $this->backend->getCard($addressbookId, $contactUri);
		if (!$card || !isset($card['carddata'])) {
			return new NotFoundResponse();
		}

		$vobject = $this->readCard($card['carddata']);
		if (!$vobject->PHOTO) {
			return new NotFoundResponse();
		}
		
		$photo = $vobject->PHOTO;
		try {
			$image = new \OC_Image();
			$image->load($photo->getValue());
		} catch(\Exception $ex) {
			$this->logger->debug($ex->getMessage());
			return new NotFoundResponse();
		}

		$image->show();
	}

	private function doesAddressBookBelongToUser($addressbookId, $userId) {
		$addressbook = $this->backend->getAddressBookById($addressbookId);
		return $addressbook['principaluri'] === 'principals/users/' . $userId;
	}

	private function readCard($cardData) {
		return Reader::read($cardData);
	}
}
