<?php
/**
 * Shared "sharing consent" table (#792), used by the members-area Sharing page
 * and the staff person record's Sharing tab, so both render identically.
 *
 * Each group row has two independent columns, Share my Name (listed in the
 * directory) and Share my Contact Info (email/phone shown with the name).  A
 * column either holds an explicit choice or is read-only and follows the
 * person's default row, displaying the default's current state.  DataStar
 * signals keep the inherited mirrors in step when a default checkbox is
 * toggled: each column has a "shareInherits..." and "shareChoice..." signal,
 * and the default row binds "shareDefault..." signals.
 *
 * Declared variables:
 * @var array       $shareGroups          Could-share groups from Person_Group::getGroups()
 * @var array|NULL  $shareDefault         Row from Person_Group::getDefaultShareConsent()
 * @var bool        $shareCanEdit         Render editable checkboxes
 * @var bool        $shareDefaultEditable Render the default row as editable checkboxes
 * @var string      $shareGroupUrl        sprintf format (one %d) for the group link
 */
$shareColumns = Array(
	'visible' => Array('label' => 'Share my Name', 'bit' => 1, 'inherit' => 1),
	'contact' => Array('label' => 'Share my Contact Info', 'bit' => DATA_PERM_VIEWEMAIL, 'inherit' => 2),
);
$shareProvenance = Array(
	'staff' => _('set by staff'),
	'user' => _('set by member'),
	'upgrade' => _('migrated from the old group setting'),
);
$shareDefaultMask = $shareDefault ? (int)$shareDefault['data_permissions'] : 0;
$shareDefaultDetail = '';
if ($shareDefault) {
	$shareDefaultDetail = format_date($shareDefault['consented_on'])
		.' ('.($shareProvenance[$shareDefault['granted_by']] ?? $shareDefault['granted_by']).')';
}
$shareSignals = Array(
	'shareDefaultVisible' => ($shareDefaultMask & 1) ? 'true' : 'false',
	'shareDefaultContact' => ($shareDefaultMask & DATA_PERM_VIEWEMAIL) ? 'true' : 'false',
);
foreach ($shareGroups as $gid => $g) {
	$explicitRow = $g['share_data_permissions'] !== NULL;
	$explicitMask = $explicitRow ? (int)$g['share_data_permissions'] : 0;
	$inheritMask = $explicitRow ? (int)$g['share_inherit_mask'] : 3;
	foreach ($shareColumns as $key => $col) {
		$shareSignals['shareInherits'.ucfirst($key).'_'.$gid] = ($inheritMask & $col['inherit']) ? 'true' : 'false';
		$shareSignals['shareChoice'.ucfirst($key).'_'.$gid] = ($explicitMask & $col['bit']) ? 'true' : 'false';
	}
}
$shareSignalJson = '{'.implode(', ', array_map(
	function ($name, $value) { return $name.': '.$value; },
	array_keys($shareSignals), $shareSignals
)).'}';
?>
<table class="table no-borders share-consent-table table-condensed table-striped table-auto-width"<?php if ($shareCanEdit) echo ' data-signals="'.ents($shareSignalJson).'"'; ?>>
	<thead>
	<tr>
		<th>Group</th>
		<?php foreach ($shareColumns as $col) { ?>
			<th class="share-consent-column"><?php echo _($col['label']); ?></th>
		<?php } ?>
		<th>Consent recorded</th>
	</tr>
	</thead>
	<tr>
		<td><em title="Default for shared groups, used where no individual choice has been made">Default</em></td>
		<?php foreach ($shareColumns as $key => $col) {
			$bind = 'shareDefault'.ucfirst($key);
			// Contact info implies the name here too: checking Contact checks
			// Name, and clearing Name clears Contact.
			$implication = '';
			if ($key == 'contact') {
				$implication = 'data-on:change="$shareDefaultVisible = el.checked ? true : $shareDefaultVisible"';
			} else {
				$implication = 'data-on:change="$shareDefaultContact = el.checked ? $shareDefaultContact : false"';
			}
			?>
			<td>
			<?php if ($shareDefaultEditable) { ?>
				<input type="checkbox" name="share_<?php echo $key; ?>_default" value="1" <?php if ($shareDefaultMask & $col['bit']) echo 'checked="checked" '; ?>data-bind="<?php echo $bind; ?>" <?php echo $implication; ?>/>
			<?php } else { ?>
				<input type="checkbox" class="share-readonly-box" tabindex="-1" aria-disabled="true" <?php if ($shareDefaultMask & $col['bit']) echo 'checked="checked" '; ?>/>
			<?php } ?>
			</td>
		<?php } ?>
		<td><?php echo ents($shareDefaultDetail); ?></td>
	</tr>
	<?php
	foreach ($shareGroups as $gid => $g) {
		$explicitRow = $g['share_data_permissions'] !== NULL;
		$explicitMask = $explicitRow ? (int)$g['share_data_permissions'] : 0;
		$inheritMask = $explicitRow ? (int)$g['share_inherit_mask'] : 3;
		$defaultMask = (int)$g['default_share_data_permissions'];
		?>
		<tr>
			<td><a href="<?php echo ents(sprintf($shareGroupUrl, (int)$gid)); ?>"><?php echo ents($g['name']); ?></a></td>
			<?php
			foreach ($shareColumns as $key => $col) {
				$inherits = ($inheritMask & $col['inherit']) !== 0;
				$checked = $inherits ? ($defaultMask & $col['bit']) : ($explicitMask & $col['bit']);
				$sigInh = 'shareInherits'.ucfirst($key).'_'.$gid;
				$sigChoice = 'shareChoice'.ucfirst($key).'_'.$gid;
				$sigDef = 'shareDefault'.ucfirst($key);
				$checked = ($inherits ? $defaultMask : $explicitMask) & $col['bit'];
				if ($key == 'visible') {
					$effExpr = '($'.$sigInh.' ? $'.$sigDef.' : $'.$sigChoice.')';
					$change = '$'.$sigChoice.' = el.checked; $'.$sigInh.' = false;'
						.' $shareChoiceContact_'.$gid.' = el.checked ? $shareChoiceContact_'.$gid.' : false;'
						.' $shareInheritsContact_'.$gid.' = el.checked ? $shareInheritsContact_'.$gid.' : false';
				} else {
					$effExpr = '($'.$sigInh.' ? $'.$sigDef.' : $'.$sigChoice.')';
					// Ticking contact ticks the name; unticking contact leaves the
					// name choice untouched.
					$change = '$'.$sigChoice.' = el.checked; $'.$sigInh.' = false;'
						.' $shareChoiceVisible_'.$gid.' = el.checked ? true : $shareChoiceVisible_'.$gid.';'
						.' $shareInheritsVisible_'.$gid.' = el.checked ? false : $shareInheritsVisible_'.$gid;
				}
				?>
				<td role="group" aria-label="<?php echo ents($col['label'].' for '.$g['name']); ?>">
				<?php if ($shareCanEdit) { ?>
					<span class="sr-only" id="share-mode-<?php echo $key; ?>-<?php echo (int)$gid; ?>" data-text="$<?php echo $sigInh; ?> ? 'Uses the default' : 'Set directly'"><?php echo $inherits ? _('Uses the default') : _('Set directly'); ?></span>
					<input type="checkbox" name="share_<?php echo $key; ?>[<?php echo (int)$gid; ?>]" value="1" class="share-consent-box"
						aria-label="<?php echo _('Shared'); ?>" aria-describedby="share-mode-<?php echo $key; ?>-<?php echo (int)$gid; ?>"
						<?php if ($checked) echo 'checked="checked" '; ?><?php if ($inherits) echo 'disabled="disabled" '; ?>
						data-effect="el.checked = <?php echo $effExpr; ?>; el.disabled = $<?php echo $sigInh; ?>"
						data-on:change="<?php echo $change; ?>" />
					<input type="checkbox" name="share_inherit_<?php echo $key; ?>[<?php echo (int)$gid; ?>]" value="1" style="display:none"
						data-bind="<?php echo $sigInh; ?>" <?php if ($inherits) echo 'checked="checked" '; ?>/>
					<span id="share-set-individually-state-<?php echo $key; ?>-<?php echo (int)$gid; ?>" class="share-set-individually" <?php if ($inherits) echo 'style="display:none" '; ?>
						data-show="!$<?php echo $sigInh; ?>"><?php echo _('set directly');?></span>
					<a href="#" id="share-set-individually-<?php echo $key; ?>-<?php echo (int)$gid; ?>" class="share-set-individually-link" <?php if (!$inherits) echo 'style="display:none" '; ?>
						data-show="$<?php echo $sigInh; ?>" data-on:click__prevent="$<?php echo $sigChoice; ?> = $<?php echo 'shareDefault'.ucfirst($key); ?>; $<?php echo $sigInh; ?> = false"><?php echo _('set directly');?></a>
					<span class="share-mode-separator"> | </span>
					<a href="#" id="share-use-default-<?php echo $key; ?>-<?php echo (int)$gid; ?>" class="share-use-default-link" <?php if ($inherits) echo 'style="display:none" '; ?>
						data-show="!$<?php echo $sigInh; ?>" data-on:click__prevent="$<?php echo $sigInh; ?> = true"><?php echo _('use default');?></a>
					<span id="share-uses-default-<?php echo $key; ?>-<?php echo (int)$gid; ?>" class="share-uses-default" <?php if (!$inherits) echo 'style="display:none" '; ?>
						data-show="$<?php echo $sigInh; ?>"><?php echo _('uses default');?></span>
				<?php } else { ?>
					<input type="checkbox" disabled="disabled" aria-label="<?php echo ents($col['label']); ?>" <?php if ($checked) echo 'checked="checked" '; ?>/>
				<?php } ?>
				</td>
			<?php } ?>
			<td class="share-consent-recorded"><?php
				if ($explicitRow) {
					echo ents(format_date($g['share_consented_on']))
						.' ('.($shareProvenance[$g['share_granted_by']] ?? $g['share_granted_by']).')';
					if ($inheritMask != 0) echo ' (partly uses default)';
				} else {
					echo '<span class="muted"><small>'.ents(_('Using default')).'</small></span>';
				}
				if ($shareCanEdit) { ?>
					<input type="hidden" name="share_rendered[<?php echo (int)$gid; ?>]" value="<?php echo (int)$explicitMask; ?>:<?php echo (int)$inheritMask; ?>" />
				<?php }
			?></td>
		</tr>
		<?php
	}
	?>
</table>
