<?php


class Tx_GoogleAuth_Controller_ProfileController extends Tx_Extbase_MVC_Controller_ActionController {

	const ERROR_NONE = 0;
	const ERROR_ACCESS = 1;
	const ERROR_POSTACCESS = 2;

	/**
	 * @var Tx_Extbase_Domain_Repository_FrontendUserRepository
	 */
	protected $frontendUserRepository;

	/**
	 * @var Tx_Extbase_Domain_Repository_FrontendUserGroupRepository
	 */
	protected $frontendUserGroupRepository;

	/**
	 * @param Tx_Extbase_Domain_Repository_FrontendUserRepository $frontendUserRepository
	 */
	public function injectFrontendUserRepository(Tx_Extbase_Domain_Repository_FrontendUserRepository $frontendUserRepository) {
		$this->frontendUserRepository = $frontendUserRepository;
	}

	/**
	 * @param Tx_Extbase_Domain_Repository_FrontendUserGroupRepository $frontendUserGroupRepository
	 */
	public function injectFrontendUserGroupRepository(Tx_Extbase_Domain_Repository_FrontendUserGroupRepository $frontendUserGroupRepository) {
		$this->frontendUserGroupRepository = $frontendUserGroupRepository;
	}

	/**
	 * Mini Profile Editor
	 *
	 * Allows editing of the (local) fields in the FE user
	 * profile which are NOT provided by the Google Auth
	 * Account details.
	 *
	 * @param Tx_Extbase_Domain_Model_FrontendUser $user
	 * @param integer $error
	 * @return string
	 */
	public function miniAction($user=NULL, $error=self::ERROR_NONE) {
		$user = $this->getLoggedInFrontendUserInstance();
		$uid = $user->getUid();

		if ($uid > 0 && ($user === NULL || $user->getUid() === $uid)) {
			$this->view->assign('user', $user);
			$this->view->assign('errors', $this->request->getErrors());
		} elseif ($uid > 0) {
			$this->view->assign('error', $error);
		}

		if ($this->settings['form']['allowGroupSelection']) {
			$groups = $this->frontendUserGroupRepository->findAll();
			$this->view->assign('groups', $groups);
		}
	}

	/**
	 * Saves the Profile, a.k.a. the FrontendUser object
	 *
	 * @param Tx_Extbase_Domain_Model_FrontendUser $user
	 * @return void
	 */
	public function saveAction(Tx_Extbase_Domain_Model_FrontendUser $user) {
		$current = $this->getLoggedInFrontendUserInstance();
		$uid = $current->getUid();
		$arguments = array('user' => $current->getUid());
		if ($uid !== $user->getUid()) {
			$arguments['error'] = self::ERROR_POSTACCESS;
			$this->forward('mini', NULL, NULL, $arguments);
		}
		$errors = array();
		$expressions = $this->settings['validation']['user'];
		if ($expressions['telephone'] && preg_match('/' . $expressions['telephone'] . '/', $user->getTelephone())) {
			$errors['user.telephone'] = new Tx_Extbase_Validation_Error(Tx_Extbase_Utility_Localization::translate('profile.form.telephone.error', 'GoogleAuth'), 1341498734);
		}
		if ($expressions['zip'] && !preg_match('/' . $expressions['zip'] . '/', $user->getZip())) {
			$errors['user.zip'] = new Tx_Extbase_Validation_Error(Tx_Extbase_Utility_Localization::translate('profile.form.zip.error', 'GoogleAuth'), 1341501392);
		}
		if ($expressions['www'] && $user->getWww() && !preg_match('~' . $expressions['www'] . '~', $user->getWww())) {
			$errors['user.www'] = new Tx_Extbase_Validation_Error(Tx_Extbase_Utility_Localization::translate('profile.form.www.error', 'GoogleAuth'), 1341501968);
		}
		if ($user->getUsergroup()->count() < 1) {
			$errors['user.usergroup'] = new Tx_Extbase_Validation_Error(Tx_Extbase_Utility_Localization::translate('profile.form.groups.error', 'GoogleAuth'), 1341826211);
		}
		if (count($errors) > 0) {
			$this->request->setErrors($errors);
			$this->forward('mini', NULL, NULL, $arguments);
		} else {
			$this->frontendUserRepository->update($user);
			$this->flashMessageContainer->add(Tx_Extbase_Utility_Localization::translate('profile.form.updated', 'GoogleAuth'));
		}
		$this->redirect('mini');
	}

	/**
	 * @return Tx_Extbase_Domain_Model_FrontendUser
	 */
	protected function getLoggedInFrontendUserInstance() {
		$uid = intval($GLOBALS['TSFE']->fe_user->user['uid']);
		$user = $this->frontendUserRepository->findByUid($uid);
		return $user;
	}

}