		<tr>
		<?php if (SizeDetector::isWide()) {
			?>
			<th class="narrow">ID</th>
			<?php
		}
		?>
			<th><?php echo _('Name')?></th>
		<?php
		if (!SizeDetector::isNarrow()) {
			foreach ($special_fields as $field) {
				?>
				<th><?php echo _(ucwords(str_replace('_', ' ', $field))); ?></th>
				<?php
			}
		}
		?>
			<th><?php echo _('Status')?></th>
			<th><?php echo _('Age')?></th>
			<th><?php echo _('Gender')?></th>
		<?php
		include_once 'include/size_detector.class.php';
		if (!SizeDetector::isNarrow()) {
		?>
			<th><?php echo _('Mobile')?></th>
		<?php
		}
		if (defined('PERSON_LIST_SHOW_GROUPS') && PERSON_LIST_SHOW_GROUPS){
			?>
			<th><?php echo _('Groups')?></th>
			 <?php
		}
		if ($show_actions) {
			?>
			<th><?php echo _('Actions')?></th>
			<th class="narrow selector form-inline"><input type="checkbox" class="select-all" title="<?php echo _('Select all')?>" /></th>
			<?php
		}
		?>
		</tr>
