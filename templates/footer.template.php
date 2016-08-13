<?php
// ---- JAVASCRIPT ------ //

if (JETHRO_VERSION == 'DEV') {
?>
<script src="https://code.jquery.com/jquery-3.1.0.min.js"></script>
<script src="https://cdn.rawgit.com/HubSpot/tether/v1.3.4/dist/js/tether.min.js"></script>
<script src="https://cdn.rawgit.com/FezVrasta/bootstrap-material-design/dist/dist/bootstrap-material-design.iife.min.js"></script>
<!-- IE10 viewport hack for Surface/desktop Windows 8 bug -->
<script src="https://maxcdn.bootstrapcdn.com/js/ie10-viewport-bug-workaround.js"></script>
<script type="text/javascript" src="<?php echo BASE_URL; ?>resources/js/tb_lib.js?t=<?php echo time(); ?>"></script>
<script type="text/javascript" src="<?php echo BASE_URL; ?>resources/js/jethro.js?t=<?php echo time(); ?>"></script>
<script type="text/javascript" src="<?php echo BASE_URL; ?>resources/js/bsn_autosuggest.js?t=<?php echo time(); ?>"></script>
<script type="text/javascript" src="<?php echo BASE_URL; ?>resources/js/jquery-ui.js?t=<?php echo time(); ?>"></script>
<script type="text/javascript" src="<?php echo BASE_URL; ?>resources/js/stupidtable.min.js?t=<?php echo time(); ?>"></script>
<script type="text/javascript">
  $('body').bootstrapMaterialDesign();
</script>
<?php
} else {
?>
        <script type="text/javascript" src="<?php echo BASE_URL; ?>resources/js/jethro-<?php echo JETHRO_VERSION; ?>.js"></script>
<?php
}
