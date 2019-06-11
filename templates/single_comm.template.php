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
	if (!empty($show_names)) {
		if (!empty($entry['familyid'])) {
			$type = 'family';
			$icon = 'home';
			$notee = $entry['family_name'].' Family';
			$view_url = '?view=families&familyid='.$entry['familyid'].'#note_'.$id;
		} else {
			$type = 'person';
			$icon = 'user';
			$notee = $entry['person_fn'].' '.$entry['person_ln'];
			$view_url = '?view=persons&personid='.$entry['personid'].'#note_'.$id;
		}
		?>
		<h4><a href="<?php echo $view_url; ?>"><?php echo ents($notee); ?></a></h4>
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
			<?php echo $entry['creator_fn'].' '.$entry['creator_ln'].' <span class="visible-desktop">(#'.$entry['creator'].')</span>,'; ?>
			<?php echo format_datetime($entry['created']); ?>
			<?php
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
					<?php echo $comment['creator_fn'].' '.$comment['creator_ln'].' <span class="visible-desktop">(#'.$entry['creator'].')</span>, '; ?>
					<?php echo format_datetime($comment['created']); ?>
				</small>
			</blockquote>
			<?php
		}
		?>
		</div>
		<?php
	}


	?>

</div>
