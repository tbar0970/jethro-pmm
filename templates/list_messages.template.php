<?php /** @var array<int, array<string, mixed>> $messages */ ?>
<div class="messages-history-container">
<?php
$isFirst = true;
foreach ($messages as $id => $entry) {
	include __DIR__ . '/single_message.template.php';
	$isFirst = false;
}
?>
</div>

