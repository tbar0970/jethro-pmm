<?php
class Call_document_merge_help extends Call
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
				<h1>Mail Merge in Jethro</h1><a id="top"></a>
				<p>Jethro can merge person and family data into Microsoft office files (DOCX, XLS, PPTX) and OpenOffice/LibreOffice files (ODT, ODS, ODP).</p>
				<p>Jethro uses the <a href="https://www.tinybutstrong.com/opentbs.php">TinyButStrong OpenTBS engine</a> to merge documents. </p>

				<h3>Sample Templates</h3>
				<h4>For merging person-by-person</h4>
				<table class="table table-bordered">
					<tr>
						<td>Simple list of people in a table</td>
						<td class="nowrap">
							<a href="./resources/merge_samples/example_list_of_people.ods">ODS</a> &nbsp;
							<a href="./resources/merge_samples/example_list_of_people.xlsx">XLSX</a> &nbsp;
							<a href="./resources/merge_samples/example_list_of_people.odt">ODT</a> &nbsp;
							<a href="./resources/merge_samples/example_list_of_people.docx">DOCX</a>
						</td>
					</tr>
					<tr>
						<td>Nametags, A4, 3x7 per page</td>
						<td>
							<a href="./resources/merge_samples/example_nametags_7x3.docx">DOCX</a>
							<a href="./resources/merge_samples/example_nametags_7x3.odt">ODT</a>
						</td>
					</tr>
					<tr>
						<td>Attendance list, with birthdays highlighted
							<br /><small><i>This also shows date of birth (a custom field) and whether they have had a birthday in the past week. The ODS version uses conditional formatting to omit dividing lines between members of the same family.</i></small></td>
						<td>
							<a href="./resources/merge_samples/example_attendance_sheet.ods">ODS</a> &nbsp;
							<a href="./resources/merge_samples/example_attendance_sheet.xlsx">XLSX</a>
						</td>
					</tr>
				</table>
				<h4>For merging family-by-family</h4>
				<table class="table table-bordered">
					<tr>
						<td>Mailing labels, A4, 3x7 per page</td>
						<td>
							<a href="./resources/merge_samples/example_mailing_labels_7x3.docx">DOCX</a>
							<a href="./resources/merge_samples/example_mailing_labels_7x3.odt">ODT</a>
						</td>
					</tr>
					<tr>
						<td>Family attendance sheet</td>
						<td>
							<a href="./resources/merge_samples/example_family_attendance.ods">ODS</a> &nbsp;
							<a href="./resources/merge_samples/example_family_attendance.xlsx">XLSX</a>
						</td>
					</tr>
				</table>
				<p>NB You can also generate family listings using Jethro's <a target="_parent" href="?view=families__contact_list">Families - Contact List page</a></p>

				<h3>Creating Templates using TBS tags</h3>

				<p>When creating a template, you include special markers (<em>TBS tags</em>) which will be replaced with the relevant person/family details.<p>
				<p>A <em>TBS block</em> is defined by one or more <em>TBS fields</em>.  When the merge is performed, the TBS block will be repeated for each person/family.</p>

				<p>To preview all the available keywords and their values, use the "preview all keywords" button when merging.</p>
								
				The available fields for merging <b>per person</b> are:
				<ul>
					<li><code>[person.id]</code> - the unique internal identifier number</li>
					<li><code>[person.first_name]</code></li>
					<li><code>[person.last_name]</code></li>
					<li><code>[person.gender]</code> - Male/Female/Unknown</li>
					<li><code>[person.age_bracket]</code></li>
					<li><code>[person.congregation]</code></li>
					<li><code>[person.status]</code></li>
					<li><code>[person.email]</code></li>
					<li><code>[person.home_tel]</code></li>
					<li><code>[person.mobile_tel]</code></li>
					<li><code>[person.work_tel]</code></li>
					<li><code>[person.contact_remarks]</code></li>
					<li><code>[person.date_of_last_status_change]</code></li>
					<li><code>[person.created_date]</code> - when added to the system</li>
					<li><code>[person.family_name]</code></li>
					<li><code>[person.address_street]</code></li>
					<li><code>[person.address_suburb]</code></li>
					<li><code>[person.address_state]</code></li>
					<li><code>[person.address_postcode]</code></li>

					<?php
					$fields = $GLOBALS['system']->getDBObjectData('custom_field', Array());
						foreach ($fields as $id => $field) {
							?>
							<li><code>[person.<?php echo $fname = strtoupper(str_replace(' ', '_', $field['name'])); ?>]</code> -  custom field</li>
							<?php
						}
						?>
				</ul>
				<br />The available fields for merging <b>per family</b> are:
				<ul>
					<li><code>[family.id]</code> - the unique internal identifier number</li>
					<li><code>[family.family_name]</code></li>
					<li><code>[family.street_address]</code></li>
					<li><code>[family.suburb]</code></li>
					<li><code>[family.state]</code></li>
					<li><code>[family.postcode]</code></li>
					<li><code>[family.home_tel]</code></li>
					<li><code>[family.mobile_tels]</code> - mobile numbers of the adults</li>
					<li><code>[family.emails]</code> - email addresses of the adults</li>
					<li><code>[family.status]</code></li>
					<li><code>[family.created_date]</code> - when added to the system</li>
					<li><code>[family.members]</code> - first names</li>
					<li><code>[family.members_full]</code> - full names</li>
					<li><code>[family.adult_members]</code> - first names</li>
					<li><code>[family.adult_members_full]</code> - full names</li>
					<li><code>[family.selected_firstnames]</code> - selected on the list generating the document</li>
					<li><code>[family.selected_lastnames]</code> - selected on the list generating the document</li>
					<li><code>[family.selected_names]</code> - full names</li>
				</ul>
				<br />

				<p>You define a <em>TBS Block</em> by adding <code>;block=tbs:row</code> to the first field in the block, eg <code>[person.first_name;block=tbs:row]</code></p>

				<p>There are also some special fields which you may use outside a TBS block:</p>
				<ul>
				<li><code>[onshow..now;frm='yyyy-mm-dd']</code> - the current date and time</li>
				<li><code>[onshow.system_name]</code> - the name of Jethro system</li>
				<li><code>[onshow.username]</code> - the username of the person generating the document</li>
				<li><code>[onshow.first_name]</code> - the first name of the person generating the document</li>
				<li><code>[onshow.last_name]</code> - the last name of the person generating the document</li>
				<li><code>[onshow.email]</code> - the email address of the person generating the document</li>
				</ul>

				<h3>Separation of records by status</h3>
				<p>It is sometimes convenient to divide people into groups by their member status. In place of <code>[person.</code> in the examples above you can place a status name.</p>
				<p>For example in a system where member status can be 'Member', 'Contact' and group membership can be 'Registered', 'Unregistered' you can use fields like</p>
				<ul>
					<li><code>[Member.first_name]</code></li>
					<li><code>[Contact.first_name]</code></li>
					<li><code>[Registered.first_name]</code></li>
					<li><code>[Unregistered.first_name]</code></li>
				</ul>

				<p>Example Attendance-Specific Template 3 uses this concept:			
							<a href="./resources/merge_samples/example_monthly_attendance2.ods">ODS</a> &nbsp;
							<a href="./resources/merge_samples/example_monthly_attendance2.xlsx">XLSX</a> &nbsp;
				</p>
				
				<p><b>Special considerations</b></p>
				<ol>
				<li>If you have a member status and a group status with the same name there will be one combined 'table'</li>
				<li>To get the group membership 'table' in a report you need to select 'Group membership status' as one of the selected fields</li>
				<li>Group membership is implicit for a group attendance template but not available for rosters</li>
				<li>If you have multiple groups in the report only group membership status for one group will be available for each person - which group is not defined</li>
				</ol>

				<h3>Extra Fields for Attendance Page</h3>
				<p>When merging a document from the 'Display attendance' screen, with "tabular" format selected, additional fields are available:
				<ul>
				<li><code>[onshow.date1]</code>...<code>[onshow.date60]</code> - for each date selected</li>
				<li><code>[onshow.dates]</code> - the number of dates selected</li>
				<li><code>[onshow.group1]</code>...<code>[onshow.group60]</code> - for each group/congregation selected</li>
				<li><code>[onshow.groups]</code> - the number of groups/congregations selected</li>
				<li><code>[onshow.headcount1]</code>...<code>[onshow.headcount60]</code> - for each column that has a head count</li>
				<li><code>[onshow.headcounts]</code> - the number of columns with a head count</li>
				<li><code>[onshow.present1]</code>...<code>[onshow.present60]</code> - for each column that has a total present</li>
				<li><code>[onshow.presents]</code> - the number of columns with a total present</li>
				<li><code>[onshow.absent1]</code>...<code>[onshow.absent60]</code> - for each column that has a total absent</li>
				<li><code>[onshow.absents]</code> - the number of columns with a total absent</li>
				<li><code>[onshow.extra1]</code>...<code>[onshow.extra60]</code> - for each column that has a total of extras</li>
				<li><code>[onshow.extras]</code> - the number of columns with a total of extras</li>
				</ul>
				<p>Note: Columns with a total of zero are included.</p>
				<p>
				<b>Example Attendance-Specific Templates</b>:<br />  Example 1:
							<a href="./resources/merge_samples/example_monthly_attendance.ods">ODS</a> &nbsp;
							<a href="./resources/merge_samples/example_monthly_attendance.xlsx">XLSX</a><br>
				Example 2:
							<a href="./resources/merge_samples/example_monthly_attendance1.ods">ODS</a> &nbsp;
							<a href="./resources/merge_samples/example_monthly_attendance1.xlsx">XLSX</a><br>
				Example 3:
							<a href="./resources/merge_samples/example_monthly_attendance2.ods">ODS</a> &nbsp;
							<a href="./resources/merge_samples/example_monthly_attendance2.xlsx">XLSX</a> &nbsp;
				</p>

				<h3>Extra Fields for Rosters Page</h3>
				<p>When merging a document from the 'Display roster assignments' screen, the following additional fields are available.
				<ul>
				<li><code>[onshow.roster_view_name]</code> - the name of the roster view</li>
				<li><code>[onshow.date]</code> - the date of the last roster</li>
				<li><code>[onshow.label1]</code>...<code>[onshow.label20]</code> - label for each column of the roster</li>
				</ul>
				<p>Also, Some keywords have a variant ending in <code>_cr</code> eg <code>[roster.notes_cr]</code>. These variants include a new line character. The 'normal' versions replace new line characters with ', '.</p>

				Block fields
				<ul>
				<li><code>[roster.date]</code> - the date the roster</li>
				<li><code>[roster.role1]</code>...<code>[roster.role20]</code> - the person(s) doing the role on that date</li>
				<li><code>[people.date]</code> - the date the person is on the roster</li>
				<li><code>[people.name]</code> - the person's name</li>
				<li><code>[roster.role1]</code>...<code>[roster.role20]</code> - the person(s) doing the role on that date</li>
				</ul>
				For each person who appears on the roster - unlike <code>[people.name]</code> each person will only appear once in this list
				<ul>
				<li><code>[person.name]</code> - the person's name</li>
				</ul>
				<p>
				<b>Example Roster-specific Templates</b>:<br />
				Sign-in sheets: 
							<a href="./resources/merge_samples/example_roster_sign_in_out_sheet.ods">ODS</a> &nbsp;
							<a href="./resources/merge_samples/example_roster_sign_in_out_sheet.xlsx">XLSX</a><br>
				Roster: 
							<a href="./resources/merge_samples/example_roster.ods">ODS</a> &nbsp;
							<a href="./resources/merge_samples/example_roster.xlsx">XLSX</a> &nbsp;
				</p>
				<p>
				<b>Handy hint</b>:<br />
				The roster data is generated with the date down the side and roles across the top. If you prefer dates across the top
				and roles down the side:-<br>
				&nbsp; 1. Create a 'template' spreadsheet to receive your data<br>
				&nbsp; 2. Create a spreadsheet similar to the sample above<br>
				&nbsp; 3. Select your data in the generated spreadsheet and copy<br>
				&nbsp; 4. Past into your 'template' using 'Paste special' -> Transpose				
				</p>
				<h3>Controlling Spreadsheet Cell Formatting</h3>
				<p>When merging a spreadsheet, cells may be formatted and also typed. For example, a cell value may be typed as String, Numerical, Boolean or Date.</p>
				<p>Whenever you enter a TBS tag into a cell, the cell will be typed as String. TBS offers special parameters to change the cell type.</p>
				<h5>Example:</h5>
				<ul>
					<li><code>[onshow.presents;ope=tbs:num]</code> - will make the cell Numeric after the number of people present is inserted.</li>
				</ul>
				<h5>Parameters for merging   data in spreadsheet cells:</h5>
				<table class="table table-bordered">
				  <tbody><tr>
					<th align="left">Required Cell Type</th>
					<th class="centered">Parameter</th>
					<th class="centered">Note</th>
				  </tr>
				  <tr>
					<td nowrap="nowrap">Number</td>
					<td class="smallcode" nowrap="nowrap">ope=tbs:num</td>
					<td class="smallcode" valign="top" nowrap="nowrap">&nbsp;</td>
				  </tr>
				  <tr>
					<td nowrap="nowrap">Boolean</td>
					<td class="smallcode" nowrap="nowrap">ope=tbs:bool</td>
					<td class="smallcode" valign="top" nowrap="nowrap">&nbsp;</td>
				  </tr>
				  <tr>
					<td nowrap="nowrap">Date/time</td>
					<td class="smallcode" nowrap="nowrap">ope=tbs:date</td>
					<td class="smallcode" valign="top" nowrap="nowrap">&nbsp;</td>
				  </tr>
				  <tr>
					<td nowrap="nowrap">Time only</td>
					<td class="smallcode" nowrap="nowrap">ope=tbs:time</td>
					<td nowrap="nowrap">For XLSX, it's an alias of <span class="smallcode">ope=tbs:date</span></td>
				  </tr>
				  <tr>
					<td nowrap="nowrap">Currency</td>
					<td class="smallcode" nowrap="nowrap">ope=tbs:curr</td>
					<td nowrap="nowrap"><p>For XLSX, it's an alias of <span class="smallcode">ope=tbs:num</span></p></td>
				  </tr>
				  <tr>
					<td nowrap="nowrap">Percentage</td>
					<td class="smallcode" nowrap="nowrap">ope=tbs:percent</td>
					<td nowrap="nowrap">For XLSX, it's an alias of <span class="smallcode">ope=tbs:num</span></td>
				  </tr>
				</tbody></table>
				<p>&nbsp;</p>
		</div>
		<script>
			$('code').click(function() {
				TBLib.selectElementText(this);
			})
		</script>
		</body>
		</html>
		<?php
	}
}