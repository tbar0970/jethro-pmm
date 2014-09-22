<?php
class View_Services__Service_Details extends View
{
	private $date = NULL;
	private $congregationid = NULL;
	private $service = FALSE;
	private $editing = FALSE;
	
	function processView()
	{
		$this->editing = !empty($_REQUEST['editing']) && $GLOBALS['user_system']->havePerm(PERM_EDITSERVICE);
		if (!empty($_REQUEST['congregationid'])) $this->congregationid = (int)$_REQUEST['congregationid'];
		$this->date = process_widget('date', Array('type' => 'date'));
		if ($this->congregationid && $this->date) {
			$this->service = NULL;
			$serviceData = $GLOBALS['system']->getDBOBjectData('service', Array(
				'congregationid' => $this->congregationid,
				'date' => $this->date
			), 'AND');
			if (!empty($serviceData)) {
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
					foreach ($_POST['componentid'] as $rank => $compid) {
						$newItem = Array(
							'componentid' => $compid,
							'is_numbered' => $_POST['is_numbered'][$rank],
							'length_mins' => $_POST['length_mins'][$rank],
							'note'        => trim($_POST['note'][$rank]),
							'heading_text'     => trim($_POST['heading_text'][$rank]),
						);
						$newItems[] = $newItem;
					}
					$this->service->saveItems($newItems);
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
		?>
		<form method="get" class="well">
			<input type="hidden" name="view" value="<?php echo ents($_REQUEST['view']); ?>" />
			<select name="editing">
				<option value="0">View</option>
				<option value="1" <?php if ($this->editing) echo 'selected="selected"'; ?>>Edit</option>
			</select>
			the
			<?php print_widget('congregationid', Array(
				'type' => 'reference',
				'references' => 'congregation',
				'allow_empty' => false,
			), $this->congregationid); ?>
			service on 
			<?php 
			// TODO: sticky dates (mmm)
			print_widget('date', Array('type' => 'date'), $this->date); ?>
			<button type="submit" class="btn">Go</button>
		</form>	
		<?php

		if ($this->service === NULL) {
			print_message("No service found for this congregation and date - add one via the service program first", 'error');
			return;
		} else if ($this->service) {
			if ($this->editing && !$this->service->haveLock('items')) {
				print_message("Somebody else is currently editing this service.  Please try again later.");
				$this->editing = FALSE;
			}
			if ($this->editing) {
				?>
				<div class="row-fluid" id="service-planner">
				<?php
				$this->printServicePlan();
				$this->printComponentSelector();
				?>
				</div>
				<?php
			} else {
				echo '<h1>'.$this->service->toString().'</h1>';
				$this->service->printServicePlan();
			}

		}
	
	}

	private function printServicePlan()
	{

		// TODO: Lock expiry warning

		$cong = $GLOBALS['system']->getDBObject('congregation', $this->congregationid);
		$startTime = preg_replace('/[^0-9]/', '', $cong->getValue('meeting_time'))
		?>
		<div class="span6"s>
			<h1>
				<a class="pull-right med-popup" href="?call=service_plan&serviceid=<?php echo $this->service->id; ?>"><small>Printable</small></a>
				<?php echo ents($this->service->toString()); ?>
			</h1>
			<form method="post" id="service-plan-container">
			<input type="hidden" name="save_service" value="1" />
			<table class="table table-bordered" id="service-plan" data-starttime="<?php echo $startTime; ?>">
				<thead>
					<tr>
						<th class="narrow">Start</th>
						<th class="narrow">#</th>
						<th>Item</th>
						<th class="narrow">&nbsp</th>
					</tr>
				</thead>

				<tbody>
				<?php
				$items = $this->service->getItems();
				if (empty($items)) {
					?>
					<tr id="service-plan-placeholder">
						<td colspan="4" style="padding: 50px; text-align: center">
							<?php
							if ($this->editing) {
								?>
								Drag or double-click components to add them to this service
								<?php
							} else {
								?>
								This service does not yet have any items
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
								<td colspan="3">
									<input type="text" class="service-heading unfocused" name="" value="<?php echo ents($item['heading_text']); ?>" />
								</td>
								<td class="tools">
									<a href="javascript:;" data-action="remove"><i class="icon-trash"></i></a>
								</td>
							</tr>
							<?php
						}
						?>
						<tr class="service-item">
							<td class="start"></td>
							<td class="number"></td>
							<td class="item">
								<span>
								<?php
								if (!empty($item['runsheet_title_format'])) {
									echo ents(str_replace('%title%', $item['title'], $item['runsheet_title_format']));
								} else {
									echo ents($item['title']);
								}
								?>
								</span>
								<?php
								foreach (Array('componentid', 'length_mins', 'is_numbered') as $k) {
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
						<td class="tools"><?php $this->_printTools(); ?></td>
					</tr>
					<tr id="service-heading-template">
						<td colspan="3">
							<input class="service-heading" name="" />
						</td>
						<td class="tools"><a href="javascript:;" data-action="remove"><i class="icon-trash"></i></a></td>
					</tr>

				</tbody>

				<tfoot>
					<tr>
						<td colspan="4">
							<button type="submit" class="btn">Save</button>
						</td>
					</tr>
				</tfoot>
			</table>
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
			<li><a href="javascript:;" data-action="addNote">Add note</a></li>
			<li><a href="javascript:;" data-action="remove">Remove</a></li>
		</ul>
		</div><?php

	}

	private function printComponentSelector()
	{
		?>
		<div class="span6">
			<h1>Available Components</h1>
			<div id="component-search" class="input-append">
				<input type="text" placeholder="Enter search terms">
				<button data-action="search" class="btn" type="button">Filter</button>
				<button data-action="clear" class="btn" type="button">Clear</button>
			</div>
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
					), 'AND', 'title');
					?>
					<div class="tab-pane <?php echo $active; ?>" id="cat<?php echo (int)$catid; ?>">
						<table class="table table-bordered">
							<thead>
								<tr>
									<th data-sort="string">Title <i class="icon-arrow-up"></i></th>
									<th data-sort="string" class="narrow">Last Used</th>
								</tr>
							</thead>
							<tbody>
							<?php
							foreach ($comps as $compid => $comp) {
								?>
								<tr data-componentid="<?php echo (int)$compid; ?>"
									data-is_numbered="<?php echo (int)$comp['is_numbered']; ?>"
									data-length_mins="<?php echo (int)$comp['length_mins']; ?>"
									data-runsheet_title_format="<?php echo ents($comp['runsheet_title_format']); ?>">
									<td>
										<span class="title"><?php echo ents($comp['title']); ?></span>
										<?php
										if ($comp['alt_title']) {
											echo ' <span class="alt-title">('.ents($comp['alt_title']).')</span>';
										}
										?>
									</td>
									<td class="hide-in-transit nowrap" data-sort-value="<?php echo ents($comp['lastused']); ?>">
										<?php
										if ($comp['lastused']) echo format_date($comp['lastused']);
										?>
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
}

