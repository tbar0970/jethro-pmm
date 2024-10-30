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
		return PERM_VIEWSERVICE;
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
				$this->service = $GLOBALS['system']->getDBObject('service', key($serviceData));

				if ($this->editing) {
					$this->service->acquireLock('items');
					if (!$this->service->haveLock('items')) {
						trigger_error("Your lock expired and your changes could not be saved");
						return;
					}

					if (!empty($_REQUEST['copy_service_id'])) {
						$fromService = new Service((int)$_REQUEST['copy_service_id']);
						if (!$fromService) {
							trigger_error("Service ".(int)$_REQUEST['copy_service_id']." not found - could not copy");
							return;
						}
						$newItems = $fromService->getItems();
						foreach ($newItems as $k => $v) {
							$newItems[$k]['personnel'] = '';
							if (!empty($v['componentid'])) {
								$comp = $GLOBALS['system']->getDBObject('service_component', $v['componentid']);
								if ($comp) {
									$newItems[$k]['personnel'] = $this->service->replaceKeywords($comp->getValue('personnel'));
								}
							}

							if (!in_array($v['categoryid'], $_REQUEST['copy_category_ids'])) {
								unset($newItems[$k]);
							}
						}
						$this->service->saveItems($newItems);
						// Retain lock and stay in editing mode
					}

					if (!empty($_REQUEST['save_service'])) {
						$newItems = Array();
						foreach (array_get($_POST, 'componentid', Array()) as $rank => $compid) {
							$newItem = Array(
								'componentid' => $compid,
								'title' => $_POST['title'][$rank],
								'personnel' => array_get($_POST['personnel'], $rank),
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
			}
		} else {
			$this->date = date('Y-m-d', strtotime('Sunday'));
		}
	}

	function getTitle()
	{
		if ($this->service) {
			if ($this->editing) {
				return 'Editing service';
			} else {
				return 'Viewing service';
			}
		}
		return NULL;
	}

	function getPageHeading()
	{
		return '';
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
						<i class="icon-chevron-left"></i>Back to service schedule
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
						<?php
						if ($GLOBALS['user_system']->havePerm(PERM_EDITSERVICE)) {
							?>
							<span class="pull-right">
									<small>
										<a href="<?php echo build_url(Array('editing' => 1)); ?>"><i class="icon-wrench"></i>Edit</a> &nbsp;
										&nbsp;
										<a class="med-popup" href="?call=service_plan&serviceid=<?php echo $this->service->id; ?>"><i class="icon-print"></i>Printable</a>
									</small>
							</span>
							<?php
						}
						echo _('Run Sheet');
						?>
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
										<a class="" href="?call=service_slides&serviceid=<?php echo $this->service->id; ?>"><i class="icon-film"></i>Slides</a>
									</small>
							</span>
							Service Handout
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
		$items = $this->service->getItems();
		$dummyItem = new Service_Item();
		?>
		<div class="span6">
			<h3>
				<?php
				if (empty($items)) {
					?>
					<span class="pull-right">
							<small>
								<a href="#" data-toggle="modal" data-target="#copy-previous-modal">
									<i class="icon-share"></i><?php echo _('Copy from previous'); ?>
								</a>
							</small>
					</span>
					<?php
				}
				?>
				Run Sheet
			</h3>
			<form method="post" id="service-planner-form" data-lock-length="<?php echo db_object::getLockLength() ?>">
			<div id="service-plan-container">
			<input type="hidden" name="save_service" value="1" />
			<table class="table table-bordered table-hover table-condensed no-autofocus" id="service-plan" data-starttime="<?php echo $startTime; ?>">
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
				?>
					<tr id="service-item-template">
						<td class="start"></td>
						<td class="number"></td>
						<td class="item">
							<span></span>
							<textarea name="note[]" class="unfocused" rows="1" style="display: none"></textarea>
						</td>
						<td class="personnel"><input name="personnel[]" type="text" value="" class="unfocused" /></td>
						<td class="tools"><?php $this->_printTools(); ?></td>
					</tr>
					<tr id="service-heading-template">
						<td colspan="4">
							<input class="service-heading" name="" />
						</td>
						<td class="tools"><a href="javascript:;" data-action="remove"><i class="icon-trash"></i></a></td>
					</tr>
					<tr id="service-plan-spacer">
						<td colspan="5">
							<?php
							if (empty($items) && $this->editing) {
								?>
								<div class="alert alert-info" id="service-plan-placeholder">
								To start building this run sheet,
								<ul>
									<li>drag an item from the component library</li>
									<li><a href="#" data-toggle="modal" data-target="#ad-hoc-modal">enter an ad hoc item</a>, or </li>
									<li><a href="#" data-toggle="modal" data-target="#copy-previous-modal">copy items from a previous service</a></li>
								</ul>
								</div>
								<?php
							}
							?>
						</td>
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
							
						</td>
					</tr>
				</tfoot>
			</table>
			</div>
			<button type="submit" class="btn">Save</button>
			</form>
 		</div>

		<!-- ad-hoc item modal -->
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

		<!-- copy-from-previous modal -->
		<div class="modal hide fade-in" id="copy-previous-modal" role="dialog">
			<form method="post">
			<div class="modal-header">
				<h4>Copy items from another service</h4>
			</div>
			<div class="modal-body form-horizontal">
				<div class="control-group">
					<label class="control-label">
						Service to copy from
					</label>
					<div class="controls">
						<?php
						$options = Array();
						$selectedID = NULL;
						$services = $GLOBALS['system']->getDBObjectData('service', Array('>date' => date('Y-m-d', strtotime('-1 year'))), 'AND', 'date DESC');
						$dummyService = new Service();
						foreach ($services as $id => $s) {
							if (!$s['has_items']) continue;
							if ($id == $this->service->id) continue;
							$dummyService->populate($id, $s);
							$options[$id] = $dummyService->toString();
							if (empty($selectedID)
								&& $s['congregationid'] == $this->service->getValue('congregationid')
								&& $s['date'] < $this->service->getValue('date')) {
								$selectedID = $id;
							}
						}
						print_widget('copy_service_id', Array('type' => 'select', 'options' => $options), $selectedID);
						?>
					</div>
				</div>
				<div class="control-group">
					<label class="control-label">
						Items to copy
					</label>
					<div class="controls">
						<?php
						$params = Array(
							'type' => 'reference',
							'references' => 'service_component_category',
							'allow_multiple' => TRUE
						);
						print_widget('copy_category_ids[]', $params, '*');
						?>
					</div>
				</div>
			</div>
			<div class="modal-footer">
				<input class="btn" type="submit" value="Copy items" />
				<input class="btn" type="button" value="Cancel" data-dismiss="modal" />
			</div>
			</form>
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
			<li><a href="javascript:;" data-action="remove">Remove item</a></li>
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
		$serviceTS = strtotime($this->service->getValue('date'));
		?>
		<div class="span6">
			<h3>
				<span class="pull-right">
					<small>
					<a href="?view=services__component_library">
						<i class="icon-wrench"></i>Manage
					</a>
					</small>
				</span>
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
						<div class="comps-table-container">

						<table style="display: none" class="table table-bordered table-condensed table-sortable"
							   title="Double-click or drag to add to service">
							<thead>
								<tr>
									<th data-sort="string-ins" id="title">Title</th>
									<th data-sort="int" id="weeks" data-sort-multicolumn="title" class="narrow" title="Weeks since last usage">Last</th>
									<th data-sort="int" data-sort-multicolumn="weeks" data-sort-default="desc" class="narrow" title="Number of usages in last 12 months">12m<i class="icon-arrow-up"></i></th>
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
								$lastUse = '';
								$lastUseSort = 0;
								if ($comp['lastused']) {
									$lastTS = strtotime($comp['lastused']);
									$lastUseSort = (int)$lastTS;
									if ($lastTS == $serviceTS) {
										$lastUse = 'now';
									} else if ($lastTS > $serviceTS) {
										$lastUse = '+'.ceil(($lastTS - $serviceTS) / 60 / 60 / 24 / 7).'w';
									} else {
										$lastUse = ceil(($serviceTS - $lastTS) / 60 / 60 / 24 / 7).'w';
									}
								}

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
									<td data-sort-value="<?php echo $lastUseSort; ?>" class="hide-in-transit">
										<?php echo $lastUse; ?>
									</td>
									<td class="hide-in-transit">
										<?php echo $comp['usage_12m']; ?>x
									</td>
									<td class="tools hide-in-transit">
										<a href="?call=service_comp_detail&id=<?php echo $compid; ?>&head=1" class="med-popup" title="View component detail"><i class="icon-eye-open"> </i></a>
									</td>
								</tr>
								<?php
							}
							?>
							</tbody>
						</table>
						</div>
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

