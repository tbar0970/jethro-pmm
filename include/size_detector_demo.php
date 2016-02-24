<?php 
@session_start();
include 'size_detector.class.php';
SizeDetector::processRequest();
?>
<html>
	<head>
		<style>html,body { height: 100% }</style>
	</head>
	<body>
		<h1>Size Detector Test</h1>
		<pre style="border: 2px solid">
			The server thinks the width is <?php echo SizeDetector::getWidth(); ?> and height is <?php echo SizeDetector::getHeight(); ?>
		</pre>

		<form method="post" style="border: 2px solid" action="" novalidate>
			<?php SizeDetector::printFormFields(); ?>
			This is a form
			<input type="submit" />
		</form>
	</body>
</html>
