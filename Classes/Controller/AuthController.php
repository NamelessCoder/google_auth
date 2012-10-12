<?php


class Tx_GoogleAuth_Controller_AuthController extends Tx_Extbase_MVC_Controller_ActionController {


	/**
	 * @var Tx_GoogleAuth_Service_Authentication
	 */
	protected $authService;

	/**
	 * @param Tx_GoogleAuth_Service_Authentication $authService
	 */
	public function injectAuthService(Tx_GoogleAuth_Service_Authentication $authService) {
		$this->authService = $authService;
	}

	/**
	 * @param integer $errorCode
	 */
	public function errorAction($errorCode) {

	}

	/**
	 * @return string
	 */
	public function returnAction() {
		session_start();
		if (!$_GET['code']) {
			throw new Exception('GET parameter "code" is required but was not found. You may have been incorrectly redirected here from Google Auth. There should be error messages before this one in your log.', 1325691094);
		}

		try {
			$this->authService->authenticateCallback();
			$this->authService->downloadUserInformation();
			$redirectionDelay = 0;
			$redirectionUri = $this->authService->getFinalUrl();
			$redirectionCode = 302;
		} catch (Exception $e) {
			$redirectionUri = $this->authService->getErrorUrl();
			$redirectionDelay = 3;
			$redirectionCode = 302;
		}

		$this->redirectToUri($redirectionUri, $redirectionDelay, $redirectionCode);
	}

}

?>