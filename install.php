<?php
// no direct access
defined('_JEXEC') or die('Restricted access');

// Modules & plugins installation

jimport('joomla.installer.installer');
$db = & JFactory::getDBO();
$status = new JObject();
$status->plugins = array();

//if( version_compare( JVERSION, '1.6.0', 'ge' ) ) {
//	$src = dirname(__FILE__);echo '1';
//} else {
	$src = $this->parent->getPath('source');
//}

$installer = new JInstaller;
$result = $installer->install($src.DS.'plg_piwik');
$status->plugins[] = array('name'=>'plg_piwik','group'=>'system', 'result'=>$result);

if( version_compare( JVERSION, '1.6.0', 'ge' ) ) {
	$query = "UPDATE #__extensions SET ordering=-30000 WHERE element='piwik' AND folder='system'";
	$db->setQuery($query);
	$db->query();

	$query = "UPDATE #__extensions SET enabled=1 WHERE element='piwik' AND folder='system'";
	$db->setQuery($query);
	$db->query();
} else {
	$query = "UPDATE #__plugins SET ordering=-30000 WHERE element='piwik' AND folder='system'";
	$db->setQuery($query);
	$db->query();

	$query = "UPDATE #__plugins SET published=1 WHERE element='piwik' AND folder='system'";
	$db->setQuery($query);
	$db->query();
}

?>

<?php $rows = 0;?>
<img src="../media/com_admintools/images/admintools-48.png" width="48" height="48" alt="Admin Tools" align="right" />
<h2><?php echo JText::_('Piwik Installation Status'); ?></h2>
<table class="adminlist">
	<thead>
		<tr>
			<th class="title" colspan="2"><?php echo JText::_('Extension'); ?></th>
			<th width="30%"><?php echo JText::_('Status'); ?></th>
		</tr>
	</thead>
	<tfoot>
		<tr>
			<td colspan="3"></td>
		</tr>
	</tfoot>
	<tbody>
		<tr class="row0">
			<td class="key" colspan="2"><?php echo 'Piwik '.JText::_('Component'); ?></td>
			<td><strong><?php echo JText::_('Installed'); ?></strong></td>
		</tr>
		<?php if (count($status->plugins)) : ?>
		<tr>
			<th><?php echo JText::_('Plugin'); ?></th>
			<th><?php echo JText::_('Group'); ?></th>
			<th></th>
		</tr>
		<?php foreach ($status->plugins as $plugin) : ?>
		<tr class="row<?php echo (++ $rows % 2); ?>">
			<td class="key"><?php echo ucfirst($plugin['name']); ?></td>
			<td class="key"><?php echo ucfirst($plugin['group']); ?></td>
			<td><strong><?php echo ($plugin['result'])?JText::_('Installed'):JText::_('Not installed'); ?></strong></td>
		</tr>
		<?php endforeach; ?>
		<?php endif; ?>
	</tbody>
</table>