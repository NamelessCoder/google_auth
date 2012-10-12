<?php
/***************************************************************
*  Copyright notice
*
*  (c) 2011 Claus Due <claus@wildside.dk>, Wildside A/S
*  All rights reserved
*
*  This script is part of the TYPO3 project. The TYPO3 project is
*  free software; you can redistribute it and/or modify
*  it under the terms of the GNU General Public License as published by
*  the Free Software Foundation; either version 2 of the License, or
*  (at your option) any later version.
*
*  The GNU General Public License can be found at
*  http://www.gnu.org/copyleft/gpl.html.
*
*  This script is distributed in the hope that it will be useful,
*  but WITHOUT ANY WARRANTY; without even the implied warranty of
*  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
*  GNU General Public License for more details.
*
*  This copyright notice MUST APPEAR in all copies of the script!
***************************************************************/

require_once t3lib_extMgm::extPath('google_auth', 'Resources/Private/Libraries/google-api-php-client/src/apiClient.php');
require_once t3lib_extMgm::extPath('google_auth', 'Resources/Private/Libraries/google-api-php-client/src/contrib/apiOauth2Service.php');

/**
 * Class that renders a selection field for Fluid FCE template selection
 *
 * @package	GoogleAuth
 * @subpackage	Service
 */
class Tx_GoogleAuth_Service_Authentication extends tx_sv_authbase {

	const ERR_INVALID_EMAIL_DOMAIN = 1;

	/**
	 * Mode to use
	 *
	 * @var string
	 */
	public $mode = 'getUserFE';

	/**
	 * @var string
	 */
	protected $sessionKey = 'google_auth';

	/**
	 * @var apiClient
	 */
	protected $api;

	/**
	 * @var apiOauth2Service
	 */
	protected $authService;

	/**
	 * @var array
	 */
	protected $config = array();

	/**
	 * @var string
	 */
	protected $scope = 'fe';

	/**
	 * CONSTRUCTOR
	 */
	public function __construct() {
		session_start();
		$this->api = new apiClient();

		$this->config = $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['google_auth']['setup'];

			// perform replacements on configured URLs to match current hostname
		$translation = array('{domain}' => $_SERVER['HTTP_HOST']);
		$urlNames = array('returnUrl', 'finalUrl', 'errorUrl');
		foreach ($urlNames as $urlName) {
			$this->config[$urlName] = strtr($this->config[$urlName], $translation);
		}

		$this->setScope(strtolower($_SESSION[$this->sessionKey]['currentScope'] ? $_SESSION[$this->sessionKey]['currentScope'] : TYPO3_MODE));
		$this->mode = $this->scope === 'be' ? 'getUserBE' : 'getUserFE';
		$this->api->setClientId($this->config['clientId']);
		$this->api->setClientSecret($this->config['secret']);
		$this->api->setScopes($this->config['scope']);
		$this->api->setRedirectUri($this->config['returnUrl']);
		$this->authService = new apiOauth2Service($this->api);
	}

	/**
	 * @return void
	 */
	public function logoff() {
		unset($_SESSION[$this->sessionKey]['user'][$this->scope]);
	}

	/**
	 * @return void
	 */
	public function resetScope() {
		unset($_SESSION[$this->sessionKey]['currentScope']);
	}

	/**
	 * @param string $scope
	 * @return void
	 */
	public function setScope($scope) {
		$this->scope = $scope;
		$_SESSION[$this->sessionKey]['currentScope'] = $scope;
	}

	/**
	 * @return string
	 */
	public function getFinalUrl() {
		if ($this->scope === 'be') {
			return str_replace(PATH_site, '', PATH_typo3);
		} else {
			return $this->config['finalUrl'];
		}
	}

	/**
	 * @return string
	 */
	public function getErrorUrl() {
		return $this->config['errorUrl'];
	}

	/**
	 * @param mixed $user
	 */
	public function authUser(&$user) {
		if ($user['email']) {
			if (!$this->validateEmailDomain($user['email'])) {
				return 101;
			}
			return 200;
		} else {
			return 100;
		}
	}

	/**
	 * @return array User Array or FALSE
	 */
	public function getUser() {
		$data = $_SESSION[$this->sessionKey]['user'][$this->scope];
		$url  = $this->api->createAuthUrl();
		if ($_POST['logintype'] === 'logout') {
			$this->logoff();
		} else
		if (isset($_POST['user']) === TRUE) {
			$this->setScope('fe');
			header("Location: {$url}");
			exit();
		} elseif (isset($_POST['username']) === TRUE) {
			$this->setScope('be');
			header("Location: {$url}");
			exit();
		} elseif ($data['email'] || $data['uid'] > 0) {
			if ($data['uid'] > 0) {
				return $data;
			} else {
				$username = $this->scope === 'fe' ? $data['email'] : substr($data['email'], 0, strpos($data['email'], '@'));
				return $GLOBALS['TYPO3_DB']->exec_SELECTgetSingleRow('*', $this->scope . '_users', "username = '" . $username . "'");
			}
		}
	}

	/**
	 * @return void
	 */
	public function authenticateCallback() {
		$this->api->authenticate($this->authService);
		if (!$this->api->getAccessToken()) {
			throw new Exception('Invalid response from Google Auth while converting Access Token to Refresh Token', 1325690841);
		}
	}

	/**
	 * @return void
	 */
	public function downloadUserInformation() {
		if ($this->scope === 'fe') {
			$this->downloadFrontendUserInformation();
		} elseif ($this->scope === 'be') {
			$this->downloadBackendUserInformation();
		}
	}

	/**
	 * @return void
	 */
	public function downloadBackendUserInformation() {
		$userInfo = $this->authService->userinfo->get();
		$username = substr($userInfo['email'], 0, strpos($userInfo['email'], '@'));
		$userInfo['email'] = filter_var($userInfo['email'], FILTER_SANITIZE_EMAIL);
		$record = $GLOBALS['TYPO3_DB']->exec_SELECTgetSingleRow('*', 'be_users', "username = '" . $username . "'");
		if ($record['disable'] > 0 || $record['deleted'] > 0) {
			return;
		}
		if (!$record) {
			// user has no DB record (yet), create one using defaults registered in extension config
			// password is not important, username is set to the user's default email address
			// fist though, we need to fetch that information from Google
			$record = array(
				'username' => $username,
				'password' => substr(sha1($GLOBALS['TYPO3_CONF_VARS']['SYS']['encryptionKey'] . (microtime(TRUE) * time())), -8),
				'realName' => $userInfo['name'],
				'email' => $userInfo['email'],
				'tstamp' => time(),
				'disable' => '0',
				'deleted' => '0',
				'pid' => 0,
				'usergroup' => $this->config['addBeUsersToGroups'],
				'admin' => $this->config['createAdminBeUsers']
			);
			$GLOBALS['TYPO3_DB']->exec_INSERTquery('be_users', $record);
			$uid = $GLOBALS['TYPO3_DB']->sql_insert_id();
			$record = $GLOBALS['TYPO3_DB']->exec_SELECTgetSingleRow('*', 'be_users', 'uid = ' . intval($uid));
		}
		$_SESSION[$this->sessionKey]['user']['be'] = $record;
	}

	/**
	 * @return void
	 */
	public function downloadFrontendUserInformation() {
		$userInfo = $this->authService->userinfo->get();
		$userInfo['email'] = filter_var($userInfo['email'], FILTER_SANITIZE_EMAIL);
		$record = $GLOBALS['TYPO3_DB']->exec_SELECTgetSingleRow('*', 'fe_users', "username = '" . $userInfo['email'] . "' AND disable = 0 AND deleted = 0");
		if (!$record) {
				// user has no DB record (yet), create one using defaults registered in extension config
				// password is not important, username is set to the user's default email address
				// fist though, we need to fetch that information from Google
			$record = array(
				'username' => $userInfo['email'],
				'password' => substr(sha1($GLOBALS['TYPO3_CONF_VARS']['SYS']['encryptionKey'] . (microtime(TRUE) * time())), -8),
				'name' => $userInfo['name'],
				'email' => $userInfo['email'],
				'disable' => '0',
				'deleted' => '0',
				'pid' => $this->config['storagePid'],
				'usergroup' => $this->config['addUsersToGroups'],
				'tstamp' => time(),
			);
			if (t3lib_extMgm::isLoaded('extbase')) {
				$record['tx_extbase_type'] = $this->config['recordType'];
			}
			$GLOBALS['TYPO3_DB']->exec_INSERTquery('fe_users', $record);
			$uid = $GLOBALS['TYPO3_DB']->sql_insert_id();
			$record = $GLOBALS['TYPO3_DB']->exec_SELECTgetSingleRow('*', 'fe_users', 'uid = ' . intval($uid));
		}
		$_SESSION[$this->sessionKey]['user']['fe'] = $record;
	}

	/**
	 * @param string $email
	 * @return boolean
	 */
	public function validateEmailDomain($email) {
		$mustBelongToDomain = $this->config['domain'];
		if (strpos($mustBelongToDomain, ',')) {
			$domains = explode(',', $mustBelongToDomain);
		} elseif (strlen(trim($mustBelongToDomain)) > 0) {
			$domains = array($mustBelongToDomain);
		} else {
			$domains = array();
		}
		if (count($domains)  > 0) {
			foreach ($domains as $domain) {
				if (substr($email, 0 - strlen($domain)) === $domain) {
					return TRUE;
				}
			}
		} else {
			return TRUE;
		}
		return FALSE;
	}

}

?>