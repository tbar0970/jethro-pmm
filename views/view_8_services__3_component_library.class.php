<?php
class View_Services__Component_Library extends View
{
	function getTitle()
	{
		return "Service Component Library";
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
			print_message("To edit services you must first go to admin > congregations and enable services for relevant congregations", 'failure');
			return;
		}
		?>
		<form class="well well-small form-inline" style="line-height: 35px">
			<input type="hidden" name="view" value="<?php echo ents($_REQUEST['view']); ?>" />
			Show components containing
			<input type="text" name="search" placeholder="Enter search terms" value="<?php echo ents(array_get($_REQUEST, 'search')); ?>">
			<span style="white-space: nowrap">
				tagged with&nbsp;
				<?php print_widget('tagid', Array('type' => 'reference', 'references' => 'service_component_tag', 'allow_empty' => TRUE, 'empty_text' => '-- Choose Tag --'), array_get($_REQUEST, 'tagid')); ?>
			</span>
			 used by 
			<?php
			$options = Array('' => 'Any Congregation');
			foreach ($congs as $id => $cong) $options[$id] = $cong['name'];
			$options['-'] = 'No congregation';
			print_widget('congregationid', Array(
				'type' => 'select',
				'options' => $options,
			), array_get($_REQUEST, 'congregationid')); ?>
			<button type="submit" class="btn">Go</button>
			<a href="?view=<?php echo ents($_REQUEST['view']); ?>" class="btn">Clear</a>
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
							<p class="pull-right">
								<a href="?view=_import_service_components&categoryid=<?php echo (int)$catid; ?>"><i class="icon-upload"></i>Import</a> &nbsp;
								<a href="?view=_add_service_component&categoryid=<?php echo (int)$catid; ?>"><i class="icon-plus-sign"></i>Add</a>
							</p>

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
								$GLOBALS['system']->includeDBClass('service_component');
								$comps = Service_Component::search(array_get($_REQUEST, 'search'), array_get($_REQUEST, 'tagid'), array_get($_REQUEST, 'congregationid'), $catid);
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
			
			<div class="span6 well well-small preview-pane" id="preview">
				<p class="center"><i><br /><br />Select a component to view its details here</i></p>
			</div>
			<div class="span6 well well-small preview-pane anchor-bottom" id="usage">
				<p class="center"><i><br /><br />Select a component to view its usage here</i></p>
			</div>

			<script>
				
				$(document).ready(function() {
					$('.service-comps tr').click(function() {
						$('#selected').attr('id', '');
						$(this).attr('id', 'selected');
						$('#preview').load("?call=service_comp_detail&id="+$(this).attr('data-id'));
						$('#usage').load("?call=service_comp_usage&id="+$(this).attr('data-id'));
					})
				})
			</script>

			<?php

	}

	
}

