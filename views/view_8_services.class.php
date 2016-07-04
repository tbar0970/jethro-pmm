<?php
/**
 * This is the view for showing/editing an individual service's run sheet.
 */
class View_services extends View
{
	private $date = NULL;
	private $congregationid = NULL;
	private $service = FALSE;
	private $editing = FALSE;

	static function getMenuPermissionLevel()
	{
		return PERM_EDITSERVICE;
	}
	
	function processView()
	{
		$this->editing = !empty($_REQUEST['editing']) && $GLOBALS['user_system']->havePerm(PERM_EDITSERVICE);
		if (!empty($_REQUEST['congregationid'])) $this->congregationid = (int)$_REQUEST['congregationid'];
		$this->date = process_widget('date', Array('type' => 'date'));
		if (empty($this->date) && !empty($_SESSION['service_date'])) {
			$this->date = $_SESSION['service_date'];
		}
		if (empty($this->congregationid) && !empty($_SESSION['service_congregationid'])) {
			$this->congregationid = $_SESSION['service_congregationid'];
		}
		if ($this->congregationid && $this->date) {
			$_SESSION['service_date'] = $this->date;
			$_SESSION['service_congregationid'] = $this->congregationid;
			$this->service = NULL;
			$serviceData = $GLOBALS['system']->getDBOBjectData('service', Array(
				'congregationid' => $this->congregationid,
				'date' => $this->date
			), 'AND');
			if (!empty($serviceData)) {
				// SAVE RUN SHEET
				$this->service = $GLOBALS['system']->getDBObject('service', key($serviceData));

				if ($this->editing) {
					$this->service->acquireLock('items');
				}

				if (!empty($_REQUEST['save_service']) && $GLOBALS['user_system']->havePerm(PERM_EDITSERVICE)) {
					if (!$this->service->haveLock('items')) {
						trigger_error("Your lock expired and your changes could not be saved");
						return;
					}
					$newItems = Array();
					foreach (array_get($_POST, 'componentid', Array()) as $rank => $compid) {
						$newItem = Array(
							'componentid' => $compid,
							'title' => $_POST['title'][$rank],
							'personnel' => $_POST['personnel'][$rank],
							'show_in_handout' => $_POST['show_in_handout'][$rank],
							'length_mins' => $_POST['length_mins'][$rank],
							'note'        => trim($_POST['note'][$rank]),
							'heading_text'     => trim($_POST['heading_text'][$rank]),
						);
						$newItems[] = $newItem;
					}
					$this->service->saveItems($newItems);
					$this->service->saveComments(process_widget('service_comments', Array('type' => 'html')));
					$this->service->releaseLock('items');
					$this->editing = FALSE;
				}
			}
		} else {
			$this->date = date('Y-m-d', strtotime('Sunday'));
		}
	}
	
	function getTitle()
	{
		return NULL;
	}

	function printView()
	{
		if ($this->service === NULL) {
			print_message("No service found for this congregation and date - add one via the service program first", 'error');
			return;
		} else if ($this->service) {
			if ($this->editing && !$this->service->haveLock('items')) {
				print_message("Somebody else is currently editing this service.  Please try again later.");
				$this->editing = FALSE;
			}
			?>
			<h1>
				<small class="pull-right">
					<a href="?view=services__list_all">
						<i class="icon-chevron-left"></i>Back to service list
					</a>
				</small>
				<?php echo ents($this->service->toString()); ?>
			</h1>
			<p class="service-details-inline">
				<?php
				$this->service->PrintFieldValue('summary_inline');
				?>
			</p>
			<?php
			$this->service->printRunSheetPersonnelFlexi();
			if ($this->editing) {
				?>
				<div class="row-fluid" id="service-planner">
				<?php
				$this->printRunSheetEditor();
				$this->printComponentSelector();
				?>
				</div>
				<?php
			} else {
				?>
				<div class="row-fluid">
					<div class="span6">
						<h3>
							<span class="pull-right">
									<small>
										<a href="<?php echo build_url(Array('editing' => 1)); ?>"><i class="icon-wrench"></i>Edit</a> &nbsp;
										&nbsp;
										<a class="med-popup" href="?call=service_plan&serviceid=<?php echo $this->service->id; ?>"><i class="icon-print"></i>Printable</a>
									</small>
							</span>
							Run Sheet
						</h3>

						<?php
						$this->service->printRunSheet();
						?>
					</div>

					<div class="span6">
						<h3>
							<span class="pull-right">
									<small>
										<a class="med-popup" href="?call=service_content&serviceid=<?php echo $this->service->id; ?>"><i class="icon-print"></i>Printable</a>
									</small>
							</span>
							Full content
						</h3>
						<div class="service-content">
							<?php
							$this->service->printServiceContent();
							?>
						</div>
					</div>
				</div>

				<?php
				
			}

		}
	
	}

	private function printRunSheetEditor()
	{
		$cong = $GLOBALS['system']->getDBObject('congregation', $this->congregationid);
		$startTime = preg_replace('/[^0-9]/', '', $cong->getValue('meeting_time'));
		$dummyItem = new Service_Item();
		?>
		<div class="span6">
			<h3>Run Sheet</h3>
			<form method="post" id="service-plan-container" data-lock-length="<?php echo LOCK_LENGTH; ?>">
			<input type="hidden" name="save_service" value="1" />
			<table class="table table-bordered table-condensed no-autofocus" id="service-plan" data-starttime="<?php echo $startTime; ?>">
				<thead>
					<tr>
						<th class="narrow">Start</th>
						<th class="narrow">#</th>
						<th>Item</th>
						<th class="personnel">Personnel</th>
						<th class="narrow">&nbsp</th>
					</tr>
				</thead>

				<tbody>
				<?php
				$items = $this->service->getItems();
				if (empty($items)) {
					?>
					<tr id="service-plan-placeholder">
						<td colspan="5" style="padding: 50px; text-align: center">
							<?php
							if ($this->editing) {
								?>
								<i>Drag or double-click components to add them to this service</i>
								<?php
							} else {
								?>
								<i>This service does not yet have any items</i>
								<?php
							}
							?>
						</td>
					</tr>
					<?php
				} else {
					foreach ($items as $rank => $item) {
						if (strlen($item['heading_text'])) {
							?>
							<tr>
								<td colspan="4">
									<input type="text" class="service-heading unfocused" name="" value="<?php echo ents($item['heading_text']); ?>" />
								</td>
								<td class="tools">
									<a href="javascript:;" data-action="remove"><i class="icon-trash"></i></a>
								</td>
							</tr>
							<?php
						}
						?>
						<tr class="service-item<?php if (empty($item['componentid'])) echo ' ad-hoc'; ?>">
							<td class="start"></td>
							<td class="number"></td>
							<td class="item">
								<span>
								<?php
								if (!empty($item['runsheet_title_format'])) {
									$title = $item['runsheet_title_format'];
									$title = str_replace('%title%', $item['title'], $title);
									$title = $this->service->replaceKeywords($title);
								} else {
									$title = $item['title'];
								}
								echo ents($title);
								?>
								</span>
								<?php
								print_hidden_field('title[]', $title);
								foreach (Array('componentid', 'length_mins', 'show_in_handout') as $k) {
									?>
									<input type="hidden" name="<?php echo $k; ?>[]" class="<?php echo $k; ?>" value="<?php echo ents($item[$k]); ?>" />
									<?php
								}
								?>
								<textarea name="note[]" class="unfocused" 
									<?php
									if (!strlen($item['note'])) {
										echo 'style="display:none" ';
										echo 'rows="1" ';
									} else {
										echo 'rows="'.(substr_count($item['note'], "\n")+1).'" ';
									}
									?>><?php echo ents($item['note']); ?></textarea>
							</td>
							<td class="personnel"><input class="unfocused" name="personnel[]" type="text" value="<?php echo ents($item['personnel']); ?>" /></td>
							<td class="tools">
								<?php $this->_printTools(); ?>
							</td>
						</tr>
						<?php

					}
				}
				?>
					<tr id="service-item-template">
						<td class="start"></td>
						<td class="number"></td>
						<td class="item">
							<span></span>
							<textarea name="note[]" class="unfocused" rows="1" style="display: none"></textarea>
						</td>
						<td class="personnel"><input name="personnel[]" type="text" value="" /></td>
						<td class="tools"><?php $this->_printTools(); ?></td>
					</tr>
					<tr id="service-heading-template">
						<td colspan="4">
							<input class="service-heading" name="" />
						</td>
						<td class="tools"><a href="javascript:;" data-action="remove"><i class="icon-trash"></i></a></td>
					</tr>

				</tbody>

				<tfoot>
					<tr>
						<td colspan="5">
							<?php
							$this->printNotesFields();
							?>
						</td>
					</tr>
					<tr>
						<td colspan="5">
							<button type="submit" class="btn">Save</button>
						</td>
					</tr>
				</tfoot>
			</table>
			</form>

			<div class="modal hide fade-in" id="ad-hoc-modal" role="dialog">
				<div class="modal-header">
					<h4>Add ad-hoc service item</h4>
				</div>
				<div class="modal-body form-horizontal">
					<div class="control-group">
						<label class="control-label">
							Title
						</label>
						<div class="controls">
							<?php $dummyItem->printFieldInterface('title'); ?>
						</div>
					</div>
					<div class="control-group">
						<label class="control-label">
							Show in handout?
						</label>
						<div class="controls">
							<?php 
							$dummyItem->setValue('show_in_handout', 'title');
							$dummyItem->printFieldInterface('show_in_handout'); ?>
						</div>
					</div>
					<div class="control-group">
						<label class="control-label">
							Length
						</label>
						<div class="controls">
							<?php
							$dummyItem->setValue('length_mins', 2);
							$dummyItem->printFieldInterface('length_mins');
							?>
							mins
						</div>
					</div>
				</div>
				<div class="modal-footer">
					<input class="btn" type="button" value="Save item" data-action="saveItemDetails" />
					<input class="btn" type="button" value="Cancel" data-dismiss="modal" />
				</div>
				
			</div>
 		</div>
		<?php
	}

	private function _printTools()
	{
		?><div class="dropdown">
			<a href="#" class="dropdown-toggle" data-toggle="dropdown"><i class="icon-chevron-down"></i></a>
		<ul class="dropdown-menu pull-right">
			<li><a href="javascript:;" data-action="addHeading">Add heading above</a></li>
			<li class="divider"></li>
			<li><a href="javascript:;" data-action="addNote">Add note</a></li>
			<li class=""><a href="javascript:;" data-action="editDetails">Edit item detail</a>
			<li class="hidden-ad-hoc"><a href="javascript:;" data-action="viewCompDetail">View component</a>
			<li><a href="javascript:;" data-action="remove">Remove</a></li>
			<li class="divider"></li>
			<li><a href="javascript:;" data-action="addAdHoc">Add ad-hoc item below</a></li>
		</ul>
		</div><?php

	}

	private function printComponentSearch()
	{
		?>
			<div id="component-search" class="input-append input-prepend no-autofocus">
				<span class="add-on"><i class="icon-search"></i></span>
				<input type="text" placeholder="Search components">
				<?php
				print_widget('tag', Array('type' => 'reference', 'references' => 'service_component_tag', 'allow_empty' => TRUE, 'empty_text' => '-- Choose Tag --'), NULL);
				?>
				<button data-action="search" class="btn" type="button">Filter</button>
				<button data-action="clear" class="btn" type="button">Clear</button>
			</div>

		<?php
	}

	private function printComponentSelector()
	{
		?>
		<div class="span6">
			<h3>
				<small class="pull-right">
					<a href="?view=services__component_library">
						<i class="icon-wrench"></i>Manage
					</a>
				</small>
				Component Library
			</h3>
			<?php $this->printComponentSearch(); ?>
			<ul class="nav nav-tabs">
				<?php
				$cats = $GLOBALS['system']->getDBObjectData('service_component_category', Array(), 'AND', 'category_name');
				$active = 'class="active"';
				foreach ($cats as $catid => $cat) {
					?>
					<li <?php echo $active; ?>><a data-toggle="tab" href="#cat<?php echo (int)$catid; ?>"><?php echo ents($cat['category_name']); ?></a></li>
					<?php
					$active = '';
				}
				?>
			</ul>
			<div class="tab-content" id="service-comps">
				<?php
				$active = 'active';
				foreach ($cats as $catid => $cat) {
					$comps = $GLOBALS['system']->getDBObjectData('service_component', Array(
						'cong.id' => $this->congregationid,
						'categoryid' => $catid
					), 'AND', 'usage_12m');
					?>
					<div class="tab-pane <?php echo $active; ?>"
						 id="cat<?php echo (int)$catid; ?>">

						<table style="display: none" class="table table-bordered table-condensed"
							   title="Double-click or drag to add to service">
							<thead>
								<tr>
									<th data-sort="string">Title</th>
									<th data-sort="string" class="narrow" title="Date when this component was last used in a service">Last</th>
									<th data-sort="int" class="narrow" title="Number of usages in last month">1m</th>
									<th data-sort="int" class="narrow" title="Number of usages in last 12 months">12m<i class="icon-arrow-up"></i></th>
									<th class="narrow"></th>
								</tr>
							</thead>
							<tbody>
							<?php
							foreach ($comps as $compid => $comp) {
								$runsheetTitle = $comp['runsheet_title_format'];
								if (strlen($runsheetTitle)) {
									$runsheetTitle = str_replace('%title%', $comp['title'], $runsheetTitle);
									$runsheetTitle = $this->service->replaceKeywords($runsheetTitle);
								}
								$comp['personnel'] = $this->service->replaceKeywords($comp['personnel']);

								?>
								<tr data-componentid="<?php echo (int)$compid; ?>"
									data-show_in_handout="<?php echo $comp['show_in_handout']; ?>"
									data-length_mins="<?php echo (int)$comp['length_mins']; ?>"
									data-runsheet_title="<?php echo ents($runsheetTitle); ?>"
									data-personnel="<?php echo ents($comp['personnel']); ?>"
								>
									<td>
										<span class="title"><?php echo ents($comp['title']); ?></span>
										<?php
										if ($comp['alt_title']) {
											echo ' <span class="alt-title">'.ents($comp['alt_title']).'</span>';
										}
										?>
									</td>
									<td class="hide-in-transit nowrap" data-sort-value="<?php echo ents($comp['lastused']); ?>">
										<?php
										if ($comp['lastused']) echo format_date($comp['lastused'], FALSE);
										?>
									</td>
									<td>
										<?php echo $comp['usage_1m']; ?>
									</td>
									<td>
										<?php echo $comp['usage_12m']; ?>
									</td>
									<td class="tools">
										<a href="?call=service_comp_detail&id=<?php echo $compid; ?>&head=1" class="med-popup" title="View component detail"><i class="icon-eye-open"> </i></a>
									</td>
								</tr>
								<?php
							}
							?>
							</tbody>
						</table>
					</div>
					<?php
					$active = '';
				}
				?>
			</div>
		</div>
		<?php
	}

	private function printNotesFields()
	{
		echo '<b>Comments</b>:';
		print_widget('service_comments', $this->service->fields['comments'], $this->service->getValue('comments'));
	}
}

