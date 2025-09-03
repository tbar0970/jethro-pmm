<?php
/** Renders the details panel of a song in the component library. Called from call_service_comp_detail.class.php.
 * @var Service_Component $comp  The service component object
 */
?>
<h3>
	<span class="pull-right">
		<small>
			<a href="<?php echo build_url(Array('call' => NULL, 'view' => '_edit_service_component', 'service_componentid' => $comp->id)); ?>"><i class="icon-wrench"></i>Edit</a> &nbsp;

            <?php if ($comp->values['congregationids']) { ?>
                <a id="disable" data-id="<?php echo $comp->id; ?>" href=""><i class="icon-eye-close"></i>Disable</a> &nbsp;
            <?php } ?>

            <?php if ($comp->canDelete()) { ?>
                <a id="delete" data-id="<?php echo $comp->id; ?>" href=""><i class="icon-trash"></i>Delete</a> &nbsp;
            <?php } ?>

			<a href="<?php echo build_url(Array('call' => NULL, 'call' => 'service_comp_slides')); ?>"><i class="icon-film"></i>Slides</a>
		</small>
        <script>
            $(document).ready(function() {
                $('#disable').on('click', function(e) {
                    e.preventDefault(); // prevent default link navigation
                    var id = $(this).attr('data-id');
                    // Disable the song, and if successful, re-render the #preview panel in which this template renders.
                    $.ajax({
                        url: "/?call=service_comp_disable",
                        type: "POST",
                        data: { service_componentid: id },
                        success: function(data, textStatus, jqXHR) {
                            if (jqXHR.status === 200) {
                                $('#preview').load("/?call=service_comp_detail&id="+id);
                            }
                        },
                        error: function(jqXHR, textStatus, errorThrown) {
                            console.log("Error: " + jqXHR.status + " " + errorThrown);
                            $("#preview").html(jqXHR.responseText);

                        }
                    });
                });
                $('#delete').on('click', function(e) {
                    e.preventDefault(); // prevent default link navigation
                    if (!confirm('Are you sure you want to delete this service component?')) {
                        return false;
                    }
                    var id = $(this).attr('data-id');
                    // Disable the song, and if successful, re-render the #preview panel in which this template renders.
                    $.ajax({
                        url: "/?call=service_comp_delete",
                        type: "POST",
                        data: { service_componentid: id },
                        success: function(data, textStatus, jqXHR) {
                            if (jqXHR.status === 200) {
                                $('#preview').load("/?call=service_comp_detail&id=" + id);
                                // TODO: it would be nice if we could reload the service component list here, to show the component is gone.
                            }
                        },
                        error: function(jqXHR, textStatus, errorThrown) {
                            console.log("Error: " + jqXHR.status + " " + errorThrown);
                            $("#preview").html(jqXHR.responseText);
                        }
                    });
                })
            });
        </script>
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




