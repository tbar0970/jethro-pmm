		<tr>
			<th class="narrow">ID</th>
			<th>Name</th>
		<?php
		foreach ($special_fields as $field) {
			?>
			<th><?php echo ucwords(str_replace('_', ' ', $field)); ?></th>
			<?php
		}
		?>
			<th>Status</th>
			<th>Age</th>
			<th>Gender</th>
		<?php 
		if (defined('PERSON_LIST_SHOW_GROUPS') && PERSON_LIST_SHOW_GROUPS){
			?>
			<th>Groups</th>
			 <?php
		}
		if ($show_actions) {
			?>
			<th>Actions</th>
			<th class="narrow selector form-inline"><input type="checkbox" class="select-all" title="Select all" /></th>
			<?php
		}
		?>
		</tr>