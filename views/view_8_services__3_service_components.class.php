<?php
class View_Services__Service_Components extends View
{
	function getTitle()
	{
		return "Manage Service Components";
	}

	static function getMenuPermissionLevel()
	{
		return PERM_SERVICECOMPS;
	}

	function processView()
	{

	}

	function printView()
	{
		$congs = $GLOBALS['system']->getDBObjectData('congregation', Array('!meeting_time' => ''), 'AND', 'name');
		if (empty($congs)) {
			print_message("To edit services you must first go to admin > congregations and set the 'code name' for the relevant congregations", 'failure');
			return;
		}

		?>
		<form class="well well-small form-inline">
			<input type="hidden" name="view" value="<?php echo ents($_REQUEST['view']); ?>" />
			Show components used by
			<?php
			$options = Array('' => 'Any Congregation');
			foreach ($congs as $id => $cong) $options[$id] = $cong['name'];
			$options['-'] = 'No congregation';
			print_widget('congregationid', Array(
				'type' => 'select',
				'options' => $options,
			), array_get($_REQUEST, 'congregationid')); ?>
			<button type="submit" class="btn">Go</button>
		</form>

		<div class="row-fluid">
			<div class="span6" id="service-comp-manager">
				<?php
				$cats = $GLOBALS['system']->getDBObjectdata('service_component_category');
				$congRestriction = Array();
				if (!empty($_REQUEST['congregationid'])) $congRestriction['congregationid'] = (int)$_REQUEST['congregationid'];
				?>
				<ul class="nav nav-tabs">
					<?php
					$c = ' class="active"';
					foreach ($cats as $catid => $cat) {
						?>
						<li<?php echo $c; ?>><a data-toggle="tab" href="#cat<?php echo $catid; ?>"><?php echo ents($cat['category_name']); ?></a></li>
						<?php
						$c = '';
					}
					?>
				</ul>
				<div class="tab-content anchor-bottom">
					<?php
					$c = ' active';
					foreach ($cats as $catid => $cat) {
						?>
						<div class="tab-pane<?php echo $c; ?>" id="cat<?php echo $catid; ?>">
							<a class="pull-right" href="?view=_add_service_component&categoryid=<?php echo (int)$catid; ?>"><i class="icon-plus-sign"></i>Add</a>

							<table style="width: 100%;" class="table table-bordered service-comps clickable-rows">
								<thead>
									<tr>
										<th>Title</th>
									<?php
									if (empty($_REQUEST['congregationid'])) {
										?>
										<th>Used By</th>
										<?php
									}
									?>
									</tr>
								</thead>
								<tbody>
								<?php
								$comps = $GLOBALS['system']->getDBObjectData('service_component', Array('categoryid' => $catid)+$congRestriction, 'AND');
								foreach ($comps as $compid => $comp) {
									?>
									<tr data-id="<?php echo (int)$compid; ?>">
										<td>
											<?php
											echo ents($comp['title']);
											if ($comp['alt_title']) echo ' <span class="alt-title">'.ents($comp['alt_title']).'</span>';
											?>
										</td>
									<?php
									if (empty($_REQUEST['congregationid'])) {
										?>
										<td><?php echo ents($comp['congregations']); ?></td>
										<?php
									}
									?>
									</tr>
									<?php
								}
								?>
								</tbody>
							</table>
						</div>
						<?php
						$c = '';
					}
					?>
				</div>
			</div>
			
			<div class="span6 well well-small preview-pane anchor-bottom" id="preview">
				<p class="center"><i><br /><br />Select a component to view its details here</i></p>
			</div>
			
			<script>
				
				$(document).ready(function() {
					$('.service-comps tr').click(function() {
						$('#selected').attr('id', '');
						$(this).attr('id', 'selected');
						$('#preview').load("?call=service_comp_detail&id="+$(this).attr('data-id'));
					})
				})
			</script>

			<?php
		
	}

	
}

