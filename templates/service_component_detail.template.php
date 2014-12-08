<?php
/* @var $comp  The service component object */
?>
<h3>
	<a href="<?php echo build_url(Array('call' => NULL, 'view' => '_edit_service_component', 'service_componentid' => $comp->id)); ?>" class="pull-right"><small><i class="icon-wrench"></i>Edit</small></a>
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




