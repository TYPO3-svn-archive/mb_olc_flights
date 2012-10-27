<?php
if (!defined('TYPO3_MODE')) {
	die ('Access denied.');
}

// last parameter==1 -> cache plugin
t3lib_extMgm::addPItoST43($_EXTKEY, 'pi1/class.tx_mbolcflights_pi1.php', '_pi1', 'list_type', 1);
?>