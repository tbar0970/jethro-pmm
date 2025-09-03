<?php
/* @var Service_Component $comp  The service component object */
?>
<h3>
	<span class="pull-right">
		<small>	
			<a href="<?php echo build_url(Array('call' => NULL, 'view' => '_edit_service_component', 'service_componentid' => $comp->id)); ?>"><i class="icon-wrench"></i>Edit</a>
			<a href="<?php echo build_url(Array('call' => NULL, 'call' => 'service_comp_slides')); ?>"><i class="icon-film"></i>Slides</a>
		</small>
	</span>
	<?php $comp->printFieldValue('title'); ?>
</h3>
<div id="comp-detail">
	<?php
	$comp->printSummary();
	?>
</div>
<script>
applyNarrowColumns('#comp-detail');
</script>




