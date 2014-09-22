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
		// TODO: Tabs for categories.
		?>
		<form class="well">
			<input type="hidden" name="view" value="<?php echo ents($_REQUEST['view']); ?>" />
			Show components used by
			<?php
			$congs = $GLOBALS['system']->getDBObjectData('congregation', Array('!meeting_time' => ''), 'AND', 'name');
			$options = Array('' => 'Any Congregation');
			foreach ($congs as $id => $cong) $options[$id] = $cong['name'];
			$options['-'] = 'No congregation';
			print_widget('congregationid', Array(
				'type' => 'select',
				'options' => $options,
			), array_get($_REQUEST, 'congregationid')); ?>
			<input type="submit" class="btn" value="Go" />
		</form>

		<div class="row-fluid">
			<div class="span6 anchor-bottom">
				<?php
				$cats = $GLOBALS['system']->getDBObjectdata('service_component_category');
				$congRestriction = Array();
				if (!empty($_REQUEST['congregationid'])) $congRestriction['congregationid'] = (int)$_REQUEST['congregationid'];
				foreach ($cats as $catid => $cat) {
					?>
					<h3>
						<a class="pull-right" href="<?php echo build_url(Array('view' => '_add_service_component', 'categoryid' => $catid)); ?>"><small><i class="icon-plus-sign"></i>Add</small></a>
						<?php echo ents($cat['category_name']); ?>
					</h3>
					<table class="table table-bordered service-comps clickable-rows">
						<tr>
							<th>Title</th>
							<th></th>
						<?php
						if (empty($_REQUEST['congregationid'])) {
							?>
							<th>Used By</th>
							<?php
						}
						?>
					<?php
					$comps = $GLOBALS['system']->getDBObjectData('service_component', Array('categoryid' => $catid)+$congRestriction, 'AND');
					foreach ($comps as $compid => $comp) {
						?>
						<tr data-id="<?php echo (int)$compid; ?>">
							<td ><?php echo ents($comp['title']); ?></td>
							<td><?php echo ents($comp['alt_title']); ?></td>
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
					</table>
					<?php
				}
				?>
			</div>
			
			<div class="span6 well preview-pane anchor-bottom" id="preview">
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

