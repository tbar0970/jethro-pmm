<?php
class Call_Opentbs_merge_help extends Call
{
	function run()
	{
		?>
		<html>
			<head>
				<style media="print">
					html body * {
						color: black;
						text-decoration: none;
					}
				</style>
				<style>
					* {
						font-family: sans-serif;
					}	
					td, th {
						padding: 3px 1ex;
						font-size: 0.8em;
					}
					th {
						background-color: #555;
						color: white;
					}
					th * {
						color: white !important;
					}
					table {
						border-collapse: collapse;
					}
					.smallprint {
						margin-top: 1ex;
						font-size: 75%;
					}
				</style>
			</head>
			<body>
				<h1>Spreadsheet mail merge help</h1><a id="top"></a>
				<p>The merge process uses the TinyButStrong (version 3.10.1) openTBS plug-in (version 1.9.11). You can read more at <a href="http://www.tinybutstrong.com/opentbs.php" >http://www.tinybutstrong.com/opentbs.php</a>. There is extensive documentation on the TBS web site if you want to explore more advanced features.</p>
				<p>It can process odt, odg, ods, odf, odp, odm, docx, xlsx and pptx files.</p>
				<p>
				<a href="#syntax">Syntax</a><br>
				<a href="#fields">TBS fields</a><br>
				<a href="#blocks">TBS blocks</a><br>
				<a href="#attendance">Fields on attendance page</a><br>
				<a href="#rosters">Fields on rosters page</a><br>
				<a href="#cells">Merging data in spreadsheet cells</a><br>
				<a href="#examples">Examples</a>
				</p>
				
				<h2><a id="syntax">Syntax</h2>
				<p>You place special markers (<em>TBS tags</em>) into your template document which are replaced with your data.<p>
				<p>There are two types of <em>TBS tags</em>: <em>Fields</em> and <em>Blocks</em>.</p>
				<p>A <em>TBS Field</em> is a <em>TBS tags</em> which has to be replaced by a single data item. It is possible to specify a display format and also other parameters.</p>
				<p>A <em>TBS Block</em> is an area which has to be repeated. It is defined using one or two TBS fields. These are usually rows of a spreadsheet or table. 
				<a href="#top" alt="return to top of page">top</a></p>
				
				<h2><a id="fields">TBS fields</h2>
				<p>A TBS field is a TBS tag which has to be replaced by a single data item. It has a name to identify it and can have parameters to modify the displayed value. </p>
				<p>Syntax: [FieldName</span>{;param1}{;param2}..]</p>
				<p>Useful fields provided by TBS include (note the double dots)
				<ul>
				<li>[onshow..now] - the current date and time</li>
				<li>[onshow..template_name] - the file name of your template</li>
				</ul></p>
				<p>The Jethro PMM implementation provides, for all templates.
				<ul>
				<li>[onshow.system_name] - the name of Jethro system</li>
				<li>[onshow.username] - the username of the person generating the document</li>
				<li>[onshow.first_name] - the first name of the person generating the document</li>
				<li>[onshow.last_name] - the last name of the person generating the document</li>
				<li>[onshow.email] - the email address of the person generating the document</li>
				</ul>
				<a href="#top" alt="return to top of page">top</a></p>
				
				<h2><a id="blocks">TBS blocks</h2>
				<p>A TBS block is used to define a repeating block of data.</p>
				<p>In our implementation we mark the begining of a block with block=tbs:row. following tags, in the block, do not this parameter.</p>
				<h5>Example:</h5>
				<ul>
					<li>[person.first_name;block=tbs:row]</li>
				</ul>
				<p>The Jethro PMM implementation provides two set of blocks, as appropriate.
				<ul>
				<li>[person.id] - the unique internal identifier numnber</li>
				<li>[person.first_name]</li>
				<li>[person.last_name]</li>
				<li>[person.gender] - Male/Female/Unknown</li>
				<li>[person.age_bracket]</li>
				<li>[person.congregation]</li>
				<li>[person.status]</li>
				<li>[person.email]</li>
				<li>[person.home_tel]</li>
				<li>[person.mobile_tel]</li>
				<li>[person.work_tel]</li>
				<li>[person.contact_remarks]</li>
				<li>[person.date_of_last_status_change]</li>
				<li>[person.created_date] - when added to the system</li>
				<li>[person.family_name]</li>
				<li>[person.address_street]</li>
				<li>[person.address_suburb]</li>
				<li>[person.address_state]</li>
				<li>[person.address_postcode]</li>
				<li>Plus any custom fields</li>
				</ul>
				<ul>
				<li>[family.id] - the unique internal identifier numnber</li>
				<li>[family.family_name]</li>
				<li>[family.street_address]</li>
				<li>[family.suburb]</li>
				<li>[family.state]</li>
				<li>[family.postcode]</li>
				<li>[family.home_tel]</li>
				<li>[family.status]</li>
				<li>[family.created_date] - when added to the system</li>
				<li>[family.members]</li>
				<li>[family.adult_members]</li>
				<li>[family.selected_firstnames] - selected on the list generating the document</li>
				<li>[family.selected_lastnames] - selected on the list generating the document</li>
				</ul>		
				<a href="#top" alt="return to top of page">top</a></p>

				<h2><a id="attendance"></a>Fields on attendance page</h2>
				<p>On the 'Display attendance' screen, if format Tabular is selected, additional fields are available when generated for a list of people.
				<ul>
				<li>[onshow.date1]...[onshow.date60] - for each date selected</li>
				<li>[onshow.dates] - the number of dates selected</li>
				<li>[onshow.group1]...[onshow.group60] - for each group/congregation selected</li>
				<li>[onshow.groups] - the number of groups/congregations selected</li>
				<li>[onshow.headcount1]...[onshow.headcount60] - for each column that has a head count</li>
				<li>[onshow.headcounts] - the number of columns with a head count</li>
				<li>[onshow.present1]...[onshow.present60] - for each column that has a total present</li>
				<li>[onshow.presents] - the number of columns with a total present</li>
				<li>[onshow.absent1]...[onshow.absent60] - for each column that has a total absent</li>
				<li>[onshow.absents] - the number of columns with a total absent</li>
				<li>[onshow.extra1]...[onshow.extra60] - for each column that has a total of extras</li>
				<li>[onshow.extras] - the number of columns with a total of extras</li>
				</ul>
				Note: Columns with a total of zero are included.
				<a href="#top" alt="return to top of page">top</a></p>
				
				<h2><a id="rosters"></a>Fields on rosters page</h2>
				<p>On the 'Display roster assignments' screen, the following fields are available.
				<ul>
				<li>[onshow.roster_view_name] - the name of the roster view</li>
				<li>[onshow.date] - the date of the last roster</li>
				<li>[onshow.label1]...[onshow.label20] - label for each column of the roster</li>
				</ul>
				Block fields
				<ul>
				<li>[roster.date] - the date the roster</li>
				<li>[roster.role1]...[roster.role20] - the person(s) doing the role on that date</li>
				<li>[people.date] - the date the person is on the roster</li>
				<li>[people.name] - the person's name</li>
				<li>[roster.role1]...[roster.role20] - the person(s) doing the role on that date</li>
				</ul>
				<a href="#top" alt="return to top of page">top</a></p>
				
				<h2><a id="cells"></a>Merging data in spreadsheet cells</h2>
<p>In spreadsheets, cells values may be formated but also typed. For example, a cell value may be typed as String, Numerical, Boolean or Date.</p>
<p>As soon as you enter a TBS tag into a cell, the cell is typed as String. TBS offers special parameters to change the cell type.</p>
<h5>Example:</h5>
<ul>
  <li>[onshow.x;ope=tbs:num] - will make the cell Numeric after onshow.x is merged.</li>
</ul>
<h5>Parameters for merging   data in spreadsheet cells:</h5>
<table cellspacing="1" cellpadding="4" border="0">
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
<a href="#top" alt="return to top of page">top</a></p>

			<h2><a id="examples"></a>Examples</h2>
			<p>Some of these examples employ conditional cell formatting using the style function. This function is not available in Excel, so the XLSX versions do not have it</p>
			<p><b>Simple list of people</b><br> 
			example_list_of_people <a href="resources/example_list_of_people.ods">ODS</a> 
			<a href="resources/example_list_of_people.xlsx">XLSX</a>
			</p>
			<p><b>List of people on an attendance sheet.</b><br>
			We also show date of birth (a custom field) and whether they have had a birthday in the past week. If they belong to the same family, we do not display a dividing line for some columns (uses conditional formating).<br>
			Example based on a play group attendance sheet.<br>
			example_attendance_sheet <a href="resources/example_attendance_sheet.ods">ODS</a> 
			<a href="resources/example_attendance_sheet.xlsx">XLSX</a>
			</p>
			<p><b>List of people on an attendance sheet.</b><br>
			If they belong to the same family, we do not display a dividing line for some columns (uses conditional formating).<br>
			Example based on a youth group sign-in sign-out sheet.<br>
			example_attendance_sheet2 <a href="resources/example_attendance_sheet2.ods">ODS</a> 
			<a href="resources/example_attendance_sheet2.xlsx">XLSX</a>
			</p>
			<p><b>Lists of attendance for a month.</b><br>
			Varying levels of complexity.<br>
			example_monthly_attendance <a href="resources/example_monthly_attendance.ods">ODS</a> 
			<a href="resources/example_monthly_attendance.xlsx">XLSX</a><br>
			example_monthly_attendance1 <a href="resources/example_monthly_attendance1.ods">ODS</a> 
			<a href="resources/example_monthly_attendance1.xlsx">XLSX</a><br>
			example_monthly_attendance2 <a href="resources/example_monthly_attendance2.ods">ODS</a> 
			<a href="resources/example_monthly_attendance2.xlsx">XLSX</a>
			</p>
			<p><b>List of family attendance.</b><br>
			example_family_attendance <a href="resources/example_family_attendance.ods">ODS</a> 
			<a href="resources/example_family_attendance.xlsx">XLSX</a>
			</p>
			<p>
			<br><a href="#top" alt="return to top of page">top</a></p>

		</body>
		</html>
		<?php
	}
}
?>
