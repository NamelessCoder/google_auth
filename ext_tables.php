<?php
if (!defined('TYPO3_MODE')) {
	die ('Access denied.');
}




t3lib_extMgm::addStaticFile($_EXTKEY, 'Configuration/TypoScript', 'Google OpenAuth v2');

Tx_Extbase_Utility_Extension::registerPlugin(
	$_EXTKEY,
	'Auth',
	'Auth controller return point'
);

if ($TYPO3_CONF_VARS['EXTCONF']['google_auth']['setup']['enableProfile']) {
	Tx_Extbase_Utility_Extension::registerPlugin(
		$_EXTKEY,
		'Profile',
		'Mini FrontendUser Profile Editor'
	);
}

?>