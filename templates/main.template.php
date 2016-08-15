<?php include 'layout.php' ?>
<!DOCTYPE html>
<html lang="en">
<head>
	<?php include 'head.template.php'; ?>
</head>

<body>
	<?php startLayout(true); ?>
			<?php

			dump_messages();

			if ($title = $GLOBALS['system']->getTitle()) {
				echo '<h1>'.ents($title).'</h1>';
			}

			$GLOBALS['system']->printBody();
			?>
	<?php finishLayout(); ?>
</body>
<?php include 'footer.template.php' ?>
</html>
