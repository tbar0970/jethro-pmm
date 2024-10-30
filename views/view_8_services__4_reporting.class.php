<?php
class View_Services__Reporting extends View
{
	private $_categoryid = NULL;
	private $_congregationid = NULL;
	private $_start_date = NULL;
	private $_end_date = NULL;

	function getTitle()
	{
		return "Service Component Usage Report";
	}

	static function getMenuPermissionLevel()
	{
		return PERM_VIEWSERVICE;
	}

	function processView()
	{
		$this->_start_date = process_widget('start_date', Array('type' => 'date'));
		if (empty($this->_start_date)) {
			$this->_start_date = array_get($_SESSION, 'reporting_start_date', date('Y-m-d', strtotime('-3 months')));
		} else {
			$_SESSION['reporting_start_date'] = $this->_start_date;
		}
		$this->_end_date = process_widget('end_date', Array('type' => 'date'));
		if (empty($this->_end_date)) {
			$this->_end_date = array_get($_SESSION, 'reporting_end_date', date('Y-m-d'));
		} else {
			$_SESSION['reporting_end_date'] = $this->_end_date;
		}
		$this->_categoryid = array_get($_REQUEST, 'categoryid');
		$this->_congregationid = array_get($_REQUEST, 'congregationid');
	}

	function printView()
	{
		?>
		<form class="well well-small form-inline" style="line-height: 35px">
			<input type="hidden" name="view" value="<?php echo ents($_REQUEST['view']); ?>" />
			Show usage of
			<?php
			print_widget(
				'categoryid',
				Array('type' => 'reference', 'references' => 'service_component_category'),
				$this->_categoryid
			);
			?>

			in
			<?php
			$congs = $GLOBALS['system']->getDBObjectData('congregation', Array('!meeting_time' => ''), 'AND', 'name');
			$options = Array('' => 'Any Congregation');
			foreach ($congs as $id => $cong) $options[$id] = $cong['name'];
			print_widget('congregationid', Array(
				'type' => 'select',
				'options' => $options,
			), $this->_congregationid);
			?>

			<br />between
			<?php
			print_widget('start_date', Array('type' => 'date'), $this->_start_date);
			?>
			and
			<?php
			print_widget('end_date', Array('type' => 'date'), $this->_end_date);
			?>

			<input class="btn" type="submit" value="Go" name="params_submitted" />
		</form>
		<?php

		if (!empty($_REQUEST['params_submitted'])) {
			$stats = Service_Item::getComponentStats($this->_start_date, $this->_end_date, $this->_categoryid, $this->_congregationid);
			$dummy = new Service_Component();
			
			$got_ccli = FALSE;
			if (ifdef('CCLI_REPORT_URL')) {
				foreach ($stats as $comp) {
					if ($comp['ccli_number']) {
						$got_ccli = TRUE;
						break;
					}
				}
			}
			if ($got_ccli) {
				?>
				<p><i>Click "Report" to open the item in the CCLI reporting site. <br />Green ticks indicate which items you've clicked on, but don't guarantee you've actually completed a report for that item.</i></p>
				<?php
			}
			?>
			<table class="table table-condensed table-bordered table-auto-width component-usage">
				<thead>
					<tr>
						<th>Title</th>
						<th>CCLI #</th>
						<th>Usages</th>
						<th></th>
					</tr>
				</thead>
				<tbody>
				<?php
				foreach ($stats as $comp) {
					?>
					<tr>
						<td><a class="med-popup" href="?view=_edit_service_component&service_componentid=<?php echo (int)$comp['id']; ?>&then=refresh_opener"><?php echo ents($comp['title']); ?></a></td>
						<td>
							<?php
							if ($comp['ccli_number']) {
								$dummy->setValue('ccli_number', $comp['ccli_number']);
								$dummy->printFieldValue('ccli_number');
							} else {
								echo '-';
							}
							?>
						</td>
						<td class="center"><?php echo (int)$comp['usage_count']; ?></td>
						<td>
							<?php
							if ($comp['ccli_number'] && ifdef('CCLI_REPORT_URL')) {
								$url = str_replace('__NUMBER__', $comp['ccli_number'], CCLI_REPORT_URL);
								?>
								<a href="<?php echo $url; ?>" class="ccli-report">Report</a>
								<span style="visibility: hidden">&#9989;</span>
								<?php
							}
							?>
						</td>
					</tr>
					<?php
				}
				?>
				</tbody>
			</table>
			<?php
		}
	}

	function NOTprintView()
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

