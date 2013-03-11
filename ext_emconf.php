<?php

########################################################################
# Extension Manager/Repository config file for ext "mb_olc_flights".
#
# Auto generated 01-11-2012 15:05
#
# Manual updates:
# Only the data in the array - everything else is removed by next
# writing. "version" and "dependencies" must not be touched!
########################################################################

$EM_CONF[$_EXTKEY] = array(
	'title' => 'mb_olc_flights',
	'description' => 'Displays OLC flights (soaring) by date, shows best flight and allows for links to aircraft details.',
	'category' => 'fe',
	'shy' => 0,
	'version' => '0.1.3',
	'dependencies' => '',
	'conflicts' => '',
	'priority' => '',
	'loadOrder' => '',
	'module' => 'cm1',
	'state' => 'beta',
	'uploadfolder' => 0,
	'createDirs' => '',
	'modify_tables' => '',
	'clearcacheonload' => 0,
	'lockType' => '',
	'author' => 'Martin Becker',
	'author_email' => 'vbmazter@web.de',
	'author_company' => '',
	'CGLcompliance' => '',
	'CGLcompliance_note' => '',
	'constraints' => array(
		'depends' => array(
			'typo3'=>'4.5.0-4.5.25',
		),
		'conflicts' => array(
		),
		'suggests' => array(
		),
	),
	'_md5_values_when_last_written' => 'a:30:{s:9:"ChangeLog";s:4:"65ab";s:29:"class.tx_mbolcflights_cm1.php";s:4:"32e3";s:12:"ext_icon.gif";s:4:"bd34";s:17:"ext_localconf.php";s:4:"0e22";s:14:"ext_tables.php";s:4:"3812";s:19:"flexform_ds_pi1.xml";s:4:"9d9d";s:13:"locallang.xml";s:4:"18ca";s:16:"locallang_db.xml";s:4:"29ce";s:10:"README.txt";s:4:"8818";s:13:"cm1/clear.gif";s:4:"cc11";s:15:"cm1/cm_icon.gif";s:4:"bd34";s:12:"cm1/conf.php";s:4:"d2ba";s:13:"cm1/index.php";s:4:"dcd3";s:17:"cm1/locallang.xml";s:4:"885a";s:21:"doc/example_setup.txt";s:4:"fb71";s:14:"doc/manual.pdf";s:4:"8fd3";s:14:"doc/manual.sxw";s:4:"2cae";s:19:"doc/wizard_form.dat";s:4:"fb08";s:20:"doc/wizard_form.html";s:4:"36cf";s:14:"pi1/ce_wiz.gif";s:4:"bd34";s:33:"pi1/class.tx_mbolcflights_pi1.php";s:4:"53d1";s:41:"pi1/class.tx_mbolcflights_pi1_wizicon.php";s:4:"8848";s:13:"pi1/clear.gif";s:4:"cc11";s:17:"pi1/locallang.xml";s:4:"b916";s:22:"pi1/olc_reader.php.inc";s:4:"de1f";s:27:"pi1/simple_html_dom.php.inc";s:4:"1f38";s:16:"res/template.css";s:4:"f320";s:17:"res/template.html";s:4:"a15d";s:24:"res/template_simple.html";s:4:"d411";s:20:"static/css/setup.txt";s:4:"1ea7";}',
	'suggests' => array(
	),
);

?>
