<?php
// no direct access
defined('_JEXEC') or die('Restricted access');

jimport('joomla.application.helper');

// PIWIK DATABASE REMOVAL
	$db = & JFactory::getDBO();
	$db->setQuery("SHOW TABLES");
	$rows = $db->loadRowList();
	for($i = 0; $i < count($rows); $i ++) {
		if (strpos($rows[$i][0], 'piwik') !== false)
			$query[] = "`" . $rows[$i][0] . "`";
	}
	
	$query = @implode(', ', $query);
	$query = "DROP TABLE " . $query;
	$db->setQuery($query);
	$db->query();

/***********************************************************************************************
 * ---------------------------------------------------------------------------------------------
 * PLUGIN REMOVAL SECTION
 * ---------------------------------------------------------------------------------------------
 ***********************************************************************************************/

jimport('joomla.installer.installer');
$db = & JFactory::getDBO();
$status = new JObject();
$status->plugins = array();
$src = $this->parent->getPath('source');

if(version_compare(JVERSION,'1.6.0','ge')) {
	$db->setQuery('SELECT `extension_id` FROM #__extensions WHERE `type` = "plugin" AND `element` = "piwik" AND `folder` = "system"');
} else {
	$db->setQuery('SELECT `id` FROM #__plugins WHERE `element` = "piwik" AND `folder` = "system"');
}

$id = $db->loadResult();
if($id) {
	$installer = new JInstaller;
	$result = $installer->uninstall('plugin', $id, 1);
	$status->plugins[] = array('name'=>'plg_piwik', 'group'=>'system', 'result'=>$result);
}

/***********************************************************************************************
 * ---------------------------------------------------------------------------------------------
 * OUTPUT TO SCREEN
 * ---------------------------------------------------------------------------------------------
 ***********************************************************************************************/
 $rows = 0;
?>
<img src="../media/com_admintools/images/admintools-48.png" width="48" height="48" alt="Admin Tools" align="right" />
<h2><?php echo JText::_('Piwik Uninstallation Status'); ?></h2>
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
			<td class="key" colspan="2"><?php echo 'Piwik '.JText::_('Component');?></td>
			<td><strong><?php echo JText::_('Removed'); ?></strong></td>
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
			<td><strong><?php echo ($plugin['result'])?JText::_('Removed'):JText::_('Not removed'); ?></strong></td>
		</tr>
		<?php endforeach; ?>
		<?php endif; ?>
	</tbody>
</table>