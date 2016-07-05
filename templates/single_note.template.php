<?php

/**
 * @var $id
 * $var $entry
 * @var $show_form
 * @var $show_edit_link
 */
		
$dummy->populate($id, $entry);
$type = (!empty($entry['familyid']) ? 'family' : 'person');
?>
<a name="note_<?php echo $id; ?>"></a>
<div class="notes-history-entry well <?php echo $type; ?>-note" id="note_<?php echo $id; ?>">
	<?php
	if (!empty($show_form) && $dummy->canEditOriginal()) {
		?>
		<a class="pull-right" href="<?php echo build_url(Array('edit_original' => 1)); ?>"><i class="icon-wrench"></i><?php echo _('Edit original note')?></a>
		<?php
	}
	?>
	<i class="icon-<?php echo $type == 'family' ? 'home' : 'user'; ?>"></i>
	<blockquote>
		<p class="subject"><?php echo ents($entry['subject']); ?></p>
	<?php
	if (strlen($entry['details'])) {
		?>
		<p class="content"><?php echo nl2br(ents($entry['details'])); ?></p>
		<?php
	}
	?>
		<small class="author">
			<?php echo _('Added by')?> 
			<?php echo $entry['creator_fn'].' '.$entry['creator_ln'].' (#'.$entry['creator'].')'; ?>
			<?php echo format_datetime($entry['created']); ?>
			<?php
			if ($entry['editor']) {
				$editor = $GLOBALS['system']->getDBObject('person', $entry['editor']);
				$name = $editor ? $editor->toString() : '(restricted user)';
				echo _('". Edited by "').ents($name)." (#{$entry['editor']}) ".format_datetime($entry['edited']);
			}
			?>
		</small>
	</blockquote>
	<?php
	if (!empty($entry['comments'])) {
		?>
		<div class="comments">
		<?php
		foreach ($entry['comments'] as $comment) {
			?>
			<blockquote>
				<p><?php echo nl2br(ents(trim($comment['contents']))); ?></p>
				<small class="author">
					Added by 
					<?php echo $comment['creator_fn'].' '.$comment['creator_ln'].' (#'.$entry['creator'].')'; ?>
					<?php echo format_datetime($comment['created']); ?>
				</small>
			</blockquote>
			<?php
		}
		?>
		</div>
		<?php
	}


	if (!empty($show_form)) {
		?>
		<h4>Add Update:</h4>
			<?php $dummy->printUpdateForm(); ?>
		<?php
	} else {
		?>
		<div class="status">
			<?php
			if (!empty($show_edit_link) && $GLOBALS['user_system']->havePerm(PERM_EDITNOTE)) {
				?>
				<a class="pull-right" href="?view=_edit_note&note_type=<?php echo $type; ?>&noteid=<?php echo $id; ?>"><i class="icon-wrench"></i><?php echo _('Edit / Comment')?></a>
				<?php
			}
			$statusClasses = Array(
								'no_action'	=> 'default',
								'pending'	=> 'important',
								'failed'	=> 'inverse',
								'complete'	=> 'success',
							);
			?>
			<span class="label label-<?php echo $statusClasses[$dummy->getValue('status')]; ?>">
			<?php $dummy->printStatusSummary(); ?>
			</span>
			<?php
			if ($entry['status'] == 'pending') {
				echo ' Assigned&nbsp;to&nbsp;'.$entry['assignee_fn'].'&nbsp;'.$entry['assignee_ln'].' (#'.$entry['assignee'].')';
				
			}

			?>
		</div>
		<?php
	}
	?>

</div>
