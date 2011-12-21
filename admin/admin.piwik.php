<?php
	defined('_JEXEC') or die('Direct Access to this location is not allowed.');
	$live_site = JURI::base();
	$abs_path = getcwd();

	if(!file_exists($abs_path . DS . 'components' . DS . 'com_piwik' . DS . "piwik" . DS . 'index.php')) {
?>
		<IFRAME SRC="<?php echo $live_site;?>components/com_piwik/extraction.php" width="100%" height="600px" align="top" scrolling="auto" frameborder="0"></IFRAME>
<?php
	} else {			
		$src = JURI::base() . "components/com_piwik/piwik/index.php";
?>
		<IFRAME SRC="<?php echo $src;?>" width="100%" height="600px" align="top" scrolling="auto" frameborder="0"></IFRAME>
<?php
	}
?>