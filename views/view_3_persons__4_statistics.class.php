<?php

class View_Persons__Statistics extends View
{
	static function getMenuPermissionLevel()
	{
		return PERM_RUNREPORT;
	}

	function getTitle()
	{
		return _('Person Statistics');
	}
	
	function printView()
	{
		$GLOBALS['system']->includeDBClass('person');
		$stats = Person::getStatusStats();
		$columns = array_keys($stats);
		?>
		<table class="table table-striped table-hover table-auto-width">
			<thead>
				<tr>
					<th>Congregation</th>
					<?php
					foreach ($columns as $status_name) {
						?><th><?php echo ents($status_name); ?></th><?php
					}
					?>
				</tr>
			</thead>
			<tbody>
			<?php
			$rows = Array('System-wide'=>Array(
				'active'=>true,
				'stats'=>$stats
			));
			$congs = $GLOBALS['system']->getDBObjectData('congregation');
			$congObject = new Congregation();
			foreach ($congs as $id => $cong) {
				$congObject->populate($id, $cong);
				$rows[$cong['name']] = Array(
					'active'=>$congObject->isActive(),
					'stats'=>Person::getStatusStats($id)
				);
			}
			foreach ($rows as $name=>$row) {
				?><tr class="<?php echo $row['active'] ? '' : 'archived'; ?>">
					<td><?php echo ents($name); ?></td>
					<?php
					foreach ($columns as $status_name) {
						?>
						<td><?php echo array_get($row['stats'], $status_name, 0); ?></td>
						<?php
					}
					?>
				</tr>
				<?php
			}
			?>
			</tbody>
		</table>
		<?php
	}


}