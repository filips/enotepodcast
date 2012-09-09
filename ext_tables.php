<?php
if (!defined ('TYPO3_MODE')) 	die ('Access denied.');

t3lib_div::loadTCA('tt_content');
$TCA['tt_content']['types']['list']['subtypes_excludelist'][$_EXTKEY.'_pi1']='layout,select_key,pages';
$TCA['tt_content']['types']['list']['subtypes_addlist'][$_EXTKEY.'_pi1']='pi_flexform';

if(t3lib_div::int_from_ver(TYPO3_version) >= 4002000)
	t3lib_extMgm::addPiFlexFormValue($_EXTKEY.'_pi1','FILE:EXT:'.$_EXTKEY.'/flexform.xml');
	
t3lib_extMgm::addPlugin(Array('Enote Podcast', $_EXTKEY.'_pi1'),'list_type');

?>