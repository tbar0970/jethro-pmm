<?php
//if (!is_ajax()) {
//	require $this->_base_dir.'/templates/main.template.php';
//} else {
	$GLOBALS['system']->printAjax();
//}

//Function to check if the request is an AJAX request
function is_ajax() {
  return isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';
}
?>
