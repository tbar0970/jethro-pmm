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
		<table class="table table-hover table-auto-width">
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
			$congs = $GLOBALS['system']->getDBObjectData('congregation');
			$congObject = new Congregation();
			foreach ($congs as $id => $cong) {
				$congObject->populate($id, $cong);
				$rows[$cong['name']] = Array(
					'active'=>$congObject->isActive(),
					'stats'=>Person::getStatusStats($id),
					'congid'=>$id
				);
			}
			$rows['Whole System'] = Array(
				'active'=>true,
				'stats'=>$stats,
				'congid'=>NULL
			);
			foreach ($rows as $name=>$row) {
				?><tr class="<?php echo $row['active'] ? '' : 'archived'; echo $row['congid'] ? '' : 'info';  ?>">
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