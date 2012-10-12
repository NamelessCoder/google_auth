<?php

class Tx_GoogleAuth_UserFunction_Logoff {

	/**
	 * @param mixed $params
	 * @param mixed $reference
	 */
	public function logoff($params, $reference) {
		/** @var Tx_Extbase_Object_ObjectManagerInterface $objectManager  */
		$objectManager = t3lib_div::makeInstance('Tx_Extbase_Object_ObjectManager');
		/** @var Tx_GoogleAuth_Service_Authentication $authService */
		$authService = $objectManager->get('Tx_GoogleAuth_Service_Authentication');
		$requestedFile = basename($_SERVER['REQUEST_URI']);
		$referer = basename($_SERVER['HTTP_REFERER']);
		if ($requestedFile === 'logout.php' || $referer === 'logout.php' || $referer === 'backend.php') {
			if ($reference instanceof t3lib_beUserAuth === TRUE) {
				$authService->setScope('be');
				$authService->logoff();
			}
		}
	}

}