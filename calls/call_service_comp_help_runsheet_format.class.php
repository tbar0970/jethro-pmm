<?php

/**
 * Help page for the 'Run sheet format' field when editing a Service Component.
 */
class call_service_comp_help_runsheet_format extends Call
{
	protected function getField()
	{
		return 'Run sheet format';
	}
	protected function getPurpose()
	{
		return "specifies how the component will look in a particular service's runsheet";
	}

	function run()
	{
		$field = $this->getField();
		$purpose = $this->getPurpose();
		?>
		<html>
		<head>
			<?php include 'templates/head.template.php'; ?>
		</head>
		<body>
		<div id="body">
			<h1><?= $field ?> help</h1><a id="top"></a>
			<p>A service component's '<?= $field ?>' field <?=$purpose?>.</p>
			<p>Service information %keywords% are allowed in '<?= $field ?>'. E.g.:</p>
			<ul>
				<li>a song's format might be 'Song: %title%' (this is the default for the 'Songs' category).</li>
				<li>a 'Bible Reading 1' component's format might be '%title%: %SERVICE_BIBLE_READ_1%', rendering as (for example)
					"Bible Reading 1: Psalm 111".
				</li>

			</ul>

			<table class="table table-bordered">
				<tbody>
				<tr>
					<td colspan="2"><h3>Service Component keywords</h3>
					</td>
				</tr>
				<?php /* Reflects code in {@link service->replaceItemKeywords()  */ ?>
				<tr>
					<td><code>%title%</code></td>
					<td>Component Title (e.g. song name)</td>
				</tr>
				<tr>
					<td><code>%alt_title%</code></td>
					<td>Alt Title (e.g. song alternative name)</td>
				</tr>
				<tr>
					<td><code>%ccli_number%</code></td>
					<td>CCLI Number</td>
				</tr>
				<tr>
					<td colspan="2"><h3>Service keywords</h3>
						The following keywords will be filled in with values from the service, e.g.:<br/>
						<img src="<?php echo BASE_URL; ?>/resources/img/help/servicefields.png" alt="Service fields"/>
					</td>
				</tr>
				<?php /* The SERVICE_* keywords correspond to the service::_getFields() keys */ ?>
				<tr>
					<td><code>SERVICE_TOPIC</code></td>
					<td>Service topic, e.g. <code>Great are the works of the Lord</code></td>
				</tr>
				<tr>
					<td><code>SERVICE_FORMAT</code></td>
					<td>Service format, e.g. <code>Lord's Supper</code></td>
				</tr>
				<tr>
					<td><code>SERVICE_DATE</code></td>
					<td>Service date, e.g. <code><?php echo date('j F Y'); ?></code></td>
				</tr>
				<tr>
					<td><code>SERVICE_NOTES</code></td>
					<td>Service notes</td>
				</tr>
				<tr>
					<td><code>SERVICE_COMMENTS</code></td>
					<td>Run sheet comments, e.g. <code>NB percolator coffee, not espresso, this week.</code></td>
				</tr>
				<?php /* SERVICE_CONGREGATIONID is also possible but probably shouldn't be */ ?>
				<tr>
					<?php /** SERVICE_BIBLE_* keywords are from service->getValue() */ ?>
					<td><code>%SERVICE_BIBLE_$readingpurpose_$selection%</code>,
						<code>%SERVICE_BIBLE_$readingpurpose_$selection_SHORT%</code></td>
					<td>

						<p>Where <code>$readingpurpose</code> is one of:
						<dl style="margin: 0 0 0 1em">
							<dt><code>READ</code></dt>
							<dd>Service texts to be read</dd>
							<dt><code>PREACH</code></dt>
							<dd>Service texts to be preached on</dd>
							<dt><code>ALL</code></dt>
							<dd>Service texts regardless of purpose</dd>
						</dl>
						</p>
						<p>
							and
							<code>$selection</code> chooses which of the <code>$readingpurpose</code> readings to display:
						<dl style="margin: 0 0 0 1em">
							<dt><code>ALL</code></dt>
							<dd>shows all readings</dd>
							<dt><code>1</code></dt>
							<dd>shows first reading</dd>
							<dt><code>2</code></dt>
							<dd>shows second reading</dd>
							<dd>etc</dd>
						</dl>
						</p>
						<p><code>SHORT</code> means to use the shortened form of the bible reference.</p>
						<p>
							e.g.<br>
							<code>%SERVICE_BIBLE_READ_ALL%</code>
							expands to <code>Psalm 111, Matthew 7:24-29</code><br>
							<code>%SERVICE_BIBLE_READ_ALL_SHORT%</code>
							expands to <code>Ps 111, Matt 7:24-29</code><br>
							<code>%SERVICE_BIBLE_READ_1%</code> expands to
							<code>Psalm 111</code><br>
							<code>%SERVICE_BIBLE_PREACH_ALL%</code> expands to
							<code>Psalm 111</code>
						</p>
					</td>
				</tr>
				</tbody>
			</table>
			<p>Note: roster role keywords may also be used in '<?= $field ?>', but typically only belong in the separate <a
						target="service-comp-help" class="med-newwin" href="?call=service_comp_help_personnel_format">Personnel field</a>.
			</p>

		</div>
		<script>
		$('code').click(function () {
			TBLib.selectElementText(this);
	})
		</script>
		</body>
		</html>
		<?php
	}
}