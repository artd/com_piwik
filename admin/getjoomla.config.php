<?php
	//get Joomla database infos
	$dir = str_replace('administrator' . $DS . 'components' . $DS . 'com_piwik', '', dirname(realpath(__FILE__)));
	include ($dir . 'configuration.php');

	$conf = new JConfig();
	
	$jhost   = $conf->host;
	$juser   = $conf->user;
	$jpass   = $conf->password;
	$jdb     = $conf->db;
	$jprefix = $conf->dbprefix;
?>