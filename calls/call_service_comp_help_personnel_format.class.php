<?php

class call_service_comp_help_personnel_format extends Call
{
	function run()
	{
		?>
        <html>
        <head>
			<?php include 'templates/head.template.php'; ?>
        </head>
        <body>
        <div id="body">
            <h1>Personnel format Help</h1><a id="top"></a>
            <p>A service component's 'Personnel' field specifies who will be assigned by default, when the service component is used in a
                particular service.</p>
            <p>Roster role %keywords% are allowed, e.g. a 'Bible Reading 1' component might have a 'Personnel' of <code>%BIBLE_READER_1_FIRSTNAME%</code>,
                meaning "the first name of the first person in the 'Bible Reader' role".</p>

            <table class="table table-bordered">
                <tbody>

                <tr>
                    <td colspan="2"><h3>Roster Role keywords</h3>
                        Each service has roster roles assigned to people:<br>
                        <img src="<?php echo BASE_URL; ?>/resources/img/help/rosterroles.png" alt="Service fields"/><br>
                        Assuming role <code><b>$ROLE</b></code> (e.g. 'BIBLE_READER'):
                    </td>
                </tr>
                <tr>
                    <td><code>%<b>ROLE</b>%</code> and <code>%NAME_OF_<b>$ROLE</b>%</code></td>
                    <td>full names (comma-separated
                        if more than one) of the persons serving in <code>$ROLE</code> for the current service.
                    </td>
                </tr>
                <tr>
                    <td><code>%<b>$ROLE</b>_1%</code> and <code>%NAME_OF_<b>$ROLE</b>_1%</code></td>
                    <td>full name of the first person listed as serving in <code><b>$ROLE</b></code> for the current service.</td>
                </tr>
                <tr>
                    <td><code>%<b>$ROLE</b>_n%</code> and <code>%NAME_OF_<b>$ROLE</b>_n%</code></td>
                    <td>full name of the nth person listed as serving in <code><b>$ROLE</b></code> for the current service.</td>
                </tr>
                <tr>
                </tr>
                <tr>
                    <td><code>%<b>$ROLE</b>_FIRSTNAME%</code></td>
                    <td>will return the first names (comma-separated) of people serving in<code><b>$ROLE</b></code> for the current service.
                    </td>
                </tr>
                <tr>
                </tr>
                <tr>
                    <td><code>%<b>$ROLE</b>_1_FIRSTNAME%</code></td>
                    <td>will return the first name of the first person serving in<code><b>$ROLE</b></code></td>
                </tr>
                <tr>
                    <td>for the current service.</td>
                </tr>
                <tr>
                    <td><code>%<b>$ROLE</b>_n_FIRSTNAME%</code></td>
                    <td>will return the first name of the nth person serving in<code><b>$ROLE</b></code> for the current service.</td>
                </tr>
                </tr>
                <tr>
                    <td colspan="2"><p>The following role keywords are available in your system:</p></td>
                </tr>
				<?php
				// Reflects code in {@link service->getKeywordReplacement()}
				foreach (Service::getPersonnelRoleTitles() as $titleinfo) { ?>
                    <tr>
                        <td><code>%<?php echo $titleinfo['title_uppercase']; ?>%</code><br>
                            <code>%NAME_OF_<?php echo $titleinfo['title_uppercase']; ?>%</code><br>
                            <code>%<?php echo $titleinfo['title_uppercase']; ?>_1%</code><br>
                            <code>%<?php echo $titleinfo['title_uppercase']; ?>_2%</code><br>
                            <code>%<?php echo $titleinfo['title_uppercase']; ?>_n%</code><br>
                            <code>%<?php echo $titleinfo['title_uppercase']; ?>_FIRSTNAME%</code><br>
                            <code>%<?php echo $titleinfo['title_uppercase']; ?>_1_FIRSTNAME%</code><br>
                            <code>%<?php echo $titleinfo['title_uppercase']; ?>_2_FIRSTNAME%</code><br>
                            <code>%<?php echo $titleinfo['title_uppercase']; ?>_n_FIRSTNAME%</code><br>
                        </td>
                        <td>
                            <a href="?view=rosters__define_roster_roles&roster_roleid=${titleinfo['id']}"><?php echo $titleinfo['title']; ?></a>
                            service personnel value
                        </td>
                    </tr>
				<?php } ?>
                </tbody>
            </table>
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