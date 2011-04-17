<?php
if (!defined ('TYPO3_MODE')) 	die ('Access denied.');
$tempColumns = Array (
	"tx_t3ouserimage_img_hash" => Array (		
		"exclude" => 1,		
		"label" => "LLL:EXT:t3o_userimage/locallang_db.xml:fe_users.tx_t3ouserimage_img_hash",		
		"config" => Array (
			"type" => "none",
		)
	),
);


t3lib_div::loadTCA("fe_users");
t3lib_extMgm::addTCAcolumns("fe_users",$tempColumns,1);
t3lib_extMgm::addToAllTCAtypes("fe_users","tx_t3ouserimage_img_hash;;;;1-1-1");


t3lib_div::loadTCA('tt_content');
$TCA['tt_content']['types']['list']['subtypes_excludelist'][$_EXTKEY.'_pi1']='layout,select_key';


t3lib_extMgm::addPlugin(array('LLL:EXT:t3o_userimage/locallang_db.xml:tt_content.list_type_pi1', $_EXTKEY.'_pi1'),'list_type');


t3lib_extMgm::addStaticFile($_EXTKEY,"pi1/static/","typo3.org - User Image Upload");
?>