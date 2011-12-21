<?php
# Piwik tracking code plugin for Joomla! 1.5
// no direct access
defined( '_JEXEC' ) or die( 'Restricted access' );

jimport( 'joomla.plugin.plugin');

class plgSystemPiwik extends JPlugin {
	function plgSystemPiwik(&$subject, $config) {
		parent::__construct($subject, $config);
		
		$this->_plugin = JPluginHelper::getPlugin( 'system', 'piwik' );
		jimport( 'joomla.html.parameter' );
		$this->_params = new JParameter( $this->_plugin->params );
	}
	
	function onAfterRender() {
	
		$piwik_site_id = $this->params->get('piwik_site_id', '1');
		$piwik_http_url = $this->params->get('piwik_http_url', JUri::base() . 'administrator/components/com_piwik/piwik/');
		$piwik_https_url = $this->params->get('piwik_https_url', '');
		
		$app = JFactory::getApplication();		
		
		if($piwik_site_id == '' || $app->isAdmin() || strpos($_SERVER["PHP_SELF"], "index.php") === false) {
			return;
		}

    	$buffer = JResponse::getBody();

		$piwik_javascript = '
			<!-- Piwik -->
			<script type="text/javascript">
			var pkBaseURL = (("https:" == document.location.protocol) ? "'.$piwik_https_url.'" : "'.$piwik_http_url.'");
			document.write(unescape("%3Cscript src=\'" + pkBaseURL + "piwik.js\' type=\'text/javascript\'%3E%3C/script%3E"));
			</script><script type="text/javascript">
			try {
			piwik_idsite = ' . $piwik_site_id . ';
			piwik_url = pkBaseURL + "piwik.php";
			var piwikTracker = Piwik.getTracker(piwik_url, piwik_idsite);
			piwikTracker.trackPageView();
			piwikTracker.enableLinkTracking();
			} catch( err ) {}
			</script><noscript><p><img src="'.$piwik_http_url.'piwik.php?idsite=' . $piwik_site_id . '" style="border:0" alt="" /></p></noscript>
			<!-- End Piwik Tracking Code -->			
			';

		$pos = strrpos($buffer, "</body>");
		
		if($pos > 0)
		{
			$buffer = substr($buffer, 0, $pos).$piwik_javascript.substr($buffer, $pos);

			JResponse::setBody($buffer);
		}
		
		return true;
	}
}
?>