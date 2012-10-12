<?php
if (!defined ('TYPO3_MODE')) {
	die ('Access denied.');
}

$TYPO3_CONF_VARS['EXTCONF']['google_auth']['setup'] = unserialize($_EXTCONF);

$subTypes = array();
if ($TYPO3_CONF_VARS['EXTCONF']['google_auth']['setup']['enableFE']) {
	array_push($subTypes, 'getUserFE');
	array_push($subTypes, 'authUserFE');
	$GLOBALS['TYPO3_CONF_VARS']['SVCONF']['auth']['setup']['FE_fetchUserIfNoSession'] = '1';
}

if ($TYPO3_CONF_VARS['EXTCONF']['google_auth']['setup']['enableBE']) {
	array_push($subTypes, 'getUserBE');
	array_push($subTypes, 'authUserBE');
	$GLOBALS['TYPO3_CONF_VARS']['SVCONF']['auth']['setup']['BE_fetchUserIfNoSession'] = '1';
}

if (count($subTypes) > 0) {
	t3lib_extMgm::addService($_EXTKEY, 'auth', 'tx_googleauth_service_authentication',
		array(
			'title' => 'Google Authentification Service',
			'description' => 'Provides authentication for FE and BE (enabled in extension configuration) through Google OAuth2',
			'subtype' => implode(',', $subTypes),
			'available' => TRUE,
			'priority' => 20,
			'quality' => 60,
			'os' => '',
			'exec' => '',
			'classFile' => t3lib_extMgm::extPath($_EXTKEY).'Classes/Service/Authentication.php',
			'className' => 'Tx_GoogleAuth_Service_Authentication',
		)
	);
}
unset($subTypes);

if (!defined ('TYPO3_MODE')) {
	die ('Access denied.');
}

Tx_Extbase_Utility_Extension::configurePlugin(
	$_EXTKEY,
	'Auth',
	array(
		'Auth' => 'return, error',
	),
	array(
		'Auth' => 'return, error',
	)
);

if ($TYPO3_CONF_VARS['EXTCONF']['google_auth']['setup']['enableProfile']) {
	Tx_Extbase_Utility_Extension::configurePlugin(
		$_EXTKEY,
		'Profile',
		array(
			'Profile' => 'mini, save',
		),
		array(
			'Profile' => 'mini, save',
		)
	);
}

$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_userauth.php']['logoff_post_processing'][] = 'EXT:google_auth/Classes/UserFunction/Logoff.php:Tx_GoogleAuth_UserFunction_Logoff->logoff';

?>